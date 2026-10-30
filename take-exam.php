<?php
/**
 * Renders an exam (timer + radio options) and POSTs to submit-exam.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();
if ($me['role'] !== 'student') redirect('admin/dashboard.php');

$quizId = (int) ($_GET['quiz'] ?? 0);
if ($quizId <= 0) { flash('error','Invalid exam.'); redirect('exams.php'); }

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id=? AND is_published=1 LIMIT 1");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) { flash('error','Exam not found.'); redirect('exams.php'); }

$qStmt = $pdo->prepare("SELECT id, text, marks, difficulty FROM questions WHERE quiz_id=? ORDER BY position, id");
$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();

if (!$questions) { flash('error','This exam has no questions yet.'); redirect('exams.php'); }
shuffle($questions);

$ids = array_column($questions, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$oStmt = $pdo->prepare("SELECT id, question_id, text FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, position, id");
$oStmt->execute($ids);
$byQ = [];
foreach ($oStmt->fetchAll() as $o) {
    $byQ[(int)$o['question_id']][] = $o;
}

// Record start time in session so we can compute time-taken & enforce server-side cutoff.
start_session();
$_SESSION['exam_started_at_' . $quizId] = time();

$pageTitle = 'Exam · ' . $quiz['title'];
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
    <div>
      <span class="exam-tag"><?= e($quiz['tag'] ?: 'General') ?></span>
      <h3 class="mt-2 mb-1"><?= e($quiz['title']) ?></h3>
      <?php if ($quiz['intro']): ?><p class="text-muted mb-0"><?= e($quiz['intro']) ?></p><?php endif; ?>
    </div>
    <div id="examTimer" class="exam-timer text-center" data-seconds="<?= (int) $quiz['duration_minutes'] * 60 ?>">
      <small class="text-uppercase fw-bold d-block">Time left</small>
      <span class="fs-3 fw-bold" data-display>--:--</span>
    </div>
  </div>

  <form id="examForm" method="post" action="<?= e(base_url('submit-exam.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="quiz_id" value="<?= (int) $quiz['id'] ?>">

    <div class="row g-3">
      <div class="col-lg-9">
        <?php foreach ($questions as $i => $q):
          $opts = $byQ[(int) $q['id']] ?? [];
        ?>
          <div class="card question-card p-4 mb-3" id="q-<?= (int) $q['id'] ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="text-muted small">Question <?= $i+1 ?> of <?= count($questions) ?> · <?= (int) $q['marks'] ?> mark<?= $q['marks']>1?'s':'' ?> · <?= e($q['difficulty']) ?></span>
            </div>
            <h5 class="mb-3"><?= e($q['text']) ?></h5>
            <?php foreach ($opts as $o): ?>
              <label class="option-label">
                <input type="radio" name="ans[<?= (int) $q['id'] ?>]" value="<?= (int) $o['id'] ?>">
                <span><?= e($o['text']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <a href="<?= e(base_url('exams.php')) ?>" class="btn btn-link text-muted" data-confirm="Leave the exam? Your answers will not be saved.">&larr; Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Submit exam</button>
        </div>
      </div>

      <div class="col-lg-3 d-none d-lg-block">
        <div class="card p-3 sticky-top" style="top:80px;">
          <h6 class="mb-3"><i class="bi bi-grid-3x3-gap"></i> Question palette</h6>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($questions as $i => $q): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm" style="width:38px" data-jump-question="<?= (int) $q['id'] ?>"><?= $i+1 ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
