<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();
if ($me['role'] !== 'student') redirect('admin/dashboard.php');

$pdo = db();

$stats = $pdo->prepare("
    SELECT
        COUNT(*) AS attempts,
        COALESCE(SUM(score), 0) AS total_score,
        COALESCE(AVG(CASE WHEN total_marks>0 THEN (score*100.0/total_marks) END), 0) AS avg_percent,
        COALESCE(MAX(CASE WHEN total_marks>0 THEN (score*100.0/total_marks) END), 0) AS best_percent
    FROM attempts WHERE user_id = ?
");
$stats->execute([$me['id']]);
$st = $stats->fetch();

$quizCount = (int) $pdo->query("SELECT COUNT(*) FROM quizzes WHERE is_published=1")->fetchColumn();

$recent = $pdo->prepare("
    SELECT a.*, q.title AS quiz_title, q.tag
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.user_id = ?
    ORDER BY a.submitted_at DESC
    LIMIT 5
");
$recent->execute([$me['id']]);
$recentAttempts = $recent->fetchAll();

// Chart: last 10 attempts (oldest -> newest) by percent.
$chart = $pdo->prepare("
    SELECT submitted_at, score, total_marks
    FROM attempts WHERE user_id = ?
    ORDER BY submitted_at DESC LIMIT 10
");
$chart->execute([$me['id']]);
$chartRows = array_reverse($chart->fetchAll());

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
    <div>
      <h2 class="mb-1">Hello, <?= e(explode(' ', $me['name'])[0]) ?> 👋</h2>
      <p class="text-muted mb-0">Here's your exam performance at a glance.</p>
    </div>
    <a href="<?= e(base_url('exams.php')) ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Browse exams</a>
  </div>

  <div class="row g-3 mb-4">
    <?php
    $cards = [
      ['Attempts',     (int) $st['attempts'],              'bi-clipboard-check',  'primary'],
      ['Total score',  (int) $st['total_score'],           'bi-stars',            'success'],
      ['Average %',    number_format((float) $st['avg_percent'], 1) . '%', 'bi-graph-up', 'info'],
      ['Best %',       number_format((float) $st['best_percent'], 1) . '%', 'bi-trophy', 'warning'],
    ];
    foreach ($cards as [$label,$value,$icon,$color]):
    ?>
      <div class="col-6 col-lg-3">
        <div class="card p-3 h-100">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small text-uppercase"><?= e($label) ?></div>
              <div class="fs-3 fw-bold mt-1"><?= e((string) $value) ?></div>
            </div>
            <div class="feature-icon text-<?= e($color) ?>" style="color:inherit"><i class="bi <?= e($icon) ?>"></i></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0"><i class="bi bi-graph-up"></i> Recent score trend</h5>
          <a class="small" href="<?= e(base_url('history.php')) ?>">Full history &rarr;</a>
        </div>
        <?php if (!$chartRows): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-bar-chart" style="font-size:2rem"></i>
            <p class="mb-0 mt-2">Take your first exam to see your trend here.</p>
          </div>
        <?php else: ?>
          <canvas id="trendChart" height="120"></canvas>
          <script>
            window.addEventListener('DOMContentLoaded', () => {
              const ctx = document.getElementById('trendChart');
              new Chart(ctx, {
                type: 'line',
                data: {
                  labels: <?= json_encode(array_map(fn($r)=>date('d M', strtotime($r['submitted_at'])), $chartRows)) ?>,
                  datasets: [{
                    label: 'Score %',
                    data: <?= json_encode(array_map(fn($r)=>$r['total_marks']>0 ? round($r['score']*100/$r['total_marks'],1) : 0, $chartRows)) ?>,
                    fill: true, tension: .35,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79,70,229,.12)',
                    pointBackgroundColor: '#4f46e5',
                  }]
                },
                options: {
                  plugins: { legend: { display: false } },
                  scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v=>v+'%' } } }
                }
              });
            });
          </script>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-4 h-100">
        <h5 class="mb-3"><i class="bi bi-clock-history"></i> Recent attempts</h5>
        <?php if (!$recentAttempts): ?>
          <p class="text-muted mb-0">No attempts yet.</p>
        <?php else: ?>
          <div class="vstack gap-2">
            <?php foreach ($recentAttempts as $a):
              $pct = $a['total_marks'] > 0 ? round($a['score'] * 100 / $a['total_marks'], 1) : 0;
            ?>
              <a href="<?= e(base_url('result.php?id=' . (int) $a['id'])) ?>" class="d-flex justify-content-between align-items-center text-decoration-none p-2 rounded bg-soft">
                <div>
                  <div class="fw-semibold text-body"><?= e($a['quiz_title']) ?></div>
                  <small class="text-muted"><?= e(date('d M, H:i', strtotime($a['submitted_at']))) ?></small>
                </div>
                <span class="badge bg-<?= $pct >= 70 ? 'success' : ($pct >= 50 ? 'warning' : 'danger') ?>"><?= $pct ?>%</span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0"><i class="bi bi-collection"></i> Available exams</h5>
      <span class="text-muted small"><?= (int) $quizCount ?> published</span>
    </div>
    <a href="<?= e(base_url('exams.php')) ?>" class="btn btn-outline-primary">See all exams &rarr;</a>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
