<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current = current_user();
if ($current) {
    redirect(match ($current['role']) {
        'admin' => 'admin/dashboard.php',
        'faculty' => 'faculty/dashboard.php',
        default => 'dashboard.php',
    });
}

$errors = [];
$branches = ['MCA', 'MBA', 'CSE', 'AIML', 'IS', 'EC', 'MECH', 'CIVIL'];
$old = [
    'name' => '',
    'email' => '',
    'college' => '',
    'mobile' => '',
    'gender' => 'M',
    'registration_no' => '',
    'branch' => '',
    'semester' => '',
    'academic_year' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (['name' => 80, 'email' => 120, 'college' => 120, 'mobile' => 20, 'registration_no' => 60, 'branch' => 20, 'academic_year' => 20] as $field => $maxLength) {
        $value = $_POST[$field] ?? '';
        $old[$field] = clean_string(is_string($value) ? $value : '', $maxLength);
    }
    $old['email'] = strtolower($old['email']);
    $old['gender'] = in_array($_POST['gender'] ?? '', ['M','F','O'], true) ? $_POST['gender'] : 'O';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
    $semesterInput = is_string($_POST['semester'] ?? null) ? $_POST['semester'] : '';
    $semester = filter_var($semesterInput, FILTER_VALIDATE_INT);

    if (mb_strlen($old['name']) < 2) $errors[] = 'Name must be at least 2 characters.';
    if (!validate_email($old['email'])) $errors[] = 'Please enter a valid email.';
    if (!account_password_is_valid($password)) $errors[] = account_password_policy_message();
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!preg_match('/^[0-9]{6,20}$/', $old['mobile'])) {
        $errors[] = 'Enter a valid mobile number using 6–20 digits.';
    }
    if ($old['registration_no'] === '') {
        $errors[] = 'Registration number is required.';
    }
    if ($old['college'] === '') {
        $errors[] = 'College name is required.';
    }
    if (!in_array($old['branch'], $branches, true)) {
        $errors[] = 'Choose a valid branch.';
    }
    if ($semester === false || $semester < 1 || $semester > 12) {
        $errors[] = 'Choose a semester or year from 1 to 12.';
    } else {
        $old['semester'] = (string) $semester;
    }
    if ($old['academic_year'] === '') {
        $errors[] = 'Academic year is required.';
    }

    if (!$errors) {
        $pdo = db();
        try {
            $emailCheck = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
            $emailCheck->execute([$old['email']]);
            if ($emailCheck->fetchColumn()) {
                throw new InvalidArgumentException('An account with that email already exists.');
            }
            $registrationCheck = $pdo->prepare(
                'SELECT 1 FROM student_profiles WHERE registration_no = ? LIMIT 1'
            );
            $registrationCheck->execute([$old['registration_no']]);
            if ($registrationCheck->fetchColumn()) {
                throw new InvalidArgumentException('That registration number is already in use.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO users (name,email,password_hash,gender,college,mobile,role,is_active)
                 VALUES (?,?,?,?,?,?, 'student', 1)"
            );
            $stmt->execute([
                $old['name'],
                $old['email'],
                hash_account_password($password),
                $old['gender'],
                $old['college'],
                $old['mobile'],
            ]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO student_profiles (user_id, registration_no, branch, semester, academic_year)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $newId,
                $old['registration_no'],
                $old['branch'],
                $semester,
                $old['academic_year'],
            ]);
            $pdo->commit();
            login_user($newId);
            flash('success', 'Account created — welcome aboard!');
            redirect('dashboard.php');
        } catch (InvalidArgumentException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $error->getMessage();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student registration failed: ' . $error->getMessage());
            $errors[] = $error->getCode() === '23000'
                ? 'The email address or registration number is already in use.'
                : 'Your account could not be created. Please try again.';
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student registration failed: ' . $error->getMessage());
            $errors[] = 'Your account could not be created. Please try again.';
        }
    }
}

$pageTitle = 'Create account';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
      <div class="card p-4 p-md-5">
        <h3 class="mb-1">Create a student account</h3>
        <p class="text-muted">Takes 30 seconds. No verification email required for the demo build.</p>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="on">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full name</label>
              <input type="text" name="name" class="form-control" required minlength="2" value="<?= e($old['name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required value="<?= e($old['email']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">College</label>
              <input type="text" name="college" class="form-control" required maxlength="120" value="<?= e($old['college']) ?>" placeholder="e.g. IIT Bombay">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile</label>
              <input type="tel" name="mobile" class="form-control" required minlength="6" maxlength="20" pattern="[0-9]{6,20}" inputmode="numeric" value="<?= e($old['mobile']) ?>" autocomplete="tel">
            </div>
            <div class="col-md-4">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-select">
                <option value="M" <?= $old['gender']==='M'?'selected':'' ?>>Male</option>
                <option value="F" <?= $old['gender']==='F'?'selected':'' ?>>Female</option>
                <option value="O" <?= $old['gender']==='O'?'selected':'' ?>>Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Registration number</label>
              <input type="text" name="registration_no" class="form-control" required maxlength="60" value="<?= e($old['registration_no']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <select name="branch" class="form-select" required>
                <option value="">Select branch</option>
                <?php foreach ($branches as $branch): ?><option value="<?= e($branch) ?>" <?= $old['branch'] === $branch ? 'selected' : '' ?>><?= e($branch) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Semester / Year</label>
              <select name="semester" class="form-select" required>
                <option value="">Select semester / year</option>
                <?php for ($term = 1; $term <= 12; $term++): ?><option value="<?= $term ?>" <?= $old['semester'] === (string) $term ? 'selected' : '' ?>><?= $term ?></option><?php endfor; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Academic year</label>
              <input type="text" name="academic_year" class="form-control" required maxlength="20" placeholder="e.g. 2026-27" value="<?= e($old['academic_year']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Password</label>
              <input type="password" id="registerPassword" name="password" class="form-control" required minlength="8" data-password-policy autocomplete="new-password">
              <div class="form-text">At least 8 characters, including a number and one of @ # $ % &amp; * !. No maximum length.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Confirm password</label>
              <input type="password" name="confirm" class="form-control" required minlength="8" data-password-confirm="#registerPassword" autocomplete="new-password">
            </div>
          </div>
          <button class="btn btn-primary w-100 btn-lg mt-4" type="submit">
            <i class="bi bi-person-plus"></i> Create account
          </button>
          <div class="text-center mt-3 small text-muted">
            Already have an account? <a href="<?= e(base_url('login.php')) ?>">Sign in</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
