<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$faculty = require_faculty();
$pdo = db();
$search = clean_string($_GET['q'] ?? '', 80);

$profile = $pdo->prepare(
    'SELECT branch, subject, max_students FROM faculty_profiles WHERE user_id = ?'
);
$profile->execute([(int) $faculty['id']]);
$facultyProfile = $profile->fetch();
if (!$facultyProfile) {
    http_response_code(409);
    die('Faculty profile is not configured. Contact the HOD.');
}

$statsQuery = $pdo->prepare(
    'SELECT COUNT(*) AS student_count,
            COALESCE(SUM(performance.attempt_count), 0) AS attempt_count,
            COALESCE(AVG(performance.average_percent), 0) AS average_percent
     FROM faculty_student_assignments assignment
     LEFT JOIN (
        SELECT user_id, COUNT(*) AS attempt_count,
               AVG(CASE WHEN total_marks > 0 THEN score * 100.0 / total_marks END) AS average_percent
        FROM attempts GROUP BY user_id
     ) performance ON performance.user_id = assignment.student_id
     WHERE assignment.faculty_id = ?'
);
$statsQuery->execute([(int) $faculty['id']]);
$stats = $statsQuery->fetch();

$examStatsQuery = $pdo->prepare(
    "SELECT COUNT(*) AS total_exams,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_approvals,
            SUM(CASE WHEN status IN ('approved','published') AND scheduled_start > NOW() THEN 1 ELSE 0 END) AS upcoming_exams,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_exams
     FROM exam_workflows WHERE faculty_id = ?"
);
$examStatsQuery->execute([(int) $faculty['id']]);
$examStats = $examStatsQuery->fetch();

$attendanceQuery = $pdo->prepare(
    "SELECT COUNT(*) AS records, SUM(status IN ('present','late')) AS attended
     FROM faculty_attendance WHERE faculty_id = ?"
);
$attendanceQuery->execute([(int) $faculty['id']]);
$attendanceStats = $attendanceQuery->fetch();
$attendancePercent = (int) $attendanceStats['records'] > 0
    ? round((int) $attendanceStats['attended'] * 100 / (int) $attendanceStats['records'], 1)
    : 0;

$pendingEvaluationQuery = $pdo->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM exam_workflows w
     JOIN exam_assigned_students eas ON eas.quiz_id = w.quiz_id
     JOIN faculty_student_assignments fsa ON fsa.faculty_id = w.faculty_id AND fsa.student_id = eas.student_id
     JOIN attempts a ON a.quiz_id = w.quiz_id AND a.user_id = eas.student_id
     JOIN questions q ON q.quiz_id = w.quiz_id AND q.question_type = 'coding'
     LEFT JOIN attempt_answers aa ON aa.attempt_id = a.id AND aa.question_id = q.id
     WHERE w.faculty_id = ? AND w.status = 'published'
       AND (aa.id IS NULL OR aa.awarded_marks IS NULL)"
);
$pendingEvaluationQuery->execute([(int) $faculty['id']]);
$pendingEvaluations = (int) $pendingEvaluationQuery->fetchColumn();

$upcomingQuery = $pdo->prepare(
    "SELECT title, event_type, starts_at, ends_at, location, source, item_id FROM (
       SELECT q.title, 'examination' AS event_type, w.scheduled_start AS starts_at, w.scheduled_end AS ends_at,
              NULL AS location, 'exam' AS source, q.id AS item_id
       FROM exam_workflows w JOIN quizzes q ON q.id = w.quiz_id
       WHERE w.faculty_id = ? AND w.status IN ('approved','published') AND w.scheduled_start >= NOW()
       UNION ALL
       SELECT title, event_type, starts_at, ends_at, location, 'event' AS source, id AS item_id
       FROM faculty_timetable WHERE faculty_id = ? AND starts_at >= NOW()
     ) upcoming ORDER BY starts_at LIMIT 6"
);
$upcomingQuery->execute([(int) $faculty['id'], (int) $faculty['id']]);
$upcomingItems = $upcomingQuery->fetchAll();

$sql = "
    SELECT u.id, u.name, u.email, sp.registration_no, sp.branch, sp.semester, sp.academic_year,
           COALESCE(performance.attempt_count, 0) AS attempt_count,
           COALESCE(performance.average_percent, 0) AS average_percent,
           COALESCE(performance.best_percent, 0) AS best_percent
    FROM faculty_student_assignments assignment
    JOIN users u ON u.id = assignment.student_id AND u.is_active = 1
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS attempt_count,
               AVG(CASE WHEN total_marks > 0 THEN score * 100.0 / total_marks END) AS average_percent,
               MAX(CASE WHEN total_marks > 0 THEN score * 100.0 / total_marks END) AS best_percent
        FROM attempts GROUP BY user_id
    ) performance ON performance.user_id = u.id
    WHERE assignment.faculty_id = ?
";
$params = [(int) $faculty['id']];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR sp.registration_no LIKE ? OR sp.branch LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$sql .= ' ORDER BY u.name LIMIT 100';
$studentsQuery = $pdo->prepare($sql);
$studentsQuery->execute($params);
$students = $studentsQuery->fetchAll();

$recentAttemptsQuery = $pdo->prepare(
    'SELECT a.id, a.score, a.total_marks, a.submitted_at, u.name AS student_name, q.title AS exam_title
     FROM faculty_student_assignments assigned
     JOIN attempts a ON a.user_id = assigned.student_id
     JOIN users u ON u.id = a.user_id
     JOIN quizzes q ON q.id = a.quiz_id
     WHERE assigned.faculty_id = ?
     ORDER BY a.submitted_at DESC LIMIT 8'
);
$recentAttemptsQuery->execute([(int) $faculty['id']]);
$recentAttempts = $recentAttemptsQuery->fetchAll();

$pageTitle = 'Faculty · Dashboard';
$activeNav = 'faculty-dashboard';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'dashboard';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
    <div>
      <h2 class="mb-1">Welcome, <?= e($faculty['name']) ?></h2>
      <p class="text-muted mb-0"><?= e(trim(($facultyProfile['branch'] ?? '') . ' · ' . ($facultyProfile['subject'] ?? ''), ' ·') ?: 'Faculty') ?></p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('faculty/students.php')) ?>"><i class="bi bi-people"></i> Student Directory</a>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['Assigned students', (int) $stats['student_count'] . ' / ' . (int) $facultyProfile['max_students'], 'bi-people', 'faculty/students.php'],
      ['Attendance rate', $attendancePercent . '%', 'bi-calendar2-check', 'faculty/attendance.php'],
      ['Student attempts', (int) $stats['attempt_count'], 'bi-clipboard-check'],
      ['Average student score', number_format((float) $stats['average_percent'], 1) . '%', 'bi-graph-up'],
    ] as $card):
      [$label, $value, $icon] = $card;
      $url = $card[3] ?? null;
    ?>
      <div class="col-6 col-xl-3"><?php if ($url): ?><a class="card p-3 h-100 text-decoration-none text-reset" href="<?= e(base_url($url)) ?>"><?php else: ?><div class="card p-3 h-100"><?php endif; ?><div class="d-flex justify-content-between align-items-center">
        <div><div class="text-muted small text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold"><?= e((string) $value) ?></div></div>
        <div class="feature-icon"><i class="bi <?= e($icon) ?>"></i></div>
      </div><?php if ($url): ?></a><?php else: ?></div><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['Examinations', (int) $examStats['total_exams'], 'bi-journal-check', 'faculty/exams.php'],
      ['Pending approvals', (int) $examStats['pending_approvals'], 'bi-hourglass-split', 'faculty/exams.php'],
      ['Upcoming', (int) $examStats['upcoming_exams'], 'bi-calendar-event', 'faculty/exams.php'],
      ['Pending evaluations', $pendingEvaluations, 'bi-code-square', 'faculty/results.php'],
      ['Completed', (int) $examStats['completed_exams'], 'bi-check2-circle', 'faculty/results.php'],
    ] as [$label, $value, $icon, $url]): ?>
      <div class="col-6 col-lg-3"><a class="card p-3 h-100 text-decoration-none text-reset" href="<?= e(base_url($url)) ?>">
        <div class="d-flex justify-content-between align-items-center"><div><div class="text-muted small text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold"><?= (int) $value ?></div></div><div class="feature-icon"><i class="bi <?= e($icon) ?>"></i></div></div>
      </a></div>
    <?php endforeach; ?>
  </div>
  <div class="card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-2 pt-2">
      <h5 class="mb-0">My students</h5>
      <form class="d-flex gap-2" method="get">
        <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search assigned students">
        <button class="btn btn-outline-primary" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
      </form>
    </div>
    <?php if (!$students): ?>
      <p class="text-muted text-center my-4"><?= $search === '' ? 'No students have been assigned to you yet.' : 'No assigned students match your search.' ?></p>
    <?php else: ?>
      <div class="table-responsive mt-3"><table class="table align-middle mb-0">
        <thead><tr><th>Student</th><th>Registration</th><th>Branch / semester</th><th>Attempts</th><th>Average</th><th>Best</th></tr></thead>
        <tbody><?php foreach ($students as $student): ?><tr>
          <td><?= e($student['name']) ?><div class="small text-muted"><?= e($student['email']) ?></div></td>
          <td><?= e($student['registration_no'] ?: '—') ?></td>
          <td><?= e(($student['branch'] ?: '—') . ($student['semester'] ? ' · Sem ' . $student['semester'] : '')) ?></td>
          <td><?= (int) $student['attempt_count'] ?></td>
          <td><?= number_format((float) $student['average_percent'], 1) ?>%</td>
          <td><?= number_format((float) $student['best_percent'], 1) ?>%</td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </div>
  <div class="row g-4 mt-1">
    <div class="col-lg-7"><div class="card p-4 h-100">
      <h5 class="mb-3">Assigned student performance</h5>
      <?php if (!$students): ?><p class="text-muted mb-0">Performance charts appear after students are assigned.</p>
      <?php else: ?><canvas id="facultyStudentChart" height="130"></canvas><?php endif; ?>
    </div></div>
    <div class="col-lg-5"><div class="card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Recent activity</h5><a class="small" href="<?= e(base_url('faculty/results.php')) ?>">Review results</a></div>
      <?php if (!$recentAttempts): ?><p class="text-muted mt-3 mb-0">No assigned student attempts yet.</p>
      <?php else: ?><div class="vstack gap-2 mt-2"><?php foreach ($recentAttempts as $attempt):
          $percent = (int) $attempt['total_marks'] > 0 ? round((int) $attempt['score'] * 100 / (int) $attempt['total_marks'], 1) : 0;
        ?><a class="d-flex justify-content-between align-items-center text-decoration-none p-2 rounded bg-soft" href="<?= e(base_url('result.php?id=' . (int) $attempt['id'])) ?>">
          <span><span class="fw-semibold small"><?= e($attempt['student_name']) ?></span><span class="d-block text-muted small"><?= e($attempt['exam_title']) ?> · <?= e(date('d M, H:i', strtotime($attempt['submitted_at']))) ?></span></span>
          <span class="badge bg-<?= $percent >= 50 ? 'success' : 'warning' ?>"><?= $percent ?>%</span>
        </a><?php endforeach; ?></div><?php endif; ?>
    </div></div>
  </div>
  <div class="card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Upcoming schedules</h5><a class="small" href="<?= e(base_url('faculty/timetable.php')) ?>">Open timetable</a></div>
    <?php if (!$upcomingItems): ?><p class="text-muted mb-0">No upcoming examinations or timetable events.</p>
    <?php else: ?><div class="row g-3"><?php foreach ($upcomingItems as $item): ?><div class="col-md-6 col-xl-4"><div class="border rounded p-3 h-100">
        <div class="d-flex justify-content-between gap-2"><strong><?= e($item['title']) ?></strong><span class="badge <?= $item['source'] === 'exam' ? 'bg-success' : 'bg-primary' ?>"><?= e(ucfirst($item['event_type'])) ?></span></div>
        <div class="small text-muted mt-2"><i class="bi bi-calendar-event"></i> <?= e(date('D, d M Y · H:i', strtotime($item['starts_at']))) ?></div>
        <?php if ($item['location']): ?><div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($item['location']) ?></div><?php endif; ?>
    </div></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<?php if ($students): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  new Chart(document.getElementById('facultyStudentChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($students, 'name'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      datasets: [{label: 'Average score %', data: <?= json_encode(array_map(static fn(array $student): float => round((float) $student['average_percent'], 1), $students)) ?>, backgroundColor: 'rgba(96,108,56,.72)', borderRadius: 6}]
    },
    options: {plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, max: 100}}}
  });
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
