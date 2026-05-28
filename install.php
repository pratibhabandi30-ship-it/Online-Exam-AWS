<?php
/**
 * One-time installer.
 *
 * Steps:
 *   1) Imports database/schema.sql
 *   2) Creates the first admin (with bcrypt-hashed password)
 *   3) Optionally creates a demo student account
 *
 * Delete this file after running it in production.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Installer';
$activeNav = '';

$step   = $_POST['step'] ?? 'check';
$errors = [];
$done   = false;

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Cannot read $path");
    }
    // Strip SQL comments first so they don't end up inside statement chunks.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;

    // Naive but adequate splitter for our own seed file (no semicolons inside string literals).
    $statements = array_map('trim', explode(';', $sql));
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($step === 'install') {
        $adminName  = clean_string($_POST['admin_name'] ?? '', 80);
        $adminEmail = strtolower(clean_string($_POST['admin_email'] ?? '', 120));
        $adminPass  = (string) ($_POST['admin_password'] ?? '');
        $createDemo = !empty($_POST['create_demo']);

        if ($adminName === '')                  $errors[] = 'Admin name is required.';
        if (!validate_email($adminEmail))       $errors[] = 'Admin email is invalid.';
        if (strlen($adminPass) < 6)             $errors[] = 'Password must be at least 6 characters.';

        if (!$errors) {
            try {
                // Connect to MySQL without a specific DB so we can CREATE DATABASE.
                $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
                $pdoBoot = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                run_sql_file($pdoBoot, __DIR__ . '/database/schema.sql');

                // Now reconnect targeting the DB and seed the admin/demo.
                $pdo = db();

                $hash = password_hash($adminPass, PASSWORD_ALGO);
                $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_active,gender,college) VALUES (?,?,?,'admin',1,'O','OES HQ')
                                       ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash), role='admin', is_active=1");
                $stmt->execute([$adminName, $adminEmail, $hash]);

                if ($createDemo) {
                    $demoHash = password_hash('student@123', PASSWORD_ALGO);
                    $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_active,gender,college,mobile) VALUES (?,?,?,'student',1,?,?,?)
                                           ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash)");
                    $stmt->execute(['Demo Student', 'student@oes.local', $demoHash, 'M', 'Demo College', '9876543210']);
                }

                $done = true;
            } catch (Throwable $e) {
                $errors[] = 'Install failed: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card p-4 p-md-5">
        <h2 class="mb-1"><i class="bi bi-tools text-gradient"></i> One-time installer</h2>
        <p class="text-muted">Sets up the database schema and creates your first admin account.</p>

        <?php if ($done): ?>
          <div class="alert alert-success mt-3">
            <h5 class="alert-heading"><i class="bi bi-check2-circle"></i> Setup complete!</h5>
            <p class="mb-1">You can now sign in:</p>
            <ul class="mb-2">
              <li>Admin: <a href="<?= e(base_url('admin/login.php')) ?>">/admin/login.php</a></li>
              <li>Student: <a href="<?= e(base_url('login.php')) ?>">/login.php</a></li>
            </ul>
            <hr>
            <p class="mb-0"><strong>Important:</strong> delete <code>install.php</code> from the server now.</p>
          </div>
        <?php else: ?>
          <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></div>
          <?php endforeach; ?>

          <div class="alert alert-info small">
            <strong>Before continuing:</strong> make sure your MySQL credentials in <code>config/config.php</code> are correct
            (defaults: host <code>127.0.0.1</code>, user <code>root</code>, no password).
          </div>

          <form method="post" autocomplete="off" class="mt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="install">

            <div class="mb-3">
              <label class="form-label">Admin name</label>
              <input class="form-control" name="admin_name" required value="<?= e($_POST['admin_name'] ?? 'Site Admin') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Admin email</label>
              <input class="form-control" type="email" name="admin_email" required value="<?= e($_POST['admin_email'] ?? 'admin@oes.local') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Admin password</label>
              <input class="form-control" type="password" name="admin_password" required minlength="6" placeholder="At least 6 characters">
            </div>
            <div class="form-check mb-4">
              <input class="form-check-input" type="checkbox" name="create_demo" id="demoChk" checked>
              <label class="form-check-label" for="demoChk">
                Also create a demo student (<code>student@oes.local</code> / <code>student@123</code>)
              </label>
            </div>
            <button class="btn btn-primary btn-lg w-100">
              <i class="bi bi-rocket-takeoff"></i> Run install
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
