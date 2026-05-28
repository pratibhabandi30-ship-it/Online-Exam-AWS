<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin  = require_admin();
$pdo    = db();
$quizId = (int) ($_GET['quiz'] ?? 0);
$action = $_GET['action'] ?? 'list';
$qid    = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($quizId <= 0) { flash('error','Pick a quiz first.'); redirect('admin/quizzes.php'); }

$qstmt = $pdo->prepare("SELECT * FROM quizzes WHERE id=? LIMIT 1");
$qstmt->execute([$quizId]);
$quiz = $qstmt->fetch();
if (!$quiz) { flash('error','Quiz not found.'); redirect('admin/quizzes.php'); }

// ---------- DELETE QUESTION ----------
if ($action === 'delete' && $qid > 0) {
    verify_csrf();
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id=? AND quiz_id=?");
    $stmt->execute([$qid, $quizId]);
    flash('success','Question deleted.');
    redirect('admin/questions.php?quiz=' . $quizId);
}

// ---------- CREATE / EDIT QUESTION ----------
if ($action === 'new' || $action === 'edit') {
    $editing = null; $opts = [];
    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE id=? AND quiz_id=? LIMIT 1");
        $stmt->execute([$qid, $quizId]);
        $editing = $stmt->fetch();
        if (!$editing) { flash('error','Question not found.'); redirect('admin/questions.php?quiz='.$quizId); }
        $os = $pdo->prepare("SELECT * FROM options WHERE question_id=? ORDER BY position, id");
        $os->execute([$qid]);
        $opts = $os->fetchAll();
    }

    $form = [
        'text'  => $editing['text']  ?? '',
        'marks' => $editing['marks'] ?? 1,
        'opts'  => $opts ?: [
            ['text'=>'', 'is_correct'=>1],
            ['text'=>'', 'is_correct'=>0],
            ['text'=>'', 'is_correct'=>0],
            ['text'=>'', 'is_correct'=>0],
        ],
        'correct' => 0,
    ];
    foreach ($form['opts'] as $i => $o) {
        if (!empty($o['is_correct'])) $form['correct'] = $i;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $form['text']  = clean_string($_POST['text'] ?? '', 1000);
        $form['marks'] = max(1, (int) ($_POST['marks'] ?? 1));
        $optTexts      = $_POST['opt_text']    ?? [];
        $correctIdx    = (int) ($_POST['correct'] ?? 0);

        if (mb_strlen($form['text']) < 3) $errors[] = 'Question text is too short.';

        $optClean = [];
        foreach ($optTexts as $idx => $t) {
            $t = clean_string($t, 500);
            if ($t !== '') $optClean[] = ['text'=>$t, 'is_correct'=>$idx === $correctIdx ? 1 : 0];
        }
        if (count($optClean) < 2) $errors[] = 'At least two non-empty options are required.';
        if (!array_filter($optClean, fn($o)=>$o['is_correct'])) $errors[] = 'Mark exactly one correct option.';

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                if ($editing) {
                    $stmt = $pdo->prepare("UPDATE questions SET text=?, marks=? WHERE id=? AND quiz_id=?");
                    $stmt->execute([$form['text'], $form['marks'], $editing['id'], $quizId]);
                    $pdo->prepare("DELETE FROM options WHERE question_id=?")->execute([$editing['id']]);
                    $thisQid = (int) $editing['id'];
                } else {
                    $pos = (int) $pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM questions WHERE quiz_id=$quizId")->fetchColumn();
                    $stmt = $pdo->prepare("INSERT INTO questions (quiz_id,text,marks,position) VALUES (?,?,?,?)");
                    $stmt->execute([$quizId, $form['text'], $form['marks'], $pos]);
                    $thisQid = (int) $pdo->lastInsertId();
                }
                $insOpt = $pdo->prepare("INSERT INTO options (question_id, text, is_correct, position) VALUES (?,?,?,?)");
                foreach ($optClean as $i => $o) {
                    $insOpt->execute([$thisQid, $o['text'], $o['is_correct'], $i+1]);
                }
                $pdo->commit();
                flash('success', $editing ? 'Question updated.' : 'Question added.');
                redirect('admin/questions.php?quiz='.$quizId);
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Save failed: ' . $e->getMessage();
            }
        }
    }

    $pageTitle = $editing ? 'Edit question' : 'New question';
    include __DIR__ . '/../includes/header.php';
    $adminActive = 'quizzes';
    include __DIR__ . '/_nav.php';
    ?>
    <div class="container">
      <a href="<?= e(base_url('admin/questions.php?quiz='.$quizId)) ?>" class="text-decoration-none small text-muted">&larr; Back to questions</a>
      <h3 class="mt-2 mb-3"><?= $editing ? 'Edit question' : 'New question' ?> · <small class="text-muted"><?= e($quiz['title']) ?></small></h3>
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <div class="card p-4">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Question</label>
            <textarea class="form-control" name="text" rows="2" data-autogrow required><?= e($form['text']) ?></textarea>
          </div>
          <div class="mb-3" style="max-width:160px">
            <label class="form-label">Marks</label>
            <input type="number" class="form-control" name="marks" min="1" max="20" value="<?= (int) $form['marks'] ?>">
          </div>
          <label class="form-label">Options (mark the correct one)</label>
          <?php for ($i = 0; $i < max(4, count($form['opts'])); $i++):
            $cur = $form['opts'][$i] ?? ['text'=>'','is_correct'=>0];
          ?>
            <div class="input-group mb-2">
              <span class="input-group-text">
                <input class="form-check-input mt-0" type="radio" name="correct" value="<?= $i ?>" <?= $form['correct']==$i?'checked':'' ?>>
              </span>
              <input class="form-control" name="opt_text[<?= $i ?>]" placeholder="Option <?= chr(65+$i) ?>" value="<?= e($cur['text']) ?>">
            </div>
          <?php endfor; ?>
          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Save question</button>
            <a class="btn btn-link" href="<?= e(base_url('admin/questions.php?quiz='.$quizId)) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; return;
}

// ---------- LIST ----------
$qs = $pdo->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY position, id");
$qs->execute([$quizId]);
$questions = $qs->fetchAll();

$pageTitle = 'Questions · ' . $quiz['title'];
include __DIR__ . '/../includes/header.php';
$adminActive = 'quizzes';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <a href="<?= e(base_url('admin/quizzes.php')) ?>" class="text-decoration-none small text-muted">&larr; Back to quizzes</a>
  <div class="d-flex justify-content-between align-items-end mt-2 mb-3">
    <div>
      <h3 class="mb-1"><?= e($quiz['title']) ?></h3>
      <p class="text-muted mb-0"><?= count($questions) ?> question(s) · <?= (int) $quiz['duration_minutes'] ?> min</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('admin/questions.php?quiz='.$quizId.'&action=new')) ?>"><i class="bi bi-plus-lg"></i> Add question</a>
  </div>

  <div class="card p-3">
    <?php if (!$questions): ?>
      <p class="text-muted text-center my-4">No questions yet. Add the first one!</p>
    <?php else: ?>
      <ol class="list-group list-group-flush">
        <?php foreach ($questions as $q):
          $opts = $pdo->prepare("SELECT text, is_correct FROM options WHERE question_id=? ORDER BY position,id");
          $opts->execute([$q['id']]);
          $oRows = $opts->fetchAll();
        ?>
          <li class="list-group-item bg-transparent py-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="flex-grow-1">
                <strong><?= e($q['text']) ?></strong>
                <span class="badge bg-light text-dark ms-2"><?= (int) $q['marks'] ?> mark</span>
                <div class="mt-2 small">
                  <?php foreach ($oRows as $o): ?>
                    <span class="badge <?= $o['is_correct']?'bg-success':'bg-secondary' ?> me-1 my-1"><?= e($o['text']) ?><?php if ($o['is_correct']): ?> <i class="bi bi-check-lg"></i><?php endif; ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/questions.php?quiz='.$quizId.'&action=edit&id='.(int)$q['id'])) ?>"><i class="bi bi-pencil"></i></a>
                <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('admin/questions.php?quiz='.$quizId.'&action=delete&id='.(int)$q['id'].'&csrf_token='.urlencode(csrf_token()))) ?>" data-confirm="Delete this question?"><i class="bi bi-trash"></i></a>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
