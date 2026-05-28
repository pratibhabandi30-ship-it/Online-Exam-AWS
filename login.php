<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current = current_user();
if ($current) {
    redirect($current['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = strtolower(clean_string($_POST['email'] ?? '', 120));
    $password = (string) ($_POST['password'] ?? '');

    if (!validate_email($email)) $errors[] = 'Please enter a valid email address.';
    if ($password === '')        $errors[] = 'Password is required.';

    if (!$errors) {
        $stmt = db()->prepare("SELECT id, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || $u['role'] !== 'student' || !password_verify($password, $u['password_hash'])) {
            $errors[] = 'Incorrect email or password.';
        } elseif (!$u['is_active']) {
            $errors[] = 'Your account is disabled. Contact the admin.';
        } else {
            login_user((int) $u['id']);
            // Optional rehash
            if (password_needs_rehash($u['password_hash'], PASSWORD_ALGO)) {
                $rh = password_hash($password, PASSWORD_ALGO);
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$rh, $u['id']]);
            }
            flash('success', 'Welcome back!');
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Sign in';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card p-4 p-md-5">
        <h3 class="mb-1">Welcome back</h3>
        <p class="text-muted">Sign in to continue your exam journey.</p>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="on">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required autofocus value="<?= e($email) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label d-flex justify-content-between">
              <span>Password</span>
            </label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100 btn-lg" type="submit">
            <i class="bi bi-box-arrow-in-right"></i> Sign in
          </button>
          <div class="text-center mt-3 small text-muted">
            New here? <a href="<?= e(base_url('register.php')) ?>">Create a student account</a>
            &middot;
            <a href="<?= e(base_url('admin/login.php')) ?>">Admin login</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
