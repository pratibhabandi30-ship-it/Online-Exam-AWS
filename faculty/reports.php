<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/faculty-tools.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$reportType = (string) ($_GET['report'] ?? 'progress');
$format = (string) ($_GET['download'] ?? '');
$studentId = (int) ($_GET['student_id'] ?? 0);
$reportTitles = [
    'attendance' => 'Student attendance',
    'performance' => 'Examination performance',
    'student' => 'Individual student results',
    'progress' => 'Assigned student progress',
];
if (!isset($reportTitles[$reportType]) || ($format !== '' && !in_array($format, ['excel', 'pdf'], true))) {
    http_response_code(400);
    die('Choose a valid report.');
}

$studentsQuery = $pdo->prepare(
    "SELECT u.id, u.name, u.email, sp.registration_no
     FROM faculty_student_assignments fsa JOIN users u ON u.id = fsa.student_id
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     WHERE fsa.faculty_id = ? AND u.role = 'student' ORDER BY u.name"
);
$studentsQuery->execute([$facultyId]);
$students = $studentsQuery->fetchAll();
$assignedStudentIds = array_map(static fn(array $student): int => (int) $student['id'], $students);
if ($reportType === 'student' && $studentId > 0 && !in_array($studentId, $assignedStudentIds, true)) {
    http_response_code(404);
    die('Student not found in your assigned roster.');
}

if ($reportType === 'attendance') {
    $query = $pdo->prepare(
        'SELECT u.name AS student, sp.registration_no AS registration, a.attendance_date AS date,
                a.status, a.remarks
         FROM faculty_attendance a
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = a.faculty_id AND fsa.student_id = a.student_id
         JOIN users u ON u.id = a.student_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE a.faculty_id = ? ORDER BY a.attendance_date DESC, u.name'
    );
    $query->execute([$facultyId]);
    $headers = ['Student', 'Registration', 'Date', 'Status', 'Remarks'];
    $rows = $query->fetchAll(PDO::FETCH_NUM);
} elseif ($reportType === 'performance' || $reportType === 'student') {
    $sql =
        "SELECT u.name AS student, sp.registration_no AS registration, q.title AS exam,
                COALESCE(q.tag, 'General') AS subject, a.score, a.total_marks,
                CASE WHEN a.total_marks > 0 THEN ROUND(a.score * 100.0 / a.total_marks, 1) ELSE 0 END AS percent,
                a.submitted_at AS submitted
         FROM exam_workflows w
         JOIN quizzes q ON q.id = w.quiz_id
         JOIN exam_assigned_students eas ON eas.quiz_id = w.quiz_id
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = w.faculty_id AND fsa.student_id = eas.student_id
         JOIN attempts a ON a.quiz_id = w.quiz_id AND a.user_id = eas.student_id
         JOIN users u ON u.id = a.user_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE w.faculty_id = ? AND w.status IN ('published','completed')";
    $params = [$facultyId];
    if ($reportType === 'student') {
        if ($studentId < 1) {
            http_response_code(400);
            die('Select one of your assigned students for an individual report.');
        }
        $sql .= ' AND a.user_id = ?';
        $params[] = $studentId;
    }
    $sql .= ' ORDER BY a.submitted_at DESC';
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $headers = ['Student', 'Registration', 'Exam', 'Subject', 'Score', 'Total marks', 'Percent', 'Submitted'];
    $rows = $query->fetchAll(PDO::FETCH_NUM);
} else {
    $query = $pdo->prepare(
        "SELECT u.name AS student, sp.registration_no AS registration, u.email,
                COUNT(DISTINCT a.id) AS attempts,
                ROUND(AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END), 1) AS average_percent,
                (SELECT COUNT(*) FROM faculty_attendance att
                 WHERE att.faculty_id = fsa.faculty_id AND att.student_id = fsa.student_id) AS attendance_days,
                (SELECT ROUND(100.0 * SUM(att.status IN ('present','late')) / NULLIF(COUNT(*), 0), 1)
                 FROM faculty_attendance att
                 WHERE att.faculty_id = fsa.faculty_id AND att.student_id = fsa.student_id) AS attendance_percent
         FROM faculty_student_assignments fsa
         JOIN users u ON u.id = fsa.student_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN exam_assigned_students eas ON eas.student_id = fsa.student_id
         LEFT JOIN exam_workflows w ON w.quiz_id = eas.quiz_id AND w.faculty_id = fsa.faculty_id
              AND w.status IN ('published','completed')
         LEFT JOIN attempts a ON a.quiz_id = w.quiz_id AND a.user_id = fsa.student_id
         WHERE fsa.faculty_id = ?
         GROUP BY fsa.faculty_id, fsa.student_id, u.name, u.email, sp.registration_no
         ORDER BY u.name"
    );
    $query->execute([$facultyId]);
    $headers = ['Student', 'Registration', 'Email', 'Exam attempts', 'Average score %', 'Attendance days', 'Attendance %'];
    $rows = $query->fetchAll(PDO::FETCH_NUM);
}

if ($format === 'excel') {
    $filename = 'faculty-' . $reportType . '-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to open report export stream.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, [$reportTitles[$reportType], $faculty['name']]);
    fputcsv($output, []);
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, array_map('faculty_csv_safe_cell', $row));
    }
    fclose($output);
    exit;
}

if ($format === 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (!class_exists(\Dompdf\Dompdf::class)) {
        http_response_code(503);
        die('PDF reports are unavailable because the PDF renderer is not installed.');
    }
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . 'body{font-family:DejaVu Sans,sans-serif;color:#25291f;font-size:10px}h1{color:#283618;font-size:20px}'
        . 'table{width:100%;border-collapse:collapse}th{background:#606c38;color:#fff;text-align:left}'
        . 'th,td{border:1px solid #d5dac8;padding:6px;vertical-align:top}tr:nth-child(even){background:#f7f8f2}'
        . '</style></head><body><h1>' . e($reportTitles[$reportType]) . '</h1><p>Faculty: ' . e($faculty['name']) . ' · Generated ' . e(date('d M Y H:i')) . '</p><table><thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . e((string) $header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $value) {
            $html .= '<td>' . e((string) ($value ?? '—')) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></body></html>';
    $pdf = new \Dompdf\Dompdf();
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'landscape');
    $pdf->render();
    $pdf->stream('faculty-' . $reportType . '-' . date('Ymd') . '.pdf', ['Attachment' => true]);
    exit;
}

$pageTitle = 'Faculty · Reports';
$activeNav = 'faculty-reports';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'reports';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="mb-4"><h2 class="mb-1">Reports</h2><p class="text-muted mb-0">Export actual records for your assigned students and examinations.</p></div>
  <div class="row g-3">
    <?php foreach ($reportTitles as $type => $label): ?>
      <div class="col-md-6 col-xl-3"><article class="card p-4 h-100">
        <div class="feature-icon mb-3"><i class="bi bi-file-earmark-bar-graph"></i></div><h5><?= e($label) ?></h5>
        <?php if ($type === 'student'): ?><form method="get" class="vstack gap-2"><input type="hidden" name="report" value="student"><select class="form-select" name="student_id" required><option value="">Select assigned student</option><?php foreach ($students as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['name']) ?> · <?= e($student['registration_no'] ?: $student['email']) ?></option><?php endforeach; ?></select><div class="d-flex gap-2"><button class="btn btn-sm btn-outline-secondary" name="download" value="excel">Excel CSV</button><button class="btn btn-sm btn-primary" name="download" value="pdf">PDF</button></div></form>
        <?php else: ?><p class="small text-muted flex-grow-1">Download this report for your assigned students.</p><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('faculty/reports.php?report=' . $type . '&download=excel')) ?>">Excel CSV</a><a class="btn btn-sm btn-primary" href="<?= e(base_url('faculty/reports.php?report=' . $type . '&download=pdf')) ?>">PDF</a></div><?php endif; ?>
      </article></div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
