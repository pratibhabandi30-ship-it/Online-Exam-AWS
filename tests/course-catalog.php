<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../database/migrations/20261030_expand_course_catalog.php';

$expectedTitles = array_column(course_catalog_seed_data(), 0);
$seedQuestionTexts = [];
foreach (course_catalog_seed_data() as $seedCourse) {
    foreach ($seedCourse[5] as $seedQuestion) {
        $questionKey = mb_strtolower(trim($seedQuestion[1]));
        if (isset($seedQuestionTexts[$questionKey])) {
            throw new RuntimeException("Duplicate question in seed data: {$seedQuestion[1]}");
        }
        $seedQuestionTexts[$questionKey] = true;
        if (count(array_unique($seedQuestion[2], SORT_STRING)) !== 4) {
            throw new RuntimeException("Duplicate answer options in seed data: {$seedQuestion[1]}");
        }
    }
}
$existingTitles = [
    'PHP Fundamentals',
    'JavaScript Essentials',
    'Database & SQL',
    'Computer Networks',
    'Data Structures Quick Quiz',
];
$pdo = db();
$findCourse = $pdo->prepare(
    'SELECT id, title, tag, intro, duration_minutes, difficulty, is_published FROM quizzes WHERE title = ? ORDER BY id LIMIT 1'
);
$findQuestions = $pdo->prepare(
    'SELECT id, text, difficulty FROM questions WHERE quiz_id = ? ORDER BY position, id'
);
$findOptions = $pdo->prepare(
    'SELECT text, is_correct FROM options WHERE question_id = ? ORDER BY position, id'
);

foreach ($expectedTitles as $title) {
    $findCourse->execute([$title]);
    $course = $findCourse->fetch();
    if (!$course) {
        throw new RuntimeException("Missing seeded course: $title");
    }
    if ((int) $course['is_published'] !== 1) {
        throw new RuntimeException("Seeded course is not published: $title");
    }
    if (!in_array($course['difficulty'], ['Beginner', 'Intermediate'], true)) {
        throw new RuntimeException("Invalid course difficulty for: $title");
    }
    if (trim((string) $course['tag']) === '' || trim((string) $course['intro']) === '' || (int) $course['duration_minutes'] < 1) {
        throw new RuntimeException("Course category, description, or duration is missing for: $title");
    }

    $findQuestions->execute([(int) $course['id']]);
    $questions = $findQuestions->fetchAll();
    if (count($questions) !== 10) {
        throw new RuntimeException(sprintf('%s has %d questions; expected 10.', $title, count($questions)));
    }

    $questionLevels = [];
    foreach ($questions as $question) {
        if (!in_array($question['difficulty'], ['Beginner', 'Intermediate'], true)) {
            throw new RuntimeException("Invalid question difficulty in: $title");
        }
        $questionLevels[$question['difficulty']] = true;
        $findOptions->execute([(int) $question['id']]);
        $options = $findOptions->fetchAll();
        if (count($options) !== 4 || count(array_unique(array_column($options, 'text'), SORT_STRING)) !== 4 || array_sum(array_column($options, 'is_correct')) !== 1) {
            throw new RuntimeException("Question '{$question['text']}' must have four options and exactly one correct answer.");
        }
    }
    if (count($questionLevels) !== 2) {
        throw new RuntimeException("Course does not contain both beginner and intermediate questions: $title");
    }
}

foreach ($existingTitles as $title) {
    $findCourse->execute([$title]);
    if (!$findCourse->fetch()) {
        throw new RuntimeException("An original course is missing: $title");
    }
}

echo sprintf(
    "Course catalog checks passed for %d new courses, %d questions, and all five original courses.\n",
    count($expectedTitles),
    count($expectedTitles) * 10
);
