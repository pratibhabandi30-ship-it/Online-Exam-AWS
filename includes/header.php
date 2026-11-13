<?php
require_once __DIR__ . '/auth.php';
$current = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
$isAdminShell = $current && $current['role'] === 'admin';
$isFacultyShell = $current && $current['role'] === 'faculty' && !empty($facultyShellEnabled);
$studentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$studentPageNav = [
    'dashboard.php' => 'dashboard',
    'exams.php' => 'exams',
    'take-exam.php' => 'exams',
    'timetable.php' => 'timetable',
    'attendance.php' => 'attendance',
    'announcements.php' => 'announcements',
    'history.php' => 'results',
    'result.php' => 'results',
    'certificates.php' => 'certificates',
    'certificate.php' => 'certificates',
    'verify-certificate.php' => 'certificates',
    'leaderboard.php' => 'leaderboard',
    'feedback.php' => 'feedback',
    'notifications.php' => 'notifications',
    'profile.php' => 'settings',
];
$isStudentShell = $current && $current['role'] === 'student' && $activeNav !== 'home' && isset($studentPageNav[$studentPage]);
$studentActive = $studentPageNav[$studentPage] ?? '';
$unreadNotifications = 0;
if ($current) {
    $unreadQuery = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
    $unreadQuery->execute([(int) $current['id']]);
    $unreadNotifications = (int) $unreadQuery->fetchColumn();
}
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
<link href="<?= e(base_url('assets/css/style.css?v=20261108-student-sidebar')) ?>" rel="stylesheet">
<?php if ($isStudentShell): ?><link href="<?= e(base_url('assets/css/student-dashboard.css?v=20261108-student-sidebar')) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="<?= $activeNav === 'home' ? 'home-page' : ($isAdminShell ? 'admin-shell-page' : ($isFacultyShell ? 'admin-shell-page faculty-shell-page' : ($isStudentShell ? 'admin-shell-page student-shell-page student-dashboard-page' : ''))) ?>">
<?php if ($isAdminShell || $isFacultyShell || $isStudentShell): ?>
<div class="admin-shell">
  <?php if ($isStudentShell) { include __DIR__ . '/student-nav.php'; } ?>
  <header class="admin-topbar">
    <button class="admin-menu-toggle" type="button" aria-label="Toggle sidebar" aria-controls="adminSidebar">
      <i class="bi bi-list"></i>
    </button>
    <div class="admin-topbar-title">
      <span class="admin-breadcrumb"><?= $isAdminShell ? 'HOD workspace' : ($isFacultyShell ? 'Faculty workspace' : 'Student portal') ?></span>
      <h1><?= e(preg_replace('/^(Admin|Faculty|Student)\s*·\s*/', '', $pageTitle) ?: 'Dashboard') ?></h1>
    </div>
    <?php if ($isAdminShell): ?><form class="admin-search" method="get" action="<?= e(base_url('admin/search.php')) ?>" role="search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="search" name="q" class="form-control" placeholder="Search users, quizzes, attempts" aria-label="Search users, quizzes, and attempts">
      <button class="btn" type="submit">Search</button>
    </form><?php endif; ?>
    <div class="admin-topbar-actions">
      <?php if ($isAdminShell): ?><button class="admin-icon-button admin-search-toggle" type="button" aria-label="Open search"><i class="bi bi-search"></i></button><?php endif; ?>
      <a class="admin-icon-button position-relative" href="<?= e(base_url('notifications.php')) ?>" aria-label="Notifications<?= $unreadNotifications > 0 ? ', ' . $unreadNotifications . ' unread' : '' ?>">
        <i class="bi bi-bell"></i>
        <?php if ($unreadNotifications > 0): ?><span class="admin-notification-count"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span><?php endif; ?>
      </a>
      <button class="admin-icon-button" id="themeToggle" type="button" title="Toggle dark/light mode" aria-label="Toggle dark/light mode"><i class="bi bi-moon-stars"></i></button>
      <div class="dropdown">
        <button class="admin-profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="admin-avatar"><?= e(mb_strtoupper(mb_substr($current['name'], 0, 1))) ?></span>
          <span class="admin-profile-label"><strong><?= e($current['name']) ?></strong><small><?= $isAdminShell ? 'HOD · Super Admin' : ($isFacultyShell ? 'Faculty' : 'Student') ?></small></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= e(base_url($isAdminShell ? 'admin/settings.php' : 'profile.php')) ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </header>
  <main class="admin-main">
<?php else: ?>
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
        <?php if ($current && $current['role'] === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url('admin/dashboard.php')) ?>"><i class="bi bi-shield-lock"></i> HOD Panel</a></li>
        <?php elseif ($current && $current['role'] === 'faculty'): ?>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='faculty-dashboard'?'active':'' ?>" href="<?= e(base_url('faculty/dashboard.php')) ?>"><i class="bi bi-easel"></i> Faculty Dashboard</a></li>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='faculty-exams'?'active':'' ?>" href="<?= e(base_url('faculty/exams.php')) ?>"><i class="bi bi-journal-check"></i> My Exams</a></li>
          <li class="nav-item"><a class="nav-link <?= $activeNav==='faculty-results'?'active':'' ?>" href="<?= e(base_url('faculty/results.php')) ?>"><i class="bi bi-clipboard-data"></i> Results</a></li>
        <?php else: ?>
          <?php if ($activeNav === 'home'): ?>
            <li class="nav-item"><a class="nav-link" href="#features">Platform</a></li>
            <li class="nav-item"><a class="nav-link" href="#courses">Courses</a></li>
            <li class="nav-item"><a class="nav-link" href="#how">How it works</a></li>
            <li class="nav-item"><a class="nav-link" href="#roles">Roles</a></li>
            <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
            <li class="nav-item"><a class="nav-link" href="#contact">Support</a></li>
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
          <li class="nav-item">
            <a class="nav-link position-relative" href="<?= e(base_url('notifications.php')) ?>" aria-label="Notifications">
              <i class="bi bi-bell"></i> Notifications
              <?php if ($unreadNotifications > 0): ?><span class="badge rounded-pill bg-danger"><?= $unreadNotifications ?></span><?php endif; ?>
            </a>
          </li>
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
<?php endif; ?>

<?php if ($msg = flash('success')): ?>
  <div class="container mt-3"><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> <?= e($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
  <div class="container mt-3"><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle"></i> <?= e($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
