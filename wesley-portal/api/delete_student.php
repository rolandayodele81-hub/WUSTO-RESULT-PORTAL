<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$studentId = isset($data['student_id']) ? (int)$data['student_id'] : 0;
if (!$studentId) { http_response_code(422); echo json_encode(['error'=>'student_id required']); exit; }

$pdo = get_db();
$stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
$stmt->execute([$studentId]);

echo json_encode(['success'=>true]);
exit;
