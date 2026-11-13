<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_student();
$pdo = db();
$studentId = (int) $me['id'];

$statsQuery = $pdo->prepare(
    "SELECT COUNT(*) AS completed_exams,
            COALESCE(SUM(a.score), 0) AS total_score,
            COALESCE(AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END), 0) AS average_percent,
            COALESCE(MAX(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END), 0) AS best_percent
     FROM attempts a
     WHERE a.user_id = ?
       AND (NOT EXISTS (SELECT 1 FROM exam_workflows w WHERE w.quiz_id = a.quiz_id)
            OR EXISTS (SELECT 1 FROM result_publications rp WHERE rp.quiz_id = a.quiz_id))"
);
$statsQuery->execute([$studentId]);
$stats = $statsQuery->fetch();

$attendanceQuery = $pdo->prepare(
    "SELECT COUNT(*) AS records, COALESCE(SUM(a.status IN ('present','late')), 0) AS attended
     FROM faculty_attendance a
     WHERE a.student_id = ?"
);
$attendanceQuery->execute([$studentId]);
$attendance = $attendanceQuery->fetch();
$attendancePercent = (int) $attendance['records'] > 0
    ? round((int) $attendance['attended'] * 100 / (int) $attendance['records'], 1)
    : 0;

$upcomingQuery = $pdo->prepare(
    "SELECT q.id, q.title, q.tag, q.duration_minutes, q.passing_percent,
            w.scheduled_start, w.scheduled_end,
            COALESCE((SELECT SUM(questions.marks) FROM questions WHERE questions.quiz_id = q.id), 0) AS total_marks,
            EXISTS(SELECT 1 FROM attempts a WHERE a.quiz_id = q.id AND a.user_id = ?) AS already_attempted
     FROM quizzes q
     JOIN exam_workflows w ON w.quiz_id = q.id
     JOIN exam_assigned_students eas ON eas.quiz_id = q.id AND eas.student_id = ?
     WHERE q.is_published = 1 AND w.status = 'published'
       AND w.scheduled_end >= NOW()
     ORDER BY w.scheduled_start, q.id
     LIMIT 6"
);
$upcomingQuery->execute([$studentId, $studentId]);
$upcomingExams = $upcomingQuery->fetchAll();

$availableCountQuery = $pdo->prepare(
    "SELECT COUNT(*)
     FROM quizzes q LEFT JOIN exam_workflows w ON w.quiz_id = q.id
     WHERE q.is_published = 1
       AND (w.quiz_id IS NULL OR (w.status = 'published' AND EXISTS (
           SELECT 1 FROM exam_assigned_students assigned
           WHERE assigned.quiz_id = q.id AND assigned.student_id = ?
       )))"
);
$availableCountQuery->execute([$studentId]);
$availableExamCount = (int) $availableCountQuery->fetchColumn();

$timetableQuery = $pdo->prepare(
    "SELECT t.title, t.event_type, t.starts_at, t.ends_at, t.location,
            fp.subject, u.name AS faculty_name
     FROM faculty_timetable t
     JOIN faculty_student_assignments fsa ON fsa.faculty_id = t.faculty_id
     JOIN users u ON u.id = t.faculty_id
     LEFT JOIN faculty_profiles fp ON fp.user_id = t.faculty_id
     WHERE fsa.student_id = ? AND t.ends_at >= NOW()
     ORDER BY t.starts_at, t.id
     LIMIT 5"
);
$timetableQuery->execute([$studentId]);
$upcomingEvents = $timetableQuery->fetchAll();

$announcementQuery = $pdo->prepare(
    'SELECT a.id, a.title, a.body, a.category, a.created_at, u.name AS faculty_name
     FROM faculty_announcements a
     JOIN faculty_student_assignments fsa ON fsa.faculty_id = a.faculty_id AND fsa.student_id = ?
     JOIN users u ON u.id = a.faculty_id
     ORDER BY a.created_at DESC, a.id DESC LIMIT 4'
);
$announcementQuery->execute([$studentId]);
$recentAnnouncements = $announcementQuery->fetchAll();

$recentQuery = $pdo->prepare(
    "SELECT a.id, a.submitted_at, a.score, a.total_marks, q.title AS quiz_title, q.tag,
            q.passing_percent
     FROM attempts a
     JOIN quizzes q ON q.id = a.quiz_id
     WHERE a.user_id = ?
       AND (NOT EXISTS (SELECT 1 FROM exam_workflows w WHERE w.quiz_id = a.quiz_id)
            OR EXISTS (SELECT 1 FROM result_publications rp WHERE rp.quiz_id = a.quiz_id))
     ORDER BY a.submitted_at DESC LIMIT 5"
);
$recentQuery->execute([$studentId]);
$recentAttempts = $recentQuery->fetchAll();

$chartQuery = $pdo->prepare(
    "SELECT COALESCE(NULLIF(q.tag, ''), 'General') AS subject,
            ROUND(AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END), 1) AS average_percent,
            COUNT(*) AS completed
     FROM attempts a
     JOIN quizzes q ON q.id = a.quiz_id
     WHERE a.user_id = ?
       AND (NOT EXISTS (SELECT 1 FROM exam_workflows w WHERE w.quiz_id = a.quiz_id)
            OR EXISTS (SELECT 1 FROM result_publications rp WHERE rp.quiz_id = a.quiz_id))
     GROUP BY COALESCE(NULLIF(q.tag, ''), 'General')
     ORDER BY subject
     LIMIT 8"
);
$chartQuery->execute([$studentId]);
$subjectProgress = $chartQuery->fetchAll();

$trendQuery = $pdo->prepare(
    "SELECT a.submitted_at, a.score, a.total_marks
     FROM attempts a
     WHERE a.user_id = ?
       AND (NOT EXISTS (SELECT 1 FROM exam_workflows w WHERE w.quiz_id = a.quiz_id)
            OR EXISTS (SELECT 1 FROM result_publications rp WHERE rp.quiz_id = a.quiz_id))
     ORDER BY a.submitted_at DESC LIMIT 10"
);
$trendQuery->execute([$studentId]);
$scoreTrend = array_reverse($trendQuery->fetchAll());

$unreadQuery = $pdo->prepare(
    'SELECT id, type, title, message, link, created_at
     FROM notifications WHERE user_id = ? AND read_at IS NULL
     ORDER BY created_at DESC, id DESC LIMIT 4'
);
$unreadQuery->execute([$studentId]);
$recentUnreadNotifications = $unreadQuery->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
    <div>
      <span class="student-dashboard-kicker">STUDENT OVERVIEW</span>
      <h2 class="mb-1">Hello, <?= e(explode(' ', $me['name'])[0]) ?> 👋</h2>
      <p class="text-muted mb-0">Your upcoming schedule and published learning progress.</p>
    </div>
    <a href="<?= e(base_url('exams.php')) ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Browse exams</a>
  </div>

  <div class="row g-3 mb-4">
    <?php
    $cards = [
      ['Completed exams', (int) $stats['completed_exams'], 'bi-clipboard-check'],
      ['Total score', (int) $stats['total_score'], 'bi-stars'],
      ['Attendance', $attendancePercent . '%', 'bi-calendar2-check'],
      ['Average score', number_format((float) $stats['average_percent'], 1) . '%', 'bi-graph-up-arrow'],
      ['Best score', number_format((float) $stats['best_percent'], 1) . '%', 'bi-trophy'],
    ];
    foreach ($cards as [$label, $value, $icon]):
    ?>
      <div class="col-6 col-md-4 col-xl">
        <div class="card student-stat-card p-3 h-100">
          <div class="d-flex justify-content-between align-items-center gap-2">
            <div><div class="text-muted small text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold mt-1"><?= e((string) $value) ?></div></div>
            <div class="feature-icon"><i class="bi <?= e($icon) ?>"></i></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <section class="card p-3 p-lg-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div><h5 class="mb-1"><i class="bi bi-alarm"></i> Upcoming examinations</h5><p class="text-muted small mb-0">Exam access is limited to the approved schedule and assigned students.</p></div>
      <a class="small" href="<?= e(base_url('exams.php')) ?>">Browse exams <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (!$upcomingExams): ?>
      <div class="text-center text-muted py-4"><i class="bi bi-calendar-check display-6"></i><p class="mt-2 mb-0">No upcoming scheduled examinations.</p></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($upcomingExams as $exam):
          $startTimestamp = strtotime($exam['scheduled_start']);
          $endTimestamp = strtotime($exam['scheduled_end']);
          $now = time();
          $canStart = !$exam['already_attempted'] && $now >= $startTimestamp && $now < $endTimestamp;
          $status = $exam['already_attempted'] ? 'Submitted' : ($now < $startTimestamp ? 'Upcoming' : ($now < $endTimestamp ? 'Open now' : 'Closed'));
        ?>
          <div class="col-md-6 col-xl-4">
            <article class="student-exam-card h-100">
              <div class="d-flex justify-content-between gap-2 align-items-start">
                <span class="student-dashboard-tag"><?= e($exam['tag'] ?: 'General') ?></span>
                <span class="small fw-semibold <?= $canStart ? 'text-success' : 'text-muted' ?>" data-exam-status><?= e($status) ?></span>
              </div>
              <h6 class="mt-3 mb-2"><?= e($exam['title']) ?></h6>
              <div class="small text-muted d-grid gap-2">
                <span><i class="bi bi-calendar3"></i> <?= e(date('D, d M Y', $startTimestamp)) ?> · <?= e(date('H:i', $startTimestamp)) ?>–<?= e(date('H:i', $endTimestamp)) ?></span>
                <span><i class="bi bi-hourglass-split"></i> <?= (int) $exam['duration_minutes'] ?> min · <?= (int) $exam['total_marks'] ?> marks</span>
                <?php if (!$exam['already_attempted'] && $now < $endTimestamp): ?>
                  <span class="exam-countdown" data-start="<?= $startTimestamp ?>" data-end="<?= $endTimestamp ?>"></span>
                <?php endif; ?>
              </div>
              <?php if ($canStart): ?>
                <a class="btn btn-primary btn-sm mt-3" data-exam-action data-start="<?= $startTimestamp ?>" data-end="<?= $endTimestamp ?>" href="<?= e(base_url('take-exam.php?quiz=' . (int) $exam['id'])) ?>">Start exam <i class="bi bi-arrow-up-right"></i></a>
              <?php else: ?>
                <?php if (!$exam['already_attempted']): ?>
                  <a class="btn btn-primary btn-sm mt-3" data-exam-action data-start="<?= $startTimestamp ?>" data-end="<?= $endTimestamp ?>" href="<?= e(base_url('take-exam.php?quiz=' . (int) $exam['id'])) ?>" hidden>Start exam <i class="bi bi-arrow-up-right"></i></a>
                <?php else: ?>
                  <a class="btn btn-outline-secondary btn-sm mt-3" href="<?= e(base_url('exams.php')) ?>">View exams</a>
                <?php endif; ?>
              <?php endif; ?>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <section class="card p-3 p-lg-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div><h5 class="mb-1"><i class="bi bi-calendar-week"></i> My timetable</h5><p class="text-muted small mb-0">Classes and schedules from your assigned faculty.</p></div>
          <a class="small" href="<?= e(base_url('timetable.php')) ?>">Full timetable <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (!$upcomingEvents): ?>
          <p class="text-muted mb-0">No upcoming academic events are scheduled.</p>
        <?php else: ?>
          <div class="student-event-list">
            <?php foreach ($upcomingEvents as $event): ?>
              <article class="student-event-row">
                <div class="student-event-date"><strong><?= e(date('d', strtotime($event['starts_at']))) ?></strong><span><?= e(date('M', strtotime($event['starts_at']))) ?></span></div>
                <div class="flex-grow-1 min-w-0">
                  <div class="fw-semibold text-truncate"><?= e($event['title']) ?></div>
                  <small class="text-muted"><?= e($event['subject'] ?: 'Subject not specified') ?> · <?= e($event['faculty_name']) ?></small>
                  <div class="small text-muted"><?= e(date('H:i', strtotime($event['starts_at']))) ?>–<?= e(date('H:i', strtotime($event['ends_at']))) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?></div>
                </div>
                <span class="badge text-bg-light"><?= e(ucfirst($event['event_type'])) ?></span>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
    <div class="col-lg-5">
      <section class="card p-3 p-lg-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0"><i class="bi bi-megaphone"></i> Recent announcements</h5>
          <a class="small" href="<?= e(base_url('announcements.php')) ?>">View all</a>
        </div>
        <?php if (!$recentAnnouncements): ?>
          <p class="text-muted mb-0">No announcements from your assigned faculty.</p>
        <?php else: ?>
          <div class="vstack gap-3">
            <?php foreach ($recentAnnouncements as $announcement): ?>
              <article class="student-announcement">
                <div class="d-flex justify-content-between gap-2"><strong><?= e($announcement['title']) ?></strong><small class="text-muted text-nowrap"><?= e(date('d M', strtotime($announcement['created_at']))) ?></small></div>
                <p class="small text-muted mb-1 mt-1"><?= e(mb_strimwidth($announcement['body'], 0, 130, '…')) ?></p>
                <small class="student-announcement-by"><?= e($announcement['faculty_name']) ?> · <?= e(str_replace('_', ' ', ucfirst($announcement['category']))) ?></small>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <section class="card p-3 p-lg-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> Subject-wise performance</h5><a class="small" href="<?= e(base_url('history.php')) ?>">Results &amp; reports</a></div>
        <?php if (!$subjectProgress): ?>
          <div class="text-center text-muted py-5"><i class="bi bi-graph-up display-6"></i><p class="mt-2 mb-0">Published results will appear here after review.</p></div>
        <?php else: ?>
          <canvas id="subjectChart" height="130" aria-label="Average published score by subject"></canvas>
        <?php endif; ?>
        <?php if ($scoreTrend): ?>
          <div class="border-top mt-3 pt-3">
            <h6 class="small text-muted mb-2"><i class="bi bi-activity"></i> Recent score trend</h6>
            <canvas id="trendChart" height="90" aria-label="Recent published exam score trend"></canvas>
          </div>
        <?php endif; ?>
      </section>
    </div>
    <div class="col-lg-5">
      <section class="card p-3 p-lg-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0"><i class="bi bi-bell"></i> Notifications</h5><a class="small" href="<?= e(base_url('notifications.php')) ?>">All notifications</a></div>
        <?php if (!$recentUnreadNotifications): ?>
          <p class="text-muted mb-0">You’re all caught up.</p>
        <?php else: ?>
          <div class="vstack gap-2">
            <?php foreach ($recentUnreadNotifications as $notification):
              $notificationLink = (string) ($notification['link'] ?? '');
              if (!preg_match('~\A[a-zA-Z0-9._/-]+(?:\?[a-zA-Z0-9_=&%.-]*)?\z~', $notificationLink)) {
                  $notificationLink = 'notifications.php';
              }
            ?>
              <a class="student-notification" href="<?= e(base_url($notificationLink)) ?>">
                <span class="student-notification-dot"></span>
                <span class="min-w-0"><strong><?= e($notification['title']) ?></strong><small><?= e(mb_strimwidth($notification['message'], 0, 100, '…')) ?></small></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <section class="card p-3 p-lg-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent published results</h5><a class="small" href="<?= e(base_url('history.php')) ?>">Full history</a></div>
    <?php if (!$recentAttempts): ?>
      <p class="text-muted mb-0">No published results yet.</p>
    <?php else: ?>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Exam</th><th>Date</th><th>Marks</th><th>Result</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recentAttempts as $attempt):
            $percent = (int) $attempt['total_marks'] > 0 ? round((int) $attempt['score'] * 100 / (int) $attempt['total_marks'], 1) : 0;
            $passed = $percent >= (float) $attempt['passing_percent'];
          ?>
            <tr>
              <td><strong><?= e($attempt['quiz_title']) ?></strong><div class="small text-muted"><?= e($attempt['tag'] ?: 'General') ?></div></td>
              <td class="text-muted small"><?= e(date('d M Y', strtotime($attempt['submitted_at']))) ?></td>
              <td><?= (int) $attempt['score'] ?>/<?= (int) $attempt['total_marks'] ?> <span class="text-muted small">(<?= $percent ?>%)</span></td>
              <td><span class="badge bg-<?= $passed ? 'success' : 'danger' ?>"><?= $passed ? 'Pass' : 'Fail' ?></span></td>
              <td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('result.php?id=' . (int) $attempt['id'])) ?>">View result</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
<script>
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.exam-countdown').forEach((element) => {
    const start = Number(element.dataset.start) * 1000;
    const end = Number(element.dataset.end) * 1000;
    const update = () => {
      const now = Date.now();
      const seconds = Math.max(0, Math.floor(((now < start ? start : end) - now) / 1000));
      const days = Math.floor(seconds / 86400);
      const hours = Math.floor((seconds % 86400) / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      const remaining = seconds % 60;
      element.textContent = now < start
        ? `Starts in ${days ? days + 'd ' : ''}${hours}h ${minutes}m ${remaining}s`
        : `Closes in ${hours}h ${minutes}m ${remaining}s`;
      const card = element.closest('.student-exam-card');
      const action = card.querySelector('[data-exam-action]');
      const status = card.querySelector('[data-exam-status]');
      const open = now >= start && now < end;
      action.hidden = !open;
      status.textContent = open ? 'Open now' : (now < start ? 'Upcoming' : 'Closed');
      status.classList.toggle('text-success', open);
      status.classList.toggle('text-muted', !open);
    };
    update();
    window.setInterval(update, 1000);
  });
  <?php if ($subjectProgress): ?>
  new Chart(document.getElementById('subjectChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($subjectProgress, 'subject'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      datasets: [{
        label: 'Average score %',
        data: <?= json_encode(array_map(static fn(array $row): float => (float) $row['average_percent'], $subjectProgress)) ?>,
        backgroundColor: '#7F8C45',
        borderColor: '#606C38',
        borderWidth: 1,
        borderRadius: 8,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, max: 100, ticks: { callback: value => value + '%' } } }
    }
  });
  <?php endif; ?>
  <?php if ($scoreTrend): ?>
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode(array_map(static fn(array $row): string => date('d M', strtotime($row['submitted_at'])), $scoreTrend)) ?>,
      datasets: [{
        label: 'Score %',
        data: <?= json_encode(array_map(static fn(array $row): float => (int) $row['total_marks'] > 0 ? round((int) $row['score'] * 100 / (int) $row['total_marks'], 1) : 0, $scoreTrend)) ?>,
        fill: true,
        tension: .35,
        borderColor: '#606C38',
        backgroundColor: 'rgba(96,108,56,.14)',
        pointBackgroundColor: '#606C38',
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: value => value + '%' } } } }
  });
  <?php endif; ?>
});
</script>
<div class="container pb-4">
  <section class="card p-3 p-lg-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><h5 class="mb-1"><i class="bi bi-collection"></i> Available exams</h5><p class="text-muted small mb-0"><?= $availableExamCount ?> published exam<?= $availableExamCount === 1 ? '' : 's' ?> available to you.</p></div>
    <a href="<?= e(base_url('exams.php')) ?>" class="btn btn-outline-primary">See all exams <i class="bi bi-arrow-right"></i></a>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
