<?php
// Minimal protected endpoint to add/update a student's semester and courses.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

// Basic auth check — in production, replace with proper admin session check
$auth = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
if (!$auth || $auth !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Use basic auth with admin credentials for initial setup.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$matric = strtoupper(trim($payload['matric'] ?? ''));
if (!$matric || !validate_matric($matric)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid matric format']);
    exit;
}

$pdo = get_db();
$pdo->beginTransaction();
try {
    // Upsert student
    $stmt = $pdo->prepare('SELECT id FROM students WHERE matric = ? LIMIT 1');
    $stmt->execute([$matric]);
    $student = $stmt->fetch();
    if ($student) {
        $studentId = $student['id'];
        $stmt = $pdo->prepare('UPDATE students SET full_name = ?, department = ?, programme = ?, level = ? WHERE id = ?');
        $stmt->execute([trim($payload['full_name'] ?? ''), trim($payload['department'] ?? ''), trim($payload['programme'] ?? ''), trim($payload['level'] ?? ''), $studentId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO students (matric, full_name, department, programme, level) VALUES (?,?,?,?,?)');
        $stmt->execute([$matric, trim($payload['full_name'] ?? ''), trim($payload['department'] ?? ''), trim($payload['programme'] ?? ''), trim($payload['level'] ?? '')]);
        $studentId = $pdo->lastInsertId();
    }

    // Insert semester
    $session = trim($payload['session'] ?? '');
    $semester = trim($payload['semester'] ?? '');
    $courses = $payload['courses'] ?? [];
    if (!$session || !$semester || !is_array($courses) || empty($courses)) {
        throw new Exception('Missing session, semester or courses');
    }

    $gpa = compute_gpa_for_courses($courses);
    // Insert semester row
    $stmt = $pdo->prepare('INSERT INTO semesters (student_id, session_name, semester_name, gpa, cgpa) VALUES (?,?,?,?,?)');
    $stmt->execute([$studentId, $session, $semester, $gpa, 0.0]);
    $semesterId = $pdo->lastInsertId();

    $courseInsert = $pdo->prepare('INSERT INTO courses (semester_id, code, title, units, ca, exam, total, grade) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($courses as $c) {
        $units = (int)($c['units'] ?? 0);
        $ca = (int)($c['ca'] ?? 0);
        $exam = (int)($c['exam'] ?? 0);
        $total = $ca + $exam;
        $grade = compute_grade($total);
        $courseInsert->execute([$semesterId, strtoupper(trim($c['code'] ?? '')), trim($c['title'] ?? ''), $units, $ca, $exam, $total, $grade]);
    }

    // TODO: compute CGPA and update (simple placeholder: copy semester gpa)
    $stmt = $pdo->prepare('UPDATE semesters SET cgpa = ? WHERE id = ?');
    $stmt->execute([$gpa, $semesterId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'student_id' => $studentId, 'semester_id' => $semesterId]);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
