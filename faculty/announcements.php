<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/faculty-tools.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$announcementId = filter_var($_GET['announcement'] ?? 0, FILTER_VALIDATE_INT);
if ($announcementId !== false && $announcementId > 0) {
    $pdo->prepare(
        "UPDATE notifications SET read_at = NOW()
         WHERE user_id = ? AND type = 'faculty_announcement' AND link = ? AND read_at IS NULL"
    )->execute([(int) $faculty['id'], 'announcements.php?announcement=' . $announcementId]);
}
$errors = [];
$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);

if ($editId > 0) {
    $query = $pdo->prepare('SELECT * FROM faculty_announcements WHERE id = ? AND faculty_id = ?');
    $query->execute([$editId, $facultyId]);
    $editing = $query->fetch() ?: null;
    if (!$editing) {
        http_response_code(404);
        die('Announcement not found.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $lookup = $pdo->prepare('SELECT title FROM faculty_announcements WHERE id = ? AND faculty_id = ?');
            $lookup->execute([$id, $facultyId]);
            $announcementTitle = $lookup->fetchColumn();
            if ($announcementTitle === false) {
                throw new InvalidArgumentException('This announcement is no longer available to delete.');
            }
            $pdo->beginTransaction();
            $delete = $pdo->prepare('DELETE FROM faculty_announcements WHERE id = ? AND faculty_id = ?');
            $delete->execute([$id, $facultyId]);
            if ($delete->rowCount() !== 1) {
                throw new InvalidArgumentException('This announcement is no longer available to delete.');
            }
            notify_faculty_students(
                $pdo,
                $facultyId,
                'faculty_announcement',
                'Faculty announcement removed',
                'The announcement "' . $announcementTitle . '" is no longer available.',
                'announcements.php?announcement=' . $id
            );
            $pdo->commit();
            flash('success', 'Announcement deleted.');
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = clean_string($_POST['title'] ?? '', 180);
            $body = trim((string) ($_POST['body'] ?? ''));
            $category = (string) ($_POST['category'] ?? 'general');
            if (mb_strlen($title) < 3 || mb_strlen($body) < 3 || mb_strlen($body) > 10000) {
                throw new InvalidArgumentException('Enter an announcement title and message between 3 and 10,000 characters.');
            }
            if (!in_array($category, ['general', 'exam_schedule', 'assignment', 'important'], true)) {
                throw new InvalidArgumentException('Choose a valid announcement category.');
            }
            if ($id > 0) {
                $pdo->beginTransaction();
                $save = $pdo->prepare(
                    'UPDATE faculty_announcements SET title = ?, body = ?, category = ?
                     WHERE id = ? AND faculty_id = ?'
                );
                $save->execute([$title, $body, $category, $id, $facultyId]);
                if ($save->rowCount() === 0) {
                    $owner = $pdo->prepare('SELECT 1 FROM faculty_announcements WHERE id = ? AND faculty_id = ?');
                    $owner->execute([$id, $facultyId]);
                    if (!$owner->fetchColumn()) {
                        throw new InvalidArgumentException('This announcement is no longer available to edit.');
                    }
                }
                notify_faculty_students(
                    $pdo,
                    $facultyId,
                    'faculty_announcement',
                    'Faculty announcement updated',
                    'Your faculty updated the announcement "' . $title . '".',
                    'announcements.php?announcement=' . $id
                );
                $pdo->commit();
                flash('success', 'Announcement updated.');
            } else {
                $pdo->beginTransaction();
                $save = $pdo->prepare(
                    'INSERT INTO faculty_announcements (faculty_id, title, body, category) VALUES (?, ?, ?, ?)'
                );
                $save->execute([$facultyId, $title, $body, $category]);
                $id = (int) $pdo->lastInsertId();
                notify_faculty_students(
                    $pdo,
                    $facultyId,
                    'faculty_announcement',
                    'New faculty announcement',
                    'Your faculty posted "' . $title . '".',
                    'announcements.php?announcement=' . $id
                );
                $pdo->commit();
                flash('success', 'Announcement published to your assigned students.');
            }
            redirect('faculty/announcements.php');
        } else {
            throw new InvalidArgumentException('Unknown announcement action.');
        }
        redirect('faculty/announcements.php');
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty announcement operation failed: ' . $error->getMessage());
        $errors[] = 'The announcement could not be saved. Please try again.';
    }
}

$announcementsQuery = $pdo->prepare(
    'SELECT a.*, (SELECT COUNT(*) FROM faculty_student_assignments fsa WHERE fsa.faculty_id = a.faculty_id) AS recipient_count
     FROM faculty_announcements a WHERE a.faculty_id = ? ORDER BY a.updated_at DESC'
);
$announcementsQuery->execute([$facultyId]);
$announcements = $announcementsQuery->fetchAll();

$pageTitle = 'Faculty · Announcements';
$activeNav = 'faculty-announcements';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'announcements';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="mb-4"><h2 class="mb-1">Announcements</h2><p class="text-muted mb-0">Messages are visible only to students currently assigned to you.</p></div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-5"><div class="card p-4">
      <h5><?= $editing ? 'Edit announcement' : 'Create announcement' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
        <div class="mb-3"><label class="form-label" for="announcementTitle">Title</label><input class="form-control" id="announcementTitle" name="title" maxlength="180" required value="<?= e($editing['title'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label" for="announcementCategory">Category</label><select class="form-select" id="announcementCategory" name="category">
          <?php foreach (['general' => 'Important message', 'exam_schedule' => 'Examination schedule', 'assignment' => 'Assignment', 'important' => 'Urgent notice'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($editing['category'] ?? 'general') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label class="form-label" for="announcementBody">Message</label><textarea class="form-control" id="announcementBody" name="body" rows="7" maxlength="10000" required><?= e($editing['body'] ?? '') ?></textarea></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> <?= $editing ? 'Save changes' : 'Publish announcement' ?></button>
        <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(base_url('faculty/announcements.php')) ?>">Cancel</a><?php endif; ?>
      </form>
    </div></div>
    <div class="col-lg-7"><div class="vstack gap-3">
      <?php foreach ($announcements as $announcement): ?>
        <article class="card p-4" id="announcement-<?= (int) $announcement['id'] ?>">
          <div class="d-flex justify-content-between gap-3">
            <div><span class="badge text-bg-light mb-2"><?= e(str_replace('_', ' ', ucfirst($announcement['category']))) ?></span><h5 class="mb-1"><?= e($announcement['title']) ?></h5></div>
            <span class="small text-muted text-nowrap"><?= e(date('d M Y, H:i', strtotime($announcement['updated_at']))) ?></span>
          </div>
          <p class="mb-2"><?= nl2br(e($announcement['body'])) ?></p>
          <div class="d-flex justify-content-between align-items-center mt-2"><small class="text-muted">Visible to <?= (int) $announcement['recipient_count'] ?> assigned students</small>
            <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('faculty/announcements.php?edit=' . (int) $announcement['id'])) ?>">Edit</a>
              <form method="post" data-confirm="Delete this announcement?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $announcement['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$announcements): ?><div class="card p-5 text-center text-muted">No announcements yet.</div><?php endif; ?>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
