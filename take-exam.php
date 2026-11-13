<?php
/**
 * Renders the question types for an exam and POSTs answers to submit-exam.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/question-types.php';

$me = require_student();

$quizId = (int) ($_GET['quiz'] ?? 0);
if ($quizId <= 0) { flash('error','Invalid exam.'); redirect('exams.php'); }

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT q.*, w.status AS workflow_status, w.scheduled_start, w.scheduled_end
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
    $now = new DateTimeImmutable();
    $scheduledStart = new DateTimeImmutable($quiz['scheduled_start']);
    $scheduledEnd = new DateTimeImmutable($quiz['scheduled_end']);
    if ($now < $scheduledStart) {
        flash('error', 'This examination opens at ' . $scheduledStart->format('d M Y, H:i') . '.');
        redirect('exams.php');
    }
    if ($now >= $scheduledEnd) {
        flash('error', 'The scheduled examination window has closed.');
        redirect('exams.php');
    }
    $existingAttempt = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE quiz_id = ? AND user_id = ?');
    $existingAttempt->execute([$quizId, (int) $me['id']]);
    if ((int) $existingAttempt->fetchColumn() > 0) {
        flash('error', 'You have already submitted this examination.');
        redirect('exams.php');
    }
}

$qStmt = $pdo->prepare(
    'SELECT id, text, marks, difficulty, question_type, programming_language,
            sample_input, expected_output, coding_constraints
     FROM questions WHERE quiz_id = ? ORDER BY position, id'
);
$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();

if (!$questions) { flash('error','This exam has no questions yet.'); redirect('exams.php'); }
shuffle($questions);

$ids = array_map('intval', array_column($questions, 'id'));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$oStmt = $pdo->prepare("SELECT id, question_id, text FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, position, id");
$oStmt->execute($ids);
$byQ = [];
foreach ($oStmt->fetchAll() as $o) {
    $byQ[(int)$o['question_id']][] = $o;
}

$pairStmt = $pdo->prepare(
    "SELECT id, question_id, left_text, right_text, marks
     FROM question_matching_pairs WHERE question_id IN ($placeholders) ORDER BY question_id, position, id"
);
$pairStmt->execute($ids);
$pairsByQ = [];
foreach ($pairStmt->fetchAll() as $pair) {
    $pairsByQ[(int) $pair['question_id']][] = $pair;
}

$savedQuery = $pdo->prepare(
    'SELECT question_id, answer_json, marked_for_review
     FROM exam_answer_autosaves WHERE quiz_id = ? AND student_id = ?'
);
$savedQuery->execute([$quizId, (int) $me['id']]);
$savedAnswers = [];
foreach ($savedQuery->fetchAll() as $savedAnswer) {
    $decoded = json_decode($savedAnswer['answer_json'], true);
    $savedAnswers[(int) $savedAnswer['question_id']] = [
        'answer' => is_array($decoded) ? $decoded : [],
        'marked' => (bool) $savedAnswer['marked_for_review'],
    ];
}

start_session();
$matchingOrderByQuestion = [];
foreach ($pairsByQ as $questionId => $questionPairs) {
    $pairIds = array_map(static fn(array $pair): int => (int) $pair['id'], $questionPairs);
    $orderKey = 'exam_match_order_' . $quizId . '_' . $questionId;
    $savedOrder = $savedAnswers[$questionId]['answer']['display_order'] ?? $_SESSION[$orderKey] ?? null;
    $sortedPairIds = $pairIds;
    $sortedSavedOrder = is_array($savedOrder) ? array_map('intval', $savedOrder) : [];
    sort($sortedPairIds);
    sort($sortedSavedOrder);
    if ($sortedSavedOrder !== $sortedPairIds) {
        $savedOrder = $pairIds;
        shuffle($savedOrder);
    }
    $matchingOrderByQuestion[$questionId] = array_map('intval', $savedOrder);
    $_SESSION[$orderKey] = $matchingOrderByQuestion[$questionId];
}

// Record start time in session so we can compute time-taken & enforce server-side cutoff.
$sessionStartKey = 'exam_started_at_' . $quizId;
if (!isset($_SESSION[$sessionStartKey])) {
    $_SESSION[$sessionStartKey] = time();
}
$timerSeconds = (int) $quiz['duration_minutes'] * 60;
$timerSeconds = min(
    $timerSeconds,
    (int) $_SESSION[$sessionStartKey] + $timerSeconds - time()
);
if ($quiz['workflow_status'] !== null) {
    $timerSeconds = min($timerSeconds, (new DateTimeImmutable($quiz['scheduled_end']))->getTimestamp() - time());
}
if ($timerSeconds <= 0) {
    unset($_SESSION[$sessionStartKey]);
    flash('error', 'Your examination time has expired.');
    redirect('exams.php');
}

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
    <div id="examTimer" class="exam-timer text-center" data-seconds="<?= $timerSeconds ?>">
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
          $questionId = (int) $q['id'];
          $type = $q['question_type'] ?: 'mcq';
          $saved = $savedAnswers[$questionId]['answer'] ?? [];
          $marked = $savedAnswers[$questionId]['marked'] ?? false;
          $opts = $byQ[$questionId] ?? [];
          $pairs = $pairsByQ[$questionId] ?? [];
          $columnBOrder = $matchingOrderByQuestion[$questionId] ?? [];
          $pairsById = [];
          foreach ($pairs as $pair) {
              $pairsById[(int) $pair['id']] = $pair;
          }
        ?>
          <div class="card question-card p-4 mb-3" id="q-<?= $questionId ?>" data-question-card data-question-id="<?= $questionId ?>" data-question-type="<?= e($type) ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="text-muted small">Question <?= $i+1 ?> of <?= count($questions) ?> · <?= (int) $q['marks'] ?> mark<?= $q['marks']>1?'s':'' ?> · <?= e($q['difficulty']) ?></span>
              <label class="form-check-label small"><input class="form-check-input" type="checkbox" name="marked[<?= $questionId ?>]" value="1" data-mark-review <?= $marked ? 'checked' : '' ?>> Mark for review</label>
            </div>
            <h5 class="mb-3"><?= nl2br(e($q['text'])) ?></h5>
            <span class="badge text-bg-light mb-2"><?= e(['mcq'=>'Multiple Choice','fill_blank'=>'Fill in the Blanks','match'=>'Match the Following','true_false'=>'True or False','coding'=>'Programming / Coding'][$type] ?? 'Question') ?></span>
            <?php if (in_array($type, ['mcq', 'fill_blank', 'true_false'], true)): ?>
              <?php foreach ($opts as $o): ?>
                <label class="option-label">
                  <input type="radio" name="ans[<?= $questionId ?>]" value="<?= (int) $o['id'] ?>" data-answer-control <?= (int) ($saved['option_id'] ?? 0) === (int) $o['id'] ? 'checked' : '' ?>>
                  <span><?= e($o['text']) ?></span>
                </label>
              <?php endforeach; ?>
            <?php elseif ($type === 'match'): ?>
              <?php $savedMatchText = is_string($saved['text'] ?? null) ? $saved['text'] : ''; ?>
              <div class="table-responsive">
                <table class="table match-question-table mb-2">
                  <thead><tr><th scope="col">Column A</th><th scope="col">Column B</th></tr></thead>
                  <tbody>
                    <?php foreach ($pairs as $index => $pair): ?>
                      <?php $rightPair = $pairsById[$columnBOrder[$index]]; ?>
                      <tr>
                        <td><?= $index + 1 ?>. <?= e($pair['left_text']) ?></td>
                        <td><?= e(matching_column_label($index)) ?>. <?= e($rightPair['right_text']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <label class="form-label mb-1" for="matching-answer-<?= $questionId ?>">Your matches</label>
              <textarea class="form-control" id="matching-answer-<?= $questionId ?>" name="ans[<?= $questionId ?>]" rows="2" maxlength="2000" placeholder="For example: 1-B, 2-A, 3-C, 4-D" aria-describedby="matching-help-<?= $questionId ?>" data-answer-control><?= e($savedMatchText) ?></textarea>
              <div class="form-text" id="matching-help-<?= $questionId ?>">Enter each Column A number and its Column B letter, separated by commas.</div>
            <?php elseif ($type === 'coding'): ?>
              <div class="small mb-2"><strong>Language:</strong> <?= e($q['programming_language'] ?: 'Not specified') ?></div>
              <?php if ($q['coding_constraints']): ?><div class="alert alert-info py-2"><?= nl2br(e($q['coding_constraints'])) ?></div><?php endif; ?>
              <?php if ($q['sample_input'] !== null || $q['expected_output'] !== null): ?>
                <div class="row g-2 mb-3">
                  <div class="col-md-6"><strong>Sample input</strong><pre class="bg-light p-2 rounded"><?= e($q['sample_input'] ?? '') ?></pre></div>
                  <div class="col-md-6"><strong>Expected output</strong><pre class="bg-light p-2 rounded"><?= e($q['expected_output'] ?? '') ?></pre></div>
                </div>
              <?php endif; ?>
              <label class="form-label">Your source code</label>
              <div class="d-flex border rounded overflow-hidden">
                <pre class="bg-light text-muted text-end px-2 mb-0 user-select-none" data-line-numbers aria-hidden="true">1</pre>
                <textarea class="form-control border-0 font-monospace" rows="16" spellcheck="false" maxlength="100000" name="ans[<?= $questionId ?>][code]" data-code-editor data-answer-control><?= e(is_string($saved['code'] ?? null) ? $saved['code'] : '') ?></textarea>
              </div>
              <input type="hidden" name="code_submitted[<?= $questionId ?>]" value="<?= !empty($saved['submitted']) ? '1' : '0' ?>" data-code-submitted>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-clear-code>Clear code</button>
                <button class="btn btn-sm btn-outline-primary" type="button" data-save-code>Save answer</button>
                <button class="btn btn-sm btn-primary" type="button" data-submit-code>Submit code answer</button>
                <span class="small text-muted align-self-center" data-code-status><?= !empty($saved['submitted']) ? 'Code answer submitted; you can still edit it.' : 'Not submitted' ?></span>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <a href="<?= e(base_url('exams.php')) ?>" class="btn btn-link text-muted" data-confirm="Leave the exam? Your answers will not be saved.">&larr; Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Submit exam</button>
        </div>
      </div>

      <div class="col-lg-3">
        <div class="card p-3 sticky-top" style="top:80px;">
          <h6 class="mb-3"><i class="bi bi-grid-3x3-gap"></i> Question palette</h6>
          <div class="d-flex flex-wrap gap-1" data-question-palette>
            <?php foreach ($questions as $i => $q): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm" style="width:38px" data-jump-question="<?= (int) $q['id'] ?>" data-palette-question><?= $i+1 ?></button>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-3">Palette: unanswered · answered · marked for review</div>
        </div>
      </div>
    </div>
  </form>
  <div id="autosaveStatus" class="small text-muted mt-2" role="status" aria-live="polite">Answers save automatically while you work.</div>
</div>
<script>
(() => {
  const form = document.getElementById('examForm');
  const status = document.getElementById('autosaveStatus');
  if (!form || !status) return;
  let saveTimer;
  let saving = false;
  const timer = document.getElementById('examTimer');
  let remaining = Number(timer.dataset.seconds);
  const countdown = () => {
    const minutes = Math.floor(Math.max(0, remaining) / 60);
    const seconds = Math.max(0, remaining) % 60;
    timer.querySelector('[data-display]').textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    if (remaining <= 0) {
      status.textContent = 'Time expired. Submitting your answers…';
      form.dataset.submitting = '1';
      form.requestSubmit();
      return;
    }
    remaining -= 1;
    window.setTimeout(countdown, 1000);
  };
  const updateLineNumbers = (editor) => {
    const lines = editor.value.split('\n').length;
    editor.parentElement.querySelector('[data-line-numbers]').textContent = Array.from({length: lines}, (_, index) => index + 1).join('\n');
  };
  const updatePalette = (questionId) => {
    const card = document.querySelector(`[data-question-card][data-question-id="${questionId}"]`);
    const answered = Array.from(card.querySelectorAll('[data-answer-control]')).some((control) => {
      if (control.type === 'radio') return control.checked;
      return control.value.trim() !== '';
    });
    const marked = card.querySelector('[data-mark-review]').checked;
    const button = document.querySelector(`[data-palette-question][data-jump-question="${questionId}"]`);
    button.className = `btn btn-sm ${marked ? 'btn-warning' : (answered ? 'btn-success' : 'btn-outline-secondary')}`;
    button.textContent = marked && answered ? `${button.dataset.questionNumber}✓` : button.dataset.questionNumber;
    button.setAttribute('aria-label', `Question ${button.dataset.questionNumber}: ${marked ? 'marked for review, ' : ''}${answered ? 'answered' : 'unanswered'}`);
  };
  const saveAnswers = async () => {
    if (saving || form.dataset.submitting === '1') return;
    saving = true;
    try {
      const response = await fetch(<?= json_encode(base_url('autosave.php')) ?>, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const result = await response.json();
      if (!response.ok || !result.saved) throw new Error(result.message || 'Save failed.');
      status.textContent = 'Answers saved at ' + new Date(result.saved_at).toLocaleTimeString();
    } catch (error) {
      status.textContent = error.message || 'Answers could not be saved.';
      status.classList.add('text-danger');
    } finally {
      saving = false;
    }
  };
  form.addEventListener('input', (event) => {
    const card = event.target.closest('[data-question-card]');
    if (card && event.target.matches('[data-code-editor]')) {
      card.querySelector('[data-code-submitted]').value = '0';
      card.querySelector('[data-code-status]').textContent = 'Edited; save or submit the updated code.';
      updateLineNumbers(event.target);
    }
    if (card) updatePalette(card.dataset.questionId);
    status.textContent = 'Saving answers…';
    status.classList.remove('text-danger');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveAnswers, 400);
  });
  form.addEventListener('change', (event) => {
    const card = event.target.closest('[data-question-card]');
    if (card) updatePalette(card.dataset.questionId);
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveAnswers, 400);
  });
  document.querySelectorAll('[data-jump-question]').forEach((button) => {
    button.dataset.questionNumber = button.textContent.trim();
    button.addEventListener('click', () => document.getElementById(`q-${button.dataset.jumpQuestion}`).scrollIntoView({behavior: 'smooth'}));
  });
  document.querySelectorAll('[data-code-editor]').forEach((editor) => {
    updateLineNumbers(editor);
    updatePalette(editor.closest('[data-question-card]').dataset.questionId);
  });
  document.querySelectorAll('[data-question-card]').forEach((card) => updatePalette(card.dataset.questionId));
  document.querySelectorAll('[data-clear-code]').forEach((button) => {
    button.addEventListener('click', () => {
      const card = button.closest('[data-question-card]');
      const editor = card.querySelector('[data-code-editor]');
      editor.value = '';
      card.querySelector('[data-code-submitted]').value = '0';
      card.querySelector('[data-code-status]').textContent = 'Code cleared.';
      updateLineNumbers(editor);
      updatePalette(card.dataset.questionId);
      saveAnswers();
    });
  });
  document.querySelectorAll('[data-save-code]').forEach((button) => {
    button.addEventListener('click', async () => {
      const card = button.closest('[data-question-card]');
      await saveAnswers();
      card.querySelector('[data-code-status]').textContent = 'Answer saved.';
    });
  });
  document.querySelectorAll('[data-submit-code]').forEach((button) => {
    button.addEventListener('click', async () => {
      const card = button.closest('[data-question-card]');
      const editor = card.querySelector('[data-code-editor]');
      if (!editor.value.trim()) {
        card.querySelector('[data-code-status]').textContent = 'Write code before submitting this answer.';
        return;
      }
      card.querySelector('[data-code-submitted]').value = '1';
      await saveAnswers();
      card.querySelector('[data-code-status]').textContent = 'Code answer submitted; you can still edit it.';
    });
  });
  window.setInterval(saveAnswers, 15000);
  form.addEventListener('submit', () => {
    clearTimeout(saveTimer);
    form.dataset.submitting = '1';
  });
  countdown();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
