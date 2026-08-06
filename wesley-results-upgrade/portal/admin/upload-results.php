<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Any of these roles can publish results. Adjust to taste.
require_any_role(['Super Admin', 'School Administrator', 'Registrar', 'Lecturer']);

/**
 * Bulk-imports a results CSV in one transaction, with in-memory
 * caches so repeated lookups (same student across many course
 * rows, same session/semester across many students) don't hit
 * the database again. This is what keeps a 1,500-row upload fast.
 */
function import_results_csv(string $path, int $uploadedBy): array
{
    $db = db();
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return ['processed' => 0, 'inserted' => 0, 'failed' => 0, 'issues' => ['Could not read the uploaded file.']];
    }

    $expected = ['matric_number', 'session', 'semester', 'course_code', 'course_title', 'units', 'ca_score', 'exam_score'];
    $header = fgetcsv($handle);
    if (!$header || array_map('strtolower', array_map('trim', $header)) !== $expected) {
        fclose($handle);
        return ['processed' => 0, 'inserted' => 0, 'failed' => 0, 'issues' => ['The first row must be exactly: ' . implode(',', $expected)]];
    }

    $studentCache = [];
    $sessionCache = [];
    $semesterCache = [];
    $courseCache = [];
    $touchedStudentIds = [];

    $findStudent = $db->prepare('SELECT s.id, s.department_id, s.faculty_id, u.last_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.matric_number = :m');
    $findSession = $db->prepare('SELECT id FROM academic_sessions WHERE name = :n');
    $insertSession = $db->prepare('INSERT INTO academic_sessions (name, start_date, end_date, is_active) VALUES (:n, :s, :e, 0)');
    $findSemester = $db->prepare('SELECT id FROM semesters WHERE academic_session_id = :sid AND name = :n');
    $insertSemester = $db->prepare('INSERT INTO semesters (name, academic_session_id, starts_on, ends_on, is_current) VALUES (:n, :sid, :s, :e, 0)');
    $findCourse = $db->prepare('SELECT id FROM courses WHERE code = :code');
    $insertCourse = $db->prepare('INSERT INTO courses (code, title, credit_units, department_id, faculty_id, semester_id, academic_session_id) VALUES (:code, :title, :units, :dept, :fac, :sem, :sess)');
    $upsertResult = $db->prepare(
        'INSERT INTO results (student_id, course_id, academic_session_id, semester_id, ca_score, exam_score, grade, gpa, status, uploaded_by, created_at, updated_at)
         VALUES (:student_id, :course_id, :session_id, :semester_id, :ca, :exam, :grade, :gpa, "Published", :uploaded_by, NOW(), NOW())
         ON DUPLICATE KEY UPDATE ca_score = VALUES(ca_score), exam_score = VALUES(exam_score), grade = VALUES(grade), gpa = VALUES(gpa), status = "Published", uploaded_by = VALUES(uploaded_by), updated_at = NOW()'
    );

    $processed = 0;
    $inserted = 0;
    $failed = 0;
    $issues = [];

    $db->beginTransaction();
    try {
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue; // skip blank lines
            }
            $processed++;

            if (count($row) < 8) {
                $failed++; $issues[] = "Row $processed: expected 8 columns, found " . count($row) . '.';
                continue;
            }

            [$matric, $sessionName, $semesterName, $courseCode, $courseTitle, $units, $ca, $exam] = array_map('trim', $row);
            $matric = strtoupper($matric);
            $courseCode = strtoupper($courseCode);

            if ($matric === '' || $sessionName === '' || $semesterName === '' || $courseCode === '') {
                $failed++; $issues[] = "Row $processed: missing a required field.";
                continue;
            }
            if (!is_numeric($units) || !is_numeric($ca) || !is_numeric($exam)) {
                $failed++; $issues[] = "Row $processed ($matric): units/CA/exam must be numbers.";
                continue;
            }

            $units = (int)$units;
            $ca = (float)$ca;
            $exam = (float)$exam;

            if ($ca < 0 || $ca > 30 || $exam < 0 || $exam > 70 || $units < 1) {
                $failed++; $issues[] = "Row $processed ($matric): CA must be 0-30, exam 0-70, units at least 1.";
                continue;
            }

            if (!isset($studentCache[$matric])) {
                $findStudent->execute([':m' => $matric]);
                $studentCache[$matric] = $findStudent->fetch() ?: null;
            }
            $student = $studentCache[$matric];
            if (!$student) {
                $failed++; $issues[] = "Row $processed: no registered student found for $matric.";
                continue;
            }

            if (!isset($sessionCache[$sessionName])) {
                $findSession->execute([':n' => $sessionName]);
                $existing = $findSession->fetch();
                if (!$existing) {
                    $startYear = (int)substr($sessionName, 0, 4) ?: (int)date('Y');
                    $insertSession->execute([':n' => $sessionName, ':s' => $startYear . '-09-01', ':e' => ($startYear + 1) . '-07-31']);
                    $existing = ['id' => (int)$db->lastInsertId()];
                }
                $sessionCache[$sessionName] = $existing;
            }
            $sessionId = (int)$sessionCache[$sessionName]['id'];

            $semKey = $sessionId . '|' . $semesterName;
            if (!isset($semesterCache[$semKey])) {
                $findSemester->execute([':sid' => $sessionId, ':n' => $semesterName]);
                $existing = $findSemester->fetch();
                if (!$existing) {
                    $startYear = (int)substr($sessionName, 0, 4) ?: (int)date('Y');
                    $insertSemester->execute([':n' => $semesterName, ':sid' => $sessionId, ':s' => $startYear . '-09-01', ':e' => ($startYear + 1) . '-07-31']);
                    $existing = ['id' => (int)$db->lastInsertId()];
                }
                $semesterCache[$semKey] = $existing;
            }
            $semesterId = (int)$semesterCache[$semKey]['id'];

            if (!isset($courseCache[$courseCode])) {
                $findCourse->execute([':code' => $courseCode]);
                $existing = $findCourse->fetch();
                if (!$existing) {
                    $insertCourse->execute([
                        ':code' => $courseCode,
                        ':title' => $courseTitle !== '' ? $courseTitle : $courseCode,
                        ':units' => $units,
                        ':dept' => $student['department_id'],
                        ':fac' => $student['faculty_id'],
                        ':sem' => $semesterId,
                        ':sess' => $sessionId,
                    ]);
                    $existing = ['id' => (int)$db->lastInsertId()];
                }
                $courseCache[$courseCode] = $existing;
            }
            $courseId = (int)$courseCache[$courseCode]['id'];

            $grade = grade_for($ca + $exam);

            $upsertResult->execute([
                ':student_id' => $student['id'],
                ':course_id' => $courseId,
                ':session_id' => $sessionId,
                ':semester_id' => $semesterId,
                ':ca' => $ca,
                ':exam' => $exam,
                ':grade' => $grade,
                ':gpa' => grade_point($grade),
                ':uploaded_by' => $uploadedBy,
            ]);

            $touchedStudentIds[$student['id']] = true;
            $inserted++;
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        $issues[] = 'Import stopped and rolled back — no partial data was saved. Error: ' . $e->getMessage();
        fclose($handle);
        return ['processed' => $processed, 'inserted' => 0, 'failed' => $failed, 'issues' => array_slice($issues, 0, 50)];
    }

    fclose($handle);

    // Invalidate the per-student cache used by check-result.php so
    // freshly-uploaded results show up immediately, not after TTL expiry.
    foreach (array_keys($touchedStudentIds) as $studentId) {
        result_cache_clear('wu_result_' . $studentId);
    }

    log_audit('bulk_result_upload: ' . $inserted . ' row(s) published');

    return ['processed' => $processed, 'inserted' => $inserted, 'failed' => $failed, 'issues' => array_slice($issues, 0, 50)];
}

$summary = null;
$errors = [];
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } elseif (empty($_FILES['results_csv']['tmp_name']) || $_FILES['results_csv']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Choose a CSV file to upload.';
    } else {
        $summary = import_results_csv($_FILES['results_csv']['tmp_name'], (int)($user['id'] ?? 0));
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Results — Wesley University Portal</title>
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/portal.css" rel="stylesheet">
  <style>
    .drop-zone { border: 2px dashed rgba(255,255,255,0.25); border-radius: 1rem; padding: 2.5rem; text-align: center; cursor: pointer; transition: border-color .2s ease, background .2s ease; }
    .drop-zone.is-dragover { border-color: #d4a017; background: rgba(212,160,23,0.08); }
    pre.sample { background: rgba(255,255,255,0.06); padding: .9rem 1rem; border-radius: .6rem; white-space: pre-wrap; font-size: .82rem; }
    .issue-list { max-height: 16rem; overflow-y: auto; font-size: .82rem; }
  </style>
</head>
<body class="bg-gray-100 text-dark">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">
    <div class="container">
      <a class="navbar-brand" href="dashboard.php">Wesley Admin</a>
      <div class="collapse navbar-collapse">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="upload-results.php">Upload results</a></li>
          <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container py-5">
    <div class="mb-4">
      <h1 class="h2">Upload student results</h1>
      <p class="text-muted">Publish results for as many students and courses as you like in one go. Their GPA and CGPA are recalculated automatically the moment this finishes.</p>
    </div>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-danger"><?= safe($error) ?></div>
    <?php endforeach ?>

    <?php if ($summary): ?>
      <div class="alert <?= $summary['failed'] > 0 ? 'alert-warning' : 'alert-success' ?>">
        <strong><?= (int)$summary['inserted'] ?></strong> result row(s) published out of <strong><?= (int)$summary['processed'] ?></strong> processed.
        <?php if ($summary['failed'] > 0): ?>
          <?= (int)$summary['failed'] ?> row(s) were skipped — see details below.
        <?php endif ?>
      </div>
      <?php if (!empty($summary['issues'])): ?>
        <div class="card mb-4"><div class="card-body issue-list">
          <ul class="mb-0">
            <?php foreach ($summary['issues'] as $issue): ?>
              <li><?= safe($issue) ?></li>
            <?php endforeach ?>
          </ul>
        </div></div>
      <?php endif ?>
    <?php endif ?>

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <form method="post" enctype="multipart/form-data" id="uploadForm">
              <input type="hidden" name="csrf_token" value="<?= safe($token) ?>">
              <label class="drop-zone d-block mb-3" id="dropZone" for="results_csv">
                <p class="mb-1 fw-semibold">Drop your CSV here, or click to browse</p>
                <p class="text-muted small mb-0">One row per student per course.</p>
                <input class="d-none" id="results_csv" name="results_csv" type="file" accept=".csv" required>
                <p class="small mt-2 mb-0" id="fileName"></p>
              </label>
              <button class="btn btn-warning btn-lg w-100" type="submit">Upload &amp; publish results</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body p-4">
            <h2 class="h5">CSV format</h2>
            <p class="text-muted small mb-1">First row must be exactly this header, in this order:</p>
            <pre class="sample">matric_number,session,semester,course_code,course_title,units,ca_score,exam_score</pre>
            <p class="text-muted small mb-1">Example row:</p>
            <pre class="sample">WU/2021/0143,2023/2024,First Semester,CSC301,Data Structures,3,24,52</pre>
            <ul class="text-muted small mb-3">
              <li>CA score: 0–30. Exam score: 0–70.</li>
              <li>Grades, GPA and CGPA are all calculated automatically.</li>
              <li>The student must already have an account — unknown matric numbers are skipped and listed above.</li>
              <li>Re-uploading the same student + course + session + semester updates that row rather than duplicating it.</li>
            </ul>
            <a class="btn btn-outline-light btn-sm" href="data:text/csv;charset=utf-8,matric_number%2Csession%2Csemester%2Ccourse_code%2Ccourse_title%2Cunits%2Cca_score%2Cexam_score" download="results-template.csv">Download blank template</a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script>
    var input = document.getElementById('results_csv');
    var zone = document.getElementById('dropZone');
    var fileName = document.getElementById('fileName');
    input.addEventListener('change', function () { fileName.textContent = input.files[0] ? input.files[0].name : ''; });
    ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        zone.classList.toggle('is-dragover', evt === 'dragover');
        if (evt === 'drop' && e.dataTransfer.files.length) {
          input.files = e.dataTransfer.files;
          fileName.textContent = e.dataTransfer.files[0].name;
        }
      });
    });
  </script>
</body>
</html>
