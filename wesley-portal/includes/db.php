<?php
require_once __DIR__ . '/config.php';

function get_db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}

function validate_matric($matric) {
    return preg_match(MATRIC_PATTERN, $matric);
}

function compute_grade($total) {
    if ($total >= 70) return 'A';
    if ($total >= 60) return 'B';
    if ($total >= 50) return 'C';
    if ($total >= 45) return 'D';
    if ($total >= 40) return 'E';
    return 'F';
}

function compute_gpa_for_courses(array $courses) {
    $points = ['A'=>5,'B'=>4,'C'=>3,'D'=>2,'E'=>1,'F'=>0];
    $totalPoints = 0; $totalUnits = 0;
    foreach ($courses as $c) {
        $grade = isset($c['grade']) ? $c['grade'] : compute_grade(($c['ca'] ?? 0) + ($c['exam'] ?? 0));
        $units = (int)($c['units'] ?? 0);
        $totalPoints += ($points[$grade] ?? 0) * $units;
        $totalUnits += $units;
    }
    return $totalUnits ? round($totalPoints / $totalUnits, 2) : 0.00;
}

function compute_and_update_cgpa($pdo, $studentId) {
    // Compute running CGPA per semester and update the semesters table
    $points = ['A'=>5,'B'=>4,'C'=>3,'D'=>2,'E'=>1,'F'=>0];
    $stmt = $pdo->prepare('SELECT id FROM semesters WHERE student_id = ? ORDER BY session_name, semester_name, id');
    $stmt->execute([$studentId]);
    $semesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $totalPoints = 0;
    $totalUnits = 0;
    foreach ($semesters as $semId) {
        $cstmt = $pdo->prepare('SELECT units, grade FROM courses WHERE semester_id = ?');
        $cstmt->execute([$semId]);
        $courses = $cstmt->fetchAll();
        foreach ($courses as $c) {
            $g = strtoupper($c['grade'] ?? 'F');
            $u = (int)($c['units'] ?? 0);
            $gp = isset($points[$g]) ? $points[$g] : 0;
            $totalPoints += $gp * $u;
            $totalUnits += $u;
        }
        $running = $totalUnits ? round($totalPoints / $totalUnits, 2) : 0.00;
        $ustmt = $pdo->prepare('UPDATE semesters SET cgpa = ? WHERE id = ?');
        $ustmt->execute([$running, $semId]);
    }
    return $totalUnits ? round($totalPoints / $totalUnits, 2) : 0.00;
}

?>
