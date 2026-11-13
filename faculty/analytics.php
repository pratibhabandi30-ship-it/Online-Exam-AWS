<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$studentId = (int) ($_GET['student'] ?? 0);

$studentsQuery = $pdo->prepare(
    "SELECT u.id, u.name FROM faculty_student_assignments fsa
     JOIN users u ON u.id = fsa.student_id AND u.role = 'student' AND u.is_active = 1
     WHERE fsa.faculty_id = ? ORDER BY u.name"
);
$studentsQuery->execute([$facultyId]);
$students = $studentsQuery->fetchAll();
$studentIds = array_map(static fn(array $student): int => (int) $student['id'], $students);
if ($studentId > 0 && !in_array($studentId, $studentIds, true)) {
    http_response_code(404);
    die('Student not found in your assigned roster.');
}

$sql =
    "SELECT a.user_id, u.name AS student_name, q.tag AS subject, q.title AS exam_title,
            a.score, a.total_marks, a.submitted_at,
            CASE WHEN a.total_marks > 0 THEN ROUND(a.score * 100.0 / a.total_marks, 1) ELSE 0 END AS percent
     FROM exam_workflows w
     JOIN quizzes q ON q.id = w.quiz_id
     JOIN exam_assigned_students eas ON eas.quiz_id = w.quiz_id
     JOIN faculty_student_assignments fsa ON fsa.faculty_id = w.faculty_id AND fsa.student_id = eas.student_id
     JOIN attempts a ON a.quiz_id = w.quiz_id AND a.user_id = eas.student_id
     JOIN users u ON u.id = a.user_id
     WHERE w.faculty_id = ? AND w.status IN ('published','completed')";
$params = [$facultyId];
if ($studentId > 0) {
    $sql .= ' AND a.user_id = ?';
    $params[] = $studentId;
}
$sql .= ' ORDER BY a.submitted_at';
$performanceQuery = $pdo->prepare($sql);
$performanceQuery->execute($params);
$performance = $performanceQuery->fetchAll();

$summaryQuery = $pdo->prepare(
    "SELECT COUNT(a.id) AS attempt_count,
            AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END) AS average_percent,
            MAX(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END) AS highest_percent,
            MIN(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END) AS lowest_percent
     FROM exam_workflows w
     JOIN exam_assigned_students eas ON eas.quiz_id = w.quiz_id
     JOIN faculty_student_assignments fsa ON fsa.faculty_id = w.faculty_id AND fsa.student_id = eas.student_id
     JOIN attempts a ON a.quiz_id = w.quiz_id AND a.user_id = eas.student_id
     WHERE w.faculty_id = ? AND w.status IN ('published','completed')" . ($studentId > 0 ? ' AND a.user_id = ?' : '')
);
$summaryQuery->execute($studentId > 0 ? [$facultyId, $studentId] : [$facultyId]);
$summary = $summaryQuery->fetch();

$subjects = [];
foreach ($performance as $row) {
    $subject = $row['subject'] ?: 'Uncategorized';
    $subjects[$subject][] = (float) $row['percent'];
}
$subjectLabels = array_keys($subjects);
$subjectAverages = array_map(
    static fn(array $scores): float => round(array_sum($scores) / count($scores), 1),
    array_values($subjects)
);
$weakSubjects = $subjects;
uasort($weakSubjects, static fn(array $a, array $b): int => (array_sum($a) / count($a)) <=> (array_sum($b) / count($b)));

$pageTitle = 'Faculty · Performance Analytics';
$activeNav = 'faculty-analytics';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'analytics';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Performance analytics</h2><p class="text-muted mb-0">Scores are based on examinations you own and your assigned students.</p></div>
    <form method="get" class="d-flex gap-2"><select class="form-select" name="student"><option value="0">All assigned students</option><?php foreach ($students as $student): ?><option value="<?= (int) $student['id'] ?>" <?= $studentId === (int) $student['id'] ? 'selected' : '' ?>><?= e($student['name']) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary">Apply</button></form>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach ([
      ['Attempts', (int) $summary['attempt_count'], 'bi-clipboard-check'],
      ['Average', $summary['average_percent'] !== null ? number_format((float) $summary['average_percent'], 1) . '%' : '—', 'bi-bar-chart'],
      ['Highest', $summary['highest_percent'] !== null ? number_format((float) $summary['highest_percent'], 1) . '%' : '—', 'bi-arrow-up-circle'],
      ['Lowest', $summary['lowest_percent'] !== null ? number_format((float) $summary['lowest_percent'], 1) . '%' : '—', 'bi-arrow-down-circle'],
    ] as [$label, $value, $icon]): ?><div class="col-6 col-xl-3"><div class="card p-3 h-100"><div class="d-flex justify-content-between align-items-center"><div><div class="small text-muted text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold"><?= e((string) $value) ?></div></div><div class="feature-icon"><i class="bi <?= e($icon) ?>"></i></div></div></div></div><?php endforeach; ?>
  </div>
  <div class="row g-4 mb-4">
    <div class="col-lg-8"><div class="card p-4 h-100"><h5 class="mb-3">Examination performance</h5><?php if ($performance): ?><div class="admin-chart-wrap"><canvas id="facultyPerformanceChart"></canvas></div><?php else: ?><div class="admin-chart-empty">Performance charts will appear when assigned students submit examinations.</div><?php endif; ?></div></div>
    <div class="col-lg-4"><div class="card p-4 h-100"><h5 class="mb-3">Subject distribution</h5><?php if ($subjectLabels): ?><div class="admin-doughnut-wrap"><canvas id="facultySubjectChart"></canvas></div><?php else: ?><div class="admin-chart-empty">No subject scores to display.</div><?php endif; ?></div></div>
  </div>
  <div class="row g-4">
    <div class="col-lg-7"><div class="card p-3"><h5 class="px-2 pt-2">Recent student scores</h5><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Student</th><th>Subject / exam</th><th>Score</th><th>Date</th></tr></thead><tbody>
      <?php foreach (array_reverse($performance) as $row): ?><tr><td><?= e($row['student_name']) ?></td><td><?= e($row['subject'] ?: 'General') ?><div class="small text-muted"><?= e($row['exam_title']) ?></div></td><td><?= (int) $row['score'] ?>/<?= (int) $row['total_marks'] ?> (<?= number_format((float) $row['percent'], 1) ?>%)</td><td><?= e(date('d M Y', strtotime($row['submitted_at']))) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
    <div class="col-lg-5"><div class="card p-4 h-100"><h5 class="mb-3">Topics to prioritize</h5><?php if ($weakSubjects): ?><div class="vstack gap-3"><?php foreach (array_slice($weakSubjects, 0, 5, true) as $subject => $scores): $average = array_sum($scores) / count($scores); ?><div><div class="d-flex justify-content-between small mb-1"><strong><?= e($subject) ?></strong><span><?= number_format($average, 1) ?>% average</span></div><div class="progress" role="progressbar" aria-valuenow="<?= (int) round($average) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar <?= $average < 50 ? 'bg-danger' : 'bg-primary' ?>" style="width:<?= max(0, min(100, $average)) ?>%"></div></div></div><?php endforeach; ?></div><?php else: ?><p class="text-muted mb-0">Weak-topic identification becomes available after graded exams.</p><?php endif; ?></div></div>
  </div>
</div>
<?php if ($performance): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  const labels = <?= json_encode(array_map(static fn(array $row): string => $row['student_name'] . ' · ' . $row['exam_title'], $performance), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const scores = <?= json_encode(array_map(static fn(array $row): float => (float) $row['percent'], $performance)) ?>;
  const subjects = <?= json_encode($subjectLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const subjectAverages = <?= json_encode($subjectAverages) ?>;
  const olive = ['#606c38', '#7f8c45', '#a8b77d', '#c4ce9f', '#3f4d24', '#8e9b60'];
  new Chart(document.getElementById('facultyPerformanceChart'), {
    type: 'bar',
    data: {labels, datasets: [{label: 'Score %', data: scores, backgroundColor: '#7f8c45', borderRadius: 6}]},
    options: {responsive: true, maintainAspectRatio: false, scales: {y: {beginAtZero: true, max: 100}}}
  });
  new Chart(document.getElementById('facultySubjectChart'), {
    type: 'doughnut',
    data: {labels: subjects, datasets: [{data: subjectAverages, backgroundColor: olive, borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false}
  });
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
