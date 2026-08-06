<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$matric = isset($_GET['matric']) ? trim($_GET['matric']) : '';
if (!$matric) {
    echo json_encode(['error' => 'Matric is required']);
    exit;
}

$matricNorm = strtoupper($matric);
$pdo = get_db();

$stmt = $pdo->prepare('SELECT id, matric, full_name, department, programme, level FROM students WHERE matric = ? LIMIT 1');
$stmt->execute([$matricNorm]);
$student = $stmt->fetch();
if (!$student) {
    echo json_encode(['found' => false, 'message' => 'No Result Found.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, session_name, semester_name, gpa, cgpa FROM semesters WHERE student_id = ? ORDER BY session_name DESC, semester_name DESC');
$stmt->execute([$student['id']]);
$semesters = $stmt->fetchAll();

foreach ($semesters as &$sem) {
    $stmt = $pdo->prepare('SELECT code, title, units, ca, exam, total, grade FROM courses WHERE semester_id = ? ORDER BY code');
    $stmt->execute([$sem['id']]);
    $sem['courses'] = $stmt->fetchAll();
}

echo json_encode(['found' => true, 'student' => $student, 'semesters' => $semesters]);
exit;
