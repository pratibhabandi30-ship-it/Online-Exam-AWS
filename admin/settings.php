<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $passwordQuery = db()->prepare('SELECT password_hash FROM users WHERE id = ? AND role = ? AND is_active = 1');
    $passwordQuery->execute([(int) $admin['id'], 'admin']);
    $passwordHash = $passwordQuery->fetchColumn();

    if (!is_string($passwordHash) || !verify_account_password($currentPassword, $passwordHash)) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (!account_password_is_valid($newPassword)) {
        $errors[] = account_password_policy_message();
    }
    if (!hash_equals($newPassword, $confirmPassword)) {
        $errors[] = 'The new password and confirmation do not match.';
    }

    if (!$errors) {
        $updatePassword = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND role = ?');
        $updatePassword->execute([hash_account_password($newPassword), (int) $admin['id'], 'admin']);
        if ($updatePassword->rowCount() !== 1) {
            throw new RuntimeException('HOD password update did not affect the expected account.');
        }
        flash('success', 'Your password has been updated.');
        redirect('admin/settings.php');
    }
}

$pageTitle = 'Admin · Settings';
include __DIR__ . '/../includes/header.php';
$adminActive = 'settings';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="mb-4"><h2 class="mb-1">Settings</h2><p class="text-muted mb-0">Manage your HOD account security and dashboard preferences.</p></div>
  <div class="row g-3">
    <div class="col-xl-7">
      <section class="card p-4">
        <div class="d-flex align-items-center gap-3 mb-3"><div class="admin-stat-icon olive"><i class="bi bi-shield-lock"></i></div><div><h5 class="mb-1">Change password</h5><p class="small text-muted mb-0">Use at least 8 characters, including a number and a special character.</p></div></div>
        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label" for="currentPassword">Current password</label><input class="form-control" id="currentPassword" type="password" name="current_password" autocomplete="current-password" required></div>
          <div class="mb-3"><label class="form-label" for="newPassword">New password</label><input class="form-control" id="newPassword" type="password" name="new_password" minlength="8" data-password-policy autocomplete="new-password" required><div class="form-text">At least 8 characters, including a number and one of @ # $ % &amp; * !. No maximum length.</div></div>
          <div class="mb-3"><label class="form-label" for="confirmPassword">Confirm new password</label><input class="form-control" id="confirmPassword" type="password" name="confirm_password" minlength="8" data-password-confirm="#newPassword" autocomplete="new-password" required></div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Update password</button>
        </form>
      </section>
    </div>
    <div class="col-xl-5">
      <section class="card p-4">
        <div class="d-flex align-items-center gap-3 mb-3"><div class="admin-stat-icon olive"><i class="bi bi-sliders"></i></div><div><h5 class="mb-1">Dashboard preferences</h5><p class="small text-muted mb-0">Saved in this browser.</p></div></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="compactSidebar"><label class="form-check-label" for="compactSidebar">Use compact, icons-only sidebar</label></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="darkDashboard"><label class="form-check-label" for="darkDashboard">Use dark dashboard theme</label></div>
        <p class="small text-muted mb-0">Your layout and theme preferences are applied across HOD pages on this device.</p>
      </section>
      <section class="card p-4 mt-3">
        <h5 class="mb-1">Signed in as</h5>
        <div class="fw-semibold"><?= e($admin['name']) ?></div>
        <div class="small text-muted"><?= e($admin['email']) ?></div>
        <span class="badge admin-score-badge align-self-start mt-2">HOD · Super Admin</span>
      </section>
    </div>
  </div>
</div>
<script>
window.addEventListener('DOMContentLoaded', () => {
  const compact = document.getElementById('compactSidebar');
  const dark = document.getElementById('darkDashboard');
  compact.checked = localStorage.getItem('oes-admin-sidebar') === 'collapsed';
  dark.checked = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  compact.addEventListener('change', () => {
    const collapsed = compact.checked;
    localStorage.setItem('oes-admin-sidebar', collapsed ? 'collapsed' : 'expanded');
    document.body.classList.toggle('admin-sidebar-collapsed', collapsed && !window.matchMedia('(max-width: 991.98px)').matches);
  });
  dark.addEventListener('change', () => {
    const theme = dark.checked ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('oes-theme', theme);
  });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
