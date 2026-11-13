<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/exam-workflows.php';
require_once __DIR__ . '/../includes/certificates.php';

$hod = require_admin();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $quizId = (int) ($_POST['quiz_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $remarks = clean_string($_POST['remarks'] ?? '', 1000);
    try {
        if ($quizId < 1 || !in_array($action, ['approve', 'reject', 'publish'], true)) {
            throw new InvalidArgumentException('Choose a valid result-approval action.');
        }
        if ($action === 'reject' && $remarks === '') {
            throw new InvalidArgumentException('Provide a reason when rejecting results.');
        }
        $pdo->beginTransaction();
        $query = $pdo->prepare(
            'SELECT q.title, w.faculty_id, w.status, w.scheduled_end, w.result_status,
                    w.results_published_at, rp.published_at AS publication_record
             FROM exam_workflows w
             JOIN quizzes q ON q.id = w.quiz_id
             LEFT JOIN result_publications rp ON rp.quiz_id = w.quiz_id
             WHERE w.quiz_id = ? FOR UPDATE'
        );
        $query->execute([$quizId]);
        $exam = $query->fetch();
        if (!$exam || $exam['status'] !== 'published' || !$exam['scheduled_end']) {
            throw new InvalidArgumentException('This examination is not available for result review.');
        }
        if (strtotime($exam['scheduled_end']) > time()) {
            throw new InvalidArgumentException('Results cannot be approved or published before the scheduled examination window ends.');
        }
        if ($exam['results_published_at'] !== null || $exam['publication_record'] !== null) {
            throw new InvalidArgumentException('Results have already been published.');
        }

        if ($action === 'approve' || $action === 'reject') {
            if ($exam['result_status'] !== 'pending') {
                throw new InvalidArgumentException('Only results awaiting HOD review can be approved or rejected.');
            }
            if ($action === 'approve') {
                $remarks = $remarks ?: null;
                $status = 'approved';
                $auditAction = 'results.approved';
                $message = 'Your results for "' . $exam['title'] . '" were approved by the HOD and are ready for publication.';
                $noticeTitle = 'Results approved';
            } else {
                $status = 'rejected';
                $auditAction = 'results.rejected';
                $message = 'Your results for "' . $exam['title'] . '" were rejected. HOD remarks: ' . $remarks;
                $noticeTitle = 'Results rejected';
            }
            $pdo->prepare(
                'UPDATE exam_workflows SET result_status = ?, result_remarks = ?, result_reviewed_by = ?,
                    result_reviewed_at = NOW() WHERE quiz_id = ?'
            )->execute([$status, $remarks, (int) $hod['id'], $quizId]);
            notify_user($pdo, (int) $exam['faculty_id'], 'result_decision', $noticeTitle, $message, 'faculty/results.php');
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)'
            )->execute([(int) $hod['id'], $auditAction, 'quiz', (string) $quizId, $remarks]);
            $pdo->commit();
            flash('success', $action === 'approve' ? 'Results approved. Publish them when ready.' : 'Results rejected with remarks.');
            redirect('admin/result-approvals.php');
        }

        if ($exam['result_status'] !== 'approved') {
            throw new InvalidArgumentException('Approve the submitted results before publishing them.');
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
            throw new InvalidArgumentException('At least one coding answer has not been evaluated.');
        }
        $attemptQuery = $pdo->prepare(
            'SELECT a.id, a.user_id, a.score, a.total_marks FROM attempts a
             JOIN exam_assigned_students eas ON eas.quiz_id = a.quiz_id AND eas.student_id = a.user_id
             WHERE a.quiz_id = ? ORDER BY a.id FOR UPDATE'
        );
        $attemptQuery->execute([$quizId]);
        $attempts = $attemptQuery->fetchAll();
        if (!$attempts) {
            throw new InvalidArgumentException('There are no assigned-student attempts to publish.');
        }
        foreach ($attempts as $attempt) {
            issue_certificate(
                $pdo,
                (int) $attempt['id'],
                (int) $attempt['user_id'],
                $quizId,
                (int) $attempt['score'],
                (int) $attempt['total_marks']
            );
        }
        $pdo->prepare(
            'INSERT INTO result_publications (quiz_id, published_by, published_at) VALUES (?, ?, NOW())'
        )->execute([$quizId, (int) $hod['id']]);
        $pdo->prepare(
            "UPDATE exam_workflows SET status = 'completed', results_published_by = ?,
                results_published_at = NOW() WHERE quiz_id = ?"
        )->execute([(int) $hod['id'], $quizId]);
        foreach ($attempts as $attempt) {
            notify_user(
                $pdo,
                (int) $attempt['user_id'],
                'results_published',
                'Exam results are available',
                'Results for "' . $exam['title'] . '" have been published.',
                'result.php?id=' . (int) $attempt['id']
            );
        }
        notify_user(
            $pdo,
            (int) $exam['faculty_id'],
            'results_published',
            'Examination results published',
            'The HOD published results for "' . $exam['title'] . '".',
            'faculty/results.php'
        );
        $pdo->prepare(
            'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
        )->execute([(int) $hod['id'], 'results.published', 'quiz', (string) $quizId]);
        $pdo->commit();
        flash('success', 'Results published to assigned students.');
        redirect('admin/result-approvals.php');
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('HOD result approval failed: ' . $error->getMessage());
        $errors[] = 'The result action could not be completed. Please try again.';
    }
}

$exams = $pdo->query(
    "SELECT q.id AS quiz_id, q.title, u.name AS faculty_name, w.result_status, w.result_remarks,
            w.result_requested_at, w.result_reviewed_at, w.scheduled_end, rp.published_at,
            COUNT(a.id) AS attempts,
            AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END) AS average_percent
     FROM exam_workflows w
     JOIN quizzes q ON q.id = w.quiz_id
     JOIN users u ON u.id = w.faculty_id
     LEFT JOIN result_publications rp ON rp.quiz_id = q.id
     LEFT JOIN exam_assigned_students eas ON eas.quiz_id = q.id
     LEFT JOIN attempts a ON a.quiz_id = q.id AND a.user_id = eas.student_id
     WHERE w.result_status IN ('pending','approved','rejected') OR rp.published_at IS NOT NULL
     GROUP BY q.id, q.title, u.name, w.result_status, w.result_remarks,
              w.result_requested_at, w.result_reviewed_at, w.scheduled_end, rp.published_at
     ORDER BY CASE w.result_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
              w.result_requested_at DESC"
)->fetchAll();

$pageTitle = 'Admin · Result approvals';
include __DIR__ . '/../includes/header.php';
$adminActive = 'result-approvals';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <h3 class="mb-1">Result approvals</h3>
  <p class="text-muted">Approve or reject faculty evaluations, then explicitly publish approved results to assigned students.</p>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <div class="vstack gap-3">
    <?php foreach ($exams as $exam):
      $attemptQuery = $pdo->prepare(
          'SELECT a.id, a.score, a.total_marks, a.submitted_at, u.name, u.email
           FROM attempts a
           JOIN exam_assigned_students eas ON eas.quiz_id = a.quiz_id AND eas.student_id = a.user_id
           JOIN users u ON u.id = a.user_id
           WHERE a.quiz_id = ? ORDER BY u.name'
      );
      $attemptQuery->execute([(int) $exam['quiz_id']]);
      $attempts = $attemptQuery->fetchAll();
      $isPublished = $exam['published_at'] !== null;
    ?>
      <article class="card p-4">
        <div class="d-flex flex-wrap justify-content-between gap-3">
          <div><h5 class="mb-1"><?= e($exam['title']) ?></h5><div class="text-muted">Faculty: <?= e($exam['faculty_name']) ?> · <?= (int) $exam['attempts'] ?> assigned attempts · Average <?= $exam['average_percent'] !== null ? number_format((float) $exam['average_percent'], 1) . '%' : '—' ?></div></div>
          <span class="badge <?= $isPublished ? 'bg-success' : ($exam['result_status'] === 'pending' ? 'bg-warning text-dark' : ($exam['result_status'] === 'approved' ? 'bg-primary' : 'bg-danger')) ?> align-self-start"><?= $isPublished ? 'Published' : ucfirst($exam['result_status']) ?></span>
        </div>
        <div class="small text-muted mt-2">Exam window ended <?= e(date('d M Y, H:i', strtotime($exam['scheduled_end']))) ?><?= $exam['result_requested_at'] ? ' · Submitted ' . e(date('d M Y, H:i', strtotime($exam['result_requested_at']))) : '' ?></div>
        <?php if ($exam['result_remarks']): ?><div class="alert alert-light border mt-3 mb-0"><strong>HOD remarks:</strong> <?= e($exam['result_remarks']) ?><?php if ($exam['result_reviewed_at']): ?><div class="small text-muted mt-1">Reviewed <?= e(date('d M Y, H:i', strtotime($exam['result_reviewed_at']))) ?></div><?php endif; ?></div><?php endif; ?>
        <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Student</th><th>Submitted</th><th>Score</th><th></th></tr></thead><tbody>
          <?php foreach ($attempts as $attempt): ?><tr><td><?= e($attempt['name']) ?><div class="small text-muted"><?= e($attempt['email']) ?></div></td><td><?= e(date('d M Y, H:i', strtotime($attempt['submitted_at']))) ?></td><td><?= $isPublished ? (int) $attempt['score'] . '/' . (int) $attempt['total_marks'] : 'Hidden from student' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('result.php?id=' . (int) $attempt['id'])) ?>">Review</a></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if (!$isPublished && $exam['result_status'] === 'pending'): ?>
          <form method="post" class="row g-2 align-items-end mt-2">
            <?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int) $exam['quiz_id'] ?>">
            <div class="col-md-6"><label class="form-label">HOD remarks (required for rejection)</label><input class="form-control" name="remarks" maxlength="1000"></div>
            <div class="col-md-3"><button class="btn btn-success w-100" name="action" value="approve" type="submit">Approve results</button></div>
            <div class="col-md-3"><button class="btn btn-outline-danger w-100" name="action" value="reject" type="submit">Reject results</button></div>
          </form>
        <?php elseif (!$isPublished && $exam['result_status'] === 'approved'): ?>
          <form method="post" class="mt-3" data-confirm="Publish the approved results to assigned students and issue eligible certificates?">
            <?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int) $exam['quiz_id'] ?>"><button class="btn btn-primary" name="action" value="publish" type="submit">Publish results to students</button>
          </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    <?php if (!$exams): ?><div class="card p-5 text-center text-muted">No faculty result submissions are awaiting review.</div><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
