<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . str_replace('%','\%',$q) . '%';
$pdo = get_db();
$stmt = $pdo->prepare('SELECT id, matric, full_name, department, programme, level FROM students WHERE matric LIKE ? OR full_name LIKE ? LIMIT 50');
$stmt->execute([$like, $like]);
$rows = $stmt->fetchAll();
echo json_encode(['results' => $rows]);
exit;
