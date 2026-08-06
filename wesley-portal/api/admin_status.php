<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (admin_is_logged_in()) {
    echo json_encode(['authenticated' => true, 'username' => $_SESSION['admin_username']]);
} else {
    echo json_encode(['authenticated' => false]);
}
exit;
