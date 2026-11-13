<?php
declare(strict_types=1);

$studentActive = $studentActive ?? '';
$items = [
    'dashboard' => ['Dashboard', 'dashboard.php', 'bi-grid-1x2'],
    'exams' => ['Exams', 'exams.php', 'bi-journal-check'],
    'timetable' => ['Timetable', 'timetable.php', 'bi-calendar3'],
    'attendance' => ['My Attendance', 'attendance.php', 'bi-calendar2-check'],
    'announcements' => ['Announcements', 'announcements.php', 'bi-megaphone'],
    'results' => ['Results & Performance', 'history.php', 'bi-graph-up-arrow'],
    'certificates' => ['My Certificates', 'certificates.php', 'bi-award'],
    'leaderboard' => ['Leaderboard', 'leaderboard.php', 'bi-trophy'],
    'feedback' => ['Feedback', 'feedback.php', 'bi-chat-dots'],
    'notifications' => ['Notifications', 'notifications.php', 'bi-bell'],
];
?>
<aside class="admin-sidebar student-sidebar" id="adminSidebar" aria-label="Student navigation">
  <a class="admin-sidebar-brand" href="<?= e(base_url('dashboard.php')) ?>">
    <span class="admin-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="admin-brand-copy"><strong>Online Exam</strong><small>System · Student</small></span>
  </a>
  <div class="admin-sidebar-section">STUDENT PORTAL</div>
  <nav class="admin-sidebar-nav">
    <?php foreach ($items as $key => [$label, $url, $icon]): ?>
      <a class="admin-sidebar-link <?= $studentActive === $key ? 'active' : '' ?>" href="<?= e(base_url($url)) ?>" <?= $studentActive === $key ? 'aria-current="page"' : '' ?> title="<?= e($label) ?>">
        <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-bottom">
    <a class="admin-sidebar-link <?= $studentActive === 'settings' ? 'active' : '' ?>" href="<?= e(base_url('profile.php')) ?>" title="Settings" <?= $studentActive === 'settings' ? 'aria-current="page"' : '' ?>>
      <i class="bi bi-gear"></i><span>Settings</span>
    </a>
    <div class="admin-sidebar-user">
      <span class="admin-avatar"><?= e(mb_strtoupper(mb_substr($current['name'] ?? 'S', 0, 1))) ?></span>
      <span class="admin-sidebar-user-copy"><strong><?= e($current['name'] ?? 'Student') ?></strong><small>Student</small></span>
    </div>
    <a class="admin-sidebar-link admin-logout-link" href="<?= e(base_url('logout.php')) ?>" title="Logout">
      <i class="bi bi-box-arrow-left"></i><span>Logout</span>
    </a>
  </div>
</aside>
<button class="admin-sidebar-backdrop" type="button" aria-label="Close navigation"></button>
