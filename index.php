<?php
require_once __DIR__ . '/includes/auth.php';

$current = current_user();
if ($current) {
    redirect($current['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
}

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';
?>

<section class="container">
  <div class="hero p-4 p-md-5">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="exam-tag mb-3"><i class="bi bi-stars"></i> Built for college projects</span>
        <h1 class="display-5 mt-2">Run secure, timed <span class="gradient-text">online exams</span> with zero hassle.</h1>
        <p class="lead text-muted mt-3">A modern PHP 8 + MySQL exam platform with role-based auth, instant grading, charts, leaderboards, and PDF/CSV exports. Clone, import the SQL, and you're live.</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <a class="btn btn-primary btn-lg" href="<?= e(base_url('register.php')) ?>"><i class="bi bi-person-plus"></i> Create student account</a>
          <a class="btn btn-outline-primary btn-lg" href="<?= e(base_url('login.php')) ?>"><i class="bi bi-box-arrow-in-right"></i> Sign in</a>
          <a class="btn btn-link btn-lg" href="#features">See features &rarr;</a>
        </div>
        <div class="small text-muted mt-3">
          <i class="bi bi-shield-check"></i> Bcrypt-hashed passwords &middot; CSRF protected &middot; PDO prepared statements
        </div>
      </div>
      <div class="col-lg-5 text-center">
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="exam-tag">Live preview</span>
            <small class="text-muted">04:38 remaining</small>
          </div>
          <h5 class="text-start">Which symbol is used to declare a variable in PHP?</h5>
          <label class="option-label"><input type="radio" name="demoq" disabled checked><span>$</span></label>
          <label class="option-label"><input type="radio" name="demoq" disabled><span>@</span></label>
          <label class="option-label"><input type="radio" name="demoq" disabled><span>#</span></label>
          <label class="option-label"><input type="radio" name="demoq" disabled><span>&amp;</span></label>
          <button class="btn btn-primary mt-2" disabled>Next question</button>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="container py-5" id="features">
  <div class="text-center mb-5">
    <span class="exam-tag">Features</span>
    <h2 class="mt-2 fw-bold">Everything an exam app needs &mdash; nothing it doesn't.</h2>
  </div>
  <div class="row g-4">
    <?php
    $features = [
      ['bi-person-badge','Role-based auth','Separate student and admin flows with bcrypt-hashed passwords, sessions, and CSRF protection.'],
      ['bi-stopwatch','Timed exams','Per-quiz timer with auto-submit, anti-leave warning, and full attempt history.'],
      ['bi-graph-up-arrow','Charts & analytics','Score trends, accuracy per quiz, attempts over time. Powered by Chart.js.'],
      ['bi-trophy','Leaderboard','Rank students by total marks earned — friendly competition baked in.'],
      ['bi-file-earmark-pdf','PDF / CSV export','Download a polished PDF report or raw CSV of any attempt.'],
      ['bi-pencil-square','Admin CRUD','Create quizzes, manage questions and options, view all attempts, read feedback.'],
      ['bi-shield-lock','Secure by default','PDO prepared statements, password_hash/verify, session regeneration, strict SQL mode.'],
      ['bi-moon-stars','Dark / light mode','Modern, responsive Bootstrap 5 UI that respects the user\'s theme preference.'],
    ];
    foreach ($features as [$icon,$title,$desc]):
    ?>
      <div class="col-md-6 col-lg-3">
        <div class="card h-100 p-4">
          <div class="feature-icon mb-3"><i class="bi <?= e($icon) ?>"></i></div>
          <h5 class="mb-2"><?= e($title) ?></h5>
          <p class="text-muted small mb-0"><?= e($desc) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="container py-5" id="how">
  <div class="row g-4 align-items-center">
    <div class="col-lg-5">
      <span class="exam-tag">How it works</span>
      <h2 class="fw-bold mt-2">Three steps from clone to first exam.</h2>
      <p class="text-muted">XAMPP-friendly: drop into <code>htdocs</code>, import the SQL, and you're done. No Composer, no Node, no build step.</p>
    </div>
    <div class="col-lg-7">
      <ol class="list-group list-group-numbered list-group-flush">
        <li class="list-group-item bg-transparent border-0 py-3">
          <strong>Drop in &amp; import.</strong> Place the folder in <code>htdocs/</code>, then visit <code>install.php</code> to run the schema and create your admin in one click.
        </li>
        <li class="list-group-item bg-transparent border-0 py-3">
          <strong>Create admin.</strong> The installer hashes your password with bcrypt before storing it.
        </li>
        <li class="list-group-item bg-transparent border-0 py-3">
          <strong>Add quizzes &amp; take exams.</strong> Build quizzes from the admin panel, share the link, watch the leaderboard fill up.
        </li>
      </ol>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
