<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_admin();
// verify CSRF token for the upload (header or form field)
require_csrf();
require_once __DIR__ . '/../includes/db.php';

// Accept multipart/form-data with `file` field
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$tmp = $file['tmp_name'];
$name = $file['name'];
$size = (int)$file['size'];
$hash = sha1_file($tmp);

$pdo = get_db();
// prevent duplicate uploads (idempotency)
$stmt = $pdo->prepare('SELECT id FROM uploads WHERE file_hash = ? LIMIT 1');
$stmt->execute([$hash]);
if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'message' => 'This file has already been processed.']);
    exit;
}

// helper: normalize header names
function normalize_header($h) {
    return strtolower(preg_replace('/[^a-z0-9]+/', '', $h));
}

function parse_text_rows($text) {
    $rows = [];
    $lines = preg_split('/\r?\n/', $text);
    $header = null;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        // table-like text: split on tabs or multiple spaces.
        $parts = preg_split('/\t+|\s{2,}/', $trimmed);
        if (!$header) {
            $normalized = array_map('normalize_header', $parts);
            if (count($normalized) >= 3 && (in_array('matric', $normalized, true) || in_array('studentname', $normalized, true) || in_array('gpa', $normalized, true))) {
                $header = $normalized;
                continue;
            }
            continue;
        }
        if (count($parts) < 3) continue;
        $rec = [];
        foreach ($header as $i => $key) {
            $rec[$key] = isset($parts[$i]) ? trim($parts[$i]) : '';
        }
        $rows[] = $rec;
    }
    return $rows;
}

// mapping helpers
function pick($row, $candidates) {
    foreach ($candidates as $c) {
        if (isset($row[$c]) && trim($row[$c]) !== '') return trim($row[$c]);
    }
    return '';
}

try {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $rows = [];

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new Exception('Dependencies unavailable. Run `composer install` in wesley-portal.');
    }
    require_once $autoload;

    if ($ext === 'csv' || $ext === 'txt') {
        $fh = fopen($tmp, 'r');
        if (!$fh) throw new Exception('Unable to open uploaded CSV');
        $header = null;
        while (($line = fgetcsv($fh)) !== false) {
            if (!$header) { $header = array_map('normalize_header', $line); continue; }
            $rec = [];
            foreach ($header as $i => $h) { $rec[$h] = isset($line[$i]) ? $line[$i] : ''; }
            $rows[] = $rec;
        }
        fclose($fh);
    } elseif ($ext === 'xlsx' || $ext === 'xls') {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);
        if (count($data) < 2) throw new Exception('No rows found in spreadsheet');
        $first = array_shift($data);
        $header = [];
        foreach ($first as $col => $val) { $header[] = normalize_header($val); }
        foreach ($data as $row) {
            $rec = [];
            $i = 0;
            foreach ($row as $col => $val) { $rec[$header[$i] ?? 'col'.$i] = $val; $i++; }
            $rows[] = $rec;
        }
    } elseif ($ext === 'pdf') {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tmp);
        $text = trim($pdf->getText());
        $rows = parse_text_rows($text);
    } elseif ($ext === 'docx' || $ext === 'doc') {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmp);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        $rows = parse_text_rows($text);
    } else {
        throw new Exception('Unsupported file type. Upload XLSX, CSV, PDF or DOCX.');
    }

    if (empty($rows)) {
        throw new Exception('No data rows detected in uploaded file');
    }

    $pdo->beginTransaction();
    $inserted = 0; $updated = 0; $skipped = 0;

    $upsertStudent = $pdo->prepare('SELECT id FROM students WHERE matric = ? LIMIT 1');
    $insertStudent = $pdo->prepare('INSERT INTO students (matric, full_name, department, programme, level) VALUES (?,?,?,?,?)');
    $updateStudent = $pdo->prepare('UPDATE students SET full_name = ?, department = ?, programme = ?, level = ? WHERE id = ?');

    $findSemester = $pdo->prepare('SELECT id FROM semesters WHERE student_id = ? AND session_name = ? AND semester_name = ? LIMIT 1');
    $insertSemester = $pdo->prepare('INSERT INTO semesters (student_id, session_name, semester_name, gpa, cgpa) VALUES (?,?,?,?,?)');
    $updateSemester = $pdo->prepare('UPDATE semesters SET gpa = ?, cgpa = ? WHERE id = ?');

    $insertCourse = $pdo->prepare('INSERT INTO courses (semester_id, code, title, units, ca, exam, total, grade) VALUES (?,?,?,?,?,?,?,?)');
    $deleteCourses = $pdo->prepare('DELETE FROM courses WHERE semester_id = ?');

    foreach ($rows as $r) {
        // normalize keys: try common candidates
        $matric = strtoupper(trim(pick($r, ['matric', 'matricnumber', 'matric_no', 'studentmatric', 'student_matno', 'matricno', 'matricnumber'])));
        $full_name = trim(pick($r, ['name', 'studentname', 'fullname', 'full_name']));
        $department = trim(pick($r, ['department', 'dept', 'faculty']));
        $level = trim(pick($r, ['level', 'programmelevel', 'levelname']));
        $session = trim(pick($r, ['session', 'academicsession', 'academic_session', 'session_name']));
        $semester = trim(pick($r, ['semester', 'semester_name', 'semestername']));
        $gpa = is_numeric(pick($r, ['gpa', 'gradepointaverage'])) ? (float)pick($r, ['gpa', 'gradepointaverage']) : null;
        $cgpa = is_numeric(pick($r, ['cgpa', 'cumulativegpa', 'cumulative_gpa'])) ? (float)pick($r, ['cgpa', 'cumulativegpa', 'cumulative_gpa']) : null;

        $course_code = strtoupper(trim(pick($r, ['coursecode', 'course_code', 'code', 'courseno'])));
        $course_title = trim(pick($r, ['coursetitle', 'course_title', 'title', 'course']));
        $course_units = is_numeric(pick($r, ['units', 'creditunits', 'credit_units'])) ? intval(pick($r, ['units', 'creditunits', 'credit_units'])) : 0;
        $course_ca = is_numeric(pick($r, ['ca', 'ca_score', 'ca_score'])) ? intval(pick($r, ['ca', 'ca_score', 'ca_score'])) : 0;
        $course_exam = is_numeric(pick($r, ['exam', 'exam_score', 'exam_score'])) ? intval(pick($r, ['exam', 'exam_score', 'exam_score'])) : 0;
        $course_grade = trim(pick($r, ['grade', 'lettergrade', 'grade_letter']));

        if (!$matric || !validate_matric($matric)) { $skipped++; continue; }

        // upsert student
        $upsertStudent->execute([$matric]);
        $sidRow = $upsertStudent->fetch();
        if ($sidRow) {
            $studentId = $sidRow['id'];
            $updateStudent->execute([$full_name, $department, '', $level, $studentId]);
            $updated++;
        } else {
            $insertStudent->execute([$matric, $full_name, $department, '', $level]);
            $studentId = $pdo->lastInsertId();
            $inserted++;
        }

        // semesters: update or insert
        $semId = null;
        if ($session && $semester) {
            $findSemester->execute([$studentId, $session, $semester]);
            $semRow = $findSemester->fetch();
            if ($semRow) {
                $semId = $semRow['id'];
                if ($gpa !== null || $cgpa !== null) {
                    $updateSemester->execute([$gpa ?? 0, $cgpa ?? 0, $semId]);
                }
            } else {
                $insertSemester->execute([$studentId, $session, $semester, $gpa ?? 0, $cgpa ?? 0]);
                $semId = $pdo->lastInsertId();
            }
        }

        // insert course row if provided
        if ($semId && $course_code) {
            $total = $course_ca + $course_exam;
            $grade = $course_grade ?: compute_grade($total);
            $insertCourse->execute([$semId, $course_code, $course_title, $course_units, $course_ca, $course_exam, $total, $grade]);
        }

        // if a courses field exists and is JSON, try insert as course list
        if ($semId && (!empty($r['courses']) || !empty($r['coursedetails']) || !empty($r['course_details']))) {
            $json = $r['courses'] ?? $r['coursedetails'] ?? $r['course_details'];
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $deleteCourses->execute([$semId]);
                foreach ($decoded as $c) {
                    $code = strtoupper(trim($c['code'] ?? ($c['course_code'] ?? '')));
                    if (!$code) continue;
                    $title = trim($c['title'] ?? ($c['course_title'] ?? ''));
                    $units = intval($c['units'] ?? 0);
                    $ca = intval($c['ca'] ?? 0);
                    $exam = intval($c['exam'] ?? 0);
                    $total = $ca + $exam;
                    $grade = trim($c['grade'] ?? '') ?: compute_grade($total);
                    $insertCourse->execute([$semId, $code, $title, $units, $ca, $exam, $total, $grade]);
                }
            }
        }
    }

    // record upload hash
    $ins = $pdo->prepare('INSERT INTO uploads (file_hash, file_name, size) VALUES (?,?,?)');
    $ins->execute([$hash, $name, $size]);

    $pdo->commit();
    echo json_encode(['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped]);
    exit;
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

?>
