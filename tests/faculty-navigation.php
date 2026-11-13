<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$nav = file_get_contents($root . '/faculty/_nav.php');
if ($nav === false) {
    throw new RuntimeException('Faculty navigation could not be read.');
}

$requiredItems = [
    'Dashboard',
    'My Students',
    'My Exams',
    'Question Bank',
    'Attendance Management',
    'Announcements',
    'Timetable Management',
    'Performance Analytics',
    'Results and Evaluation',
    'Reports',
    'Notifications',
    'Settings',
    'Logout',
];
foreach ($requiredItems as $item) {
    if (!str_contains($nav, $item)) {
        throw new RuntimeException('Missing faculty navigation item: ' . $item);
    }
}

preg_match_all("/'((?:faculty\\/)?[a-z-]+\\.php)'/", $nav, $matches);
foreach ($matches[1] as $path) {
    if (str_contains($path, 'faculty/faculty/')) {
        throw new RuntimeException('Faculty route repeats the role directory.');
    }
    if (!is_file($root . '/' . $path)) {
        throw new RuntimeException('Faculty navigation points to a missing page: ' . $path);
    }
}

echo "Faculty navigation labels and destinations passed." . PHP_EOL;
