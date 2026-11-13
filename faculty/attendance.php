<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/faculty-tools.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$errors = [];
$date = (string) ($_GET['date'] ?? $_POST['attendance_date'] ?? date('Y-m-d'));
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
    http_response_code(400);
    die('Choose a valid attendance date.');
}

$studentsQuery = $pdo->prepare(
    "SELECT u.id, u.name, u.email, sp.registration_no
     FROM faculty_student_assignments fsa
     JOIN users u ON u.id = fsa.student_id AND u.role = 'student' AND u.is_active = 1
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     WHERE fsa.faculty_id = ? ORDER BY u.name"
);
$studentsQuery->execute([$facultyId]);
$students = $studentsQuery->fetchAll();
$assignedIds = array_map(static fn(array $student): int => (int) $student['id'], $students);
$submitted = is_array($_POST['attendance'] ?? null) ? $_POST['attendance'] : [];

if (isset($_GET['download']) && $_GET['download'] === 'excel') {
    $recordsQuery = $pdo->prepare(
        'SELECT u.name, sp.registration_no, a.attendance_date, a.status, a.remarks
         FROM faculty_attendance a
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = a.faculty_id AND fsa.student_id = a.student_id
         JOIN users u ON u.id = a.student_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE a.faculty_id = ? AND a.attendance_date = ? AND u.is_active = 1
         ORDER BY u.name'
    );
    $recordsQuery->execute([$facultyId, $date]);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-' . $date . '.csv"');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to open attendance export stream.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Student', 'Registration', 'Date', 'Status', 'Remarks'], ',', '"', '');
    foreach ($recordsQuery->fetchAll() as $record) {
        fputcsv($output, array_map('faculty_csv_safe_cell', [
            $record['name'],
            $record['registration_no'],
            $record['attendance_date'],
            ucfirst($record['status']),
            $record['remarks'],
        ]), ',', '"', '');
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $statuses = ['present', 'absent', 'late'];
    try {
        if (!$students) {
            throw new InvalidArgumentException('You do not have any assigned students to mark.');
        }
        if (count($submitted) !== count($assignedIds)) {
            throw new InvalidArgumentException('Choose an attendance status for every assigned student before saving.');
        }
        foreach ($assignedIds as $studentId) {
            if (!array_key_exists($studentId, $submitted) || !is_array($submitted[$studentId])) {
                throw new InvalidArgumentException('Attendance contains a student who is not assigned to you.');
            }
            if (!in_array($submitted[$studentId]['status'] ?? '', $statuses, true)) {
                throw new InvalidArgumentException('Choose Present, Absent, or Late for every assigned student.');
            }
        }

        $pdo->beginTransaction();
        $currentAssignments = $pdo->prepare(
            "SELECT fsa.student_id
             FROM faculty_student_assignments fsa
             JOIN users u ON u.id = fsa.student_id AND u.role = 'student' AND u.is_active = 1
             WHERE fsa.faculty_id = ? FOR UPDATE"
        );
        $currentAssignments->execute([$facultyId]);
        $currentAssignedIds = array_map('intval', $currentAssignments->fetchAll(PDO::FETCH_COLUMN));
        sort($currentAssignedIds);
        $postedIds = array_map('intval', array_keys($submitted));
        sort($postedIds);
        if ($postedIds !== $currentAssignedIds) {
            throw new InvalidArgumentException('The assigned student list changed. Reload attendance and try again.');
        }

        $save = $pdo->prepare(
            'INSERT INTO faculty_attendance (faculty_id, student_id, attendance_date, status, remarks)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)'
        );
        foreach ($assignedIds as $studentId) {
            $record = $submitted[$studentId];
            $save->execute([
                $facultyId,
                $studentId,
                $date,
                $record['status'],
                clean_string($record['remarks'] ?? '', 500) ?: null,
            ]);
        }
        $pdo->commit();
        flash('success', 'Attendance saved for ' . count($assignedIds) . ' assigned students.');
        redirect('faculty/attendance.php?date=' . rawurlencode($date));
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty attendance save failed: ' . $error->getMessage());
        $errors[] = 'Attendance could not be saved. Please try again.';
    }
}

$recordQuery = $pdo->prepare(
    'SELECT student_id, status, remarks FROM faculty_attendance
     WHERE faculty_id = ? AND attendance_date = ?'
);
$recordQuery->execute([$facultyId, $date]);
$records = [];
foreach ($recordQuery->fetchAll() as $record) {
    $records[(int) $record['student_id']] = $record;
}
$summaryQuery = $pdo->prepare(
    "SELECT COUNT(*) AS recorded,
            COALESCE(SUM(a.status = 'present'), 0) AS present_count,
            COALESCE(SUM(a.status = 'absent'), 0) AS absent_count,
            COALESCE(SUM(a.status = 'late'), 0) AS late_count
     FROM faculty_attendance a
     JOIN faculty_student_assignments fsa
       ON fsa.faculty_id = a.faculty_id AND fsa.student_id = a.student_id
     WHERE a.faculty_id = ? AND a.attendance_date = ?"
);
$summaryQuery->execute([$facultyId, $date]);
$summary = $summaryQuery->fetch();
$overallQuery = $pdo->prepare(
    "SELECT COUNT(*) AS records, SUM(status IN ('present','late')) AS attended
     FROM faculty_attendance WHERE faculty_id = ?"
);
$overallQuery->execute([$facultyId]);
$overall = $overallQuery->fetch();
$attendancePercent = (int) $overall['records'] > 0
    ? round((int) $overall['attended'] * 100 / (int) $overall['records'], 1)
    : 0;
$selectedCounts = ['present' => 0, 'absent' => 0, 'late' => 0];
$markedCount = 0;
foreach ($students as $student) {
    $studentId = (int) $student['id'];
    $record = is_array($submitted[$studentId] ?? null)
        ? $submitted[$studentId]
        : ($records[$studentId] ?? []);
    $status = $record['status'] ?? '';
    if (isset($selectedCounts[$status])) {
        $selectedCounts[$status]++;
        $markedCount++;
    }
}
$studentAttendanceQuery = $pdo->prepare(
    "SELECT student_id, COUNT(*) AS total, SUM(status IN ('present','late')) AS attended
     FROM faculty_attendance WHERE faculty_id = ? GROUP BY student_id"
);
$studentAttendanceQuery->execute([$facultyId]);
$studentAttendance = [];
foreach ($studentAttendanceQuery->fetchAll() as $row) {
    $studentAttendance[(int) $row['student_id']] = (int) $row['total'] > 0
        ? round((int) $row['attended'] * 100 / (int) $row['total'], 1)
        : 0;
}

$pageTitle = 'Faculty · Attendance';
$activeNav = 'faculty-attendance';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'attendance';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Attendance management</h2><p class="text-muted mb-0">Mark attendance for students assigned to you.</p></div>
    <div class="d-flex flex-wrap gap-2">
      <form method="get" class="d-flex gap-2"><input class="form-control" type="date" name="date" value="<?= e($date) ?>" required><button class="btn btn-outline-primary" type="submit">View date</button></form>
      <a class="btn btn-outline-secondary" href="<?= e(base_url('faculty/attendance.php?download=excel&date=' . rawurlencode($date))) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Excel CSV</a>
    </div>
  </div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['Selected present', $selectedCounts['present'], 'bi-check-circle', 'present'],
      ['Selected absent', $selectedCounts['absent'], 'bi-x-circle', 'absent'],
      ['Selected late', $selectedCounts['late'], 'bi-clock', 'late'],
    ] as [$label, $value, $icon, $status]): ?>
      <div class="col-6 col-xl-3"><div class="card p-3 h-100"><div class="d-flex align-items-center justify-content-between"><div><div class="small text-muted text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold" data-attendance-status-count="<?= e($status) ?>"><?= e((string) $value) ?></div></div><div class="feature-icon"><i class="bi <?= e($icon) ?>"></i></div></div></div></div>
    <?php endforeach; ?>
    <div class="col-6 col-xl-3"><div class="card p-3 h-100"><div class="small text-muted text-uppercase">Overall attendance</div><div class="fs-3 fw-bold"><?= e((string) $attendancePercent) ?>%</div><small class="text-muted">Based on all saved records</small></div></div>
  </div>
  <div class="card p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <strong>Saved attendance for <?= e(date('d M Y', strtotime($date))) ?></strong>
      <span class="text-muted small"><?= (int) ($summary['recorded'] ?? 0) ?> of <?= count($students) ?> records saved</span>
    </div>
    <div class="small text-muted mt-2">Present: <?= (int) ($summary['present_count'] ?? 0) ?> · Absent: <?= (int) ($summary['absent_count'] ?? 0) ?> · Late: <?= (int) ($summary['late_count'] ?? 0) ?></div>
  </div>
  <form method="post" class="card p-3 p-lg-4">
    <?= csrf_field() ?><input type="hidden" name="attendance_date" value="<?= e($date) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div><h5 class="mb-0"><?= e(date('l, d F Y', strtotime($date))) ?></h5><small class="text-muted"><span data-attendance-marked><?= $markedCount ?></span> of <?= count($students) ?> students marked · changes are not saved until you click Save Attendance</small></div>
      <button class="btn btn-primary" type="submit" data-attendance-submit disabled><i class="bi bi-save"></i> Save attendance</button>
    </div>
    <?php if (!$students): ?><div class="text-center text-muted py-5">No students are assigned to your faculty account.</div>
    <?php else: ?><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Student</th><th>Registration</th><th>Status</th><th>Remarks</th><th>Attendance</th></tr></thead>
      <tbody>      <?php foreach ($students as $student):
        $studentId = (int) $student['id'];
        $record = $records[$studentId] ?? [];
        $draft = is_array($submitted[$studentId] ?? null) ? $submitted[$studentId] : [];
        $statusValue = $draft['status'] ?? $record['status'] ?? '';
        $remarksValue = $draft['remarks'] ?? $record['remarks'] ?? '';
        $percent = $studentAttendance[$studentId] ?? 0;
      ?><tr>
        <td><?= e($student['name']) ?><div class="small text-muted"><?= e($student['email']) ?></div></td>
        <td><?= e($student['registration_no'] ?: '—') ?></td>
        <td><select class="form-select" name="attendance[<?= $studentId ?>][status]" required data-attendance-status aria-label="Attendance status for <?= e($student['name']) ?>">
          <option value="" <?= $statusValue === '' ? 'selected' : '' ?> disabled>Select status</option>
          <?php foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late'] as $status => $label): ?>
            <option value="<?= e($status) ?>" <?= $statusValue === $status ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select></td>
        <td><input class="form-control" name="attendance[<?= $studentId ?>][remarks]" maxlength="500" value="<?= e($remarksValue) ?>" placeholder="Optional"></td>
        <td><?= $percent ?>%</td>
      </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </form>
</div>
<script>
(() => {
  const form = document.querySelector('form.card');
  if (!form) return;
  const selectors = Array.from(form.querySelectorAll('[data-attendance-status]'));
  const counts = {
    present: document.querySelector('[data-attendance-status-count="present"]'),
    absent: document.querySelector('[data-attendance-status-count="absent"]'),
    late: document.querySelector('[data-attendance-status-count="late"]')
  };
  const marked = document.querySelector('[data-attendance-marked]');
  const submit = form.querySelector('[data-attendance-submit]');
  const updateCounts = () => {
    const selected = { present: 0, absent: 0, late: 0 };
    let markedCount = 0;
    selectors.forEach((select) => {
      if (Object.prototype.hasOwnProperty.call(selected, select.value)) {
        selected[select.value]++;
        markedCount++;
      }
    });
    Object.keys(selected).forEach((status) => {
      if (counts[status]) counts[status].textContent = String(selected[status]);
    });
    if (marked) marked.textContent = String(markedCount);
    if (submit) submit.disabled = selectors.length === 0 || markedCount !== selectors.length;
  };
  selectors.forEach((select) => select.addEventListener('change', updateCounts));
  updateCounts();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
