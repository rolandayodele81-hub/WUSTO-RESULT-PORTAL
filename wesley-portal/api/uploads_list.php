<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_admin();

require_once __DIR__ . '/../includes/db.php';

// List processed uploads for admins. Supports ?limit=50&offset=0
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
if ($limit <= 0) $limit = 50;
if ($limit > 500) $limit = 500;
if ($offset < 0) $offset = 0;

$pdo = get_db();
$totalStmt = $pdo->query('SELECT COUNT(*) FROM uploads');
$total = (int)$totalStmt->fetchColumn();

$stmt = $pdo->prepare('SELECT id, file_hash, file_name, size, processed_at FROM uploads ORDER BY processed_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'items' => $items]);
exit;

?>
