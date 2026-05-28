<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo   = db();

$quizId = (int) ($_GET['quiz'] ?? 0);
$search = clean_string($_GET['q'] ?? '', 80);

$sql = "SELECT a.*, u.name AS uname, u.email AS uemail, q.title AS qtitle, q.tag
        FROM attempts a JOIN users u ON u.id = a.user_id JOIN quizzes q ON q.id = a.quiz_id WHERE 1=1";
$args = [];
if ($quizId > 0)   { $sql .= " AND a.quiz_id = ?";  $args[] = $quizId; }
if ($search !== ''){ $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $args[] = "%$search%"; $args[] = "%$search%"; }
$sql .= " ORDER BY a.submitted_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql); $stmt->execute($args); $rows = $stmt->fetchAll();
$quizzes = $pdo->query("SELECT id, title FROM quizzes ORDER BY title")->fetchAll();

$pageTitle = 'Admin · Attempts';
include __DIR__ . '/../includes/header.php';
$adminActive = 'attempts';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
    <h3 class="mb-0">All attempts</h3>
    <form class="d-flex gap-2 flex-wrap">
      <select name="quiz" class="form-select" onchange="this.form.submit()">
        <option value="0">All quizzes</option>
        <?php foreach ($quizzes as $q): ?>
          <option value="<?= (int) $q['id'] ?>" <?= $quizId===(int)$q['id']?'selected':'' ?>><?= e($q['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search student…">
      <button class="btn btn-primary"><i class="bi bi-search"></i></button>
      <a class="btn btn-outline-secondary" href="<?= e(base_url('export.php?type=attempts_csv'.($quizId>0?'&quiz='.$quizId:''))) ?>"><i class="bi bi-download"></i> CSV</a>
    </form>
  </div>
  <div class="card p-3">
    <?php if (!$rows): ?>
      <p class="text-muted text-center my-4">No attempts on record.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>When</th><th>Student</th><th>Quiz</th><th class="text-center">Score</th><th class="text-center">%</th><th class="text-center">Time</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r):
            $pct = $r['total_marks']>0 ? round($r['score']*100/$r['total_marks'],1) : 0;
          ?>
            <tr>
              <td class="small text-muted"><?= e(date('d M Y, H:i', strtotime($r['submitted_at']))) ?></td>
              <td><?= e($r['uname']) ?><br><small class="text-muted"><?= e($r['uemail']) ?></small></td>
              <td><?= e($r['qtitle']) ?> <span class="exam-tag ms-1"><?= e($r['tag'] ?: '—') ?></span></td>
              <td class="text-center"><?= (int) $r['score'] ?>/<?= (int) $r['total_marks'] ?></td>
              <td class="text-center"><span class="badge bg-<?= $pct>=70?'success':($pct>=50?'warning':'danger') ?>"><?= $pct ?>%</span></td>
              <td class="text-center small text-muted"><?= e(format_duration_seconds((int)$r['time_taken_sec'])) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('result.php?id='.(int)$r['id'])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
