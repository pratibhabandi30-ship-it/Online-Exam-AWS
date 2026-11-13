<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/faculty-tools.php';

$columns = ['Student', 'Registration', 'Date', 'Status', 'Remarks'];
$values = [
    'Student, "Example"',
    'REG-1',
    '2026-11-08',
    'Present',
    "Line one\nLine two",
];
$safeValues = array_map('faculty_csv_safe_cell', $values);
$stream = fopen('php://temp', 'w+b');
if ($stream === false) {
    throw new RuntimeException('Unable to open temporary CSV stream.');
}
fputcsv($stream, $columns, ',', '"', '');
fputcsv($stream, $safeValues, ',', '"', '');
rewind($stream);
$actualColumns = fgetcsv($stream, null, ',', '"', '');
$actualValues = fgetcsv($stream, null, ',', '"', '');
fclose($stream);

if ($actualColumns !== $columns || $actualValues !== $values) {
    throw new RuntimeException('Attendance CSV columns or quoted cell values did not round-trip.');
}

foreach ([
    [null, ''],
    ['=1+1', "'=1+1"],
    [" \t@SUM(A1:A2)", "' \t@SUM(A1:A2)"],
] as [$input, $expected]) {
    if (faculty_csv_safe_cell($input) !== $expected) {
        throw new RuntimeException('CSV cell sanitization failed.');
    }
}

echo "Faculty CSV quoting, columns, null handling, and formula sanitization passed.\n";
