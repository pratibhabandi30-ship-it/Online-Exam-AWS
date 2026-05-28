<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();
if ($me['role'] !== 'student') redirect('admin/dashboard.php');

$pdo = db();
$stmt = $pdo->prepare("
    SELECT a.*, q.title AS quiz_title, q.tag AS quiz_tag
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.user_id = ?
    ORDER BY a.submitted_at DESC
");
$stmt->execute([$me['id']]);
$rows = $stmt->fetchAll();

// Tag-accuracy aggregate for chart.
$agg = $pdo->prepare("
    SELECT COALESCE(q.tag,'Other') AS tag,
           ROUND(AVG(CASE WHEN a.total_marks>0 THEN a.score*100/a.total_marks END), 1) AS avg_pct,
           COUNT(*) AS attempts
    FROM attempts a JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.user_id = ?
    GROUP BY q.tag
    ORDER BY avg_pct DESC
");
$agg->execute([$me['id']]);
$byTag = $agg->fetchAll();

$pageTitle = 'My history';
$activeNav = 'history';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h2 class="mb-1">My attempt history</h2>
  <p class="text-muted mb-4"><?= count($rows) ?> attempts total.</p>

  <?php if ($byTag): ?>
    <div class="card p-4 mb-4">
      <h5 class="mb-3"><i class="bi bi-bullseye"></i> Accuracy by topic</h5>
      <canvas id="tagChart" height="100"></canvas>
      <script>
        window.addEventListener('DOMContentLoaded', () => {
          new Chart(document.getElementById('tagChart'), {
            type: 'bar',
            data: {
              labels: <?= json_encode(array_column($byTag,'tag')) ?>,
              datasets: [{
                label: 'Avg score %',
                data: <?= json_encode(array_map(fn($r)=>(float)$r['avg_pct'], $byTag)) ?>,
                backgroundColor: 'rgba(79,70,229,.65)',
                borderRadius: 8,
              }]
            },
            options: { plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}} } }
          });
        });
      </script>
    </div>
  <?php endif; ?>

  <div class="card p-3">
    <?php if (!$rows): ?>
      <p class="text-center text-muted my-4">No attempts yet. <a href="<?= e(base_url('exams.php')) ?>">Browse exams &rarr;</a></p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Date</th>
              <th>Exam</th>
              <th>Tag</th>
              <th class="text-center">Score</th>
              <th class="text-center">Percent</th>
              <th class="text-center">Time</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $pct = $r['total_marks']>0 ? round($r['score']*100/$r['total_marks'],1) : 0;
            ?>
              <tr>
                <td class="small text-muted"><?= e(date('d M Y, H:i', strtotime($r['submitted_at']))) ?></td>
                <td><?= e($r['quiz_title']) ?></td>
                <td><span class="exam-tag"><?= e($r['quiz_tag'] ?: 'General') ?></span></td>
                <td class="text-center"><?= (int) $r['score'] ?>/<?= (int) $r['total_marks'] ?></td>
                <td class="text-center">
                  <span class="badge bg-<?= $pct>=70?'success':($pct>=50?'warning':'danger') ?>"><?= $pct ?>%</span>
                </td>
                <td class="text-center text-muted small"><?= e(format_duration_seconds((int)$r['time_taken_sec'])) ?></td>
                <td><a href="<?= e(base_url('result.php?id='.(int)$r['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
