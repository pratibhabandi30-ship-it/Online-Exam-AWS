<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$cases = [
    ['/online-exam-system/faculty/dashboard.php', 'faculty/exams.php', 'http://localhost/online-exam-system/faculty/exams.php'],
    ['/online-exam-system/faculty/exams.php', 'faculty/results.php', 'http://localhost/online-exam-system/faculty/results.php'],
    ['/online-exam-system/faculty/results.php', 'notifications.php', 'http://localhost/online-exam-system/notifications.php'],
    ['/online-exam-system/admin/dashboard.php', 'admin/approvals.php', 'http://localhost/online-exam-system/admin/approvals.php'],
    ['/online-exam-system/dashboard.php', 'faculty/dashboard.php', 'http://localhost/online-exam-system/faculty/dashboard.php'],
];

foreach ($cases as [$scriptName, $path, $expected]) {
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['HTTPS'] = '';
    $actual = base_url($path);
    if ($actual !== $expected) {
        throw new RuntimeException(sprintf('Expected %s, got %s.', $expected, $actual));
    }

    $relativePath = substr((string) parse_url($actual, PHP_URL_PATH), strlen('/online-exam-system/'));
    $target = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($target)) {
        throw new RuntimeException(sprintf('Navigation target does not exist: %s.', $target));
    }
}

echo "Role navigation URL checks passed.\n";
