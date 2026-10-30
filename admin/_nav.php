<?php
/**
 * Shared admin sidebar nav. Include after header.php.
 */
$adminActive = $adminActive ?? '';
$items = [
    'dashboard' => ['Dashboard',   'admin/dashboard.php',  'bi-speedometer2'],
    'quizzes'   => ['Quizzes',     'admin/quizzes.php',    'bi-collection'],
    'attempts'  => ['Attempts',    'admin/attempts.php',   'bi-clipboard-data'],
    'certificates' => ['Certificates', 'admin/certificates.php', 'bi-award'],
    'users'     => ['Users',       'admin/users.php',      'bi-people'],
    'feedback'  => ['Feedback',    'admin/feedback.php',   'bi-chat-dots'],
    'analytics' => ['Analytics',   'admin/analytics.php',  'bi-graph-up-arrow'],
];
?>
<div class="d-flex flex-wrap gap-1 mb-4 border-bottom pb-3">
  <?php foreach ($items as $k => [$label, $url, $icon]): ?>
    <a class="btn btn-sm <?= $adminActive===$k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(base_url($url)) ?>">
      <i class="bi <?= e($icon) ?>"></i> <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>
