<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$semId = isset($data['semester_id']) ? (int)$data['semester_id'] : 0;
if (!$semId) { http_response_code(422); echo json_encode(['error'=>'semester_id required']); exit; }

$pdo = get_db();
// get student_id for recompute
$stmt = $pdo->prepare('SELECT student_id FROM semesters WHERE id = ? LIMIT 1');
$stmt->execute([$semId]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); echo json_encode(['error'=>'semester not found']); exit; }
$studentId = $row['student_id'];

$stmt = $pdo->prepare('DELETE FROM semesters WHERE id = ?');
$stmt->execute([$semId]);

compute_and_update_cgpa($pdo, $studentId);

echo json_encode(['success' => true]);
exit;
