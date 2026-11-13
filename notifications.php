<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')
            ->execute([(int) $me['id']]);
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'read_one') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$id, (int) $me['id']]);
    } else {
        http_response_code(400);
        die('Unknown notification action.');
    }
    redirect('notifications.php');
}

$stmt = $pdo->prepare(
    'SELECT id, type, title, message, link, read_at, created_at
     FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100'
);
$stmt->execute([(int) $me['id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
$facultyShellEnabled = ($me['role'] ?? '') === 'faculty';
include __DIR__ . '/includes/header.php';
if ($me['role'] === 'admin') {
    $adminActive = 'notifications';
    include __DIR__ . '/admin/_nav.php';
} elseif ($me['role'] === 'faculty') {
    $facultyShellEnabled = true;
    $facultyActive = 'notifications';
    include __DIR__ . '/faculty/_nav.php';
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><h2 class="mb-1">Notifications</h2><p class="text-muted mb-0">Exam requests, publication updates, and results.</p></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-outline-secondary" type="submit">Mark all read</button></form>
  </div>
  <div class="vstack gap-2">
    <?php foreach ($notifications as $notification): ?>
      <article class="card p-3 <?= $notification['read_at'] ? '' : 'border-primary' ?>">
        <div class="d-flex justify-content-between gap-3">
          <div>
            <div class="fw-semibold"><?= e($notification['title']) ?> <?php if (!$notification['read_at']): ?><span class="badge bg-primary">New</span><?php endif; ?></div>
            <p class="mb-1 mt-1"><?= e($notification['message']) ?></p>
            <small class="text-muted"><?= e(date('d M Y, H:i', strtotime($notification['created_at']))) ?></small>
          </div>
          <div class="text-end flex-shrink-0">
            <?php if ($notification['link'] && preg_match('~\A[a-zA-Z0-9._/-]+(?:\?[a-zA-Z0-9_=&%.-]*)?\z~', $notification['link'])): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url($notification['link'])) ?>">Open</a>
            <?php endif; ?>
            <?php if (!$notification['read_at']): ?>
              <form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="read_one"><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><button class="btn btn-sm btn-link" type="submit">Mark read</button></form>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$notifications): ?><div class="card p-5 text-center text-muted">You have no notifications.</div><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
