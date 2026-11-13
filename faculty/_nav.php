<?php
declare(strict_types=1);

$facultyActive = $facultyActive ?? '';
$items = [
    'dashboard' => ['Dashboard', 'faculty/dashboard.php', 'bi-grid-1x2'],
    'students' => ['My Students', 'faculty/students.php', 'bi-people'],
    'exams' => ['My Exams', 'faculty/exams.php', 'bi-journal-check'],
    'question-bank' => ['Question Bank', 'faculty/question-bank.php', 'bi-collection'],
    'attendance' => ['Attendance Management', 'faculty/attendance.php', 'bi-calendar2-check'],
    'announcements' => ['Announcements', 'faculty/announcements.php', 'bi-megaphone'],
    'timetable' => ['Timetable Management', 'faculty/timetable.php', 'bi-calendar3'],
    'analytics' => ['Performance Analytics', 'faculty/analytics.php', 'bi-graph-up-arrow'],
    'results' => ['Results and Evaluation', 'faculty/results.php', 'bi-clipboard-data'],
    'reports' => ['Reports', 'faculty/reports.php', 'bi-file-earmark-bar-graph'],
    'notifications' => ['Notifications', 'notifications.php', 'bi-bell'],
];
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Faculty navigation">
  <a class="admin-sidebar-brand" href="<?= e(base_url('faculty/dashboard.php')) ?>">
    <span class="admin-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="admin-brand-copy"><strong>Online Exam</strong><small>System · Faculty</small></span>
  </a>
  <div class="admin-sidebar-section">FACULTY WORKSPACE</div>
  <nav class="admin-sidebar-nav">
    <?php foreach ($items as $key => [$label, $url, $icon]): ?>
      <a class="admin-sidebar-link <?= $facultyActive === $key ? 'active' : '' ?>" href="<?= e(base_url($url)) ?>" <?= $facultyActive === $key ? 'aria-current="page"' : '' ?> title="<?= e($label) ?>">
        <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-bottom">
    <a class="admin-sidebar-link <?= $facultyActive === 'settings' ? 'active' : '' ?>" href="<?= e(base_url('profile.php')) ?>" title="Settings">
      <i class="bi bi-gear"></i><span>Settings</span>
    </a>
    <div class="admin-sidebar-user">
      <span class="admin-avatar"><?= e(mb_strtoupper(mb_substr($current['name'] ?? 'F', 0, 1))) ?></span>
      <span class="admin-sidebar-user-copy"><strong><?= e($current['name'] ?? 'Faculty') ?></strong><small>Faculty</small></span>
    </div>
    <a class="admin-sidebar-link admin-logout-link" href="<?= e(base_url('logout.php')) ?>" title="Logout">
      <i class="bi bi-box-arrow-left"></i><span>Logout</span>
    </a>
  </div>
</aside>
<button class="admin-sidebar-backdrop" type="button" aria-label="Close navigation"></button>
