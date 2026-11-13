<?php
/**
 * Shared HOD sidebar. Include after the shared header on authenticated admin pages.
 */
$adminActive = $adminActive ?? '';
$items = [
    'dashboard' => ['Dashboard', 'admin/dashboard.php', 'bi-grid-1x2'],
    'quizzes' => ['Quiz Management', 'admin/quizzes.php', 'bi-collection'],
    'attempts' => ['Exam Attempts', 'admin/attempts.php', 'bi-clipboard-data'],
    'certificates' => ['Certificate Management', 'admin/certificates.php', 'bi-award'],
    'users' => ['User Management', 'admin/users.php', 'bi-people'],
    'department' => ['Department Management', 'admin/department.php', 'bi-building'],
    'approvals' => ['Exam Approvals', 'admin/approvals.php', 'bi-clipboard-check'],
    'result-approvals' => ['Result Approvals', 'admin/result-approvals.php', 'bi-patch-check'],
    'department-exams' => ['Department Exams', 'admin/exams.php', 'bi-calendar2-week'],
    'activity' => ['Activity Logs', 'admin/activity.php', 'bi-journal-text'],
    'feedback' => ['Feedback', 'admin/feedback.php', 'bi-chat-square-text'],
    'analytics' => ['Analytics', 'admin/analytics.php', 'bi-graph-up-arrow'],
    'notifications' => ['Notifications', 'notifications.php', 'bi-bell'],
];
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="HOD navigation">
  <a class="admin-sidebar-brand" href="<?= e(base_url('admin/dashboard.php')) ?>">
    <span class="admin-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="admin-brand-copy"><strong>Online Exam</strong><small>System · HOD</small></span>
  </a>
  <div class="admin-sidebar-section">WORKSPACE</div>
  <nav class="admin-sidebar-nav">
    <?php foreach ($items as $key => [$label, $url, $icon]): ?>
      <a class="admin-sidebar-link <?= $adminActive === $key ? 'active' : '' ?>" href="<?= e(base_url($url)) ?>" <?= $adminActive === $key ? 'aria-current="page"' : '' ?> title="<?= e($label) ?>">
        <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-bottom">
    <a class="admin-sidebar-link <?= $adminActive === 'settings' ? 'active' : '' ?>" href="<?= e(base_url('admin/settings.php')) ?>" title="Settings">
      <i class="bi bi-gear"></i><span>Settings</span>
    </a>
    <div class="admin-sidebar-user">
      <span class="admin-avatar"><?= e(mb_strtoupper(mb_substr($current['name'] ?? 'H', 0, 1))) ?></span>
      <span class="admin-sidebar-user-copy"><strong><?= e($current['name'] ?? 'HOD') ?></strong><small>Super Admin</small></span>
    </div>
    <a class="admin-sidebar-link admin-logout-link" href="<?= e(base_url('logout.php')) ?>" title="Logout">
      <i class="bi bi-box-arrow-left"></i><span>Logout</span>
    </a>
  </div>
</aside>
<button class="admin-sidebar-backdrop" type="button" aria-label="Close navigation"></button>
