<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo   = db();

$counts = [
    'students' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'faculty'  => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty'")->fetchColumn(),
    'pending_approvals' => (int) $pdo->query("SELECT COUNT(*) FROM exam_workflows WHERE status='pending'")->fetchColumn(),
    'quizzes'  => (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn(),
    'attempts' => (int) $pdo->query("SELECT COUNT(*) FROM attempts")->fetchColumn(),
    'feedback' => (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE is_read=0")->fetchColumn(),
    'certificates' => (int) $pdo->query("SELECT COUNT(*) FROM certificates WHERE status='issued'")->fetchColumn(),
];
$certificateCounts = ['Silver' => 0, 'Gold' => 0];
foreach ($pdo->query("SELECT certificate_type, COUNT(*) AS amount FROM certificates WHERE status='issued' GROUP BY certificate_type") as $certificateCount) {
    $certificateCounts[$certificateCount['certificate_type']] = (int) $certificateCount['amount'];
}

$recent = $pdo->query("
    SELECT a.id, a.score, a.total_marks, a.submitted_at,
           u.name AS uname, u.email AS uemail,
           q.title AS qtitle
    FROM attempts a
    JOIN users u ON u.id = a.user_id
    JOIN quizzes q ON q.id = a.quiz_id
    ORDER BY a.submitted_at DESC LIMIT 10
")->fetchAll();

$dailyAttempts = $pdo->query("
    SELECT DATE(submitted_at) AS d, COUNT(*) AS c
    FROM attempts WHERE submitted_at >= (NOW() - INTERVAL 14 DAY)
    GROUP BY DATE(submitted_at) ORDER BY d
")->fetchAll();
$attemptsByDay = [];
foreach ($dailyAttempts as $dailyAttempt) {
    $attemptsByDay[$dailyAttempt['d']] = (int) $dailyAttempt['c'];
}
$attemptLabels = [];
$attemptValues = [];
$firstDay = new DateTimeImmutable('today -13 days');
for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
    $day = $firstDay->modify('+' . $dayOffset . ' days');
    $dateKey = $day->format('Y-m-d');
    $attemptLabels[] = $day->format('d M');
    $attemptValues[] = $attemptsByDay[$dateKey] ?? 0;
}

$studentPerformance = $pdo->query("
    SELECT u.id, u.name, u.email, COUNT(a.id) AS attempts,
           ROUND(AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100 / a.total_marks END), 1) AS avg_pct
    FROM users u JOIN attempts a ON a.user_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id, u.name, u.email
    ORDER BY avg_pct DESC, attempts DESC, u.name ASC LIMIT 6
")->fetchAll();
$quizStatistics = $pdo->query("
    SELECT q.id, q.title, q.is_published, COUNT(a.id) AS attempts,
           ROUND(AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100 / a.total_marks END), 1) AS avg_pct
    FROM quizzes q LEFT JOIN attempts a ON a.quiz_id = q.id
    GROUP BY q.id, q.title, q.is_published
    ORDER BY attempts DESC, q.title ASC LIMIT 8
")->fetchAll();

$pageTitle = 'Admin · Dashboard';
include __DIR__ . '/../includes/header.php';
$adminActive = 'dashboard';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
      <h2 class="mb-1 fw-bold">Good <?= (int) date('G') < 12 ? 'morning' : ((int) date('G') < 17 ? 'afternoon' : 'evening') ?>, <?= e($admin['name']) ?></h2>
      <p class="text-muted mb-0">Here is your examination platform overview.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('admin/quizzes.php?action=new')) ?>"><i class="bi bi-plus-lg me-1"></i> Create quiz</a>
  </div>

  <div class="row g-3 mb-3">
    <?php foreach ([
      ['Total Certificates Issued', $counts['certificates'], 'bi-award', 'olive'],
      ['Silver Certificates', $certificateCounts['Silver'], 'bi-award-fill', 'silver'],
      ['Gold Certificates', $certificateCounts['Gold'], 'bi-award-fill', 'gold'],
    ] as [$label, $amount, $icon, $color]): ?>
      <div class="col-6 col-xl-4"><div class="card admin-stat-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div><div class="text-muted small text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold mt-1"><?= (int) $amount ?></div></div>
          <div class="admin-stat-icon <?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></div>
        </div>
      </div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-3">
    <?php
    $cards = [
      ['Total Students', $counts['students'], 'bi-people'],
      ['Total Faculty', $counts['faculty'], 'bi-person-workspace'],
      ['Pending Approvals', $counts['pending_approvals'], 'bi-hourglass-split'],
      ['Total Quizzes', $counts['quizzes'], 'bi-collection'],
    ];
    foreach ($cards as [$label, $value, $icon]):
    ?>
      <div class="col-6 col-xl-3">
        <div class="card admin-stat-card p-3 h-100">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="text-muted small text-uppercase"><?= e($label) ?></div>
              <div class="fs-3 fw-bold mt-1"><?= (int) $value ?></div>
            </div>
            <div class="admin-stat-icon olive"><i class="bi <?= e($icon) ?>"></i></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-3">
    <?php foreach ([
      ['Total Exam Attempts', $counts['attempts'], 'bi-clipboard-data'],
      ['Unread Feedback', $counts['feedback'], 'bi-chat-dots'],
    ] as [$label, $value, $icon]): ?>
      <div class="col-6 col-lg-3"><div class="card admin-stat-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="text-muted small text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold mt-1"><?= (int) $value ?></div></div><div class="admin-stat-icon olive"><i class="bi <?= e($icon) ?>"></i></div></div>
      </div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-7">
      <div class="card p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-2"><div><h5 class="mb-1">Attempts in the last 14 days</h5><p class="small text-muted mb-0">Daily examination submissions</p></div><i class="bi bi-bar-chart-line admin-section-icon"></i></div>
        <div class="admin-chart-wrap"><canvas id="attChart" aria-label="Bar chart of exam attempts in the last 14 days" role="img"></canvas></div>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="card p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-2"><div><h5 class="mb-1">Certificate distribution</h5><p class="small text-muted mb-0">Issued certificates by tier</p></div><i class="bi bi-pie-chart admin-section-icon"></i></div>
        <?php if ($counts['certificates'] > 0): ?>
          <div class="admin-doughnut-wrap"><canvas id="certificateChart" aria-label="Doughnut chart of certificate tiers" role="img"></canvas></div>
        <?php else: ?>
          <div class="admin-chart-empty">No certificates have been issued yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-7">
      <div class="card p-3 h-100">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><div><h5 class="mb-1">Recent examination attempts</h5><p class="small text-muted mb-0">Latest student submissions</p></div><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/attempts.php')) ?>">All attempts</a></div>
        <div class="table-responsive">
          <table class="table align-middle mb-0"><thead><tr><th>Student / Quiz</th><th>Submitted</th><th class="text-end">Score</th></tr></thead><tbody>
          <?php foreach ($recent as $attempt): $percent = (int) $attempt['total_marks'] > 0 ? round((int) $attempt['score'] * 100 / (int) $attempt['total_marks']) : 0; ?>
            <tr><td><a class="fw-semibold text-decoration-none" href="<?= e(base_url('result.php?id=' . (int) $attempt['id'])) ?>"><?= e($attempt['uname']) ?></a><div class="small text-muted"><?= e($attempt['qtitle']) ?></div></td><td class="small text-muted"><?= e(date('d M, H:i', strtotime($attempt['submitted_at']))) ?></td><td class="text-end"><span class="badge admin-score-badge"><?= $percent ?>%</span></td></tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="3" class="text-center text-muted py-4">No attempts yet.</td></tr><?php endif; ?>
          </tbody></table>
        </div>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="card p-3 h-100">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><div><h5 class="mb-1">Student performance</h5><p class="small text-muted mb-0">Average score across submitted exams</p></div><i class="bi bi-mortarboard admin-section-icon"></i></div>
        <div class="table-responsive">
          <table class="table align-middle mb-0"><thead><tr><th>Student</th><th class="text-center">Attempts</th><th class="text-end">Average</th></tr></thead><tbody>
          <?php foreach ($studentPerformance as $student): ?><tr><td><div class="fw-semibold"><?= e($student['name']) ?></div><div class="small text-muted"><?= e($student['email']) ?></div></td><td class="text-center"><?= (int) $student['attempts'] ?></td><td class="text-end"><span class="badge admin-score-badge"><?= e((string) $student['avg_pct']) ?>%</span></td></tr><?php endforeach; ?>
          <?php if (!$studentPerformance): ?><tr><td colspan="3" class="text-center text-muted py-4">No student attempts to analyze yet.</td></tr><?php endif; ?>
          </tbody></table>
        </div>
      </div>
    </div>
  </div>

  <div class="card p-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><div><h5 class="mb-1">Quiz statistics</h5><p class="small text-muted mb-0">Participation and average performance by quiz</p></div><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/quizzes.php')) ?>">Manage quizzes</a></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Quiz</th><th>Status</th><th class="text-center">Attempts</th><th class="text-end">Average score</th></tr></thead><tbody>
      <?php foreach ($quizStatistics as $quiz): ?><tr><td class="fw-semibold"><?= e($quiz['title']) ?></td><td><span class="badge <?= $quiz['is_published'] ? 'admin-status-live' : 'admin-status-draft' ?>"><?= $quiz['is_published'] ? 'Published' : 'Draft' ?></span></td><td class="text-center"><?= (int) $quiz['attempts'] ?></td><td class="text-end"><?= $quiz['avg_pct'] !== null ? e((string) $quiz['avg_pct']) . '%' : '—' ?></td></tr><?php endforeach; ?>
      <?php if (!$quizStatistics): ?><tr><td colspan="4" class="text-center text-muted py-4">No quizzes have been created yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
<script>
window.addEventListener('DOMContentLoaded', () => {
  const axisColor = getComputedStyle(document.body).getPropertyValue('--admin-chart-label').trim() || '#7b826c';
  const gridColor = getComputedStyle(document.body).getPropertyValue('--admin-chart-grid').trim() || 'rgba(40,54,24,.08)';
  new Chart(document.getElementById('attChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($attemptLabels) ?>,
      datasets: [{label: 'Attempts', data: <?= json_encode($attemptValues) ?>, backgroundColor: '#7f8c45', hoverBackgroundColor: '#606c38', borderRadius: 6, maxBarThickness: 26}]
    },
    options: {maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {grid: {display: false}, ticks: {color: axisColor}}, y: {beginAtZero: true, ticks: {precision: 0, color: axisColor}, grid: {color: gridColor}}}}
  });
  <?php if ($counts['certificates'] > 0): ?>
  new Chart(document.getElementById('certificateChart'), {
    type: 'doughnut',
    data: {
      labels: ['Silver', 'Gold'],
      datasets: [{data: <?= json_encode(array_values($certificateCounts)) ?>, backgroundColor: ['#9ca3af', '#c19a42'], borderColor: '#fff', borderWidth: 3, hoverOffset: 6}]
    },
    options: {maintainAspectRatio: false, cutout: '68%', plugins: {legend: {position: 'bottom', labels: {usePointStyle: true, padding: 18, color: axisColor}}}}
  });
  <?php endif; ?>
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
