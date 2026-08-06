<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

auth_start();

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$db = db();
$mode = 'guest';
$studentRow = null;

if (is_logged_in()) {
    // Signed-in students never need to type their matric number again.
    $user = current_user();
    $stmt = $db->prepare('SELECT s.id, s.matric_number, s.level, u.first_name, u.last_name, d.name AS department_name
                           FROM students s JOIN users u ON u.id = s.user_id JOIN departments d ON d.id = s.department_id
                           WHERE s.user_id = :uid');
    $stmt->execute([':uid' => $user['id']]);
    $studentRow = $stmt->fetch();
    $mode = 'account';
    if (!$studentRow) json_out(['ok' => false, 'error' => 'No student profile is linked to this account yet.'], 404);
} else {
    // Guest lookup: matric + surname, so a matric number alone can't
    // pull up someone else's scores.
    $matric = strtoupper(trim($_GET['matric'] ?? ''));
    $lastName = trim($_GET['last_name'] ?? '');
    if ($matric === '' || $lastName === '') {
        json_out(['ok' => false, 'error' => 'Enter both matric number and surname.'], 422);
    }

    // Basic per-session rate limit for anonymous lookups.
    $_SESSION['guest_lookup_count'] = $_SESSION['guest_lookup_count'] ?? 0;
    $_SESSION['guest_lookup_window'] = $_SESSION['guest_lookup_window'] ?? time();
    if (time() - $_SESSION['guest_lookup_window'] > 60) {
        $_SESSION['guest_lookup_count'] = 0;
        $_SESSION['guest_lookup_window'] = time();
    }
    $_SESSION['guest_lookup_count']++;
    if ($_SESSION['guest_lookup_count'] > 20) {
        json_out(['ok' => false, 'error' => 'Too many lookups from this session. Please wait a minute and try again.'], 429);
    }

    $stmt = $db->prepare('SELECT s.id, s.matric_number, s.level, u.first_name, u.last_name, d.name AS department_name
                           FROM students s JOIN users u ON u.id = s.user_id JOIN departments d ON d.id = s.department_id
                           WHERE s.matric_number = :m');
    $stmt->execute([':m' => $matric]);
    $studentRow = $stmt->fetch();

    if (!$studentRow || strcasecmp($lastName, $studentRow['last_name']) !== 0) {
        json_out(['ok' => false, 'error' => 'Matric number and surname do not match our records.'], 404);
    }
}

$studentId = (int)$studentRow['id'];
$cacheKey = 'wu_result_' . $studentId . '_' . $mode;
$cached = result_cache_get($cacheKey);

if ($cached === false) {
    $resultsStmt = $db->prepare(
        'SELECT r.ca_score, r.exam_score, r.total_score, r.grade,
                c.code, c.title, c.credit_units,
                sem.id AS semester_id, sem.name AS semester_name,
                ses.id AS session_id, ses.name AS session_name, ses.start_date
         FROM results r
         JOIN courses c ON c.id = r.course_id
         JOIN semesters sem ON sem.id = r.semester_id
         JOIN academic_sessions ses ON ses.id = r.academic_session_id
         WHERE r.student_id = :sid AND r.status = "Published"
         ORDER BY ses.start_date ASC, sem.starts_on ASC, c.code ASC'
    );
    $resultsStmt->execute([':sid' => $studentId]);
    $rows = $resultsStmt->fetchAll();

    $semesters = [];
    foreach ($rows as $row) {
        $key = $row['session_id'] . '-' . $row['semester_id'];
        if (!isset($semesters[$key])) {
            $semesters[$key] = ['session' => $row['session_name'], 'semester' => $row['semester_name'], 'courses' => []];
        }
        $semesters[$key]['courses'][] = [
            'code' => $row['code'],
            'title' => $row['title'],
            'units' => (int)$row['credit_units'],
            'ca' => (float)$row['ca_score'],
            'exam' => (float)$row['exam_score'],
            'total' => (float)$row['total_score'],
            'grade' => $row['grade'],
        ];
    }
    $semesters = array_values($semesters);

    $totalPoints = 0.0;
    $totalUnits = 0;
    foreach ($semesters as &$sem) {
        $semPoints = 0.0;
        $semUnits = 0;
        foreach ($sem['courses'] as $c) {
            $semPoints += grade_point($c['grade']) * $c['units'];
            $semUnits += $c['units'];
        }
        $sem['gpa'] = $semUnits ? round($semPoints / $semUnits, 2) : 0.0;
        $totalPoints += $semPoints;
        $totalUnits += $semUnits;
    }
    unset($sem);

    $cgpa = $totalUnits ? round($totalPoints / $totalUnits, 2) : 0.0;

    $payload = [
        'ok' => true,
        'mode' => $mode,
        'student' => [
            'firstName' => $studentRow['first_name'],
            'lastName' => $studentRow['last_name'],
            'matric' => $studentRow['matric_number'],
            'department' => $studentRow['department_name'],
            'level' => $studentRow['level'],
        ],
        // Guests only ever see their most recent published semester —
        // full transcript history requires signing in.
        'semesters' => $mode === 'guest' && $semesters ? [end($semesters)] : $semesters,
        'cgpa' => $mode === 'account' ? $cgpa : null,
    ];

    result_cache_set($cacheKey, $payload, 45); // short TTL: fresh enough, still absorbs bursts
    echo json_encode($payload);
    exit;
}

echo json_encode($cached);
