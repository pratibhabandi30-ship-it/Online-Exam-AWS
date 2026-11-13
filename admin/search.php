<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = db();
$search = trim(clean_string($_GET['q'] ?? '', 80));
$users = [];
$quizzes = [];
$attempts = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $userQuery = $pdo->prepare(
        "SELECT id, name, email, role, is_active FROM users
         WHERE name LIKE ? OR email LIKE ? ORDER BY role, name LIMIT 20"
    );
    $userQuery->execute([$like, $like]);
    $users = $userQuery->fetchAll();

    $quizQuery = $pdo->prepare(
        'SELECT id, title, tag, is_published FROM quizzes WHERE title LIKE ? OR tag LIKE ? ORDER BY title LIMIT 20'
    );
    $quizQuery->execute([$like, $like]);
    $quizzes = $quizQuery->fetchAll();

    $attemptQuery = $pdo->prepare(
        'SELECT a.id, a.score, a.total_marks, a.submitted_at, u.name AS student_name, q.title AS quiz_title
         FROM attempts a JOIN users u ON u.id = a.user_id JOIN quizzes q ON q.id = a.quiz_id
         WHERE u.name LIKE ? OR u.email LIKE ? OR q.title LIKE ?
         ORDER BY a.submitted_at DESC LIMIT 20'
    );
    $attemptQuery->execute([$like, $like, $like]);
    $attempts = $attemptQuery->fetchAll();
}

$pageTitle = 'Admin · Search';
include __DIR__ . '/../includes/header.php';
$adminActive = '';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="mb-4"><h2 class="mb-1">Search the platform</h2><p class="text-muted mb-0">Find students, faculty, quizzes, and submitted exam attempts.</p></div>
  <form class="admin-search-page-form mb-4" method="get" action="<?= e(base_url('admin/search.php')) ?>">
    <label class="visually-hidden" for="adminSearchQuery">Search</label>
    <i class="bi bi-search" aria-hidden="true"></i>
    <input class="form-control" id="adminSearchQuery" type="search" name="q" value="<?= e($search) ?>" placeholder="Enter a name, email, or quiz title" required autofocus>
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <?php if ($search === ''): ?>
    <div class="card p-5 text-center text-muted">Search by a student or faculty name, email address, or quiz title.</div>
  <?php else: ?>
    <div class="row g-3">
      <div class="col-xl-6">
        <div class="card p-3 h-100">
          <h5 class="mb-3">Users <span class="badge admin-score-badge"><?= count($users) ?></span></h5>
          <?php if ($users): ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($users as $user): ?>
              <tr><td><strong><?= e($user['name']) ?></strong><div class="small text-muted"><?= e($user['email']) ?></div></td><td><?= e(ucfirst($user['role'])) ?></td><td><?= (int) $user['is_active'] ? 'Active' : 'Inactive' ?></td></tr>
            <?php endforeach; ?>
          </tbody></table></div><?php else: ?><p class="text-muted mb-0">No matching users.</p><?php endif; ?>
        </div>
      </div>
      <div class="col-xl-6">
        <div class="card p-3 h-100">
          <h5 class="mb-3">Quizzes <span class="badge admin-score-badge"><?= count($quizzes) ?></span></h5>
          <?php if ($quizzes): ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Quiz</th><th>Subject</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($quizzes as $quiz): ?>
              <tr><td><?= e($quiz['title']) ?></td><td><?= e($quiz['tag'] ?: '—') ?></td><td><?= (int) $quiz['is_published'] ? 'Published' : 'Draft' ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/questions.php?quiz=' . (int) $quiz['id'])) ?>">Open</a></td></tr>
            <?php endforeach; ?>
          </tbody></table></div><?php else: ?><p class="text-muted mb-0">No matching quizzes.</p><?php endif; ?>
        </div>
      </div>
      <div class="col-12">
        <div class="card p-3">
          <h5 class="mb-3">Exam attempts <span class="badge admin-score-badge"><?= count($attempts) ?></span></h5>
          <?php if ($attempts): ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Student</th><th>Quiz</th><th>Submitted</th><th>Score</th><th></th></tr></thead><tbody>
            <?php foreach ($attempts as $attempt): ?>
              <tr><td><?= e($attempt['student_name']) ?></td><td><?= e($attempt['quiz_title']) ?></td><td><?= e(date('d M Y, H:i', strtotime($attempt['submitted_at']))) ?></td><td><?= (int) $attempt['score'] ?>/<?= (int) $attempt['total_marks'] ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('result.php?id=' . (int) $attempt['id'])) ?>">View result</a></td></tr>
            <?php endforeach; ?>
          </tbody></table></div><?php else: ?><p class="text-muted mb-0">No matching attempts.</p><?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
