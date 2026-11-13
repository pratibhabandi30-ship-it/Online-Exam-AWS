<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificates.php';
require_once __DIR__ . '/includes/question-types.php';

$me = require_login();
$attemptId = (int) ($_GET['id'] ?? 0);
if ($attemptId <= 0) redirect('history.php');

$pdo = db();
$stmt = $pdo->prepare("
    SELECT a.*, q.title AS quiz_title, q.tag AS quiz_tag, q.passing_percent, u.name AS student_name, u.email AS student_email,
           w.faculty_id, w.status AS workflow_status, rp.published_at AS results_published_at
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    JOIN users   u ON u.id = a.user_id
    LEFT JOIN exam_workflows w ON w.quiz_id = q.id
    LEFT JOIN result_publications rp ON rp.quiz_id = q.id
    WHERE a.id = ? LIMIT 1
");
$stmt->execute([$attemptId]);
$att = $stmt->fetch();
if (!$att) { flash('error','Attempt not found.'); redirect('history.php'); }

$isOwner = (int) $att['user_id'] === (int) $me['id'];
$isHod = $me['role'] === 'admin';
$isAssignedFaculty = false;
if ($me['role'] === 'faculty' && (int) $att['faculty_id'] === (int) $me['id']) {
    $assignedQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM exam_assigned_students eas
         JOIN faculty_student_assignments fsa
           ON fsa.faculty_id = ? AND fsa.student_id = eas.student_id
         WHERE eas.quiz_id = ? AND eas.student_id = ?'
    );
    $assignedQuery->execute([(int) $me['id'], (int) $att['quiz_id'], (int) $att['user_id']]);
    $isAssignedFaculty = (int) $assignedQuery->fetchColumn() > 0;
}
if (!$isOwner && !$isHod && !$isAssignedFaculty) {
    flash('error','You are not allowed to view this attempt.');
    redirect('history.php');
}

if ($isOwner && $att['results_published_at'] !== null) {
    $pdo->prepare(
        "UPDATE notifications SET read_at = NOW()
         WHERE user_id = ? AND type = 'results_published' AND link = ? AND read_at IS NULL"
    )->execute([(int) $me['id'], 'result.php?id=' . $attemptId]);
}

if ($isOwner && !$isHod && $att['faculty_id'] !== null && $att['results_published_at'] === null) {
    $pageTitle = 'Result pending · ' . $att['quiz_title'];
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="container py-4"><div class="card p-4 p-md-5 text-center">
      <i class="bi bi-hourglass-split display-4 text-primary"></i>
      <h3 class="mt-3">Your submission is recorded</h3>
      <p class="text-muted mb-1">Results for <?= e($att['quiz_title']) ?> are hidden until your faculty member reviews and publishes them.</p>
      <p class="small text-muted">Submitted <?= e(date('d M Y, H:i', strtotime($att['submitted_at']))) ?></p>
      <a class="btn btn-outline-primary align-self-center" href="<?= e(base_url('history.php')) ?>">Back to exam history</a>
    </div></div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Per-question breakdown.
$brk = $pdo->prepare("
    SELECT aa.question_id, aa.selected_option_id, aa.is_correct, aa.answer_text,
           aa.awarded_marks, aa.feedback,
           q.text AS qtext, q.marks AS qmarks, q.question_type, q.programming_language,
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
$certificateStmt = $pdo->prepare("SELECT certificate_id, certificate_type, status FROM certificates WHERE attempt_id = ? LIMIT 1");
$certificateStmt->execute([$attemptId]);
$certificate = $certificateStmt->fetch();
if ($certificate) {
    $certificate = find_certificate($pdo, $certificate['certificate_id']);
    if (!$certificate || !certificate_record_matches_attempt($certificate)) {
        $certificate = null;
    }
}

$pageTitle = 'Result · ' . $att['quiz_title'];
$facultyShellEnabled = $isAssignedFaculty;
include __DIR__ . '/includes/header.php';
if ($isHod) {
    $adminActive = 'attempts';
    include __DIR__ . '/admin/_nav.php';
} elseif ($isAssignedFaculty) {
    $facultyActive = 'results';
    include __DIR__ . '/faculty/_nav.php';
}
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
          <?php if ($certificate && $certificate['status'] === 'issued'): ?>
            <a class="btn btn-success" href="<?= e(base_url('certificate.php?id=' . rawurlencode($certificate['certificate_id']))) ?>"><i class="bi bi-award"></i> View <?= e($certificate['certificate_type']) ?> Certificate</a>
          <?php endif; ?>
          <a class="btn btn-outline-primary" href="<?= e(base_url('export.php?type=pdf&attempt=' . (int) $att['id'])) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
          <a class="btn btn-outline-secondary" href="<?= e(base_url('export.php?type=csv&attempt=' . (int) $att['id'])) ?>"><i class="bi bi-filetype-csv"></i> Export CSV</a>
          <a class="btn btn-primary" href="<?= e(base_url('take-exam.php?quiz=' . (int) $att['quiz_id'])) ?>"><i class="bi bi-arrow-clockwise"></i> Retake</a>
        </div>
        <?php if ($certificate && $certificate['status'] === 'issued' && !$passed): ?>
          <div class="alert alert-info small py-2 mt-3 mb-0">
            Certificate tier follows the fixed achievement thresholds; exam pass status follows this quiz's configured passing score.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card p-4">
    <h5 class="mb-3"><i class="bi bi-list-check"></i> Per-question breakdown</h5>
    <div class="vstack gap-2">
      <?php foreach ($breakdown as $i => $b):
        $correctTxts = $b['correct_texts'] ? explode('||', $b['correct_texts']) : [];
        $earned = $b['awarded_marks'] !== null ? (int) $b['awarded_marks'] : ((int) $b['is_correct'] ? (int) $b['qmarks'] : 0);
        $savedMatch = $b['question_type'] === 'match' ? json_decode((string) $b['answer_text'], true) : [];
        $matchValues = is_array($savedMatch['pairs'] ?? null) ? array_filter($savedMatch['pairs']) : [];
        $hasResponse = $b['selected_option_id'] !== null
          || ($b['question_type'] === 'match' && $matchValues)
          || ($b['question_type'] !== 'match' && trim((string) $b['answer_text']) !== '');
        $status = $earned >= (int) $b['qmarks'] ? 'correct' : ($earned > 0 ? 'partially correct' : (!$hasResponse ? 'skipped' : 'wrong'));
        $color  = $status === 'correct' ? 'success' : (in_array($status, ['wrong', 'partially correct'], true) ? ($status === 'wrong' ? 'danger' : 'warning') : 'secondary');
        $icon   = $status === 'correct' ? 'bi-check-circle' : ($status === 'wrong' ? 'bi-x-circle' : 'bi-dash-circle');
      ?>
        <div class="border rounded p-3">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="text-muted small">Q<?= $i+1 ?> · <?= e(ucwords(str_replace('_', ' ', $b['question_type'] ?: 'mcq'))) ?> · <?= $earned ?>/<?= (int) $b['qmarks'] ?> marks</div>
              <div class="fw-semibold mt-1"><?= e($b['qtext']) ?></div>
            </div>
            <span class="badge bg-<?= $color ?>"><i class="bi <?= $icon ?>"></i> <?= e(ucfirst($status)) ?></span>
          </div>
          <div class="mt-2 small">
            <?php if ($b['question_type'] === 'coding'): ?>
              <div class="mb-1">Language: <strong><?= e($b['programming_language'] ?: 'Not specified') ?></strong></div>
              <div>Your submitted code:</div><pre class="bg-light border rounded p-2 mt-1"><code><?= e($b['answer_text'] ?? '— no code submitted —') ?></code></pre>
              <?php if ($b['feedback']): ?><div class="alert alert-info py-2 mb-0">Faculty feedback: <?= nl2br(e($b['feedback'])) ?></div><?php endif; ?>
            <?php elseif ($b['question_type'] === 'match'): ?>
              <?php
                $pairQuery = $pdo->prepare('SELECT id, left_text, right_text FROM question_matching_pairs WHERE question_id = ? ORDER BY position, id');
                $pairQuery->execute([(int) $b['question_id']]);
                $matchRows = $pairQuery->fetchAll();
                $selectedMatches = is_array($savedMatch['pairs'] ?? null) ? $savedMatch['pairs'] : [];
                $matchIds = array_map(static fn(array $match): int => (int) $match['id'], $matchRows);
                $displayOrder = is_array($savedMatch['display_order'] ?? null)
                    ? array_map('intval', $savedMatch['display_order'])
                    : $matchIds;
                $sortedMatchIds = $matchIds;
                $sortedDisplayOrder = $displayOrder;
                sort($sortedMatchIds);
                sort($sortedDisplayOrder);
                if ($sortedDisplayOrder !== $sortedMatchIds) {
                    $displayOrder = $matchIds;
                }
                $labelsByMatchId = [];
                foreach ($displayOrder as $displayIndex => $matchId) {
                    $labelsByMatchId[$matchId] = matching_column_label($displayIndex);
                }
                $matchesById = [];
                foreach ($matchRows as $match) {
                    $matchesById[(int) $match['id']] = $match;
                }
              ?>
              <?php if (!empty($savedMatch['text'])): ?><div class="mb-2">Your answer: <strong><?= e((string) $savedMatch['text']) ?></strong></div><?php endif; ?>
              <div class="table-responsive">
                <table class="table match-question-table mb-0">
                  <thead><tr><th scope="col">Column A</th><th scope="col">Your match</th><?php if ($att['results_published_at'] !== null): ?><th scope="col">Correct match</th><?php endif; ?></tr></thead>
                  <tbody>
                    <?php foreach ($matchRows as $matchIndex => $match): ?>
                      <?php
                        $matchId = (int) $match['id'];
                        $chosenId = (int) ($selectedMatches[$matchId] ?? 0);
                        $selectedMatch = $matchesById[$chosenId] ?? null;
                        $correctId = $matchId;
                      ?>
                      <tr>
                        <td><?= $matchIndex + 1 ?>. <?= e($match['left_text']) ?></td>
                        <td><?= $selectedMatch ? e($labelsByMatchId[$chosenId] . '. ' . $selectedMatch['right_text']) : '— unanswered —' ?></td>
                        <?php if ($att['results_published_at'] !== null): ?><td class="text-success"><?= e($labelsByMatchId[$correctId] . '. ' . $match['right_text']) ?></td><?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div>Your answer: <strong><?= e($b['selected_text'] ?? '— not answered —') ?></strong></div>
            <?php endif; ?>
            <?php if (!in_array($b['question_type'], ['coding', 'match'], true) && $status !== 'correct' && $correctTxts): ?>
              <div class="text-success">Correct answer: <strong><?= e(implode(' / ', $correctTxts)) ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
