<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/exam-workflows.php';

$hod = require_admin();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $quizId = (int) ($_POST['quiz_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $remarks = clean_string($_POST['remarks'] ?? '', 1000);
    try {
        if (!in_array($decision, ['approve', 'reject'], true) || $quizId < 1) {
            throw new InvalidArgumentException('Choose a valid exam approval decision.');
        }
        if ($decision === 'reject' && $remarks === '') {
            throw new InvalidArgumentException('Provide a reason when rejecting an examination request.');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT q.title, q.duration_minutes, w.faculty_id, w.status, w.scheduled_start, w.scheduled_end
             FROM quizzes q JOIN exam_workflows w ON w.quiz_id = q.id
             WHERE q.id = ? FOR UPDATE"
        );
        $stmt->execute([$quizId]);
        $exam = $stmt->fetch();
        if (!$exam || $exam['status'] !== 'pending') {
            throw new InvalidArgumentException('This exam request is no longer pending review.');
        }
        if ($decision === 'approve') {
            if (!$exam['scheduled_start'] || !$exam['scheduled_end']) {
                throw new InvalidArgumentException('The examination needs a valid schedule before approval.');
            }
            $startAt = new DateTimeImmutable($exam['scheduled_start']);
            $endAt = new DateTimeImmutable($exam['scheduled_end']);
            validate_exam_schedule(
                $startAt->format('Y-m-d\TH:i'),
                $endAt->format('Y-m-d\TH:i'),
                (int) $exam['duration_minutes']
            );
            ensure_no_exam_schedule_conflict($pdo, $quizId, $startAt, $endAt);
            $pdo->prepare(
                "UPDATE exam_workflows SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), hod_remarks = ? WHERE quiz_id = ?"
            )->execute([(int) $hod['id'], $remarks ?: null, $quizId]);
            $message = 'Your examination "' . $exam['title'] . '" was approved.' . ($remarks !== '' ? ' HOD remarks: ' . $remarks : '');
            $notifyTitle = 'Exam request approved';
            $auditAction = 'exam.approved';
        } else {
            $pdo->prepare(
                "UPDATE exam_workflows SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), hod_remarks = ? WHERE quiz_id = ?"
            )->execute([(int) $hod['id'], $remarks, $quizId]);
            $message = 'Your examination "' . $exam['title'] . '" was rejected. Reason: ' . $remarks;
            $notifyTitle = 'Exam request rejected';
            $auditAction = 'exam.rejected';
        }
        notify_user($pdo, (int) $exam['faculty_id'], 'exam_decision', $notifyTitle, $message, 'faculty/exams.php');
        $pdo->prepare(
            'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)'
        )->execute([(int) $hod['id'], $auditAction, 'quiz', (string) $quizId, $remarks ?: null]);
        $pdo->commit();
        flash('success', $decision === 'approve' ? 'Examination approved.' : 'Examination rejected with remarks.');
        redirect('admin/approvals.php');
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Exam approval failed: ' . $error->getMessage());
        $errors[] = 'The decision could not be saved. Please try again.';
    }
}

$pending = $pdo->query(
    "SELECT q.id, q.title, q.tag, q.intro, q.duration_minutes, q.passing_percent, u.name AS faculty_name,
            w.faculty_id, w.scheduled_start, w.scheduled_end, w.instructions, w.requested_at,
            (SELECT COUNT(*) FROM questions questions WHERE questions.quiz_id = q.id) AS question_count,
            (SELECT COUNT(*) FROM exam_assigned_students assigned WHERE assigned.quiz_id = q.id) AS student_count
     FROM exam_workflows w
     JOIN quizzes q ON q.id = w.quiz_id
     JOIN users u ON u.id = w.faculty_id
     WHERE w.status = 'pending'
     ORDER BY w.requested_at, q.id"
)->fetchAll();

$pageTitle = 'HOD · Exam approvals';
include __DIR__ . '/../includes/header.php';
$adminActive = 'approvals';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <h3 class="mb-1">Exam approval requests</h3>
  <p class="text-muted">Review exam content and student assignments. Rejections require a reason.</p>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if (!$pending): ?><div class="card p-5 text-center text-muted">No pending examination requests.</div><?php endif; ?>
  <?php foreach ($pending as $exam): ?>
    <?php
      $studentQuery = $pdo->prepare(
          'SELECT u.name, sp.registration_no FROM exam_assigned_students a JOIN users u ON u.id = a.student_id
           LEFT JOIN student_profiles sp ON sp.user_id = u.id WHERE a.quiz_id = ? ORDER BY u.name'
      );
      $studentQuery->execute([(int) $exam['id']]);
      $requestStudents = $studentQuery->fetchAll();
      $questionQuery = $pdo->prepare(
          'SELECT q.id, q.text, q.marks, q.question_type, q.programming_language, q.sample_input,
                  q.expected_output, q.coding_constraints, o.text AS option_text, o.is_correct
           FROM questions q LEFT JOIN options o ON o.question_id = q.id
           WHERE q.quiz_id = ? ORDER BY q.position, o.position'
      );
      $questionQuery->execute([(int) $exam['id']]);
      $requestQuestions = [];
      foreach ($questionQuery->fetchAll() as $row) {
          $questionId = (int) $row['id'];
          if (!isset($requestQuestions[$questionId])) {
              $requestQuestions[$questionId] = [
                  'text' => $row['text'],
                  'marks' => (int) $row['marks'],
                  'type' => $row['question_type'] ?: 'mcq',
                  'programming_language' => $row['programming_language'],
                  'sample_input' => $row['sample_input'],
                  'expected_output' => $row['expected_output'],
                  'coding_constraints' => $row['coding_constraints'],
                  'options' => [],
                  'pairs' => [],
                  'test_cases' => [],
              ];
          }
          if ($row['option_text'] !== null) {
              $requestQuestions[$questionId]['options'][] = [
                  'text' => $row['option_text'],
                  'correct' => (bool) $row['is_correct'],
              ];
          }
      }
      $pairQuery = $pdo->prepare(
          'SELECT left_text, right_text, marks FROM question_matching_pairs WHERE question_id = ? ORDER BY position, id'
      );
      $testCaseQuery = $pdo->prepare(
          'SELECT input_data, expected_output FROM question_coding_test_cases WHERE question_id = ? ORDER BY position, id'
      );
      foreach ($requestQuestions as $questionId => &$requestQuestion) {
          $pairQuery->execute([(int) $questionId]);
          $requestQuestion['pairs'] = $pairQuery->fetchAll();
          $testCaseQuery->execute([(int) $questionId]);
          $requestQuestion['test_cases'] = $testCaseQuery->fetchAll();
      }
      unset($requestQuestion);
    ?>
    <article class="card p-4 mb-4">
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div><h5 class="mb-1"><?= e($exam['title']) ?></h5><div class="text-muted">By <?= e($exam['faculty_name']) ?> · <?= e($exam['tag'] ?: 'General') ?></div></div>
        <span class="badge bg-warning text-dark align-self-start">Pending</span>
      </div>
      <?php if ($exam['intro']): ?><p class="mt-3 mb-2"><?= e($exam['intro']) ?></p><?php endif; ?>
      <div class="row g-2 small text-muted mb-3">
        <div class="col-md-3">Schedule: <?= e(date('d M Y, H:i', strtotime($exam['scheduled_start']))) ?> – <?= e(date('H:i', strtotime($exam['scheduled_end']))) ?></div>
        <div class="col-md-3">Duration: <?= (int) $exam['duration_minutes'] ?> minutes</div>
        <div class="col-md-3">Passing: <?= (int) $exam['passing_percent'] ?>%</div>
        <div class="col-md-3"><?= (int) $exam['question_count'] ?> questions · <?= (int) $exam['student_count'] ?> students</div>
      </div>
      <?php if ($exam['instructions']): ?><div class="alert alert-light border"><strong>Instructions:</strong> <?= e($exam['instructions']) ?></div><?php endif; ?>
      <div class="row g-3">
        <div class="col-lg-7"><h6>Questions</h6><ol class="small"><?php foreach ($requestQuestions as $question): ?>
          <li class="mb-2"><strong><?= e(ucwords(str_replace('_', ' ', $question['type']))) ?>:</strong> <?= e($question['text']) ?> <span class="text-muted">(<?= (int) $question['marks'] ?> marks)</span>
            <?php if ($question['options']): ?><ul><?php foreach ($question['options'] as $option): ?><li><?= e($option['text']) ?><?= $option['correct'] ? ' · Correct answer' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($question['pairs']): ?><ul><?php foreach ($question['pairs'] as $pair): ?><li><?= e($pair['left_text']) ?> → <?= e($pair['right_text']) ?> (<?= (int) $pair['marks'] ?> marks)</li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($question['type'] === 'coding'): ?>
              <div class="small text-muted">Language: <?= e($question['programming_language'] ?? '') ?></div>
              <?php if ($question['coding_constraints']): ?><div class="small">Constraints: <?= nl2br(e($question['coding_constraints'])) ?></div><?php endif; ?>
              <?php if ($question['sample_input'] !== null): ?><div class="row small"><div class="col-md-6"><strong>Sample input</strong><pre><?= e($question['sample_input']) ?></pre></div><div class="col-md-6"><strong>Expected output</strong><pre><?= e($question['expected_output'] ?? '') ?></pre></div></div><?php endif; ?>
              <?php if ($question['test_cases']): ?><details class="small"><summary>Hidden test cases for faculty review (<?= count($question['test_cases']) ?>)</summary><?php foreach ($question['test_cases'] as $case): ?><div class="row"><div class="col-md-6"><pre><?= e($case['input_data']) ?></pre></div><div class="col-md-6"><pre><?= e($case['expected_output']) ?></pre></div></div><?php endforeach; ?></details><?php endif; ?>
            <?php endif; ?>
          </li><?php endforeach; ?></ol></div>
        <div class="col-lg-5"><h6>Assigned students</h6><ul class="small"><?php foreach ($requestStudents as $student): ?><li><?= e($student['name'] . ($student['registration_no'] ? ' · ' . $student['registration_no'] : '')) ?></li><?php endforeach; ?></ul></div>
      </div>
      <form method="post" class="border-top pt-3">
        <?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int) $exam['id'] ?>">
        <label class="form-label">HOD remarks (required for rejection)</label>
        <textarea class="form-control mb-2" name="remarks" rows="2" maxlength="1000"></textarea>
        <button class="btn btn-success" name="decision" value="approve" type="submit">Approve</button>
        <button class="btn btn-outline-danger" name="decision" value="reject" type="submit">Reject</button>
      </form>
    </article>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
