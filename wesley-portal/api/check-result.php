<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/results-store.php';

session_start();

$matric = normalize_matric($_GET['matric'] ?? '');
$lastName = trim((string)($_GET['last_name'] ?? ''));
$mode = 'guest';

if ($matric === '' || $lastName === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Enter a matric number and surname.']);
    exit;
}

$students = load_students();
$student = find_student($students, $matric);

if (!$student || strcasecmp($lastName, (string)($student['lastName'] ?? '')) !== 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Matric number and surname do not match any student.']);
    exit;
}

$payload = build_result_payload($student, $mode);
echo json_encode($payload);
