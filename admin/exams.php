<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = db();
$status = clean_string($_GET['status'] ?? '', 20);
$allowedStatuses = ['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'];
$sql = "
    SELECT q.id, q.title, q.is_published, q.duration_minutes, f.name AS faculty_name,
           w.status, w.scheduled_start, w.scheduled_end, w.hod_remarks,
           (SELECT COUNT(*) FROM exam_assigned_students assigned WHERE assigned.quiz_id = q.id) AS student_count,
           (SELECT COUNT(*) FROM questions question WHERE question.quiz_id = q.id) AS question_count,
           (SELECT COUNT(*) FROM attempts attempt WHERE attempt.quiz_id = q.id) AS attempt_count
    FROM exam_workflows w
    JOIN quizzes q ON q.id = w.quiz_id
    JOIN users f ON f.id = w.faculty_id
";
$params = [];
if (in_array($status, $allowedStatuses, true)) {
    $sql .= ' WHERE w.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY w.updated_at DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exams = $stmt->fetchAll();

$pageTitle = 'HOD · Department examinations';
include __DIR__ . '/../includes/header.php';
$adminActive = 'department-exams';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div><h3 class="mb-1">Department examinations</h3><p class="text-muted mb-0">Schedules, approvals, participation, and publication status.</p></div>
    <form method="get" class="d-flex gap-2"><select class="form-select" name="status"><option value="">All statuses</option>
      <?php foreach ($allowedStatuses as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e(ucfirst($item)) ?></option><?php endforeach; ?>
    </select><button class="btn btn-outline-primary">Filter</button></form>
  </div>
  <div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Examination</th><th>Faculty</th><th>Schedule</th><th>Students / questions</th><th>Attempts</th><th>Status</th><th></th></tr></thead>
    <tbody><?php foreach ($exams as $exam): ?>
      <tr>
        <td><?= e($exam['title']) ?><div class="small text-muted"><?= (int) $exam['duration_minutes'] ?> min · <?= $exam['is_published'] ? 'Published to students' : 'Not published' ?></div></td>
        <td><?= e($exam['faculty_name']) ?></td>
        <td><?= $exam['scheduled_start'] ? e(date('d M Y, H:i', strtotime($exam['scheduled_start']))) : '—' ?><div class="small text-muted"><?= $exam['scheduled_end'] ? e(date('d M Y, H:i', strtotime($exam['scheduled_end']))) : '' ?></div></td>
        <td><?= (int) $exam['student_count'] ?> / <?= (int) $exam['question_count'] ?></td>
        <td><?= (int) $exam['attempt_count'] ?></td>
        <td><span class="badge bg-<?= $exam['status'] === 'rejected' ? 'danger' : (in_array($exam['status'], ['approved','completed'], true) ? 'success' : ($exam['status'] === 'pending' ? 'warning text-dark' : 'secondary')) ?>"><?= e(ucfirst($exam['status'])) ?></span></td>
        <td class="text-end">
          <?php if ($exam['status'] === 'pending'): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/approvals.php')) ?>">Review</a><?php endif; ?>
          <?php if ($exam['attempt_count']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/attempts.php?quiz=' . (int) $exam['id'])) ?>">Results</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
