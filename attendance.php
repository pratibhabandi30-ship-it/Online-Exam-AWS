<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$student = require_student();
$pdo = db();
$studentId = (int) $student['id'];
$monthValue = (string) ($_GET['month'] ?? date('Y-m'));
$month = DateTimeImmutable::createFromFormat('!Y-m', $monthValue);
if (!$month || $month->format('Y-m') !== $monthValue) {
    http_response_code(400);
    die('Choose a valid attendance month.');
}
$monthStart = $month->format('Y-m-01');
$monthEnd = $month->modify('first day of next month')->format('Y-m-01');

$summaryQuery = $pdo->prepare(
    "SELECT COUNT(*) AS records,
            COALESCE(SUM(a.status = 'present'), 0) AS present_count,
            COALESCE(SUM(a.status = 'absent'), 0) AS absent_count,
            COALESCE(SUM(a.status = 'late'), 0) AS late_count,
            COALESCE(SUM(a.status IN ('present','late')), 0) AS attended_count
     FROM faculty_attendance a
     WHERE a.student_id = ?"
);
$summaryQuery->execute([$studentId]);
$summary = $summaryQuery->fetch();
$overallPercent = (int) $summary['records'] > 0
    ? round((int) $summary['attended_count'] * 100 / (int) $summary['records'], 1)
    : 0;

$recordsQuery = $pdo->prepare(
    "SELECT a.attendance_date, a.status, a.remarks, u.name AS faculty_name, fp.subject
     FROM faculty_attendance a
     JOIN users u ON u.id = a.faculty_id
     LEFT JOIN faculty_profiles fp ON fp.user_id = a.faculty_id
     WHERE a.student_id = ? AND a.attendance_date >= ? AND a.attendance_date < ?
     ORDER BY a.attendance_date DESC, u.name"
);
$recordsQuery->execute([$studentId, $monthStart, $monthEnd]);
$records = $recordsQuery->fetchAll();

$subjectQuery = $pdo->prepare(
    "SELECT COALESCE(NULLIF(fp.subject, ''), 'Subject not specified') AS subject,
            COUNT(*) AS records, SUM(a.status IN ('present','late')) AS attended
     FROM faculty_attendance a
     LEFT JOIN faculty_profiles fp ON fp.user_id = a.faculty_id
     WHERE a.student_id = ?
     GROUP BY COALESCE(NULLIF(fp.subject, ''), 'Subject not specified')
     ORDER BY subject"
);
$subjectQuery->execute([$studentId]);
$subjectStats = $subjectQuery->fetchAll();

$pageTitle = 'My Attendance';
$activeNav = 'attendance';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><span class="student-dashboard-kicker">MY RECORDS</span><h2 class="mb-1">My attendance</h2><p class="text-muted mb-0">Your saved attendance history, including records from previous faculty assignments.</p></div>
    <form method="get" class="d-flex gap-2"><input class="form-control" type="month" name="month" value="<?= e($monthValue) ?>" aria-label="Attendance month"><button class="btn btn-primary" type="submit">View month</button></form>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['Overall attendance', $overallPercent . '%', 'bi-percent'],
      ['Present', (int) $summary['present_count'], 'bi-check-circle'],
      ['Absent', (int) $summary['absent_count'], 'bi-x-circle'],
      ['Late', (int) $summary['late_count'], 'bi-clock'],
    ] as [$label, $value, $icon]): ?>
      <div class="col-6 col-xl-3"><div class="card student-stat-card p-3 h-100"><div class="d-flex align-items-center justify-content-between gap-2"><div><div class="text-muted small text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold"><?= e((string) $value) ?></div></div><div class="feature-icon"><i class="bi <?= e($icon) ?>"></i></div></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-4">
    <div class="col-lg-8">
      <section class="card p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-1"><?= e($month->format('F Y')) ?> history</h5><p class="text-muted small mb-0"><?= count($records) ?> attendance records</p></div></div>
        <?php if (!$records): ?>
          <div class="text-center text-muted py-5"><i class="bi bi-calendar2-x display-6"></i><p class="mt-2 mb-0">No attendance records for this month.</p></div>
        <?php else: ?>
          <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Date</th><th>Status</th><th>Subject</th><th>Faculty</th><th>Remarks</th></tr></thead>
            <tbody><?php foreach ($records as $record):
              $status = ucfirst($record['status']);
              $badge = $record['status'] === 'present' ? 'success' : ($record['status'] === 'late' ? 'warning' : 'danger');
            ?><tr>
              <td><?= e(date('D, d M Y', strtotime($record['attendance_date']))) ?></td>
              <td><span class="badge bg-<?= e($badge) ?>"><?= e($status) ?></span></td>
              <td><?= e($record['subject'] ?: 'Not specified') ?></td>
              <td><?= e($record['faculty_name']) ?></td>
              <td class="text-muted"><?= e($record['remarks'] ?: '—') ?></td>
            </tr><?php endforeach; ?></tbody>
          </table></div>
        <?php endif; ?>
      </section>
    </div>
    <div class="col-lg-4">
      <section class="card p-3 p-lg-4 h-100">
        <h5 class="mb-3"><i class="bi bi-journal-bookmark"></i> Subject-wise attendance</h5>
        <?php if (!$subjectStats): ?>
          <p class="text-muted mb-0">No attendance has been recorded yet.</p>
        <?php else: ?>
          <div class="vstack gap-3">
            <?php foreach ($subjectStats as $subject):
              $percent = (int) $subject['records'] > 0 ? round((int) $subject['attended'] * 100 / (int) $subject['records'], 1) : 0;
            ?>
              <div><div class="d-flex justify-content-between gap-2"><strong><?= e($subject['subject']) ?></strong><span><?= $percent ?>%</span></div><div class="progress mt-2" role="progressbar" aria-label="<?= e($subject['subject']) ?> attendance" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar student-progress-bar" style="width: <?= $percent ?>%"></div></div><small class="text-muted"><?= (int) $subject['attended'] ?> of <?= (int) $subject['records'] ?> attended (present or late)</small></div>
            <?php endforeach; ?>
          </div>
          <p class="small text-muted mt-3 mb-0">Subject labels use the subject saved in each faculty profile. Existing attendance records do not store a separate subject.</p>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
