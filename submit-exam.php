<?php
/**
 * Handles POST from take-exam.php: grades answers, saves attempt+answers, redirects to result.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificates.php';

$me = require_login();
if ($me['role'] !== 'student') redirect('admin/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('exams.php');
verify_csrf();

$quizId = (int) ($_POST['quiz_id'] ?? 0);
$answers = is_array($_POST['ans'] ?? null) ? $_POST['ans'] : [];

if ($quizId <= 0) { flash('error','Invalid submission.'); redirect('exams.php'); }

$pdo = db();
$stmt = $pdo->prepare("SELECT id, duration_minutes FROM quizzes WHERE id=? AND is_published=1 LIMIT 1");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) { flash('error','Exam not found.'); redirect('exams.php'); }

$qStmt = $pdo->prepare("SELECT id, marks FROM questions WHERE quiz_id=?");
$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();
if (!$questions) { flash('error','Exam has no questions.'); redirect('exams.php'); }

$qIds = array_column($questions, 'id');
$placeholders = implode(',', array_fill(0, count($qIds), '?'));
$oStmt = $pdo->prepare("SELECT id, question_id, is_correct FROM options WHERE question_id IN ($placeholders)");
$oStmt->execute($qIds);
$correctByQ = [];
$validOpts  = [];
foreach ($oStmt->fetchAll() as $o) {
    $qid = (int) $o['question_id'];
    $oid = (int) $o['id'];
    $validOpts[$qid][$oid] = (bool) $o['is_correct'];
    if ($o['is_correct']) $correctByQ[$qid][] = $oid;
}

// Compute timing.
start_session();
$startedAt = $_SESSION['exam_started_at_' . $quizId] ?? null;
$now       = time();
$timeTaken = $startedAt ? max(0, $now - (int) $startedAt) : 0;
$cap       = (int) $quiz['duration_minutes'] * 60 + 5; // grace window
if ($timeTaken > $cap) $timeTaken = $cap;
unset($_SESSION['exam_started_at_' . $quizId]);

// Grade.
$score = 0; $totalMarks = 0; $correct = 0; $wrong = 0; $unanswered = 0;
$grading = []; // [question_id => [selected_option_id|null, is_correct(bool)]]

foreach ($questions as $q) {
    $qid = (int) $q['id'];
    $marks = (int) $q['marks'];
    $totalMarks += $marks;

    $picked = isset($answers[$qid]) ? (int) $answers[$qid] : 0;

    if ($picked <= 0 || !isset($validOpts[$qid][$picked])) {
        $grading[$qid] = [null, false];
        $unanswered++;
        continue;
    }

    if ($validOpts[$qid][$picked]) {
        $grading[$qid] = [$picked, true];
        $score   += $marks;
        $correct++;
    } else {
        $grading[$qid] = [$picked, false];
        $wrong++;
    }
}

$pdo->beginTransaction();
$certificateId = null;
try {
    $ins = $pdo->prepare("
        INSERT INTO attempts (user_id, quiz_id, score, total_marks, correct_count, wrong_count, unanswered_count, time_taken_sec, started_at, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW())
    ");
    $ins->execute([
        $me['id'], $quizId, $score, $totalMarks, $correct, $wrong, $unanswered, $timeTaken,
        $startedAt ?: $now - $timeTaken,
    ]);
    $attemptId = (int) $pdo->lastInsertId();

    $insAns = $pdo->prepare("INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?)");
    foreach ($grading as $qid => [$pickedOpt, $isCorrect]) {
        $insAns->execute([$attemptId, $qid, $pickedOpt, $isCorrect ? 1 : 0]);
    }
    $certificateId = issue_certificate($pdo, $attemptId, (int) $me['id'], $quizId, $score, $totalMarks);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Could not save your submission. Please try again.');
    error_log('submit-exam: ' . $e->getMessage());
    redirect('exams.php');
}

flash('success', $certificateId ? 'Exam submitted! You earned a certificate.' : 'Exam submitted!');
redirect('result.php?id=' . $attemptId);
