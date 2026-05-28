<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();
$pdo = db();

$rows = $pdo->query("
    SELECT u.id, u.name, u.college,
           SUM(a.score) AS total_score,
           SUM(a.total_marks) AS total_marks,
           COUNT(*) AS attempts,
           MAX(a.submitted_at) AS last_attempt
    FROM attempts a JOIN users u ON u.id = a.user_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY total_score DESC, attempts DESC
    LIMIT 50
")->fetchAll();

$pageTitle = 'Leaderboard';
$activeNav = 'leaderboard';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div>
      <h2 class="mb-1"><i class="bi bi-trophy text-warning"></i> Leaderboard</h2>
      <p class="text-muted mb-0">Top performers across all quizzes.</p>
    </div>
  </div>
  <div class="card p-3">
    <?php if (!$rows): ?>
      <p class="text-center text-muted my-4">No attempts on record yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr><th>#</th><th>Student</th><th>College</th><th class="text-center">Attempts</th><th class="text-center">Accuracy</th><th class="text-end">Total score</th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r):
              $acc = $r['total_marks']>0 ? round($r['total_score']*100/$r['total_marks'],1) : 0;
              $isMe = (int)$r['id'] === (int)$me['id'];
            ?>
              <tr class="<?= $isMe ? 'table-primary' : '' ?>">
                <td class="fw-bold">
                  <?php if ($i<3): ?><i class="bi bi-trophy-fill text-<?= ['warning','secondary','danger'][$i] ?>"></i> <?php endif; ?>
                  <?= $i+1 ?>
                </td>
                <td><?= e($r['name']) ?> <?php if ($isMe): ?><span class="badge bg-primary">You</span><?php endif; ?></td>
                <td class="small text-muted"><?= e($r['college'] ?: '—') ?></td>
                <td class="text-center"><?= (int) $r['attempts'] ?></td>
                <td class="text-center"><span class="badge bg-<?= $acc>=70?'success':($acc>=50?'warning':'danger') ?>"><?= $acc ?>%</span></td>
                <td class="text-end fw-bold"><?= (int) $r['total_score'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
