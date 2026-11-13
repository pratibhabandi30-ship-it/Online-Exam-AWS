<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo   = db();

$byQuiz = $pdo->query("
    SELECT q.title, q.tag,
           COUNT(a.id) AS attempts,
           ROUND(AVG(CASE WHEN a.total_marks>0 THEN a.score*100/a.total_marks END), 1) AS avg_pct,
           ROUND(AVG(a.time_taken_sec)) AS avg_time
    FROM quizzes q LEFT JOIN attempts a ON a.quiz_id = q.id
    GROUP BY q.id ORDER BY attempts DESC, avg_pct DESC
")->fetchAll();

$byDay = $pdo->query("
    SELECT DATE(submitted_at) AS d, COUNT(*) AS c
    FROM attempts WHERE submitted_at >= (NOW() - INTERVAL 30 DAY)
    GROUP BY DATE(submitted_at) ORDER BY d
")->fetchAll();

$byTag = $pdo->query("
    SELECT COALESCE(q.tag,'Other') AS tag,
           ROUND(AVG(CASE WHEN a.total_marks>0 THEN a.score*100/a.total_marks END),1) AS avg_pct,
           COUNT(a.id) AS attempts
    FROM attempts a JOIN quizzes q ON q.id = a.quiz_id
    GROUP BY q.tag ORDER BY attempts DESC
")->fetchAll();

$pageTitle = 'Admin · Analytics';
include __DIR__ . '/../includes/header.php';
$adminActive = 'analytics';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <h3 class="mb-3">Analytics</h3>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card p-4">
        <h5 class="mb-3"><i class="bi bi-calendar3"></i> Attempts (last 30 days)</h5>
        <canvas id="dayChart" height="120"></canvas>
        <script>
          window.addEventListener('DOMContentLoaded', () => {
            new Chart(document.getElementById('dayChart'), {
              type:'line',
              data:{
                labels: <?= json_encode(array_map(fn($r)=>date('d M',strtotime($r['d'])),$byDay)) ?>,
                datasets:[{label:'Attempts', data: <?= json_encode(array_map(fn($r)=>(int)$r['c'],$byDay)) ?>,
                  borderColor:'#606c38', backgroundColor:'rgba(96,108,56,.14)', fill:true, tension:.35}]
              },
              options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
            });
          });
        </script>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card p-4 h-100">
        <h5 class="mb-3"><i class="bi bi-tags"></i> Avg accuracy by topic</h5>
        <?php if (!$byTag): ?>
          <p class="text-muted">No data yet.</p>
        <?php else: ?>
          <canvas id="tagChart" height="160"></canvas>
          <script>
            window.addEventListener('DOMContentLoaded', () => {
              new Chart(document.getElementById('tagChart'), {
                type:'doughnut',
                data:{
                  labels: <?= json_encode(array_column($byTag,'tag')) ?>,
                  datasets:[{ data: <?= json_encode(array_map(fn($r)=>(float)$r['avg_pct'],$byTag)) ?>,
                    backgroundColor:['#606c38','#7f8c45','#a3b078','#283618','#bac78e','#89965a','#d2dbb5'] }]
                },
                options:{plugins:{legend:{position:'bottom'}}}
              });
            });
          </script>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card p-3 mt-4">
    <h5 class="mb-3"><i class="bi bi-list-ul"></i> Per-quiz summary</h5>
    <?php if (!$byQuiz): ?>
      <p class="text-muted">No quizzes yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Quiz</th><th>Tag</th><th class="text-center">Attempts</th><th class="text-center">Avg %</th><th class="text-center">Avg time</th></tr></thead>
          <tbody>
          <?php foreach ($byQuiz as $r): ?>
            <tr>
              <td><?= e($r['title']) ?></td>
              <td><span class="exam-tag"><?= e($r['tag'] ?: '—') ?></span></td>
              <td class="text-center"><?= (int) $r['attempts'] ?></td>
              <td class="text-center">
                <?php if ($r['avg_pct'] !== null): ?>
                  <span class="badge bg-<?= $r['avg_pct']>=70?'success':($r['avg_pct']>=50?'warning':'danger') ?>"><?= e((string) $r['avg_pct']) ?>%</span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center text-muted small"><?= $r['avg_time'] !== null ? e(format_duration_seconds((int)$r['avg_time'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
