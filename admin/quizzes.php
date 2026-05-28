<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo   = db();

$action = $_GET['action'] ?? 'list';
$id     = (int) ($_GET['id'] ?? 0);
$errors = [];

// ---------- CREATE / EDIT ----------
if ($action === 'new' || $action === 'edit') {
    $editing = null;
    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $editing = $stmt->fetch();
        if (!$editing) { flash('error','Quiz not found.'); redirect('admin/quizzes.php'); }
    }

    $vals = $editing ?: [
        'title' => '', 'tag' => '', 'intro' => '',
        'duration_minutes' => 5, 'passing_percent' => 50, 'is_published' => 1,
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $vals['title']            = clean_string($_POST['title'] ?? '', 150);
        $vals['tag']              = clean_string($_POST['tag']   ?? '', 60);
        $vals['intro']            = clean_string($_POST['intro'] ?? '', 1000);
        $vals['duration_minutes'] = max(1, (int) ($_POST['duration_minutes'] ?? 5));
        $vals['passing_percent']  = max(0, min(100, (int) ($_POST['passing_percent'] ?? 50)));
        $vals['is_published']     = isset($_POST['is_published']) ? 1 : 0;

        if (mb_strlen($vals['title']) < 3) $errors[] = 'Title is too short.';

        if (!$errors) {
            if ($editing) {
                $stmt = $pdo->prepare("UPDATE quizzes SET title=?, tag=?, intro=?, duration_minutes=?, passing_percent=?, is_published=? WHERE id=?");
                $stmt->execute([$vals['title'], $vals['tag'] ?: null, $vals['intro'] ?: null, $vals['duration_minutes'], $vals['passing_percent'], $vals['is_published'], $editing['id']]);
                flash('success','Quiz updated.');
                redirect('admin/quizzes.php');
            } else {
                $stmt = $pdo->prepare("INSERT INTO quizzes (title, tag, intro, duration_minutes, passing_percent, is_published, created_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$vals['title'], $vals['tag'] ?: null, $vals['intro'] ?: null, $vals['duration_minutes'], $vals['passing_percent'], $vals['is_published'], $admin['id']]);
                $newId = (int) $pdo->lastInsertId();
                flash('success','Quiz created. Add questions now.');
                redirect('admin/questions.php?quiz=' . $newId);
            }
        }
    }

    $pageTitle = $editing ? 'Edit quiz' : 'New quiz';
    include __DIR__ . '/../includes/header.php';
    $adminActive = 'quizzes';
    include __DIR__ . '/_nav.php';
    ?>
    <div class="container">
      <h3 class="mb-3"><?= $editing ? 'Edit quiz' : 'New quiz' ?></h3>
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <div class="card p-4">
        <form method="post">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Title</label>
              <input class="form-control" name="title" required value="<?= e($vals['title']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Tag</label>
              <input class="form-control" name="tag" value="<?= e($vals['tag']) ?>" placeholder="PHP, JavaScript…">
            </div>
            <div class="col-12">
              <label class="form-label">Intro</label>
              <textarea class="form-control" name="intro" rows="2" data-autogrow><?= e($vals['intro']) ?></textarea>
            </div>
            <div class="col-md-3">
              <label class="form-label">Duration (minutes)</label>
              <input type="number" class="form-control" name="duration_minutes" min="1" value="<?= (int) $vals['duration_minutes'] ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Passing %</label>
              <input type="number" class="form-control" name="passing_percent" min="0" max="100" value="<?= (int) $vals['passing_percent'] ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_published" id="pubChk" <?= !empty($vals['is_published'])?'checked':'' ?>>
                <label class="form-check-label" for="pubChk">Published</label>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
            <a class="btn btn-link" href="<?= e(base_url('admin/quizzes.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; return;
}

// ---------- DELETE ----------
if ($action === 'delete' && $id > 0) {
    verify_csrf();
    $pdo->prepare("DELETE FROM quizzes WHERE id=?")->execute([$id]);
    flash('success','Quiz deleted.');
    redirect('admin/quizzes.php');
}

// ---------- LIST ----------
$rows = $pdo->query("
    SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) AS qcount,
           (SELECT COUNT(*) FROM attempts  WHERE quiz_id=q.id) AS acount
    FROM quizzes q
    ORDER BY q.created_at DESC
")->fetchAll();

$pageTitle = 'Admin · Quizzes';
include __DIR__ . '/../includes/header.php';
$adminActive = 'quizzes';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Quizzes</h3>
    <a class="btn btn-primary" href="<?= e(base_url('admin/quizzes.php?action=new')) ?>"><i class="bi bi-plus-lg"></i> New quiz</a>
  </div>
  <div class="card p-3">
    <?php if (!$rows): ?>
      <p class="text-muted text-center my-4">No quizzes yet. Create your first one!</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Title</th><th>Tag</th><th class="text-center">Qs</th><th class="text-center">Attempts</th><th class="text-center">Duration</th><th class="text-center">Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['title']) ?></strong><br><small class="text-muted"><?= e($r['intro'] ?: '—') ?></small></td>
                <td><span class="exam-tag"><?= e($r['tag'] ?: '—') ?></span></td>
                <td class="text-center"><?= (int) $r['qcount'] ?></td>
                <td class="text-center"><?= (int) $r['acount'] ?></td>
                <td class="text-center"><?= (int) $r['duration_minutes'] ?>m</td>
                <td class="text-center">
                  <?php if ($r['is_published']): ?>
                    <span class="badge bg-success">Published</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Draft</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/questions.php?quiz='.(int)$r['id'])) ?>"><i class="bi bi-list-check"></i></a>
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/quizzes.php?action=edit&id='.(int)$r['id'])) ?>"><i class="bi bi-pencil"></i></a>
                  <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('admin/quizzes.php?action=delete&id='.(int)$r['id'].'&csrf_token='.urlencode(csrf_token()))) ?>" data-confirm="Delete this quiz and all of its questions/attempts? This cannot be undone."><i class="bi bi-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
