<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = db();
$stmt = $pdo->query(
    'SELECT log.id, log.action, log.entity_type, log.entity_id, log.details, log.created_at, u.name AS actor_name, u.email AS actor_email
     FROM audit_logs log LEFT JOIN users u ON u.id = log.actor_id
     ORDER BY log.created_at DESC, log.id DESC LIMIT 500'
);
$logs = $stmt->fetchAll();

$pageTitle = 'HOD · Activity log';
include __DIR__ . '/../includes/header.php';
$adminActive = 'activity';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <h3 class="mb-1">Department activity</h3>
  <p class="text-muted">Recent account, assignment, approval, and publication actions.</p>
  <div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead>
    <tbody><?php foreach ($logs as $log): ?><tr>
      <td class="small text-muted"><?= e(date('d M Y, H:i:s', strtotime($log['created_at']))) ?></td>
      <td><?= e($log['actor_name'] ?: 'Deleted account') ?><div class="small text-muted"><?= e($log['actor_email'] ?: '') ?></div></td>
      <td><code><?= e($log['action']) ?></code></td>
      <td><?= e($log['entity_type']) ?> #<?= e($log['entity_id'] ?: '—') ?></td>
      <td><?= e($log['details'] ?: '—') ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div><?php if (!$logs): ?><p class="text-center text-muted my-4">No activity logged yet.</p><?php endif; ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
