<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/certificates.php';

$cases = [
    25.0 => null,
    34.0 => null,
    49.99 => null,
    50.0 => 'Silver',
    66.7 => 'Silver',
    79.99 => 'Silver',
    80.0 => 'Gold',
    100.0 => 'Gold',
];

foreach ($cases as $percentage => $expected) {
    $actual = certificate_type_for_percentage((float) $percentage);
    if ($actual !== $expected) {
        throw new RuntimeException(sprintf(
            'Expected %s%% to receive %s, got %s.',
            $percentage,
            $expected ?? 'no certificate',
            $actual ?? 'no certificate'
        ));
    }
}

foreach ([0.0, 34.99, 49.99] as $percentage) {
    if (certificate_type_for_percentage($percentage) !== null) {
        throw new RuntimeException(sprintf('Expected %s%% to receive no certificate.', $percentage));
    }
}

foreach ([50.0, 79.99] as $percentage) {
    if (certificate_type_for_percentage($percentage) !== 'Silver') {
        throw new RuntimeException(sprintf('Expected %s%% to receive Silver.', $percentage));
    }
}

foreach ([80.0, 100.0] as $percentage) {
    if (certificate_type_for_percentage($percentage) !== 'Gold') {
        throw new RuntimeException(sprintf('Expected %s%% to receive Gold.', $percentage));
    }
}

if (certificate_type_for_percentage(100.01) !== null) {
    throw new RuntimeException('Scores above 100% must not receive certificates.');
}

$record = [
    'score' => 50,
    'total_marks' => 100,
    'certificate_type' => 'Silver',
    'percentage' => '50.00000',
    'grade' => 'D',
    'student_id' => 7,
    'user_id' => 7,
    'exam_id' => 12,
    'quiz_id' => 12,
];
if (!certificate_record_matches_attempt($record)) {
    throw new RuntimeException('A matching certificate and attempt should validate.');
}
$record['score'] = 49;
$record['percentage'] = '49.00000';
$record['grade'] = 'F';
if (certificate_record_matches_attempt($record)) {
    throw new RuntimeException('An ineligible certificate must not validate.');
}
$record['score'] = 80;
$record['certificate_type'] = 'Gold';
$record['percentage'] = '80.00000';
$record['grade'] = 'A';
if (!certificate_record_matches_attempt($record)) {
    throw new RuntimeException('A matching Gold certificate at the 80% boundary should validate.');
}

echo "Certificate percentage rules passed.\n";
