<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$student = require_student();
$pdo = db();
$announcementId = filter_var($_GET['announcement'] ?? 0, FILTER_VALIDATE_INT);
if ($announcementId !== false && $announcementId > 0) {
    $pdo->prepare(
        "UPDATE notifications SET read_at = NOW()
         WHERE user_id = ? AND type = 'faculty_announcement' AND link = ? AND read_at IS NULL"
    )->execute([(int) $student['id'], 'announcements.php?announcement=' . $announcementId]);
}
$query = db()->prepare(
    'SELECT a.id, a.title, a.body, a.category, a.created_at, u.name AS faculty_name
     FROM faculty_announcements a
     JOIN faculty_student_assignments fsa ON fsa.faculty_id = a.faculty_id AND fsa.student_id = ?
     JOIN users u ON u.id = a.faculty_id
     ORDER BY a.created_at DESC LIMIT 100'
);
$query->execute([(int) $student['id']]);
$announcements = $query->fetchAll();

$pageTitle = 'My Announcements';
$activeNav = 'announcements';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h2 class="mb-1">Announcements</h2>
  <p class="text-muted">Messages from your assigned faculty.</p>
  <div class="vstack gap-3">
    <?php foreach ($announcements as $announcement): ?>
      <article class="card p-4" id="announcement-<?= (int) $announcement['id'] ?>">
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <div><span class="badge bg-primary mb-2"><?= e(str_replace('_', ' ', ucfirst($announcement['category']))) ?></span><h5 class="mb-1"><?= e($announcement['title']) ?></h5></div>
          <small class="text-muted"><?= e(date('d M Y, H:i', strtotime($announcement['created_at']))) ?></small>
        </div>
        <p class="mb-2"><?= nl2br(e($announcement['body'])) ?></p>
        <small class="text-muted">From <?= e($announcement['faculty_name']) ?></small>
      </article>
    <?php endforeach; ?>
    <?php if (!$announcements): ?><div class="card p-5 text-center text-muted">No announcements from your assigned faculty.</div><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
