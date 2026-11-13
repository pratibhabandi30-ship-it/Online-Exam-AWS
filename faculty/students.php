<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$errors = [];
$branches = ['MCA', 'MBA', 'CSE', 'AIML', 'IS', 'EC', 'MECH', 'CIVIL'];
$existingBranches = $pdo->query(
    "SELECT DISTINCT sp.branch
     FROM student_profiles sp
     JOIN users u ON u.id = sp.user_id AND u.role = 'student'
     WHERE sp.branch IS NOT NULL AND sp.branch <> ''
       AND NOT EXISTS (
           SELECT 1 FROM audit_logs deleted
           WHERE deleted.action = 'account.deleted' AND deleted.entity_type = 'student'
             AND deleted.entity_id = CAST(u.id AS CHAR)
       )
     ORDER BY sp.branch"
)->fetchAll(PDO::FETCH_COLUMN);
$branches = array_values(array_unique(array_merge($branches, $existingBranches)));
$search = clean_string(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 80);
$branchFilter = is_string($_GET['branch'] ?? null) ? $_GET['branch'] : '';
$semesterFilter = is_string($_GET['semester'] ?? null) ? $_GET['semester'] : '';

if (!in_array($branchFilter, $branches, true)) {
    $branchFilter = '';
}
if ($semesterFilter !== '' && (!ctype_digit($semesterFilter) || (int) $semesterFilter < 1 || (int) $semesterFilter > 12)) {
    $semesterFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    try {
        if ($action === 'assign_students') {
            $postedIds = $_POST['student_ids'] ?? [];
            if (!is_array($postedIds) || !$postedIds) {
                throw new InvalidArgumentException('Select one or more unassigned students.');
            }
            if (count($postedIds) > 100) {
                throw new InvalidArgumentException('Select no more than 100 students at a time.');
            }

            $studentIds = [];
            foreach ($postedIds as $value) {
                if (!is_string($value) && !is_int($value)) {
                    throw new InvalidArgumentException('Invalid student selection.');
                }
                $studentId = filter_var((string) $value, FILTER_VALIDATE_INT);
                if ($studentId === false || $studentId < 1) {
                    throw new InvalidArgumentException('Invalid student selection.');
                }
                $studentIds[] = (int) $studentId;
            }
            $studentIds = array_values(array_unique($studentIds));
            sort($studentIds, SORT_NUMERIC);

            $pdo->beginTransaction();
            $facultyQuery = $pdo->prepare(
                "SELECT u.id, fp.max_students FROM users u
                 JOIN faculty_profiles fp ON fp.user_id = u.id
                 WHERE u.id = ? AND u.role = 'faculty' AND u.is_active = 1 FOR UPDATE"
            );
            $facultyQuery->execute([$facultyId]);
            $facultyProfile = $facultyQuery->fetch();
            if (!$facultyProfile) {
                throw new InvalidArgumentException('Your active faculty profile could not be verified.');
            }

            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $lockStudents = $pdo->prepare(
                "SELECT id FROM users
                 WHERE id IN ($placeholders) AND role = 'student' AND is_active = 1
                 ORDER BY id FOR UPDATE"
            );
            $lockStudents->execute($studentIds);
            $lockedIds = array_map('intval', $lockStudents->fetchAll(PDO::FETCH_COLUMN));
            if ($lockedIds !== $studentIds) {
                throw new InvalidArgumentException('One or more selected students are no longer available.');
            }

            $deletedCheck = $pdo->prepare(
                "SELECT COUNT(*) FROM audit_logs
                 WHERE action = 'account.deleted' AND entity_type = 'student'
                   AND entity_id IN ($placeholders)"
            );
            $deletedCheck->execute(array_map('strval', $studentIds));
            if ((int) $deletedCheck->fetchColumn() > 0) {
                throw new InvalidArgumentException('Deleted student accounts cannot be assigned.');
            }

            $existingAssignments = $pdo->prepare(
                "SELECT student_id FROM faculty_student_assignments
                 WHERE student_id IN ($placeholders) ORDER BY student_id"
            );
            $existingAssignments->execute($studentIds);
            if ($existingAssignments->fetchAll(PDO::FETCH_COLUMN)) {
                throw new InvalidArgumentException('At least one selected student is already assigned. Refresh the directory and try again.');
            }

            $capacityQuery = $pdo->prepare(
                "SELECT COUNT(*) FROM faculty_student_assignments fsa
                 JOIN users u ON u.id = fsa.student_id AND u.role = 'student' AND u.is_active = 1
                 WHERE fsa.faculty_id = ?"
            );
            $capacityQuery->execute([$facultyId]);
            $currentCount = (int) $capacityQuery->fetchColumn();
            if ($currentCount + count($studentIds) > (int) $facultyProfile['max_students']) {
                throw new InvalidArgumentException(
                    'This selection exceeds your capacity of ' . (int) $facultyProfile['max_students'] . ' assigned students.'
                );
            }

            $insert = $pdo->prepare(
                'INSERT INTO faculty_student_assignments (faculty_id, student_id, assigned_by)
                 VALUES (?, ?, ?)'
            );
            $audit = $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($studentIds as $studentId) {
                $insert->execute([$facultyId, $studentId, $facultyId]);
                $audit->execute([
                    $facultyId,
                    'student.assigned',
                    'student',
                    (string) $studentId,
                    'Student assigned by faculty.',
                ]);
            }

            $pdo->commit();
            flash('success', count($studentIds) . ' student' . (count($studentIds) === 1 ? ' was' : 's were') . ' assigned to you.');
            redirect('faculty/students.php' . ($branchFilter !== '' || $semesterFilter !== '' || $search !== '' ? '?' . http_build_query([
                'q' => $search,
                'branch' => $branchFilter,
                'semester' => $semesterFilter,
            ]) : ''));
        }

        if ($action === 'unassign_student') {
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            if ($studentId === false || $studentId < 1) {
                throw new InvalidArgumentException('Select a valid student to unassign.');
            }

            $pdo->beginTransaction();
            $assignment = $pdo->prepare(
                'SELECT student_id FROM faculty_student_assignments
                 WHERE faculty_id = ? AND student_id = ? FOR UPDATE'
            );
            $assignment->execute([$facultyId, $studentId]);
            if (!$assignment->fetchColumn()) {
                throw new InvalidArgumentException('This student is not assigned to you.');
            }
            $pdo->prepare(
                'DELETE FROM faculty_student_assignments WHERE faculty_id = ? AND student_id = ?'
            )->execute([$facultyId, $studentId]);
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $facultyId,
                'student.unassigned',
                'student',
                (string) $studentId,
                'Student unassigned by faculty.',
            ]);
            $pdo->commit();
            flash('success', 'Student unassigned. Historical academic records were preserved.');
            redirect('faculty/students.php' . ($branchFilter !== '' || $semesterFilter !== '' || $search !== '' ? '?' . http_build_query([
                'q' => $search,
                'branch' => $branchFilter,
                'semester' => $semesterFilter,
            ]) : ''));
        }

        throw new InvalidArgumentException('Unknown student directory action.');
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty student assignment failed: ' . $error->getMessage());
        $errors[] = $error->getCode() === '23000'
            ? 'A selected student was assigned by another faculty member. Refresh the directory and try again.'
            : 'Student assignments could not be updated. Please try again.';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty student directory action failed: ' . $error->getMessage());
        $errors[] = 'The student directory could not be updated. Please try again.';
    }
}

$sql =
    "SELECT u.id, u.name, u.email, u.college, u.is_active,
            sp.registration_no, sp.branch, sp.semester, sp.academic_year,
            assigned.faculty_id AS assigned_faculty_id, assigned_faculty.name AS assigned_faculty_name,
            COALESCE(performance.attempts, 0) AS attempts,
            performance.average_percent, performance.best_percent
     FROM users u
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     LEFT JOIN faculty_student_assignments assigned ON assigned.student_id = u.id
     LEFT JOIN users assigned_faculty ON assigned_faculty.id = assigned.faculty_id
     LEFT JOIN (
         SELECT fsa.student_id, COUNT(DISTINCT a.id) AS attempts,
                AVG(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END) AS average_percent,
                MAX(CASE WHEN a.total_marks > 0 THEN a.score * 100.0 / a.total_marks END) AS best_percent
         FROM faculty_student_assignments fsa
         JOIN exam_assigned_students eas ON eas.student_id = fsa.student_id
         JOIN exam_workflows w ON w.quiz_id = eas.quiz_id AND w.faculty_id = fsa.faculty_id
             AND w.status IN ('published','completed')
         JOIN attempts a ON a.user_id = fsa.student_id AND a.quiz_id = w.quiz_id
         WHERE fsa.faculty_id = ?
         GROUP BY fsa.student_id
     ) performance ON performance.student_id = u.id
     WHERE u.role = 'student'
       AND NOT EXISTS (
           SELECT 1 FROM audit_logs deleted
           WHERE deleted.action = 'account.deleted' AND deleted.entity_type = 'student'
             AND deleted.entity_id = CAST(u.id AS CHAR)
       )";
$params = [$facultyId];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR sp.registration_no LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
if ($branchFilter !== '') {
    $sql .= ' AND sp.branch = ?';
    $params[] = $branchFilter;
}
if ($semesterFilter !== '') {
    $sql .= ' AND sp.semester = ?';
    $params[] = (int) $semesterFilter;
}
$sql .= ' ORDER BY u.name, u.id';
$studentsQuery = $pdo->prepare($sql);
$studentsQuery->execute($params);
$students = $studentsQuery->fetchAll();

$pageTitle = 'Faculty · My Students';
$activeNav = 'faculty-students';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'students';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Student directory</h2><p class="text-muted mb-0">Browse registered students, filter the directory, and assign unassigned students to yourself.</p></div>
    <a class="btn btn-outline-primary" href="<?= e(base_url('faculty/attendance.php')) ?>"><i class="bi bi-calendar2-check"></i> Manage attendance</a>
  </div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

  <form method="get" class="card p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-5"><label class="form-label" for="studentSearch">Search students</label><input class="form-control" id="studentSearch" type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email or registration number"></div>
      <div class="col-md-3"><label class="form-label" for="branchFilter">Branch</label><select class="form-select" id="branchFilter" name="branch"><option value="">All branches</option><?php foreach ($branches as $branch): ?><option value="<?= e($branch) ?>" <?= $branchFilter === $branch ? 'selected' : '' ?>><?= e($branch) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label" for="semesterFilter">Semester</label><select class="form-select" id="semesterFilter" name="semester"><option value="">All semesters</option><?php for ($term = 1; $term <= 12; $term++): ?><option value="<?= $term ?>" <?= $semesterFilter === (string) $term ? 'selected' : '' ?>><?= $term ?></option><?php endfor; ?></select></div>
      <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-fill" type="submit">Filter</button><a class="btn btn-outline-secondary" href="<?= e(base_url('faculty/students.php')) ?>">Reset</a></div>
    </div>
  </form>

  <form method="post" id="assignStudentsForm" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="assign_students">
    <button class="btn btn-primary" type="submit" data-assign-submit disabled><i class="bi bi-person-check"></i> Assign to Me</button>
    <span class="small text-muted"><span data-selection-count>0</span> unassigned students selected</span>
  </form>

  <div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Select</th><th>Student</th><th>Email</th><th>Registration</th><th>Branch</th><th>Semester</th><th>Academic year</th><th>Faculty assignment</th><th>Attempts</th><th>Average</th><th>Best</th><th></th></tr></thead>
    <tbody><?php foreach ($students as $student):
      $studentId = (int) $student['id'];
      $isAssignedToMe = (int) ($student['assigned_faculty_id'] ?? 0) === $facultyId;
      $isUnassigned = empty($student['assigned_faculty_id']);
      $selectable = $isUnassigned && (int) $student['is_active'] === 1;
    ?><tr>
      <td><?php if ($selectable): ?><input class="form-check-input" type="checkbox" name="student_ids[]" value="<?= $studentId ?>" form="assignStudentsForm" data-student-selection aria-label="Select <?= e($student['name']) ?>"><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
      <td><?= e($student['name']) ?><?php if (!(int) $student['is_active']): ?><div><span class="badge bg-secondary">Inactive</span></div><?php endif; ?></td>
      <td><?= e($student['email']) ?></td>
      <td><?= e($student['registration_no'] ?: '—') ?></td>
      <td><?= e($student['branch'] ?: '—') ?></td>
      <td><?= e($student['semester'] ? (string) $student['semester'] : '—') ?></td>
      <td><?= e($student['academic_year'] ?: '—') ?></td>
      <td><?php if ($isUnassigned): ?><span class="badge bg-secondary">Unassigned</span><?php else: ?><span class="badge bg-success">Assigned</span><div class="small text-muted"><?= e($student['assigned_faculty_name'] ?: 'Faculty') ?></div><?php endif; ?></td>
      <td><?= (int) $student['attempts'] ?></td>
      <td><?= $student['average_percent'] !== null ? number_format((float) $student['average_percent'], 1) . '%' : '—' ?></td>
      <td><?= $student['best_percent'] !== null ? number_format((float) $student['best_percent'], 1) . '%' : '—' ?></td>
      <td><?php if ($isAssignedToMe): ?><form method="post" class="d-inline" data-unassign-confirm data-student-name="<?= e($student['name']) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="unassign_student"><input type="hidden" name="student_id" value="<?= $studentId ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Unassign</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php if (!$students): ?><div class="text-center text-muted py-5">No students match these filters.</div><?php endif; ?>
  </div>
</div>
<script>
(() => {
  const checkboxes = Array.from(document.querySelectorAll('[data-student-selection]'));
  const assignButton = document.querySelector('[data-assign-submit]');
  const selectionCount = document.querySelector('[data-selection-count]');
  const updateSelection = () => {
    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
    if (selectionCount) selectionCount.textContent = String(selected);
    if (assignButton) assignButton.disabled = selected === 0;
  };
  checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));

  document.querySelectorAll('form[data-unassign-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const name = form.dataset.studentName || 'this student';
      if (!window.confirm(`Unassign ${name}? Their historical academic records will be preserved.`)) {
        event.preventDefault();
      }
    });
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
