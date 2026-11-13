<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/exam-workflows.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $quizId = (int) ($_POST['quiz_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'submit_results');
    try {
        $pdo->beginTransaction();
        $owner = $pdo->prepare(
            "SELECT q.title, w.status, w.scheduled_end, w.results_published_at, w.result_status
             FROM quizzes q JOIN exam_workflows w ON w.quiz_id = q.id
             WHERE q.id = ? AND w.faculty_id = ? FOR UPDATE"
        );
        $owner->execute([$quizId, $facultyId]);
        $exam = $owner->fetch();
        if (!$exam || $exam['status'] !== 'published' || !$exam['scheduled_end']) {
            throw new InvalidArgumentException('Results can only be evaluated for your published examinations.');
        }
        if ($exam['results_published_at'] !== null) {
            throw new InvalidArgumentException('These results have already been published by the HOD.');
        }
        if (strtotime($exam['scheduled_end']) > time()) {
            throw new InvalidArgumentException('Evaluation and result submission are available after the scheduled exam window ends.');
        }

        if ($action === 'grade_coding') {
            if (!in_array($exam['result_status'], ['not_submitted', 'rejected'], true)) {
                throw new InvalidArgumentException('Coding answers cannot be changed while results are awaiting HOD review or approved for publication.');
            }
            $attemptId = (int) ($_POST['attempt_id'] ?? 0);
            $questionId = (int) ($_POST['question_id'] ?? 0);
            $marks = filter_var($_POST['marks'] ?? null, FILTER_VALIDATE_INT);
            $feedback = clean_string($_POST['feedback'] ?? '', 2000);
            $submission = $pdo->prepare(
                "SELECT aa.id AS answer_id, q.marks AS question_marks
                 FROM attempts a
                 JOIN exam_assigned_students eas ON eas.quiz_id = a.quiz_id AND eas.student_id = a.user_id
                 JOIN questions q ON q.quiz_id = a.quiz_id AND q.id = ?
                 JOIN attempt_answers aa ON aa.attempt_id = a.id AND aa.question_id = q.id
                 WHERE a.id = ? AND a.quiz_id = ? AND q.question_type = 'coding'
                 FOR UPDATE"
            );
            $submission->execute([$questionId, $attemptId, $quizId]);
            $codeAnswer = $submission->fetch();
            if (!$codeAnswer || $marks === false || $marks < 0 || $marks > (int) $codeAnswer['question_marks']) {
                throw new InvalidArgumentException('Enter a mark from zero up to the coding question maximum for an assigned student’s answer.');
            }
            $pdo->prepare(
                'UPDATE attempt_answers SET awarded_marks = ?, is_correct = ?, feedback = ? WHERE id = ?'
            )->execute([
                $marks,
                $marks === (int) $codeAnswer['question_marks'] ? 1 : 0,
                $feedback ?: null,
                (int) $codeAnswer['answer_id'],
            ]);
            $scoreQuery = $pdo->prepare(
                "SELECT COALESCE(SUM(COALESCE(aa.awarded_marks, 0)), 0) AS score,
                        COALESCE(SUM(aa.is_correct = 1), 0) AS correct_count,
                        COALESCE(SUM(aa.awarded_marks IS NOT NULL AND aa.is_correct = 0
                            AND (aa.selected_option_id IS NOT NULL OR aa.answer_text IS NOT NULL AND aa.answer_text <> '')), 0) AS wrong_count
                 FROM attempt_answers aa WHERE aa.attempt_id = ?"
            );
            $scoreQuery->execute([$attemptId]);
            $score = $scoreQuery->fetch();
            $pdo->prepare(
                'UPDATE attempts SET score = ?, correct_count = ?, wrong_count = ? WHERE id = ?'
            )->execute([
                (int) $score['score'],
                (int) $score['correct_count'],
                (int) $score['wrong_count'],
                $attemptId,
            ]);
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
            )->execute([$facultyId, 'coding.answer_graded', 'attempt', (string) $attemptId]);
            $pdo->commit();
            flash('success', 'Coding answer evaluated and attempt score updated.');
            redirect('faculty/results.php');
        }

        if ($action !== 'submit_results') {
            throw new InvalidArgumentException('Unknown results action.');
        }
        if (!in_array($exam['result_status'], ['not_submitted', 'rejected'], true)) {
            throw new InvalidArgumentException('Results are already awaiting HOD review or approved for publication.');
        }
        $pendingCoding = $pdo->prepare(
            "SELECT COUNT(*)
             FROM exam_assigned_students eas
             JOIN attempts a ON a.quiz_id = eas.quiz_id AND a.user_id = eas.student_id
             JOIN questions q ON q.quiz_id = a.quiz_id AND q.question_type = 'coding'
             LEFT JOIN attempt_answers aa ON aa.attempt_id = a.id AND aa.question_id = q.id
             WHERE eas.quiz_id = ? AND (aa.id IS NULL OR aa.awarded_marks IS NULL)"
        );
        $pendingCoding->execute([$quizId]);
        if ((int) $pendingCoding->fetchColumn() > 0) {
            throw new InvalidArgumentException('Grade every submitted coding answer before sending results to the HOD.');
        }
        $attemptCount = $pdo->prepare(
            'SELECT COUNT(*) FROM attempts a
             JOIN exam_assigned_students eas ON eas.quiz_id = a.quiz_id AND eas.student_id = a.user_id
             WHERE a.quiz_id = ?'
        );
        $attemptCount->execute([$quizId]);
        if ((int) $attemptCount->fetchColumn() < 1) {
            throw new InvalidArgumentException('There are no submitted attempts to send for result approval.');
        }
        $pdo->prepare(
            "UPDATE exam_workflows SET result_status = 'pending', result_remarks = NULL,
                    result_reviewed_by = NULL, result_reviewed_at = NULL, result_requested_at = NOW()
             WHERE quiz_id = ? AND faculty_id = ?"
        )->execute([$quizId, $facultyId]);
        notify_role(
            $pdo,
            'admin',
            'result_approval_request',
            'Examination results need review',
            $faculty['name'] . ' submitted results for "' . $exam['title'] . '" for HOD approval.',
            'admin/result-approvals.php'
        );
        $pdo->prepare(
            'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
        )->execute([$facultyId, 'results.submitted_for_approval', 'quiz', (string) $quizId]);
        $pdo->commit();
        flash('success', 'Results submitted to the HOD for approval. Students cannot see them until the HOD publishes them.');
        redirect('faculty/results.php');
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty result evaluation failed: ' . $error->getMessage());
        $errors[] = 'The evaluation could not be saved. Please try again.';
    }
}

$stmt = $pdo->prepare(
    "SELECT q.id AS quiz_id, q.title, w.status, w.scheduled_end, w.result_status,
            w.result_remarks, w.result_requested_at, rp.published_at,
            COUNT(a.id) AS attempts,
            AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END) AS average_percent
     FROM exam_workflows w JOIN quizzes q ON q.id = w.quiz_id
     LEFT JOIN result_publications rp ON rp.quiz_id = q.id
     LEFT JOIN exam_assigned_students eas ON eas.quiz_id = q.id
     LEFT JOIN attempts a ON a.quiz_id = q.id AND a.user_id = eas.student_id
     WHERE w.faculty_id = ? AND w.status IN ('published','completed')
     GROUP BY q.id, q.title, w.status, w.scheduled_end, w.result_status, w.result_remarks,
              w.result_requested_at, rp.published_at
     ORDER BY w.scheduled_end DESC"
);
$stmt->execute([$facultyId]);
$exams = $stmt->fetchAll();

$pageTitle = 'Faculty · Results';
$activeNav = 'faculty-results';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'results';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <h2 class="mb-1">Results and evaluation</h2>
  <p class="text-muted">Review submitted work, manually grade programming answers, then submit results to the HOD. Only HOD publication makes results visible to students.</p>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Exam</th><th>Attempts</th><th>Average</th><th>Results status</th><th>Publication</th><th></th></tr></thead>
    <tbody><?php foreach ($exams as $exam):
      $resultStatus = $exam['published_at'] ? 'Published' : match ($exam['result_status']) {
          'pending' => 'Pending HOD approval',
          'rejected' => 'Rejected',
          'approved' => 'Approved · awaiting HOD publication',
          default => 'Not submitted',
      };
      $pendingCodingQuery = $pdo->prepare(
          "SELECT COUNT(*) FROM exam_assigned_students eas
           JOIN attempts a ON a.quiz_id = eas.quiz_id AND a.user_id = eas.student_id
           JOIN questions q ON q.quiz_id = a.quiz_id AND q.question_type = 'coding'
           LEFT JOIN attempt_answers aa ON aa.attempt_id = a.id AND aa.question_id = q.id
           WHERE eas.quiz_id = ? AND (aa.id IS NULL OR aa.awarded_marks IS NULL)"
      );
      $pendingCodingQuery->execute([(int) $exam['quiz_id']]);
      $hasPendingCoding = (int) $pendingCodingQuery->fetchColumn() > 0;
    ?>
      <tr>
        <td><?= e($exam['title']) ?><div class="small text-muted">Schedule ended <?= e(date('d M Y, H:i', strtotime($exam['scheduled_end']))) ?></div></td>
        <td><?= (int) $exam['attempts'] ?></td>
        <td><?= $exam['average_percent'] !== null ? number_format((float) $exam['average_percent'], 1) . '%' : '—' ?></td>
        <td><span class="badge <?= $exam['published_at'] ? 'bg-success' : (in_array($exam['result_status'], ['pending', 'approved'], true) ? 'bg-warning text-dark' : ($exam['result_status'] === 'rejected' ? 'bg-danger' : 'bg-secondary')) ?>"><?= e($resultStatus) ?></span>
          <?php if ($exam['result_requested_at']): ?><div class="small text-muted mt-1">Submitted <?= e(date('d M Y, H:i', strtotime($exam['result_requested_at']))) ?></div><?php endif; ?>
          <?php if ($exam['result_remarks']): ?><div class="small text-danger mt-1">HOD remarks: <?= e($exam['result_remarks']) ?></div><?php endif; ?>
        </td>
        <td><?= $exam['published_at'] ? e(date('d M Y, H:i', strtotime($exam['published_at']))) : 'Hidden from students' ?></td>
        <td><?php if (!$exam['published_at'] && in_array($exam['result_status'], ['not_submitted', 'rejected'], true) && (int) $exam['attempts'] > 0): ?>
          <form method="post" data-confirm="Submit these evaluated results to the HOD for approval?">
            <?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int) $exam['quiz_id'] ?>">
            <button class="btn btn-sm btn-primary" type="submit" <?= $hasPendingCoding ? 'disabled title="Grade pending coding answers first."' : '' ?>>Submit to HOD</button>
          </form>
        <?php endif; ?></td>
      </tr>
      <?php
        $attemptsQuery = $pdo->prepare(
            'SELECT a.id, a.score, a.total_marks, a.submitted_at, u.name, u.email
             FROM attempts a
             JOIN exam_assigned_students eas ON eas.quiz_id = a.quiz_id AND eas.student_id = a.user_id
             JOIN users u ON u.id = a.user_id
             WHERE a.quiz_id = ? ORDER BY a.submitted_at DESC'
        );
        $attemptsQuery->execute([(int) $exam['quiz_id']]);
        $examAttempts = $attemptsQuery->fetchAll();
        $codingQuery = $pdo->prepare(
            "SELECT q.id AS question_id, q.text, q.marks, q.programming_language,
                    aa.answer_text, aa.awarded_marks, aa.feedback
             FROM questions q
             JOIN attempt_answers aa ON aa.question_id = q.id
             WHERE q.quiz_id = ? AND q.question_type = 'coding' AND aa.attempt_id = ?
             ORDER BY q.position, q.id"
        );
        $testCasesQuery = $pdo->prepare(
            'SELECT input_data, expected_output FROM question_coding_test_cases
             WHERE question_id = ? ORDER BY position, id'
        );
      ?>
      <?php foreach ($examAttempts as $attempt): ?>
        <tr class="table-light"><td class="ps-4"><a href="<?= e(base_url('result.php?id=' . (int) $attempt['id'])) ?>"><?= e($attempt['name']) ?></a><div class="small text-muted"><?= e($attempt['email']) ?> · <?= e(date('d M, H:i', strtotime($attempt['submitted_at']))) ?></div></td><td colspan="3">Attempt review</td><td colspan="2" class="text-end"><?= $exam['published_at'] ? (int) $attempt['score'] . '/' . (int) $attempt['total_marks'] : 'Hidden from students' ?></td></tr>
        <?php
          $codingQuery->execute([(int) $exam['quiz_id'], (int) $attempt['id']]);
          $codingAnswers = $codingQuery->fetchAll();
        ?>
        <?php foreach ($codingAnswers as $codingAnswer): ?>
          <tr><td colspan="6"><div class="border rounded p-3">
            <div class="d-flex flex-wrap justify-content-between gap-2"><strong>Coding question · <?= e($codingAnswer['programming_language'] ?: 'Language not set') ?> · <?= (int) $codingAnswer['marks'] ?> marks</strong>
              <span class="badge <?= $codingAnswer['awarded_marks'] === null ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $codingAnswer['awarded_marks'] === null ? 'Awaiting manual evaluation' : 'Evaluated: ' . (int) $codingAnswer['awarded_marks'] . '/' . (int) $codingAnswer['marks'] ?></span></div>
            <div class="mt-2"><?= nl2br(e($codingAnswer['text'])) ?></div>
            <pre class="bg-light border rounded p-3 mt-2 mb-2 overflow-auto"><code><?= e($codingAnswer['answer_text'] ?? 'No code submitted.') ?></code></pre>
            <?php
              $testCasesQuery->execute([(int) $codingAnswer['question_id']]);
              $hiddenCases = $testCasesQuery->fetchAll();
            ?>
            <?php if ($hiddenCases): ?><details class="small mb-3"><summary>Faculty-only hidden test cases (<?= count($hiddenCases) ?>)</summary><?php foreach ($hiddenCases as $caseIndex => $testCase): ?><div class="row g-2 mt-1"><div class="col-md-6"><strong>Input <?= $caseIndex + 1 ?></strong><pre class="bg-light p-2"><?= e($testCase['input_data']) ?></pre></div><div class="col-md-6"><strong>Expected output</strong><pre class="bg-light p-2"><?= e($testCase['expected_output']) ?></pre></div></div><?php endforeach; ?></details><?php endif; ?>
            <?php if (!$exam['published_at'] && in_array($exam['result_status'], ['not_submitted', 'rejected'], true)): ?>
              <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="grade_coding"><input type="hidden" name="quiz_id" value="<?= (int) $exam['quiz_id'] ?>"><input type="hidden" name="attempt_id" value="<?= (int) $attempt['id'] ?>"><input type="hidden" name="question_id" value="<?= (int) $codingAnswer['question_id'] ?>">
                <div class="col-sm-2"><label class="form-label">Marks</label><input class="form-control" type="number" name="marks" min="0" max="<?= (int) $codingAnswer['marks'] ?>" value="<?= e((string) ($codingAnswer['awarded_marks'] ?? 0)) ?>" required></div>
                <div class="col-sm-8"><label class="form-label">Feedback (optional)</label><textarea class="form-control" name="feedback" rows="2"><?= e($codingAnswer['feedback'] ?? '') ?></textarea></div>
                <div class="col-sm-2"><button class="btn btn-outline-primary w-100" type="submit">Save evaluation</button></div>
              </form>
            <?php elseif ($codingAnswer['feedback']): ?><div class="small text-muted">Feedback: <?= e($codingAnswer['feedback']) ?></div><?php endif; ?>
          </div></td></tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endforeach; ?></tbody>
  </table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
