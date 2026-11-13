<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/faculty-tools.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
$monthValue = (string) ($_GET['month'] ?? date('Y-m'));
$month = DateTimeImmutable::createFromFormat('!Y-m', $monthValue);
if (!$month || $month->format('Y-m') !== $monthValue) {
    http_response_code(400);
    die('Choose a valid calendar month.');
}

if ($editId > 0) {
    $query = $pdo->prepare('SELECT * FROM faculty_timetable WHERE id = ? AND faculty_id = ?');
    $query->execute([$editId, $facultyId]);
    $editing = $query->fetch() ?: null;
    if (!$editing) {
        http_response_code(404);
        die('Timetable event not found.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $lookup = $pdo->prepare('SELECT title FROM faculty_timetable WHERE id = ? AND faculty_id = ?');
            $lookup->execute([$id, $facultyId]);
            $eventTitle = $lookup->fetchColumn();
            if ($eventTitle === false) {
                throw new InvalidArgumentException('This event is no longer available to delete.');
            }
            $pdo->beginTransaction();
            $delete = $pdo->prepare('DELETE FROM faculty_timetable WHERE id = ? AND faculty_id = ?');
            $delete->execute([$id, $facultyId]);
            if ($delete->rowCount() !== 1) {
                throw new InvalidArgumentException('This event is no longer available to delete.');
            }
            notify_faculty_students(
                $pdo,
                $facultyId,
                'timetable_changed',
                'Timetable event removed',
                'Your faculty removed "' . $eventTitle . '" from the timetable.',
                'timetable.php?event=' . $id
            );
            $pdo->commit();
            flash('success', 'Timetable event deleted.');
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = clean_string($_POST['title'] ?? '', 180);
            $description = trim((string) ($_POST['description'] ?? ''));
            $type = (string) ($_POST['event_type'] ?? 'class');
            $startRaw = (string) ($_POST['starts_at'] ?? '');
            $endRaw = (string) ($_POST['ends_at'] ?? '');
            $location = clean_string($_POST['location'] ?? '', 180);
            if (mb_strlen($title) < 3 || !in_array($type, ['class', 'exam', 'assignment', 'other'], true)) {
                throw new InvalidArgumentException('Enter an event title and select a valid event type.');
            }
            if (mb_strlen($description) > 5000) {
                throw new InvalidArgumentException('Event description must be no more than 5,000 characters.');
            }
            validate_faculty_timetable_slot($pdo, $facultyId, $startRaw, $endRaw, $id);
            $values = [
                $title,
                $description ?: null,
                $type,
                str_replace('T', ' ', $startRaw) . ':00',
                str_replace('T', ' ', $endRaw) . ':00',
                $location ?: null,
            ];
            if ($id > 0) {
                $pdo->beginTransaction();
                $save = $pdo->prepare(
                    'UPDATE faculty_timetable SET title = ?, description = ?, event_type = ?,
                     starts_at = ?, ends_at = ?, location = ? WHERE id = ? AND faculty_id = ?'
                );
                $save->execute([...$values, $id, $facultyId]);
                if ($save->rowCount() === 0) {
                    $owner = $pdo->prepare('SELECT 1 FROM faculty_timetable WHERE id = ? AND faculty_id = ?');
                    $owner->execute([$id, $facultyId]);
                    if (!$owner->fetchColumn()) {
                        throw new InvalidArgumentException('This event is no longer available to edit.');
                    }
                }
                notify_faculty_students(
                    $pdo,
                    $facultyId,
                    'timetable_changed',
                    'Timetable event updated',
                    'Your faculty updated "' . $title . '".',
                    'timetable.php?event=' . $id
                );
                $pdo->commit();
                flash('success', 'Timetable event updated.');
            } else {
                $pdo->beginTransaction();
                $save = $pdo->prepare(
                    'INSERT INTO faculty_timetable
                     (faculty_id, title, description, event_type, starts_at, ends_at, location)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $save->execute([$facultyId, ...$values]);
                $id = (int) $pdo->lastInsertId();
                notify_faculty_students(
                    $pdo,
                    $facultyId,
                    'timetable_changed',
                    'New timetable event',
                    'Your faculty scheduled "' . $title . '".',
                    'timetable.php?event=' . $id
                );
                $pdo->commit();
                flash('success', 'Timetable event created.');
            }
            redirect('faculty/timetable.php?month=' . rawurlencode(substr($startRaw, 0, 7)));
        } else {
            throw new InvalidArgumentException('Unknown timetable action.');
        }
        redirect('faculty/timetable.php?month=' . rawurlencode($monthValue));
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty timetable operation failed: ' . $error->getMessage());
        $errors[] = 'The timetable event could not be saved. Please try again.';
    }
}

$monthStart = $month->modify('first day of this month')->setTime(0, 0);
$monthEnd = $monthStart->modify('first day of next month');
$calendarStart = $monthStart->modify('monday this week');
$calendarEnd = $calendarStart->modify('+6 weeks');
$itemsQuery = $pdo->prepare(
    "SELECT id, title, event_type, starts_at, ends_at, location, 'event' AS source
     FROM faculty_timetable
     WHERE faculty_id = ? AND starts_at < ? AND ends_at >= ?
     UNION ALL
     SELECT q.id, q.title, 'exam' AS event_type, w.scheduled_start, w.scheduled_end, NULL AS location, 'exam' AS source
     FROM exam_workflows w JOIN quizzes q ON q.id = w.quiz_id
     WHERE w.faculty_id = ? AND w.status IN ('approved','published')
       AND w.scheduled_start < ? AND w.scheduled_end >= ?
     ORDER BY starts_at"
);
$itemsQuery->execute([
    $facultyId,
    $calendarEnd->format('Y-m-d H:i:s'),
    $calendarStart->format('Y-m-d H:i:s'),
    $facultyId,
    $calendarEnd->format('Y-m-d H:i:s'),
    $calendarStart->format('Y-m-d H:i:s'),
]);
$calendarEvents = [];
foreach ($itemsQuery->fetchAll() as $item) {
    $day = substr($item['starts_at'], 0, 10);
    $calendarEvents[$day][] = $item;
}

$pageTitle = 'Faculty · Timetable';
$activeNav = 'faculty-timetable';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'timetable';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Timetable management</h2><p class="text-muted mb-0">Manage academic events and view approved examination schedules.</p></div>
    <form method="get" class="d-flex gap-2"><input class="form-control" type="month" name="month" value="<?= e($monthValue) ?>"><button class="btn btn-outline-primary" type="submit">Show month</button></form>
  </div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-4"><div class="card p-4">
      <h5><?= $editing ? 'Edit timetable event' : 'Add timetable event' ?></h5>
      <p class="small text-muted">Conflicts with existing events and approved examinations for your students are blocked.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
        <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="180" required value="<?= e($editing['title'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="event_type">
          <?php foreach (['class' => 'Academic class', 'exam' => 'Examination', 'assignment' => 'Assignment', 'other' => 'Other'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($editing['event_type'] ?? 'class') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label class="form-label">Starts</label><input class="form-control" type="datetime-local" name="starts_at" required value="<?= e(isset($editing['starts_at']) ? date('Y-m-d\TH:i', strtotime($editing['starts_at'])) : '') ?>"></div>
        <div class="mb-3"><label class="form-label">Ends</label><input class="form-control" type="datetime-local" name="ends_at" required value="<?= e(isset($editing['ends_at']) ? date('Y-m-d\TH:i', strtotime($editing['ends_at'])) : '') ?>"></div>
        <div class="mb-3"><label class="form-label">Location (optional)</label><input class="form-control" name="location" maxlength="180" value="<?= e($editing['location'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">Details (optional)</label><textarea class="form-control" name="description" rows="3" maxlength="5000"><?= e($editing['description'] ?? '') ?></textarea></div>
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add to timetable' ?></button>
        <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(base_url('faculty/timetable.php?month=' . rawurlencode($monthValue))) ?>">Cancel</a><?php endif; ?>
      </form>
    </div></div>
    <div class="col-lg-8"><div class="card p-3 p-lg-4">
      <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0"><?= e($month->format('F Y')) ?></h5><div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('faculty/timetable.php?month=' . $month->modify('-1 month')->format('Y-m'))) ?>" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('faculty/timetable.php?month=' . date('Y-m'))) ?>">Today</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('faculty/timetable.php?month=' . $month->modify('+1 month')->format('Y-m'))) ?>" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
      </div></div>
      <div class="faculty-calendar-scroll"><div class="faculty-calendar">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?><div class="faculty-calendar-heading"><?= e($weekday) ?></div><?php endforeach; ?>
        <?php for ($day = $calendarStart; $day < $calendarEnd; $day = $day->modify('+1 day')):
          $dayKey = $day->format('Y-m-d');
          $outsideMonth = $day->format('Y-m') !== $monthValue;
        ?><div class="faculty-calendar-day <?= $outsideMonth ? 'is-outside' : '' ?> <?= $dayKey === date('Y-m-d') ? 'is-today' : '' ?>">
          <div class="faculty-calendar-date"><?= (int) $day->format('j') ?></div>
          <?php foreach ($calendarEvents[$dayKey] ?? [] as $event): ?>
            <div class="faculty-calendar-event <?= $event['source'] === 'exam' ? 'is-exam' : '' ?>" title="<?= e($event['title']) ?>">
              <span><?= e(date('H:i', strtotime($event['starts_at']))) ?></span> <?= e($event['title']) ?>
              <?php if ($event['source'] === 'event'): ?><a href="<?= e(base_url('faculty/timetable.php?edit=' . (int) $event['id'] . '&month=' . rawurlencode($monthValue))) ?>" aria-label="Edit <?= e($event['title']) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div><?php endfor; ?>
      </div></div>
      <div class="d-flex gap-3 small text-muted mt-3"><span><i class="bi bi-circle-fill text-primary"></i> Academic event</span><span><i class="bi bi-circle-fill text-success"></i> Approved examination</span></div>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
