<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me     = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = clean_string($_POST['name'] ?? '', 80);
        $college = clean_string($_POST['college'] ?? '', 120);
        $mobile  = clean_string($_POST['mobile'] ?? '', 20);
        $gender  = in_array($_POST['gender'] ?? '', ['M','F','O'], true) ? $_POST['gender'] : $me['gender'];

        if (mb_strlen($name) < 2) $errors[] = 'Name must be at least 2 characters.';
        if (!$errors) {
            $stmt = db()->prepare('UPDATE users SET name=?, college=?, mobile=?, gender=? WHERE id=?');
            $stmt->execute([$name, $college ?: null, $mobile ?: null, $gender, $me['id']]);
            flash('success', 'Profile updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $next    = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$me['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) $errors[] = 'Current password is incorrect.';
        if (strlen($next) < 6)        $errors[] = 'New password must be at least 6 characters.';
        if ($next !== $confirm)       $errors[] = 'New passwords do not match.';

        if (!$errors) {
            $hash = password_hash($next, PASSWORD_ALGO);
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $me['id']]);
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'Profile';
$activeNav = 'profile';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex align-items-center mb-4">
    <div class="me-3" style="width:56px;height:56px;border-radius:50%;background:rgba(79,70,229,.1);display:grid;place-items:center;font-size:1.5rem;color:var(--oes-primary);font-weight:700">
      <?= e(mb_strtoupper(mb_substr($me['name'],0,1))) ?>
    </div>
    <div>
      <h3 class="mb-0"><?= e($me['name']) ?></h3>
      <small class="text-muted">Member since <?= e(date('M Y', strtotime($me['created_at']))) ?></small>
    </div>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card p-4">
        <h5 class="mb-3"><i class="bi bi-person"></i> Personal details</h5>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full name</label>
              <input class="form-control" name="name" required value="<?= e($me['name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <small class="text-muted">(read-only)</small></label>
              <input class="form-control" value="<?= e($me['email']) ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label">College</label>
              <input class="form-control" name="college" value="<?= e($me['college']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile</label>
              <input class="form-control" name="mobile" value="<?= e($me['mobile']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Gender</label>
              <select class="form-select" name="gender">
                <option value="M" <?= $me['gender']==='M'?'selected':'' ?>>Male</option>
                <option value="F" <?= $me['gender']==='F'?'selected':'' ?>>Female</option>
                <option value="O" <?= $me['gender']==='O'?'selected':'' ?>>Other</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary mt-4"><i class="bi bi-save"></i> Save changes</button>
        </form>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card p-4">
        <h5 class="mb-3"><i class="bi bi-lock"></i> Change password</h5>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label">Current password</label>
            <input type="password" class="form-control" name="current_password" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New password</label>
            <input type="password" class="form-control" name="new_password" required minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm new password</label>
            <input type="password" class="form-control" name="confirm_password" required minlength="6">
          </div>
          <button class="btn btn-outline-primary"><i class="bi bi-shield-check"></i> Update password</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
