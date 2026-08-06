<?php
declare(strict_types=1);

function results_data_path(): string
{
    return __DIR__ . '/../data/students.json';
}

function grade_for(int|float $total): string
{
    if ($total >= 70) return 'A';
    if ($total >= 60) return 'B';
    if ($total >= 50) return 'C';
    if ($total >= 45) return 'D';
    if ($total >= 40) return 'E';
    return 'F';
}

function grade_point(string $grade): float
{
    return match ($grade) {
        'A' => 5.0,
        'B' => 4.0,
        'C' => 3.0,
        'D' => 2.0,
        'E' => 1.0,
        default => 0.0,
    };
}

function default_students(): array
{
    return [
        [
            'matric' => 'WU/2021/0143',
            'password' => 'wesley2026',
            'firstName' => 'Adaeze',
            'lastName' => 'Okonkwo',
            'department' => 'Computer Science',
            'level' => '300 Level',
            'semesters' => [
                [
                    'session' => '2023/2024',
                    'semester' => 'First Semester',
                    'courses' => [
                        ['code' => 'CSC301', 'title' => 'Data Structures & Algorithms', 'units' => 3, 'ca' => 24, 'exam' => 52, 'total' => 76, 'grade' => 'A'],
                        ['code' => 'CSC303', 'title' => 'Operating Systems', 'units' => 3, 'ca' => 22, 'exam' => 48, 'total' => 70, 'grade' => 'A'],
                        ['code' => 'CSC305', 'title' => 'Database Management Systems', 'units' => 2, 'ca' => 26, 'exam' => 58, 'total' => 84, 'grade' => 'A'],
                    ],
                ],
                [
                    'session' => '2023/2024',
                    'semester' => 'Second Semester',
                    'courses' => [
                        ['code' => 'CSC302', 'title' => 'Software Engineering', 'units' => 3, 'ca' => 25, 'exam' => 55, 'total' => 80, 'grade' => 'A'],
                        ['code' => 'CSC304', 'title' => 'Computer Networks', 'units' => 3, 'ca' => 23, 'exam' => 44, 'total' => 67, 'grade' => 'B'],
                    ],
                ],
            ],
        ],
        [
            'matric' => 'WU/2020/0871',
            'password' => 'wesley2026',
            'firstName' => 'Tobiloba',
            'lastName' => 'Adewale',
            'department' => 'Accounting',
            'level' => '400 Level',
            'semesters' => [
                [
                    'session' => '2023/2024',
                    'semester' => 'First Semester',
                    'courses' => [
                        ['code' => 'ACC411', 'title' => 'Public Sector Accounting', 'units' => 3, 'ca' => 25, 'exam' => 57, 'total' => 82, 'grade' => 'A'],
                        ['code' => 'ACC413', 'title' => 'Management Accounting', 'units' => 3, 'ca' => 23, 'exam' => 49, 'total' => 72, 'grade' => 'A'],
                    ],
                ],
            ],
        ],
        [
            'matric' => 'WU/2022/1290',
            'password' => 'wesley2026',
            'firstName' => 'Miracle',
            'lastName' => 'Eze',
            'department' => 'Mass Communication',
            'level' => '200 Level',
            'semesters' => [
                [
                    'session' => '2023/2024',
                    'semester' => 'First Semester',
                    'courses' => [
                        ['code' => 'MAC201', 'title' => 'Reporting & News Writing', 'units' => 3, 'ca' => 26, 'exam' => 56, 'total' => 82, 'grade' => 'A'],
                        ['code' => 'MAC203', 'title' => 'Broadcast Journalism', 'units' => 3, 'ca' => 24, 'exam' => 51, 'total' => 75, 'grade' => 'A'],
                    ],
                ],
            ],
        ],
    ];
}

function load_students(): array
{
    $path = results_data_path();
    if (!file_exists($path)) {
        save_students(default_students());
        return default_students();
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return default_students();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        save_students(default_students());
        return default_students();
    }

    return $decoded;
}

function save_students(array $students): void
{
    $path = results_data_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function normalize_matric(string $matric): string
{
    return strtoupper(trim($matric));
}

function find_student(array $students, string $matric): ?array
{
    $needle = normalize_matric($matric);
    foreach ($students as $student) {
        if (($student['matric'] ?? '') === $needle) {
            return $student;
        }
    }
    return null;
}

function enrich_semesters(array $semesters): array
{
    $enriched = [];
    $totalPoints = 0.0;
    $totalUnits = 0;

    foreach ($semesters as $sem) {
        $semPoints = 0.0;
        $semUnits = 0;
        $courses = [];
        foreach ($sem['courses'] ?? [] as $course) {
            $total = (float)($course['total'] ?? 0);
            $grade = $course['grade'] ?? grade_for($total);
            $units = (int)($course['units'] ?? 0);
            $courses[] = [
                'code' => $course['code'],
                'title' => $course['title'],
                'units' => $units,
                'ca' => (float)($course['ca'] ?? 0),
                'exam' => (float)($course['exam'] ?? 0),
                'total' => $total,
                'grade' => $grade,
            ];
            $semPoints += grade_point($grade) * $units;
            $semUnits += $units;
        }

        $gpa = $semUnits > 0 ? round($semPoints / $semUnits, 2) : 0.0;
        $totalPoints += $semPoints;
        $totalUnits += $semUnits;
        $enriched[] = [
            'session' => $sem['session'],
            'semester' => $sem['semester'],
            'courses' => $courses,
            'gpa' => $gpa,
        ];
    }

    return ['semesters' => $enriched, 'cgpa' => $totalUnits > 0 ? round($totalPoints / $totalUnits, 2) : 0.0];
}

function build_result_payload(array $student, string $mode): array
{
    $studentData = [
        'firstName' => $student['firstName'] ?? '',
        'lastName' => $student['lastName'] ?? '',
        'matric' => $student['matric'] ?? '',
        'department' => $student['department'] ?? '',
        'level' => $student['level'] ?? '',
    ];

    $enriched = enrich_semesters($student['semesters'] ?? []);
    $semesters = $enriched['semesters'];
    $latest = $semesters ? $semesters[count($semesters) - 1] : null;

    return [
        'ok' => true,
        'mode' => $mode,
        'student' => $studentData,
        'semesters' => $mode === 'guest' && $latest ? [$latest] : $semesters,
        'cgpa' => $mode === 'account' ? $enriched['cgpa'] : null,
    ];
}

function import_results_csv(string $path): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return ['processed' => 0, 'inserted' => 0, 'failed' => 0, 'issues' => ['Could not read the uploaded CSV file.']];
    }

    $expected = ['matric_number', 'session', 'semester', 'course_code', 'course_title', 'units', 'ca_score', 'exam_score'];
    $header = fgetcsv($handle);
    if (!$header || array_map('strtolower', array_map('trim', $header)) !== $expected) {
        fclose($handle);
        return ['processed' => 0, 'inserted' => 0, 'failed' => 0, 'issues' => ['The first row must be exactly: ' . implode(',', $expected)]];
    }

    $students = load_students();
    $processed = 0;
    $inserted = 0;
    $failed = 0;
    $issues = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (count(array_filter($row, fn($value) => trim((string)$value) !== '')) === 0) {
            continue;
        }

        $processed++;
        if (count($row) < 8) {
            $failed++;
            $issues[] = "Row $processed: expected 8 columns, found " . count($row) . '.';
            continue;
        }

        [$matric, $session, $semester, $courseCode, $courseTitle, $units, $ca, $exam] = array_map('trim', $row);
        $matric = normalize_matric($matric);
        $courseCode = strtoupper($courseCode);

        if ($matric === '' || $session === '' || $semester === '' || $courseCode === '') {
            $failed++;
            $issues[] = "Row $processed: missing required values.";
            continue;
        }

        if (!is_numeric($units) || !is_numeric($ca) || !is_numeric($exam)) {
            $failed++;
            $issues[] = "Row $processed ($matric): units/CA/exam must be numeric.";
            continue;
        }

        $student = find_student($students, $matric);
        if (!$student) {
            $failed++;
            $issues[] = "Row $processed: no matching student found for $matric.";
            continue;
        }

        $units = (int)$units;
        $ca = (float)$ca;
        $exam = (float)$exam;
        $total = $ca + $exam;
        $grade = grade_for($total);

        $updated = false;
        foreach ($student['semesters'] ?? [] as &$existingSemester) {
            if (($existingSemester['session'] ?? '') === $session && ($existingSemester['semester'] ?? '') === $semester) {
                foreach ($existingSemester['courses'] ?? [] as &$course) {
                    if (($course['code'] ?? '') === $courseCode) {
                        $course['title'] = $courseTitle !== '' ? $courseTitle : $course['title'];
                        $course['units'] = $units;
                        $course['ca'] = $ca;
                        $course['exam'] = $exam;
                        $course['total'] = $total;
                        $course['grade'] = $grade;
                        $updated = true;
                        break;
                    }
                }
                if (!$updated) {
                    $existingSemester['courses'][] = [
                        'code' => $courseCode,
                        'title' => $courseTitle !== '' ? $courseTitle : $courseCode,
                        'units' => $units,
                        'ca' => $ca,
                        'exam' => $exam,
                        'total' => $total,
                        'grade' => $grade,
                    ];
                    $updated = true;
                }
                break;
            }
        }
        unset($existingSemester, $course);

        if (!$updated) {
            $student['semesters'][] = [
                'session' => $session,
                'semester' => $semester,
                'courses' => [[
                    'code' => $courseCode,
                    'title' => $courseTitle !== '' ? $courseTitle : $courseCode,
                    'units' => $units,
                    'ca' => $ca,
                    'exam' => $exam,
                    'total' => $total,
                    'grade' => $grade,
                ]],
            ];
        }

        $inserted++;
    }

    fclose($handle);
    save_students($students);

    return ['processed' => $processed, 'inserted' => $inserted, 'failed' => $failed, 'issues' => array_slice($issues, 0, 50)];
}
