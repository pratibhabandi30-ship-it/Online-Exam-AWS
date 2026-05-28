<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();
if ($me['role'] !== 'student') redirect('admin/dashboard.php');

$pdo    = db();
$search = clean_string($_GET['q'] ?? '', 100);
$tag    = clean_string($_GET['tag'] ?? '', 60);

$sql = "SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS qcount
        FROM quizzes q WHERE q.is_published = 1";
$args = [];
if ($search !== '') { $sql .= " AND q.title LIKE ?"; $args[] = "%$search%"; }
if ($tag    !== '') { $sql .= " AND q.tag = ?";     $args[] = $tag; }
$sql .= " ORDER BY q.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$quizzes = $stmt->fetchAll();

$tags = $pdo->query("SELECT DISTINCT tag FROM quizzes WHERE is_published=1 AND tag IS NOT NULL AND tag<>'' ORDER BY tag")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Exams';
$activeNav = 'exams';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
    <div>
      <h2 class="mb-1">Browse exams</h2>
      <p class="text-muted mb-0"><?= count($quizzes) ?> exams ready to take.</p>
    </div>
    <form class="d-flex flex-wrap gap-2">
      <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search exams…">
      <select class="form-select" name="tag" onchange="this.form.submit()">
        <option value="">All tags</option>
        <?php foreach ($tags as $t): ?>
          <option value="<?= e($t) ?>" <?= $tag===$t?'selected':'' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary"><i class="bi bi-search"></i></button>
    </form>
  </div>

  <?php if (!$quizzes): ?>
    <div class="card p-5 text-center text-muted">
      <i class="bi bi-inbox" style="font-size:2.5rem"></i>
      <p class="mt-3 mb-0">No exams match your filters.</p>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($quizzes as $q): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card exam-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="exam-tag"><?= e($q['tag'] ?: 'General') ?></span>
              <small class="text-muted"><i class="bi bi-stopwatch"></i> <?= (int) $q['duration_minutes'] ?> min</small>
            </div>
            <h5 class="mt-2"><?= e($q['title']) ?></h5>
            <p class="text-muted small flex-grow-1"><?= e($q['intro'] ?: 'A quick test to check your knowledge.') ?></p>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <small class="text-muted"><i class="bi bi-list-check"></i> <?= (int) $q['qcount'] ?> questions</small>
              <a href="<?= e(base_url('take-exam.php?quiz=' . (int) $q['id'])) ?>" class="btn btn-primary btn-sm">
                Start <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
