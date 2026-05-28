<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo   = db();
$action = $_GET['action'] ?? 'list';
$id     = (int) ($_GET['id'] ?? 0);

if ($action === 'mark' && $id > 0) {
    verify_csrf();
    $pdo->prepare("UPDATE feedback SET is_read = 1 - is_read WHERE id=?")->execute([$id]);
    redirect('admin/feedback.php');
}
if ($action === 'delete' && $id > 0) {
    verify_csrf();
    $pdo->prepare("DELETE FROM feedback WHERE id=?")->execute([$id]);
    flash('success','Feedback deleted.');
    redirect('admin/feedback.php');
}

$rows = $pdo->query("SELECT * FROM feedback ORDER BY is_read ASC, created_at DESC")->fetchAll();

$pageTitle = 'Admin · Feedback';
include __DIR__ . '/../includes/header.php';
$adminActive = 'feedback';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <h3 class="mb-3">Feedback</h3>
  <?php if (!$rows): ?>
    <div class="card p-5 text-center text-muted">No feedback yet.</div>
  <?php else: ?>
    <div class="vstack gap-3">
      <?php foreach ($rows as $f): ?>
        <div class="card p-3 <?= $f['is_read']?'':'border-start border-primary border-3' ?>">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <h6 class="mb-1"><?= e($f['subject']) ?> <?php if (!$f['is_read']): ?><span class="badge bg-primary">New</span><?php endif; ?></h6>
              <small class="text-muted"><?= e($f['name']) ?> &middot; <?= e($f['email']) ?> &middot; <?= e(date('d M Y, H:i', strtotime($f['created_at']))) ?></small>
            </div>
            <div class="text-warning small">
              <?php for ($i=0;$i<(int)$f['rating'];$i++): ?>★<?php endfor; ?>
              <?php for ($i=(int)$f['rating'];$i<5;$i++): ?><span class="text-muted">☆</span><?php endfor; ?>
            </div>
          </div>
          <p class="mt-2 mb-2" style="white-space:pre-wrap"><?= e($f['message']) ?></p>
          <div class="text-end small">
            <a class="text-decoration-none" href="<?= e(base_url('admin/feedback.php?action=mark&id='.(int)$f['id'].'&csrf_token='.urlencode(csrf_token()))) ?>"><?= $f['is_read']?'Mark unread':'Mark read' ?></a>
            &middot;
            <a class="text-decoration-none text-danger" href="<?= e(base_url('admin/feedback.php?action=delete&id='.(int)$f['id'].'&csrf_token='.urlencode(csrf_token()))) ?>" data-confirm="Delete this feedback?">Delete</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
