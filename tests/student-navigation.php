<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$current = ['name' => 'Navigation Test Student'];
$studentActive = 'dashboard';
ob_start();
require __DIR__ . '/../includes/student-nav.php';
$navigation = (string) ob_get_clean();

$expectedItems = [
    'Dashboard' => 'dashboard.php',
    'Exams' => 'exams.php',
    'Timetable' => 'timetable.php',
    'My Attendance' => 'attendance.php',
    'Announcements' => 'announcements.php',
    'Results & Performance' => 'history.php',
    'My Certificates' => 'certificates.php',
    'Leaderboard' => 'leaderboard.php',
    'Feedback' => 'feedback.php',
    'Notifications' => 'notifications.php',
    'Settings' => 'profile.php',
];

foreach ($expectedItems as $label => $path) {
    $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    if (!str_contains($navigation, $escapedLabel) || !str_contains($navigation, base_url($path)) || !is_file(__DIR__ . '/../' . $path)) {
        throw new RuntimeException("Student navigation item is missing or invalid: $label");
    }
}
if (!str_contains($navigation, 'aria-current="page"') || !str_contains($navigation, 'Navigation Test Student')) {
    throw new RuntimeException('Student active navigation or profile is missing.');
}

echo "Student sidebar labels, routes, active state, and profile passed.\n";
