<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_student();
$pdo = db();

$rows = $pdo->query("
    SELECT u.id, u.name, u.college,
           SUM(a.score) AS total_score,
           COUNT(*) AS attempts
    FROM attempts a JOIN users u ON u.id = a.user_id
    WHERE u.role = 'student'
      AND (NOT EXISTS (SELECT 1 FROM exam_workflows w WHERE w.quiz_id = a.quiz_id)
           OR EXISTS (SELECT 1 FROM result_publications rp WHERE rp.quiz_id = a.quiz_id))
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
        <table class="table align-middle mb-0" style="table-layout: fixed;">
          <colgroup>
            <col style="width: 8%;">
            <col style="width: 27%;">
            <col style="width: 27%;">
            <col style="width: 20%;">
            <col style="width: 18%;">
          </colgroup>
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Student Name</th>
              <th scope="col">College</th>
              <th scope="col" class="text-center">Total Attempts</th>
              <th scope="col" class="text-end">Total Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r):
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
                <td class="text-end"><span class="badge rounded-pill bg-primary px-3 py-2"><?= (int) $r['total_score'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
