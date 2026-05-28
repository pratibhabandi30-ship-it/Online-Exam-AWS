<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin  = require_admin();
$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int) ($_GET['id'] ?? 0);

if ($action === 'toggle' && $id > 0) {
    verify_csrf();
    if ($id !== (int) $admin['id']) {
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='student'")->execute([$id]);
        flash('success','Status toggled.');
    } else {
        flash('error','You cannot disable yourself.');
    }
    redirect('admin/users.php');
}
if ($action === 'delete' && $id > 0) {
    verify_csrf();
    if ($id !== (int) $admin['id']) {
        $pdo->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$id]);
        flash('success','User deleted.');
    }
    redirect('admin/users.php');
}

$search = clean_string($_GET['q'] ?? '', 80);
$sql = "SELECT u.*, (SELECT COUNT(*) FROM attempts WHERE user_id=u.id) AS attempts FROM users u WHERE u.role='student'";
$args = [];
if ($search !== '') { $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.college LIKE ?)"; $args = ["%$search%","%$search%","%$search%"]; }
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($args); $rows = $stmt->fetchAll();

$pageTitle = 'Admin · Users';
include __DIR__ . '/../includes/header.php';
$adminActive = 'users';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <h3 class="mb-0">Students</h3>
    <form class="d-flex gap-2"><input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name/email/college"><button class="btn btn-primary"><i class="bi bi-search"></i></button></form>
  </div>
  <div class="card p-3">
    <?php if (!$rows): ?>
      <p class="text-muted text-center my-4">No students yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Name</th><th>Email</th><th>College</th><th class="text-center">Attempts</th><th class="text-center">Status</th><th>Joined</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $u): ?>
            <tr>
              <td><?= e($u['name']) ?></td>
              <td class="small"><?= e($u['email']) ?></td>
              <td class="small text-muted"><?= e($u['college'] ?: '—') ?></td>
              <td class="text-center"><?= (int) $u['attempts'] ?></td>
              <td class="text-center"><?php if ($u['is_active']): ?><span class="badge bg-success">Active</span><?php else: ?><span class="badge bg-secondary">Disabled</span><?php endif; ?></td>
              <td class="small text-muted"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/users.php?action=toggle&id='.(int)$u['id'].'&csrf_token='.urlencode(csrf_token()))) ?>" data-confirm="Toggle active status?"><i class="bi bi-power"></i></a>
                <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('admin/users.php?action=delete&id='.(int)$u['id'].'&csrf_token='.urlencode(csrf_token()))) ?>" data-confirm="Delete this student and all their attempts?"><i class="bi bi-trash"></i></a>
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
