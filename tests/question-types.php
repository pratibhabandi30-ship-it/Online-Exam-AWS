<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/question-types.php';

$checks = [
    'correct MCQ' => grade_exam_question(
        ['question_type' => 'mcq', 'marks' => 3],
        ['option_id' => 12],
        [11 => false, 12 => true]
    )['awarded_marks'] === 3,
    'wrong fill blank' => grade_exam_question(
        ['question_type' => 'fill_blank', 'marks' => 2],
        ['option_id' => 21],
        [21 => false, 22 => true]
    )['awarded_marks'] === 0,
    'true false correct' => grade_exam_question(
        ['question_type' => 'true_false', 'marks' => 1],
        ['option_id' => 32],
        [31 => false, 32 => true]
    )['is_correct'],
    'matching partial marks' => grade_exam_question(
        ['question_type' => 'match', 'marks' => 5],
        ['pairs' => [41 => 41, 42 => 99]],
        [],
        [['id' => 41, 'marks' => 2], ['id' => 42, 'marks' => 3]]
    )['awarded_marks'] === 2,
    'matching text maps randomized letters to canonical answers' => (static function (): bool {
        $pairs = [['id' => 41, 'marks' => 2], ['id' => 42, 'marks' => 3]];
        $answer = parse_matching_answer('1-B, 2-A', $pairs, [42, 41]);
        $grade = grade_exam_question(['question_type' => 'match', 'marks' => 5], $answer, [], $pairs);
        $stored = json_decode((string) $grade['answer_text'], true);
        return $grade['awarded_marks'] === 5
            && ($stored['pairs'] ?? null) === [41 => 41, 42 => 42]
            && ($stored['display_order'] ?? null) === [42, 41];
    })(),
    'matching labels support more than 26 options' => matching_column_label(26) === 'AA',
    'coding pending manual grade' => grade_exam_question(
        ['question_type' => 'coding', 'marks' => 10],
        ['code' => 'print("ok")']
    )['awarded_marks'] === null,
    'invalid option unanswered' => grade_exam_question(
        ['question_type' => 'mcq', 'marks' => 1],
        ['option_id' => 999],
        [51 => true]
    )['unanswered'],
];

try {
    parse_matching_answer('1-A, 1-B', [['id' => 41], ['id' => 42]], [41, 42]);
    $checks['duplicate matching rows are rejected'] = false;
} catch (InvalidArgumentException) {
    $checks['duplicate matching rows are rejected'] = true;
}

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('Question type grading failed: ' . $name);
    }
}

echo 'All question type grading checks passed.' . PHP_EOL;
