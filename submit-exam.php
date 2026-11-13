<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificates.php';
require_once __DIR__ . '/includes/exam-workflows.php';
require_once __DIR__ . '/includes/question-types.php';

$me = require_student();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('exams.php');
}
verify_csrf();

$quizId = filter_var($_POST['quiz_id'] ?? null, FILTER_VALIDATE_INT);
$answers = is_array($_POST['ans'] ?? null) ? $_POST['ans'] : [];
if (!$quizId || $quizId < 1) {
    flash('error', 'Invalid submission.');
    redirect('exams.php');
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT q.id, q.title, q.duration_minutes, w.status AS workflow_status, w.faculty_id,
            w.scheduled_start, w.scheduled_end
     FROM quizzes q LEFT JOIN exam_workflows w ON w.quiz_id = q.id
     WHERE q.id = ? AND q.is_published = 1 LIMIT 1"
);
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) {
    flash('error', 'This examination is unavailable or has not been published.');
    redirect('exams.php');
}
if ($quiz['workflow_status'] !== null) {
    if ($quiz['workflow_status'] !== 'published') {
        flash('error', 'This examination is not currently published.');
        redirect('exams.php');
    }
    $eligibility = $pdo->prepare(
        'SELECT 1 FROM exam_assigned_students WHERE quiz_id = ? AND student_id = ?'
    );
    $eligibility->execute([$quizId, (int) $me['id']]);
    if (!$eligibility->fetchColumn()) {
        flash('error', 'Access denied. You were not selected for this examination.');
        redirect('exams.php');
    }
    if (!$quiz['scheduled_start'] || !$quiz['scheduled_end']) {
        flash('error', 'This examination does not have a valid schedule.');
        redirect('exams.php');
    }
}

$questionQuery = $pdo->prepare(
    'SELECT id, marks, question_type FROM questions WHERE quiz_id = ? ORDER BY position, id'
);
$questionQuery->execute([$quizId]);
$questions = $questionQuery->fetchAll();
if (!$questions) {
    flash('error', 'Exam has no questions.');
    redirect('exams.php');
}

$questionIds = array_map('intval', array_column($questions, 'id'));
$placeholders = implode(',', array_fill(0, count($questionIds), '?'));
$optionQuery = $pdo->prepare(
    "SELECT id, question_id, is_correct FROM options WHERE question_id IN ($placeholders)"
);
$optionQuery->execute($questionIds);
$optionsByQuestion = [];
foreach ($optionQuery->fetchAll() as $option) {
    $optionsByQuestion[(int) $option['question_id']][(int) $option['id']] = (bool) $option['is_correct'];
}
$pairQuery = $pdo->prepare(
    "SELECT id, question_id, marks FROM question_matching_pairs
     WHERE question_id IN ($placeholders) ORDER BY question_id, position, id"
);
$pairQuery->execute($questionIds);
$pairsByQuestion = [];
foreach ($pairQuery->fetchAll() as $pair) {
    $pairsByQuestion[(int) $pair['question_id']][] = [
        'id' => (int) $pair['id'],
        'marks' => (int) $pair['marks'],
    ];
}

start_session();
$startedAt = $_SESSION['exam_started_at_' . $quizId] ?? null;
$now = time();
$managedExam = $quiz['workflow_status'] !== null;
if (!$startedAt) {
    flash('error', 'Start the examination before submitting answers.');
    redirect('exams.php');
}
if ($managedExam) {
    $scheduledStart = strtotime($quiz['scheduled_start']);
    $scheduledEnd = strtotime($quiz['scheduled_end']);
    if ($now < $scheduledStart) {
        flash('error', 'This examination is not currently open for submission.');
        redirect('exams.php');
    }
}
$deadline = $managedExam
    ? min(strtotime($quiz['scheduled_end']) + 10, (int) $startedAt + ((int) $quiz['duration_minutes'] * 60) + 10)
    : (int) $startedAt + ((int) $quiz['duration_minutes'] * 60) + 10;
if ($now > $deadline) {
    unset($_SESSION['exam_started_at_' . $quizId]);
    flash('error', 'The examination deadline has passed; your answers were not submitted.');
    redirect('exams.php');
}
$timeTaken = min((int) $quiz['duration_minutes'] * 60 + 10, max(0, $now - (int) $startedAt));

$grading = [];
$score = 0;
$totalMarks = 0;
$correct = 0;
$wrong = 0;
$unanswered = 0;
foreach ($questions as $question) {
    $questionId = (int) $question['id'];
    $rawAnswer = $answers[$questionId] ?? null;
    $type = $question['question_type'] ?: 'mcq';
    if (in_array($type, ['mcq', 'fill_blank', 'true_false'], true)) {
        $optionId = is_scalar($rawAnswer) ? filter_var((string) $rawAnswer, FILTER_VALIDATE_INT) : false;
        $answer = ['option_id' => $optionId === false ? null : (int) $optionId];
    } elseif ($type === 'match') {
        if ($rawAnswer !== null && (!is_string($rawAnswer) || strlen($rawAnswer) > 2000)) {
            flash('error', 'Matching answers must use the number-letter format and stay within 2,000 characters.');
            redirect('take-exam.php?quiz=' . $quizId);
        }
        $matchingText = is_string($rawAnswer) ? $rawAnswer : '';
        $columnBOrder = $_SESSION['exam_match_order_' . $quizId . '_' . $questionId] ?? [];
        try {
            $answer = parse_matching_answer(
                $matchingText,
                $pairsByQuestion[$questionId] ?? [],
                is_array($columnBOrder) ? $columnBOrder : []
            );
        } catch (InvalidArgumentException $error) {
            flash('error', $error->getMessage());
            redirect('take-exam.php?quiz=' . $quizId);
        }
    } else {
        $code = is_array($rawAnswer) && is_string($rawAnswer['code'] ?? null) ? $rawAnswer['code'] : '';
        if (strlen($code) > 100000) {
            flash('error', 'A code answer exceeds the 100 KB limit.');
            redirect('take-exam.php?quiz=' . $quizId);
        }
        $answer = ['code' => $code];
    }

    $grade = grade_exam_question(
        $question,
        $answer,
        $optionsByQuestion[$questionId] ?? [],
        $pairsByQuestion[$questionId] ?? []
    );
    $grade['question_id'] = $questionId;
    $grade['marks'] = (int) $question['marks'];
    $grading[] = $grade;
    $totalMarks += (int) $question['marks'];
    if ($grade['awarded_marks'] !== null) {
        $score += (int) $grade['awarded_marks'];
    }
    if ($grade['unanswered']) {
        $unanswered++;
    } elseif ($grade['is_correct']) {
        $correct++;
    } else {
        $wrong++;
    }
}

$pdo->beginTransaction();
$certificateId = null;
try {
    if ($managedExam) {
        $workflowLock = $pdo->prepare(
            'SELECT status, scheduled_start, scheduled_end, faculty_id
             FROM exam_workflows WHERE quiz_id = ? FOR UPDATE'
        );
        $workflowLock->execute([$quizId]);
        $lockedWorkflow = $workflowLock->fetch();
        $now = time();
        $submissionDeadline = min(
            strtotime($lockedWorkflow['scheduled_end'] ?? '1970-01-01') + 10,
            (int) $startedAt + ((int) $quiz['duration_minutes'] * 60) + 10
        );
        if (
            !$lockedWorkflow
            || $lockedWorkflow['status'] !== 'published'
            || $now < strtotime($lockedWorkflow['scheduled_start'])
            || $now > $submissionDeadline
        ) {
            throw new RuntimeException('Exam schedule closed while the submission was being saved.');
        }
        $eligibilityLock = $pdo->prepare(
            'SELECT 1 FROM exam_assigned_students
             WHERE quiz_id = ? AND student_id = ? FOR UPDATE'
        );
        $eligibilityLock->execute([$quizId, (int) $me['id']]);
        if (!$eligibilityLock->fetchColumn()) {
            throw new RuntimeException('You are no longer selected for this examination.');
        }
        $quiz['faculty_id'] = (int) $lockedWorkflow['faculty_id'];
    } else {
        $quizLock = $pdo->prepare('SELECT id FROM quizzes WHERE id = ? AND is_published = 1 FOR UPDATE');
        $quizLock->execute([$quizId]);
        if (!$quizLock->fetchColumn()) {
            throw new RuntimeException('Exam was unpublished while the submission was being saved.');
        }
    }
    $alreadySubmitted = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE quiz_id = ? AND user_id = ?');
    $alreadySubmitted->execute([$quizId, (int) $me['id']]);
    if ((int) $alreadySubmitted->fetchColumn() > 0) {
        throw new RuntimeException('This examination was already submitted.');
    }

    $insertAttempt = $pdo->prepare(
        'INSERT INTO attempts
         (user_id, quiz_id, score, total_marks, correct_count, wrong_count, unanswered_count, time_taken_sec, started_at, submitted_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW())'
    );
    $insertAttempt->execute([
        (int) $me['id'],
        $quizId,
        $score,
        $totalMarks,
        $correct,
        $wrong,
        $unanswered,
        $timeTaken,
        (int) $startedAt,
    ]);
    $attemptId = (int) $pdo->lastInsertId();

    $insertAnswer = $pdo->prepare(
        'INSERT INTO attempt_answers
         (attempt_id, question_id, selected_option_id, is_correct, answer_text, awarded_marks)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($grading as $grade) {
        $insertAnswer->execute([
            $attemptId,
            $grade['question_id'],
            $grade['selected_option_id'],
            $grade['is_correct'] ? 1 : 0,
            $grade['answer_text'],
            $grade['awarded_marks'],
        ]);
    }
    $pdo->prepare('DELETE FROM exam_autosaves WHERE quiz_id = ? AND student_id = ?')
        ->execute([$quizId, (int) $me['id']]);
    $pdo->prepare('DELETE FROM exam_answer_autosaves WHERE quiz_id = ? AND student_id = ?')
        ->execute([$quizId, (int) $me['id']]);
    unset($_SESSION['exam_started_at_' . $quizId]);

    if (!$managedExam) {
        $certificateId = issue_certificate($pdo, $attemptId, (int) $me['id'], $quizId, $score, $totalMarks);
    } else {
        notify_user(
            $pdo,
            (int) $quiz['faculty_id'],
            'exam_submission',
            'Student submitted an examination',
            $me['name'] . ' submitted an attempt for "' . $quiz['title'] . '".',
            'faculty/results.php'
        );
    }
    $pdo->commit();
    foreach ($questions as $question) {
        if (($question['question_type'] ?: 'mcq') === 'match') {
            unset($_SESSION['exam_match_order_' . $quizId . '_' . (int) $question['id']]);
        }
    }
} catch (Throwable $error) {
    $pdo->rollBack();
    flash('error', 'Could not save your submission. Please try again.');
    error_log('submit-exam: ' . $error->getMessage());
    redirect('exams.php');
}

flash('success', $certificateId ? 'Exam submitted! You earned a certificate.' : 'Exam submitted!');
redirect('result.php?id=' . $attemptId);
