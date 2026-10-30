<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo   = db();

$action = $_GET['action'] ?? 'list';
$id     = (int) ($_GET['id'] ?? 0);
$errors = [];

function remove_quiz_thumbnail(string $imagePath): void
{
    if (!preg_match('~\Auploads/courses/[A-Za-z0-9_-]+\.(?:jpg|png|webp)\z~i', $imagePath)) {
        return;
    }

    $filePath = __DIR__ . '/../' . $imagePath;
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Unable to remove replaced course thumbnail: ' . $filePath);
    }
}

// ---------- CREATE / EDIT ----------
if ($action === 'new' || $action === 'edit') {
    $editing = null;
    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $editing = $stmt->fetch();
        if (!$editing) { flash('error','Quiz not found.'); redirect('admin/quizzes.php'); }
    }

    $vals = $editing ?: [
        'title' => '', 'tag' => '', 'intro' => '',
        'duration_minutes' => 5, 'passing_percent' => 50,
        'difficulty' => 'Beginner', 'is_published' => 1, 'image_path' => null,
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $vals['title']            = clean_string($_POST['title'] ?? '', 150);
        $vals['tag']              = clean_string($_POST['tag']   ?? '', 60);
        $vals['intro']            = clean_string($_POST['intro'] ?? '', 1000);
        $vals['duration_minutes'] = max(1, (int) ($_POST['duration_minutes'] ?? 5));
        $vals['passing_percent']  = max(0, min(100, (int) ($_POST['passing_percent'] ?? 50)));
        $vals['difficulty']       = clean_string($_POST['difficulty'] ?? '', 20);
        $vals['is_published']     = isset($_POST['is_published']) ? 1 : 0;
        $thumbnailUpload = $_FILES['thumbnail'] ?? null;
        $hasThumbnailUpload = is_array($thumbnailUpload) && ($thumbnailUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $thumbnailExtension = null;

        if (mb_strlen($vals['title']) < 3) $errors[] = 'Title is too short.';
        if (!in_array($vals['difficulty'], ['Beginner', 'Intermediate'], true)) $errors[] = 'Choose a valid difficulty level.';
        if ($hasThumbnailUpload) {
            if (($thumbnailUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'The thumbnail upload did not complete. Please try again.';
            } elseif (!is_uploaded_file($thumbnailUpload['tmp_name']) || (int) $thumbnailUpload['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Choose an image smaller than 5 MB.';
            } else {
                $imageInfo = getimagesize($thumbnailUpload['tmp_name']);
                $mimeExtensions = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                $imageMime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
                if (
                    !is_array($imageInfo)
                    || !isset($mimeExtensions[$imageMime])
                    || $imageInfo[0] < 1
                    || $imageInfo[1] < 1
                    || $imageInfo[0] > 8000
                    || $imageInfo[1] > 8000
                    || $imageInfo[0] * $imageInfo[1] > 40000000
                ) {
                    $errors[] = 'Use a valid JPEG, PNG, or WebP image no larger than 8000 × 8000 pixels.';
                } else {
                    $thumbnailExtension = $mimeExtensions[$imageMime];
                }
            }
        }

        if (!$errors) {
            $previousImagePath = (string) ($editing['image_path'] ?? '');
            $newImagePath = null;
            $newImageFile = null;
            if ($hasThumbnailUpload) {
                $uploadDirectory = __DIR__ . '/../uploads/courses';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Unable to create the course image upload directory.');
                }
                $filename = 'course-' . bin2hex(random_bytes(16)) . '.' . $thumbnailExtension;
                $newImageFile = $uploadDirectory . '/' . $filename;
                if (!move_uploaded_file($thumbnailUpload['tmp_name'], $newImageFile)) {
                    throw new RuntimeException('Unable to save the uploaded course thumbnail.');
                }
                $newImagePath = 'uploads/courses/' . $filename;
            }

            $imagePath = $newImagePath ?? (
                $editing && !isset($_POST['remove_thumbnail']) ? $previousImagePath : null
            );
            try {
                $pdo->beginTransaction();
                if ($editing) {
                    $stmt = $pdo->prepare("UPDATE quizzes SET title=?, tag=?, intro=?, duration_minutes=?, passing_percent=?, difficulty=?, is_published=?, image_path=? WHERE id=?");
                    $stmt->execute([$vals['title'], $vals['tag'] ?: null, $vals['intro'] ?: null, $vals['duration_minutes'], $vals['passing_percent'], $vals['difficulty'], $vals['is_published'], $imagePath, $editing['id']]);
                    $pdo->commit();
                    if ($previousImagePath !== '' && $previousImagePath !== $imagePath) {
                        remove_quiz_thumbnail($previousImagePath);
                    }
                    flash('success','Quiz updated.');
                    redirect('admin/quizzes.php');
                }

                $stmt = $pdo->prepare("INSERT INTO quizzes (title, tag, intro, duration_minutes, passing_percent, difficulty, is_published, created_by, image_path) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$vals['title'], $vals['tag'] ?: null, $vals['intro'] ?: null, $vals['duration_minutes'], $vals['passing_percent'], $vals['difficulty'], $vals['is_published'], $admin['id'], $imagePath]);
                $newId = (int) $pdo->lastInsertId();
                $pdo->commit();
                flash('success','Quiz created. Add questions now.');
                redirect('admin/questions.php?quiz=' . $newId);
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newImageFile !== null && is_file($newImageFile) && !unlink($newImageFile)) {
                    error_log('Unable to clean up failed course thumbnail upload: ' . $newImageFile);
                }
                throw $error;
            }
        }
    }

    $pageTitle = $editing ? 'Edit quiz' : 'New quiz';
    include __DIR__ . '/../includes/header.php';
    $adminActive = 'quizzes';
    include __DIR__ . '/_nav.php';
    ?>
    <div class="container">
      <h3 class="mb-3"><?= $editing ? 'Edit quiz' : 'New quiz' ?></h3>
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <div class="card p-4">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Title</label>
              <input class="form-control" name="title" required value="<?= e($vals['title']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Tag</label>
              <input class="form-control" name="tag" value="<?= e($vals['tag']) ?>" placeholder="PHP, JavaScript…">
            </div>
            <div class="col-12">
              <label class="form-label">Intro</label>
              <textarea class="form-control" name="intro" rows="2" data-autogrow><?= e($vals['intro']) ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="courseThumbnail">Course thumbnail</label>
              <input class="form-control" type="file" id="courseThumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp">
              <div class="form-text">Optional. JPEG, PNG, or WebP; maximum 5 MB. Images are displayed in a 16:9 crop.</div>
              <?php
                $previewPath = trim((string) ($vals['image_path'] ?? ''));
                if ($previewPath === '') $previewPath = 'assets/images/courses/course-default.jpg';
              ?>
              <img
                class="course-thumbnail-preview"
                id="courseThumbnailPreview"
                src="<?= e(base_url($previewPath)) ?>"
                alt="Current course thumbnail preview"
                width="1200"
                height="675"
                onerror="this.onerror=null;this.src='<?= e(base_url('assets/images/courses/course-default.jpg')) ?>';"
              >
              <?php if ($editing && !empty($editing['image_path'])): ?>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" name="remove_thumbnail" id="removeThumbnail">
                  <label class="form-check-label" for="removeThumbnail">Use the default course image</label>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-md-3">
              <label class="form-label">Duration (minutes)</label>
              <input type="number" class="form-control" name="duration_minutes" min="1" value="<?= (int) $vals['duration_minutes'] ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Passing %</label>
              <input type="number" class="form-control" name="passing_percent" min="0" max="100" value="<?= (int) $vals['passing_percent'] ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Difficulty</label>
              <select class="form-select" name="difficulty" required>
                <?php foreach (['Beginner', 'Intermediate'] as $level): ?>
                  <option value="<?= e($level) ?>" <?= $vals['difficulty']===$level?'selected':'' ?>><?= e($level) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_published" id="pubChk" <?= !empty($vals['is_published'])?'checked':'' ?>>
                <label class="form-check-label" for="pubChk">Published</label>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
            <a class="btn btn-link" href="<?= e(base_url('admin/quizzes.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; return;
}

// ---------- DELETE ----------
if ($action === 'delete' && $id > 0) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        flash('error', 'Use the delete form to safely remove a quiz.');
        redirect('admin/quizzes.php');
    }
    verify_csrf();
    $attemptCount = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE quiz_id=?");
    $attemptCount->execute([$id]);
    if ((int) $attemptCount->fetchColumn() > 0) {
        $pdo->prepare("UPDATE quizzes SET is_published=0 WHERE id=?")->execute([$id]);
        flash('error', 'This quiz has exam attempts, so it was unpublished instead. Existing results and certificates were preserved.');
    } else {
        $pdo->prepare("DELETE FROM quizzes WHERE id=?")->execute([$id]);
        flash('success','Quiz deleted.');
    }
    redirect('admin/quizzes.php');
}

// ---------- LIST ----------
$rows = $pdo->query("
    SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) AS qcount,
           (SELECT COUNT(*) FROM attempts WHERE quiz_id=q.id) AS acount,
           (SELECT COUNT(DISTINCT user_id) FROM attempts WHERE quiz_id=q.id) AS student_count
    FROM quizzes q
    ORDER BY q.created_at DESC
")->fetchAll();

$pageTitle = 'Admin · Quizzes';
include __DIR__ . '/../includes/header.php';
$adminActive = 'quizzes';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Quizzes</h3>
    <a class="btn btn-primary" href="<?= e(base_url('admin/quizzes.php?action=new')) ?>"><i class="bi bi-plus-lg"></i> New quiz</a>
  </div>
  <div class="card p-3">
    <?php if (!$rows): ?>
      <p class="text-muted text-center my-4">No quizzes yet. Create your first one!</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Title</th><th>Category</th><th>Difficulty</th><th class="text-center">Qs</th><th class="text-center">Students</th><th class="text-center">Attempts</th><th class="text-center">Duration</th><th class="text-center">Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['title']) ?></strong><br><small class="text-muted"><?= e($r['intro'] ?: '—') ?></small></td>
                <td><span class="exam-tag"><?= e($r['tag'] ?: '—') ?></span></td>
                <td><?= e($r['difficulty']) ?></td>
                <td class="text-center"><?= (int) $r['qcount'] ?></td>
                <td class="text-center"><?= (int) $r['student_count'] ?></td>
                <td class="text-center"><?= (int) $r['acount'] ?></td>
                <td class="text-center"><?= (int) $r['duration_minutes'] ?>m</td>
                <td class="text-center">
                  <?php if ($r['is_published']): ?>
                    <span class="badge bg-success">Published</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Draft</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/questions.php?quiz='.(int)$r['id'])) ?>"><i class="bi bi-list-check"></i></a>
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/quizzes.php?action=edit&id='.(int)$r['id'])) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" action="<?= e(base_url('admin/quizzes.php?action=delete&id='.(int)$r['id'])) ?>" class="d-inline" data-confirm="Delete this quiz if it has no attempts. Quizzes with history are unpublished to preserve results.">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete or unpublish quiz"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
