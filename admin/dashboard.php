<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo   = db();

$counts = [
    'students' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'quizzes'  => (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn(),
    'attempts' => (int) $pdo->query("SELECT COUNT(*) FROM attempts")->fetchColumn(),
    'feedback' => (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE is_read=0")->fetchColumn(),
];

$recent = $pdo->query("
    SELECT a.id, a.score, a.total_marks, a.submitted_at,
           u.name AS uname, u.email AS uemail,
           q.title AS qtitle
    FROM attempts a
    JOIN users u ON u.id = a.user_id
    JOIN quizzes q ON q.id = a.quiz_id
    ORDER BY a.submitted_at DESC LIMIT 10
")->fetchAll();

$series = $pdo->query("
    SELECT DATE(submitted_at) AS d, COUNT(*) AS c
    FROM attempts WHERE submitted_at >= (NOW() - INTERVAL 14 DAY)
    GROUP BY DATE(submitted_at) ORDER BY d
")->fetchAll();

$pageTitle = 'Admin · Dashboard';
include __DIR__ . '/../includes/header.php';
$adminActive = 'dashboard';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div>
      <h3 class="mb-1">Admin dashboard</h3>
      <p class="text-muted mb-0">Welcome, <?= e($admin['name']) ?>.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('admin/quizzes.php?action=new')) ?>"><i class="bi bi-plus-lg"></i> New quiz</a>
  </div>

  <div class="row g-3 mb-4">
    <?php
    $cards = [
      ['Students',      $counts['students'], 'bi-people'],
      ['Quizzes',       $counts['quizzes'],  'bi-collection'],
      ['Total attempts',$counts['attempts'], 'bi-clipboard-data'],
      ['Unread feedback',$counts['feedback'],'bi-chat-dots'],
    ];
    foreach ($cards as [$label, $value, $icon]):
    ?>
      <div class="col-6 col-lg-3">
        <div class="card p-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small text-uppercase"><?= e($label) ?></div>
              <div class="fs-3 fw-bold"><?= (int) $value ?></div>
            </div>
            <div class="feature-icon"><i class="bi <?= e($icon) ?>"></i></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card p-4 h-100">
        <h5 class="mb-3"><i class="bi bi-graph-up"></i> Attempts in last 14 days</h5>
        <?php if (!$series): ?>
          <p class="text-muted">No attempts in this window.</p>
        <?php else: ?>
          <canvas id="attChart" height="120"></canvas>
          <script>
            window.addEventListener('DOMContentLoaded', () => {
              new Chart(document.getElementById('attChart'), {
                type:'bar',
                data:{
                  labels: <?= json_encode(array_map(fn($r)=>date('d M',strtotime($r['d'])),$series)) ?>,
                  datasets:[{label:'Attempts', data: <?= json_encode(array_map(fn($r)=>(int)$r['c'],$series)) ?>,
                    backgroundColor:'rgba(6,182,212,.65)', borderRadius:8}]
                },
                options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
              });
            });
          </script>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card p-4 h-100">
        <h5 class="mb-3"><i class="bi bi-clock-history"></i> Recent attempts</h5>
        <?php if (!$recent): ?>
          <p class="text-muted">No attempts yet.</p>
        <?php else: ?>
          <div class="vstack gap-2">
            <?php foreach ($recent as $a):
              $pct = $a['total_marks']>0 ? round($a['score']*100/$a['total_marks'],1) : 0;
            ?>
              <a class="d-flex justify-content-between align-items-center text-decoration-none p-2 rounded bg-soft" href="<?= e(base_url('result.php?id='.(int)$a['id'])) ?>">
                <div>
                  <div class="text-body small fw-semibold"><?= e($a['qtitle']) ?></div>
                  <small class="text-muted"><?= e($a['uname']) ?> · <?= e(date('d M, H:i', strtotime($a['submitted_at']))) ?></small>
                </div>
                <span class="badge bg-<?= $pct>=70?'success':($pct>=50?'warning':'danger') ?>"><?= $pct ?>%</span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
