<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
    )->execute(['Question type test', 'question-types-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO quizzes (title, duration_minutes, passing_percent, difficulty, is_published)
         VALUES (?, 30, 50, ?, 0)'
    )->execute(['Question type test ' . $suffix, 'Beginner']);
    $quizId = (int) $pdo->lastInsertId();

    $insertQuestion = $pdo->prepare(
        'INSERT INTO questions (quiz_id, text, marks, difficulty, position, question_type, programming_language)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $expectedTypes = ['mcq', 'fill_blank', 'match', 'true_false', 'coding'];
    $questionIds = [];
    foreach ($expectedTypes as $position => $type) {
        $insertQuestion->execute([
            $quizId,
            'Test ' . $type,
            $type === 'match' ? 3 : 2,
            'Beginner',
            $position + 1,
            $type,
            $type === 'coding' ? 'Python' : null,
        ]);
        $questionIds[$type] = (int) $pdo->lastInsertId();
    }

    $option = $pdo->prepare('INSERT INTO options (question_id, text, is_correct, position) VALUES (?, ?, ?, ?)');
    $option->execute([$questionIds['mcq'], 'A', 1, 1]);
    $option->execute([$questionIds['fill_blank'], 'B', 1, 1]);
    $option->execute([$questionIds['true_false'], 'True', 1, 1]);
    $pdo->prepare(
        'INSERT INTO question_matching_pairs (question_id, left_text, right_text, marks, position)
         VALUES (?, ?, ?, 2, 1), (?, ?, ?, 1, 2)'
    )->execute([
        $questionIds['match'], 'Left A', 'Right A',
        $questionIds['match'], 'Left B', 'Right B',
    ]);
    $pdo->prepare(
        'INSERT INTO question_coding_test_cases (question_id, input_data, expected_output, position)
         VALUES (?, ?, ?, 1)'
    )->execute([$questionIds['coding'], '2', '4']);

    $pdo->prepare(
        'INSERT INTO attempts (user_id, quiz_id, score, total_marks) VALUES (?, ?, 2, 12)'
    )->execute([$studentId, $quizId]);
    $attemptId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, is_correct, answer_text, awarded_marks)
         VALUES (?, ?, NULL, 0, ?, NULL)'
    )->execute([$attemptId, $questionIds['coding'], 'print(4)']);

    $savedTypes = $pdo->prepare('SELECT question_type FROM questions WHERE quiz_id = ? ORDER BY position');
    $savedTypes->execute([$quizId]);
    if ($savedTypes->fetchAll(PDO::FETCH_COLUMN) !== $expectedTypes) {
        throw new RuntimeException('One or more question types were not stored.');
    }
    $savedCode = $pdo->prepare(
        'SELECT aa.answer_text, aa.awarded_marks, q.programming_language
         FROM attempt_answers aa JOIN questions q ON q.id = aa.question_id WHERE aa.attempt_id = ?'
    );
    $savedCode->execute([$attemptId]);
    $code = $savedCode->fetch();
    if (!$code || $code['answer_text'] !== 'print(4)' || $code['awarded_marks'] !== null || $code['programming_language'] !== 'Python') {
        throw new RuntimeException('The code answer or pending manual grade was not preserved.');
    }
    $testCaseCount = $pdo->prepare('SELECT COUNT(*) FROM question_coding_test_cases WHERE question_id = ?');
    $testCaseCount->execute([$questionIds['coding']]);
    if ((int) $testCaseCount->fetchColumn() !== 1) {
        throw new RuntimeException('Coding test cases were not stored.');
    }

    echo "Question type schema and coding answer persistence checks passed." . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
