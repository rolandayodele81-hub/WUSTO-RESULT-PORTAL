<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (!trim($username) || !trim($password)) {
    http_response_code(422);
    echo json_encode(['error' => 'Username and password required']);
    exit;
}

if (attempt_admin_login($username, $password)) {
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(401);
echo json_encode(['error' => 'Invalid credentials']);
exit;
