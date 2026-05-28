<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();
$attemptId = (int) ($_GET['id'] ?? 0);
if ($attemptId <= 0) redirect('history.php');

$pdo = db();
$stmt = $pdo->prepare("
    SELECT a.*, q.title AS quiz_title, q.tag AS quiz_tag, q.passing_percent, u.name AS student_name, u.email AS student_email
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    JOIN users   u ON u.id = a.user_id
    WHERE a.id = ? LIMIT 1
");
$stmt->execute([$attemptId]);
$att = $stmt->fetch();
if (!$att) { flash('error','Attempt not found.'); redirect('history.php'); }

// Authorization: owner OR admin.
if ((int) $att['user_id'] !== (int) $me['id'] && $me['role'] !== 'admin') {
    flash('error','You are not allowed to view this attempt.');
    redirect('history.php');
}

// Per-question breakdown.
$brk = $pdo->prepare("
    SELECT aa.question_id, aa.selected_option_id, aa.is_correct,
           q.text AS qtext, q.marks AS qmarks,
           sel.text AS selected_text,
           (SELECT GROUP_CONCAT(text SEPARATOR '||') FROM options WHERE question_id = q.id AND is_correct = 1) AS correct_texts
    FROM attempt_answers aa
    JOIN questions q ON q.id = aa.question_id
    LEFT JOIN options sel ON sel.id = aa.selected_option_id
    WHERE aa.attempt_id = ?
    ORDER BY q.position, q.id
");
$brk->execute([$attemptId]);
$breakdown = $brk->fetchAll();

$pct = $att['total_marks'] > 0 ? round($att['score'] * 100 / $att['total_marks'], 1) : 0.0;
$passed = $pct >= (float) $att['passing_percent'];
$grade  = grade_letter($pct);

$pageTitle = 'Result · ' . $att['quiz_title'];
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="card p-4 p-md-5 mb-4">
    <div class="row align-items-center g-4">
      <div class="col-md-4 text-center">
        <div class="score-ring mx-auto" data-value="<?= $pct ?>">
          <span><?= $pct ?>%</span>
        </div>
        <div class="mt-3">
          <span class="badge bg-<?= $passed ? 'success' : 'danger' ?> fs-6"><?= $passed ? 'Passed' : 'Failed' ?> · Grade <?= e($grade) ?></span>
        </div>
      </div>
      <div class="col-md-8">
        <span class="exam-tag"><?= e($att['quiz_tag'] ?: 'General') ?></span>
        <h3 class="mt-2"><?= e($att['quiz_title']) ?></h3>
        <p class="text-muted small mb-3">
          Attempted on <?= e(date('d M Y, H:i', strtotime($att['submitted_at']))) ?>
          · Time: <?= e(format_duration_seconds((int) $att['time_taken_sec'])) ?>
        </p>
        <div class="row g-2">
          <div class="col-6 col-md-3"><div class="bg-soft p-3 rounded text-center"><div class="text-muted small">Score</div><div class="fs-4 fw-bold"><?= (int) $att['score'] ?>/<?= (int) $att['total_marks'] ?></div></div></div>
          <div class="col-6 col-md-3"><div class="bg-soft p-3 rounded text-center"><div class="text-muted small">Correct</div><div class="fs-4 fw-bold text-success"><?= (int) $att['correct_count'] ?></div></div></div>
          <div class="col-6 col-md-3"><div class="bg-soft p-3 rounded text-center"><div class="text-muted small">Wrong</div><div class="fs-4 fw-bold text-danger"><?= (int) $att['wrong_count'] ?></div></div></div>
          <div class="col-6 col-md-3"><div class="bg-soft p-3 rounded text-center"><div class="text-muted small">Skipped</div><div class="fs-4 fw-bold text-warning"><?= (int) $att['unanswered_count'] ?></div></div></div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <a class="btn btn-outline-primary" href="<?= e(base_url('export.php?type=pdf&attempt=' . (int) $att['id'])) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
          <a class="btn btn-outline-secondary" href="<?= e(base_url('export.php?type=csv&attempt=' . (int) $att['id'])) ?>"><i class="bi bi-filetype-csv"></i> Export CSV</a>
          <a class="btn btn-primary" href="<?= e(base_url('take-exam.php?quiz=' . (int) $att['quiz_id'])) ?>"><i class="bi bi-arrow-clockwise"></i> Retake</a>
        </div>
      </div>
    </div>
  </div>

  <div class="card p-4">
    <h5 class="mb-3"><i class="bi bi-list-check"></i> Per-question breakdown</h5>
    <div class="vstack gap-2">
      <?php foreach ($breakdown as $i => $b):
        $correctTxts = $b['correct_texts'] ? explode('||', $b['correct_texts']) : [];
        $status = $b['selected_option_id'] === null ? 'skipped' : ($b['is_correct'] ? 'correct' : 'wrong');
        $color  = $status === 'correct' ? 'success' : ($status === 'wrong' ? 'danger' : 'warning');
        $icon   = $status === 'correct' ? 'bi-check-circle' : ($status === 'wrong' ? 'bi-x-circle' : 'bi-dash-circle');
      ?>
        <div class="border rounded p-3">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="text-muted small">Q<?= $i+1 ?> · <?= (int) $b['qmarks'] ?> mark</div>
              <div class="fw-semibold mt-1"><?= e($b['qtext']) ?></div>
            </div>
            <span class="badge bg-<?= $color ?>"><i class="bi <?= $icon ?>"></i> <?= e(ucfirst($status)) ?></span>
          </div>
          <div class="mt-2 small">
            <div>Your answer: <strong><?= e($b['selected_text'] ?? '— not answered —') ?></strong></div>
            <?php if ($status !== 'correct' && $correctTxts): ?>
              <div class="text-success">Correct answer: <strong><?= e(implode(' / ', $correctTxts)) ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
