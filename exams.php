<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_student();

$pdo    = db();
$pdo->prepare(
    "UPDATE notifications SET read_at = NOW()
     WHERE user_id = ? AND type = 'exam_published' AND read_at IS NULL"
)->execute([(int) $me['id']]);
$search = clean_string($_GET['q'] ?? '', 100);
$tag    = clean_string($_GET['tag'] ?? '', 60);
$difficulty = clean_string($_GET['difficulty'] ?? '', 20);
$sort = clean_string($_GET['sort'] ?? 'newest', 20);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;
$allowedDifficulties = ['Beginner', 'Intermediate'];
$allowedSorts = ['newest', 'name'];
if (!in_array($difficulty, $allowedDifficulties, true)) $difficulty = '';
if (!in_array($sort, $allowedSorts, true)) $sort = 'newest';

$where = " FROM quizzes q
           LEFT JOIN exam_workflows w ON w.quiz_id = q.id
           WHERE q.is_published = 1
             AND (w.quiz_id IS NULL OR (w.status = 'published' AND EXISTS (
                 SELECT 1 FROM exam_assigned_students assigned
                 WHERE assigned.quiz_id = q.id AND assigned.student_id = ?
             )))";
$args = [(int) $me['id']];
if ($search !== '') { $where .= " AND q.title LIKE ?"; $args[] = "%$search%"; }
if ($tag !== '') { $where .= " AND q.tag = ?"; $args[] = $tag; }
if ($difficulty !== '') { $where .= " AND q.difficulty = ?"; $args[] = $difficulty; }

$countStmt = $pdo->prepare("SELECT COUNT(*)" . $where);
$countStmt->execute($args);
$totalExams = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalExams / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$orderBy = $sort === 'name' ? 'q.title ASC, q.id ASC' : 'q.created_at DESC, q.id DESC';
$sql = "SELECT q.*, w.scheduled_start, w.scheduled_end,
               EXISTS (SELECT 1 FROM attempts a WHERE a.quiz_id = q.id AND a.user_id = ? AND w.quiz_id IS NOT NULL) AS already_attempted,
               (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS qcount" .
       $where . " ORDER BY " . $orderBy . " LIMIT " . $perPage . " OFFSET " . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([(int) $me['id']], $args));
$quizzes = $stmt->fetchAll();

$tagStmt = $pdo->prepare(
    "SELECT DISTINCT q.tag FROM quizzes q
     LEFT JOIN exam_workflows w ON w.quiz_id = q.id
     WHERE q.is_published = 1 AND q.tag IS NOT NULL AND q.tag <> ''
       AND (w.quiz_id IS NULL OR (w.status = 'published' AND EXISTS (
           SELECT 1 FROM exam_assigned_students assigned WHERE assigned.quiz_id = q.id AND assigned.student_id = ?
       )))
     ORDER BY q.tag"
);
$tagStmt->execute([(int) $me['id']]);
$tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Exams';
$activeNav = 'exams';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4" id="browseExams">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
    <div>
      <h2 class="mb-1">Browse exams</h2>
      <p class="text-muted mb-0"><?= $totalExams ?> exams ready to take.</p>
    </div>
    <form class="d-flex flex-wrap gap-2 align-items-center" method="get">
      <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search courses…">
      <select class="form-select" name="tag" aria-label="Filter by category">
        <option value="">All categories</option>
        <?php foreach ($tags as $t): ?>
          <option value="<?= e($t) ?>" <?= $tag===$t?'selected':'' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select" name="difficulty" aria-label="Filter by difficulty">
        <option value="">All difficulties</option>
        <?php foreach ($allowedDifficulties as $level): ?>
          <option value="<?= e($level) ?>" <?= $difficulty===$level?'selected':'' ?>><?= e($level) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select" name="sort" aria-label="Sort courses">
        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest</option>
        <option value="name" <?= $sort==='name'?'selected':'' ?>>Course name</option>
      </select>
      <button class="btn btn-primary"><i class="bi bi-search"></i> Apply</button>
    </form>
  </div>

  <?php if (!$quizzes): ?>
    <div class="card p-5 text-center text-muted">
      <i class="bi bi-inbox" style="font-size:2.5rem"></i>
      <p class="mt-3 mb-0">No exams match your filters.</p>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($quizzes as $q): ?>
        <div class="col-md-6 col-lg-4">
          <article class="card exam-card course-card h-100">
            <?php $thumbnail = trim((string) ($q['image_path'] ?? '')); ?>
            <div class="course-card-image-wrap">
              <img
                class="course-card-image"
                src="<?= e(base_url($thumbnail !== '' ? $thumbnail : 'assets/images/courses/course-default.jpg')) ?>"
                alt="<?= e($q['title']) ?> course thumbnail"
                width="1200"
                height="675"
                loading="lazy"
                decoding="async"
                onerror="this.onerror=null;this.src='<?= e(base_url('assets/images/courses/course-default.jpg')) ?>';"
              >
              <span class="course-card-category"><i class="bi bi-bookmark-fill"></i> <?= e($q['tag'] ?: 'General') ?></span>
              <span class="course-card-status"><i class="bi bi-check-circle-fill"></i> Published</span>
            </div>
            <div class="course-card-content">
              <h3 class="course-card-title"><?= e($q['title']) ?></h3>
              <p class="course-card-description"><?= e($q['intro'] ?: 'A quick test to check your knowledge.') ?></p>
              <div class="course-card-meta" aria-label="Exam details">
                <span><i class="bi bi-clock"></i> <?= (int) $q['duration_minutes'] ?> min</span>
                <span><i class="bi bi-list-check"></i> <?= (int) $q['qcount'] ?> questions</span>
                <?php if ($q['scheduled_start']): ?><span><i class="bi bi-calendar-event"></i> <?= e(date('d M, H:i', strtotime($q['scheduled_start']))) ?></span><?php endif; ?>
              </div>
              <div class="course-card-footer">
                <span class="course-card-difficulty difficulty-<?= e(strtolower($q['difficulty'])) ?>"><?= e($q['difficulty']) ?></span>
                <?php
                  $examStartsAt = $q['scheduled_start'] ? strtotime($q['scheduled_start']) : null;
                  $examEndsAt = $q['scheduled_end'] ? strtotime($q['scheduled_end']) : null;
                  $managedUnavailable = (bool) $q['already_attempted']
                      || ($examStartsAt !== null && time() < $examStartsAt)
                      || ($examEndsAt !== null && time() >= $examEndsAt);
                ?>
                <?php if ($managedUnavailable): ?>
                  <button type="button" class="btn btn-outline-secondary course-card-start" disabled>
                    <?= $q['already_attempted'] ? 'Submitted' : (($examStartsAt !== null && time() < $examStartsAt) ? 'Upcoming' : 'Closed') ?>
                  </button>
                <?php else: ?>
                  <a href="<?= e(base_url('take-exam.php?quiz=' . (int) $q['id'])) ?>" class="btn btn-primary course-card-start">
                    Start Exam <i class="bi bi-arrow-up-right"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Course pages">
      <ul class="pagination justify-content-center">
        <?php
          $pageParams = ['q' => $search, 'tag' => $tag, 'difficulty' => $difficulty, 'sort' => $sort];
          $previousUrl = base_url('exams.php?' . http_build_query($pageParams + ['page' => max(1, $page - 1)]));
          $nextUrl = base_url('exams.php?' . http_build_query($pageParams + ['page' => min($totalPages, $page + 1)]));
        ?>
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= e($previousUrl) ?>" aria-label="Previous page">Previous</a>
        </li>
        <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $totalPages ?></span></li>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= e($nextUrl) ?>" aria-label="Next page">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
