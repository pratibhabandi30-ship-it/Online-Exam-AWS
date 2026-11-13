<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$student = require_student();
header('Content-Type: application/json; charset=utf-8');

function autosave_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    autosave_response(405, ['saved' => false, 'message' => 'POST required.']);
}

verify_csrf();
$quizId = filter_var($_POST['quiz_id'] ?? null, FILTER_VALIDATE_INT);
$answers = is_array($_POST['ans'] ?? null) ? $_POST['ans'] : [];
$markedQuestions = is_array($_POST['marked'] ?? null) ? $_POST['marked'] : [];
$codeSubmitted = is_array($_POST['code_submitted'] ?? null) ? $_POST['code_submitted'] : [];
if (!$quizId || $quizId < 1) {
    autosave_response(400, ['saved' => false, 'message' => 'Invalid examination.']);
}

$pdo = db();
$quizQuery = $pdo->prepare(
    "SELECT q.duration_minutes, w.status AS workflow_status, w.scheduled_start, w.scheduled_end
     FROM quizzes q LEFT JOIN exam_workflows w ON w.quiz_id = q.id
     WHERE q.id = ? AND q.is_published = 1
       AND (w.quiz_id IS NULL OR (w.status = 'published' AND EXISTS (
           SELECT 1 FROM exam_assigned_students assigned WHERE assigned.quiz_id = q.id AND assigned.student_id = ?
       )))
     LIMIT 1"
);
$quizQuery->execute([$quizId, (int) $student['id']]);
$quiz = $quizQuery->fetch();
if (!$quiz) {
    autosave_response(404, ['saved' => false, 'message' => 'Examination not found.']);
}

start_session();
$startedAt = $_SESSION['exam_started_at_' . $quizId] ?? null;
$now = time();
$deadline = $startedAt
    ? (int) $startedAt + (int) $quiz['duration_minutes'] * 60 + 10
    : 0;
if ($quiz['workflow_status'] !== null) {
    $deadline = min($deadline, strtotime($quiz['scheduled_end']) + 10);
}
if (
    !$startedAt
    || ($quiz['workflow_status'] !== null && $now < strtotime($quiz['scheduled_start']))
    || $now > $deadline
) {
    autosave_response(409, ['saved' => false, 'message' => 'The examination is no longer open.']);
}

$attemptQuery = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE quiz_id = ? AND user_id = ?');
$attemptQuery->execute([$quizId, (int) $student['id']]);
if ((int) $attemptQuery->fetchColumn() > 0) {
    autosave_response(409, ['saved' => false, 'message' => 'This examination was already submitted.']);
}

$questionQuery = $pdo->prepare('SELECT id, question_type FROM questions WHERE quiz_id = ?');
$questionQuery->execute([$quizId]);
$questions = [];
foreach ($questionQuery->fetchAll() as $question) {
    $questions[(int) $question['id']] = $question['question_type'] ?: 'mcq';
}
$optionCheck = $pdo->prepare('SELECT COUNT(*) FROM options WHERE question_id = ? AND id = ?');
$normalized = [];
foreach ($answers as $rawQuestionId => $rawAnswer) {
    $questionId = filter_var($rawQuestionId, FILTER_VALIDATE_INT);
    if (!$questionId || !isset($questions[(int) $questionId]) || !is_array($rawAnswer) && !is_string($rawAnswer)) {
        autosave_response(400, ['saved' => false, 'message' => 'An answer references an invalid question.']);
    }
    $type = $questions[(int) $questionId];
    if (in_array($type, ['mcq', 'fill_blank', 'true_false'], true)) {
        if (is_array($rawAnswer)) {
            autosave_response(400, ['saved' => false, 'message' => 'Invalid answer option.']);
        }
        $optionId = filter_var(is_string($rawAnswer) ? $rawAnswer : null, FILTER_VALIDATE_INT);
        if (is_string($rawAnswer) && trim($rawAnswer) !== '' && ($optionId === false || $optionId < 1)) {
            autosave_response(400, ['saved' => false, 'message' => 'Invalid answer option.']);
        }
        if ($optionId !== false && $optionId !== null && $optionId > 0) {
            $optionCheck->execute([(int) $questionId, (int) $optionId]);
            if ((int) $optionCheck->fetchColumn() !== 1) {
                autosave_response(400, ['saved' => false, 'message' => 'An answer option does not belong to this question.']);
            }
            $normalized[(int) $questionId] = ['option_id' => (int) $optionId];
        } elseif (is_array($rawAnswer) && isset($rawAnswer['option_id'])) {
            autosave_response(400, ['saved' => false, 'message' => 'Invalid answer option.']);
        }
    } elseif ($type === 'match') {
        if (!is_string($rawAnswer) || strlen($rawAnswer) > 2000) {
            autosave_response(400, ['saved' => false, 'message' => 'Invalid matching answer.']);
        }
        $columnBOrder = $_SESSION['exam_match_order_' . $quizId . '_' . (int) $questionId] ?? null;
        if (!is_array($columnBOrder)) {
            autosave_response(409, ['saved' => false, 'message' => 'Reload the examination before saving matching answers.']);
        }
        $normalized[(int) $questionId] = [
            'text' => $rawAnswer,
            'display_order' => array_map('intval', $columnBOrder),
        ];
    } elseif ($type === 'coding') {
        if (!is_array($rawAnswer)) {
            autosave_response(400, ['saved' => false, 'message' => 'Invalid code answer.']);
        }
        $code = is_string($rawAnswer['code'] ?? null) ? $rawAnswer['code'] : '';
        if (strlen($code) > 100000) {
            autosave_response(413, ['saved' => false, 'message' => 'Code answers must be no larger than 100 KB.']);
        }
        $normalized[(int) $questionId] = [
            'code' => $code,
            'submitted' => !empty($codeSubmitted[(int) $questionId]) && trim($code) !== '',
        ];
    }
}

try {
    $pdo->beginTransaction();
    if ($quiz['workflow_status'] !== null) {
        $lock = $pdo->prepare('SELECT status FROM exam_workflows WHERE quiz_id = ? FOR UPDATE');
        $lock->execute([$quizId]);
        if ($lock->fetchColumn() !== 'published') {
            throw new RuntimeException('Exam was closed while saving answers.');
        }
    } else {
        $lock = $pdo->prepare('SELECT id FROM quizzes WHERE id = ? AND is_published = 1 FOR UPDATE');
        $lock->execute([$quizId]);
        if (!$lock->fetchColumn()) {
            throw new RuntimeException('Exam was removed while saving answers.');
        }
    }
    $attemptQuery->execute([$quizId, (int) $student['id']]);
    if ((int) $attemptQuery->fetchColumn() > 0) {
        throw new RuntimeException('This examination was submitted while saving answers.');
    }
    if (time() > $deadline) {
        throw new RuntimeException('The examination deadline passed while saving answers.');
    }

    $save = $pdo->prepare(
        'INSERT INTO exam_answer_autosaves (quiz_id, student_id, question_id, answer_json, marked_for_review)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE answer_json = VALUES(answer_json), marked_for_review = VALUES(marked_for_review)'
    );
    foreach ($questions as $questionId => $type) {
        $answer = $normalized[$questionId] ?? [];
        if ($type === 'coding' && !array_key_exists($questionId, $normalized)) {
            $answer = ['code' => '', 'submitted' => false];
        } elseif ($type === 'match' && !array_key_exists($questionId, $normalized)) {
            $answer = [
                'text' => '',
                'display_order' => array_map(
                    'intval',
                    $_SESSION['exam_match_order_' . $quizId . '_' . $questionId] ?? []
                ),
            ];
        }
        $save->execute([
            $quizId,
            (int) $student['id'],
            $questionId,
            json_encode($answer, JSON_THROW_ON_ERROR),
            !empty($markedQuestions[$questionId]) ? 1 : 0,
        ]);
    }
    $pdo->commit();
    autosave_response(200, ['saved' => true, 'saved_at' => date(DATE_ATOM)]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Exam autosave failed: ' . $error->getMessage());
    autosave_response(500, ['saved' => false, 'message' => 'Could not save your answers.']);
}
