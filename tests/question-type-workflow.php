<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/question-types.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $insertUser->execute(['Question type faculty', 'question-type-faculty-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'faculty']);
    $facultyId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Question type HOD', 'question-type-hod-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'admin']);
    $hodId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Question type student', 'question-type-student-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO faculty_student_assignments (faculty_id, student_id) VALUES (?, ?)')
        ->execute([$facultyId, $studentId]);
    $pdo->prepare(
        'INSERT INTO quizzes (title, duration_minutes, passing_percent, difficulty, is_published, created_by)
         VALUES (?, 30, 50, ?, 1, ?)'
    )->execute(['Question type workflow ' . $suffix, 'Beginner', $facultyId]);
    $quizId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO exam_workflows (quiz_id, faculty_id, status, scheduled_start, scheduled_end)
         VALUES (?, ?, 'published', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 1 DAY))"
    )->execute([$quizId, $facultyId]);
    $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)')
        ->execute([$quizId, $studentId]);

    $insertQuestion = $pdo->prepare(
        'INSERT INTO questions (quiz_id, text, marks, difficulty, position, question_type, programming_language)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $types = [
        ['mcq', 2, null],
        ['fill_blank', 2, null],
        ['match', 2, null],
        ['true_false', 1, null],
        ['coding', 5, 'Python'],
    ];
    $questions = [];
    foreach ($types as $index => [$type, $marks, $language]) {
        $insertQuestion->execute([$quizId, 'Test ' . $type, $marks, 'Beginner', $index + 1, $type, $language]);
        $questions[$type] = ['id' => (int) $pdo->lastInsertId(), 'question_type' => $type, 'marks' => $marks];
    }

    $insertOption = $pdo->prepare(
        'INSERT INTO options (question_id, text, is_correct, position) VALUES (?, ?, ?, ?)'
    );
    $optionAnswers = [];
    foreach (['mcq', 'fill_blank', 'true_false'] as $type) {
        $questionId = $questions[$type]['id'];
        $insertOption->execute([$questionId, 'Correct ' . $type, 1, 1]);
        $correctId = (int) $pdo->lastInsertId();
        $insertOption->execute([$questionId, 'Incorrect ' . $type, 0, 2]);
        $wrongId = (int) $pdo->lastInsertId();
        $optionAnswers[$type] = [
            'answer' => ['option_id' => $correctId],
            'correct' => [$correctId => true, $wrongId => false],
        ];
    }

    $insertPair = $pdo->prepare(
        'INSERT INTO question_matching_pairs (question_id, left_text, right_text, marks, position)
         VALUES (?, ?, ?, 1, ?)'
    );
    $pairs = [];
    foreach ([['Left 1', 'Right 1'], ['Left 2', 'Right 2']] as $index => [$left, $right]) {
        $insertPair->execute([$questions['match']['id'], $left, $right, $index + 1]);
        $pairs[] = ['id' => (int) $pdo->lastInsertId(), 'marks' => 1];
    }
    $pdo->prepare(
        'INSERT INTO question_coding_test_cases (question_id, input_data, expected_output, position)
         VALUES (?, ?, ?, 1)'
    )->execute([$questions['coding']['id'], '2', '4']);

    $answers = [
        'mcq' => grade_exam_question($questions['mcq'], $optionAnswers['mcq']['answer'], $optionAnswers['mcq']['correct']),
        'fill_blank' => grade_exam_question($questions['fill_blank'], $optionAnswers['fill_blank']['answer'], $optionAnswers['fill_blank']['correct']),
        'match' => grade_exam_question(
            $questions['match'],
            parse_matching_answer('1-B', $pairs, [$pairs[1]['id'], $pairs[0]['id']]),
            [],
            $pairs
        ),
        'true_false' => grade_exam_question($questions['true_false'], $optionAnswers['true_false']['answer'], $optionAnswers['true_false']['correct']),
        'coding' => grade_exam_question($questions['coding'], ['code' => 'print(4)']),
    ];
    $totalMarks = array_sum(array_column($questions, 'marks'));
    $autoScore = array_sum(array_map(
        static fn(array $answer): int => (int) ($answer['awarded_marks'] ?? 0),
        $answers
    ));
    if ($totalMarks !== 12 || $autoScore !== 6 || $answers['coding']['awarded_marks'] !== null) {
        throw new RuntimeException('Automatic grading did not produce the expected initial result.');
    }

    $pdo->prepare(
        'INSERT INTO attempts (user_id, quiz_id, score, total_marks) VALUES (?, ?, ?, ?)'
    )->execute([$studentId, $quizId, $autoScore, $totalMarks]);
    $attemptId = (int) $pdo->lastInsertId();
    $insertAnswer = $pdo->prepare(
        'INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, is_correct, answer_text, awarded_marks)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($answers as $type => $answer) {
        $insertAnswer->execute([
            $attemptId,
            $questions[$type]['id'],
            $answer['selected_option_id'],
            $answer['is_correct'] ? 1 : 0,
            $answer['answer_text'] ?? ($type === 'coding' ? 'print(4)' : null),
            $answer['awarded_marks'],
        ]);
    }

    $pending = $pdo->prepare(
        "SELECT COUNT(*) FROM attempts a
         JOIN questions q ON q.quiz_id = a.quiz_id AND q.question_type = 'coding'
         LEFT JOIN attempt_answers aa ON aa.attempt_id = a.id AND aa.question_id = q.id
         WHERE a.quiz_id = ? AND (aa.id IS NULL OR aa.awarded_marks IS NULL)"
    );
    $pending->execute([$quizId]);
    if ((int) $pending->fetchColumn() !== 1) {
        throw new RuntimeException('The coding submission was not held for manual grading.');
    }

    $pdo->prepare(
        "UPDATE attempt_answers SET awarded_marks = 4, is_correct = 0, feedback = 'Good approach; one edge case is missing.'
         WHERE attempt_id = ? AND question_id = ?"
    )->execute([$attemptId, $questions['coding']['id']]);
    $pdo->prepare(
        'UPDATE attempts SET score = (SELECT SUM(COALESCE(awarded_marks, 0)) FROM attempt_answers WHERE attempt_id = ?)
         WHERE id = ?'
    )->execute([$attemptId, $attemptId]);
    $pending->execute([$quizId]);
    if ((int) $pending->fetchColumn() !== 0) {
        throw new RuntimeException('Manual grading did not clear the publication gate.');
    }
    $scoreQuery = $pdo->prepare('SELECT score, total_marks FROM attempts WHERE id = ?');
    $scoreQuery->execute([$attemptId]);
    $attempt = $scoreQuery->fetch();
    if ((int) $attempt['score'] !== 10 || (int) $attempt['total_marks'] !== 12) {
        throw new RuntimeException('Manual coding marks were not included in the final score.');
    }

    $pdo->prepare(
        "UPDATE exam_workflows SET result_status = 'pending', result_requested_at = NOW() WHERE quiz_id = ?"
    )->execute([$quizId]);
    $published = $pdo->prepare('SELECT COUNT(*) FROM result_publications WHERE quiz_id = ?');
    $published->execute([$quizId]);
    if ((int) $published->fetchColumn() !== 0) {
        throw new RuntimeException('Results became visible before HOD approval.');
    }
    $pdo->prepare(
        "UPDATE exam_workflows SET result_status = 'approved', result_reviewed_by = ?, result_reviewed_at = NOW()
         WHERE quiz_id = ? AND result_status = 'pending'"
    )->execute([$hodId, $quizId]);
    $pdo->prepare('INSERT INTO result_publications (quiz_id, published_by, published_at) VALUES (?, ?, NOW())')
        ->execute([$quizId, $hodId]);
    $pdo->prepare(
        "UPDATE exam_workflows SET status = 'completed', results_published_by = ?, results_published_at = NOW()
         WHERE quiz_id = ? AND result_status = 'approved'"
    )->execute([$hodId, $quizId]);
    $publicationCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM result_publications rp
         JOIN users u ON u.id = rp.published_by
         JOIN exam_workflows w ON w.quiz_id = rp.quiz_id
         WHERE rp.quiz_id = ? AND u.role = 'admin' AND w.status = 'completed'
           AND w.results_published_at IS NOT NULL AND w.result_status = 'approved'"
    );
    $publicationCheck->execute([$quizId]);
    if ((int) $publicationCheck->fetchColumn() !== 1) {
        throw new RuntimeException('HOD approval and publication were not recorded correctly.');
    }

    echo "Faculty-to-student grading, manual coding review, HOD approval, and result publication checks passed." . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
