<?php
require_once __DIR__ . '/auth.php';
$current = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
?><!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="<?= e(APP_TAGLINE) ?>">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='80' font-size='80'>🎓</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(base_url('assets/css/style.css?v=20261030-courses')) ?>" rel="stylesheet">
</head>
<body class="<?= $activeNav === 'home' ? 'home-page' : '' ?>">
<nav class="navbar navbar-expand-lg sticky-top oes-navbar">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= e(base_url('index.php')) ?>">
      <i class="bi bi-mortarboard-fill"></i> <?= e(APP_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php if ($current && $current['role'] === 'student'): ?>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='dashboard'?'active':'' ?>" href="<?= e(base_url('dashboard.php')) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='exams'?'active':'' ?>" href="<?= e(base_url('exams.php')) ?>"><i class="bi bi-pencil-square"></i> Exams</a></li>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='history'?'active':'' ?>" href="<?= e(base_url('history.php')) ?>"><i class="bi bi-clock-history"></i> History</a></li>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='certificates'?'active':'' ?>" href="<?= e(base_url('certificates.php')) ?>"><i class="bi bi-award"></i> My Certificates</a></li>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='leaderboard'?'active':'' ?>" href="<?= e(base_url('leaderboard.php')) ?>"><i class="bi bi-trophy"></i> Leaderboard</a></li>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='feedback'?'active':'' ?>" href="<?= e(base_url('feedback.php')) ?>"><i class="bi bi-chat-dots"></i> Feedback</a></li>
        <?php elseif ($current && $current['role'] === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url('admin/dashboard.php')) ?>"><i class="bi bi-shield-lock"></i> Admin Panel</a></li>
        <?php else: ?>
          <?php if ($activeNav === 'home'): ?>
            <li class="nav-item"><a class="nav-link" href="#features">Platform</a></li>
            <li class="nav-item"><a class="nav-link" href="#courses">Courses</a></li>
            <li class="nav-item"><a class="nav-link" href="#how">How it works</a></li>
            <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="<?= e(base_url('index.php#features')) ?>">Features</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= e(base_url('index.php#how')) ?>">How it works</a></li>
          <?php endif; ?>
        <?php endif; ?>

        <li class="nav-item">
          <button class="btn btn-outline-secondary btn-sm ms-lg-2" id="themeToggle" title="Toggle theme">
            <i class="bi bi-moon-stars"></i>
          </button>
        </li>

        <?php if ($current): ?>
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
              <i class="bi bi-person-circle"></i> <?= e($current['name']) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($current['role'] === 'student'): ?>
                <li><a class="dropdown-item" href="<?= e(base_url('profile.php')) ?>"><i class="bi bi-person"></i> Profile</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2"><a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('login.php')) ?>">Sign in</a></li>
          <li class="nav-item ms-lg-1"><a class="btn btn-primary btn-sm" href="<?= e(base_url('register.php')) ?>">Get started</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php if ($msg = flash('success')): ?>
  <div class="container mt-3"><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> <?= e($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
  <div class="container mt-3"><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle"></i> <?= e($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
