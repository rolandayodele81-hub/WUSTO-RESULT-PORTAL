<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$payload = json_decode(file_get_contents('php://input'), true);
$semId = isset($payload['semester_id']) ? (int)$payload['semester_id'] : 0;
if (!$semId) { http_response_code(422); echo json_encode(['error'=>'semester_id required']); exit; }

$pdo = get_db();
$stmt = $pdo->prepare('SELECT student_id FROM semesters WHERE id = ? LIMIT 1');
$stmt->execute([$semId]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); echo json_encode(['error'=>'semester not found']); exit; }
$studentId = $row['student_id'];

$session = trim($payload['session'] ?? '');
$semester = trim($payload['semester'] ?? '');
$courses = $payload['courses'] ?? [];
if (!$session || !$semester || !is_array($courses)) { http_response_code(422); echo json_encode(['error'=>'session, semester, courses required']); exit; }

$pdo->beginTransaction();
try {
    // update semester
    $gpa = compute_gpa_for_courses($courses);
    $ustmt = $pdo->prepare('UPDATE semesters SET session_name = ?, semester_name = ?, gpa = ? WHERE id = ?');
    $ustmt->execute([$session, $semester, $gpa, $semId]);

    // remove existing courses
    $pdo->prepare('DELETE FROM courses WHERE semester_id = ?')->execute([$semId]);

    $cin = $pdo->prepare('INSERT INTO courses (semester_id, code, title, units, ca, exam, total, grade) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($courses as $c) {
        $units = (int)($c['units'] ?? 0);
        $ca = (int)($c['ca'] ?? 0);
        $exam = (int)($c['exam'] ?? 0);
        $total = $ca + $exam;
        $grade = compute_grade($total);
        $cin->execute([$semId, strtoupper(trim($c['code'] ?? '')), trim($c['title'] ?? ''), $units, $ca, $exam, $total, $grade]);
    }

    compute_and_update_cgpa($pdo, $studentId);
    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
