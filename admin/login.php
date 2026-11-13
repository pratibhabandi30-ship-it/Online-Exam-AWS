<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$current = current_user();
if ($current) {
    redirect(match ($current['role']) {
        'admin' => 'admin/dashboard.php',
        'faculty' => 'faculty/dashboard.php',
        default => 'dashboard.php',
    });
}

$errors = [];
$email  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = strtolower(clean_string($_POST['email'] ?? '', 120));
    $password = (string) ($_POST['password'] ?? '');
    if (!validate_email($email)) $errors[] = 'Invalid email.';
    if ($password === '')        $errors[] = 'Password required.';

    if (!$errors) {
        $stmt = db()->prepare("SELECT id, password_hash, role, is_active FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || $u['role'] !== 'admin' || !verify_account_password($password, $u['password_hash'])) {
            $errors[] = 'Invalid admin credentials.';
        } elseif (!(int) $u['is_active']) {
            $errors[] = 'This account is disabled.';
        } else {
            login_user((int) $u['id']);
            if (account_password_needs_rehash($u['password_hash'])) {
                $rehash = hash_account_password($password);
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$rehash, $u['id']]);
            }
            flash('success', 'Welcome back, admin.');
            redirect('admin/dashboard.php');
        }
    }
}

$pageTitle = 'HOD sign in';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card p-4 p-md-5">
        <div class="text-center mb-3">
          <div class="feature-icon mx-auto mb-2" style="width:56px;height:56px"><i class="bi bi-shield-lock"></i></div>
          <h4 class="mb-0">HOD sign in</h4>
          <small class="text-muted">Restricted access</small>
        </div>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required autofocus value="<?= e($email) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required>
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
          <div class="text-center mt-3 small">
            <a href="<?= e(base_url('login.php')) ?>">Faculty / student login &rarr;</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
