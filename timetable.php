<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$student = require_student();
$pdo = db();
$studentId = (int) $student['id'];
$eventId = filter_var($_GET['event'] ?? 0, FILTER_VALIDATE_INT);
if ($eventId !== false && $eventId > 0) {
    $pdo->prepare(
        "UPDATE notifications SET read_at = NOW()
         WHERE user_id = ? AND type = 'timetable_changed' AND link = ? AND read_at IS NULL"
    )->execute([$studentId, 'timetable.php?event=' . $eventId]);
}

$eventsQuery = $pdo->prepare(
    "SELECT event_id, title, event_type, starts_at, ends_at, location, subject, faculty_name, source
     FROM (
         SELECT t.id AS event_id, t.title, t.event_type, t.starts_at, t.ends_at, t.location,
                fp.subject, u.name AS faculty_name, 'academic' AS source
         FROM faculty_timetable t
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = t.faculty_id AND fsa.student_id = ?
         JOIN users u ON u.id = t.faculty_id
         LEFT JOIN faculty_profiles fp ON fp.user_id = t.faculty_id
         WHERE t.ends_at >= NOW()
         UNION ALL
         SELECT q.id AS event_id, q.title, 'exam' AS event_type, w.scheduled_start AS starts_at,
                w.scheduled_end AS ends_at, NULL AS location, q.tag AS subject, u.name AS faculty_name,
                'examination' AS source
         FROM exam_workflows w
         JOIN quizzes q ON q.id = w.quiz_id AND q.is_published = 1
         JOIN users u ON u.id = w.faculty_id
         JOIN exam_assigned_students eas ON eas.quiz_id = q.id AND eas.student_id = ?
         WHERE w.status = 'published' AND w.scheduled_end >= NOW()
     ) upcoming
     ORDER BY starts_at, event_id
     LIMIT 100"
);
$eventsQuery->execute([$studentId, $studentId]);
$events = $eventsQuery->fetchAll();

$pageTitle = 'My Timetable';
$activeNav = 'timetable';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><span class="student-dashboard-kicker">MY SCHEDULE</span><h2 class="mb-1">My timetable</h2><p class="text-muted mb-0">Upcoming events shared by your assigned faculty.</p></div>
    <a class="btn btn-outline-primary" href="<?= e(base_url('dashboard.php')) ?>"><i class="bi bi-arrow-left"></i> Dashboard</a>
  </div>
  <?php if (!$events): ?>
    <div class="card p-5 text-center text-muted"><i class="bi bi-calendar-x display-5"></i><h5 class="mt-3">No upcoming events</h5><p class="mb-0">Your assigned faculty have not scheduled any upcoming classes or examinations.</p></div>
  <?php else: ?>
    <div class="card p-3 p-lg-4">
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Subject / event</th><th>Date</th><th>Start</th><th>End</th><th>Location</th><th>Faculty</th></tr></thead>
        <tbody>
          <?php foreach ($events as $event): ?>
            <tr>
              <td><strong><?= e($event['title']) ?></strong><div class="small text-muted"><?= e($event['subject'] ?: 'Subject not specified') ?> · <?= e(ucfirst($event['event_type'])) ?></div></td>
              <td><?= e(date('D, d M Y', strtotime($event['starts_at']))) ?></td>
              <td><?= e(date('H:i', strtotime($event['starts_at']))) ?></td>
              <td><?= e(date('H:i', strtotime($event['ends_at']))) ?></td>
              <td><?= e($event['location'] ?: '—') ?></td>
              <td><?= e($event['faculty_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if (count($events) === 100): ?><p class="small text-muted mt-3 mb-0">Showing the next 100 scheduled events.</p><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
