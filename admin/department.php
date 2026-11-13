<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$hod = require_admin();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_user' || $action === 'update_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $role = (string) ($_POST['role'] ?? '');
            $name = clean_string($_POST['name'] ?? '', 80);
            $email = strtolower(clean_string($_POST['email'] ?? '', 120));
            $college = clean_string($_POST['college'] ?? '', 120);
            $mobile = clean_string($_POST['mobile'] ?? '', 20);
            $branch = clean_string(
                $_POST[$role === 'faculty' ? 'faculty_branch' : 'student_branch'] ?? '',
                120
            );
            $subject = clean_string($_POST['subject'] ?? '', 120);
            $registration = clean_string($_POST['registration_no'] ?? '', 60);
            $semester = (int) ($_POST['semester'] ?? 0);
            $academicYear = clean_string($_POST['academic_year'] ?? '', 20);
            $maxStudents = (int) ($_POST['max_students'] ?? 12);
            $password = (string) ($_POST['password'] ?? '');

            if (!in_array($role, ['faculty', 'student'], true)) {
                throw new InvalidArgumentException('Choose a faculty or student account.');
            }
            if (mb_strlen($name) < 2 || !validate_email($email)) {
                throw new InvalidArgumentException('Enter a valid name and email address.');
            }
            if ($role === 'faculty' && ($maxStudents < 1 || $maxStudents > 100)) {
                throw new InvalidArgumentException('Faculty capacity must be between 1 and 100 students.');
            }
            if ($role === 'student' && $semester !== 0 && ($semester < 1 || $semester > 12)) {
                throw new InvalidArgumentException('Semester must be between 1 and 12.');
            }
            if ($action === 'create_user' && !account_password_is_valid($password)) {
                throw new InvalidArgumentException(account_password_policy_message());
            }
            if ($action === 'update_user' && $id < 1) {
                throw new InvalidArgumentException('Select a valid account to update.');
            }

            $pdo->beginTransaction();
            if ($action === 'create_user') {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, college, mobile, role, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)'
                );
                $stmt->execute([
                    $name,
                    $email,
                    hash_account_password($password),
                    $college ?: null,
                    $mobile ?: null,
                    $role,
                ]);
                $id = (int) $pdo->lastInsertId();
                $auditAction = 'account.created';
            } else {
                $existing = $pdo->prepare('SELECT role FROM users WHERE id = ? FOR UPDATE');
                $existing->execute([$id]);
                $existingRole = $existing->fetchColumn();
                if ($existingRole !== $role) {
                    throw new InvalidArgumentException('Account type cannot be changed here.');
                }
                if ($role === 'faculty') {
                    $currentCapacity = $pdo->prepare('SELECT COUNT(*) FROM faculty_student_assignments WHERE faculty_id = ?');
                    $currentCapacity->execute([$id]);
                    if ($maxStudents < (int) $currentCapacity->fetchColumn()) {
                        throw new InvalidArgumentException('Student limit cannot be lower than the current assigned student count.');
                    }
                }
                $stmt = $pdo->prepare(
                    'UPDATE users SET name = ?, email = ?, college = ?, mobile = ? WHERE id = ? AND role = ?'
                );
                $stmt->execute([$name, $email, $college ?: null, $mobile ?: null, $id, $role]);
                if ($password !== '') {
                    if (!account_password_is_valid($password)) {
                        throw new InvalidArgumentException(account_password_policy_message());
                    }
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([hash_account_password($password), $id]);
                }
                $auditAction = 'account.updated';
            }

            if ($role === 'faculty') {
                $pdo->prepare(
                    'INSERT INTO faculty_profiles (user_id, branch, subject, max_students) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE branch = VALUES(branch), subject = VALUES(subject), max_students = VALUES(max_students)'
                )->execute([$id, $branch ?: null, $subject ?: null, $maxStudents]);
            } else {
                $pdo->prepare(
                    'INSERT INTO student_profiles (user_id, registration_no, branch, semester, academic_year)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE registration_no = VALUES(registration_no), branch = VALUES(branch),
                        semester = VALUES(semester), academic_year = VALUES(academic_year)'
                )->execute([
                    $id,
                    $registration ?: null,
                    $branch ?: null,
                    $semester ?: null,
                    $academicYear ?: null,
                ]);
            }
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
            )->execute([(int) $hod['id'], $auditAction, $role, (string) $id]);
            $pdo->commit();
            flash('success', $action === 'create_user' ? 'Account created.' : 'Account updated.');
            redirect('admin/department.php');
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id < 1) {
                throw new InvalidArgumentException('Select a valid account to delete.');
            }
            if (($_POST['confirm_delete'] ?? '') !== '1') {
                throw new InvalidArgumentException('Confirm the account deletion before continuing.');
            }

            $pdo->beginTransaction();
            $account = $pdo->prepare(
                "SELECT u.id, u.role FROM users u
                 WHERE u.id = ? AND u.role IN ('faculty','student')
                   AND NOT EXISTS (
                       SELECT 1 FROM audit_logs al
                       WHERE al.action = 'account.deleted'
                         AND al.entity_type = u.role
                         AND al.entity_id = CAST(u.id AS CHAR)
                   )
                 FOR UPDATE"
            );
            $account->execute([$id]);
            $deletedAccount = $account->fetch();
            if (!$deletedAccount) {
                throw new InvalidArgumentException('The selected faculty/student account was not found.');
            }

            $pdo->prepare(
                'DELETE FROM faculty_student_assignments WHERE faculty_id = ? OR student_id = ?'
            )->execute([$id, $id]);
            $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')
                ->execute([$id]);
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                (int) $hod['id'],
                'account.deleted',
                (string) $deletedAccount['role'],
                (string) $id,
                'Account deactivated and current faculty assignment cleared; historical academic records preserved.',
            ]);
            $pdo->commit();
            flash('success', 'Account deleted. Historical academic records have been preserved.');
            redirect('admin/department.php');
        }

        if ($action === 'toggle_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE users SET is_active = 1 - is_active
                 WHERE id = ? AND role IN ('faculty','student')
                   AND NOT EXISTS (
                       SELECT 1 FROM audit_logs al
                       WHERE al.action = 'account.deleted'
                         AND al.entity_type = users.role
                         AND al.entity_id = CAST(users.id AS CHAR)
                   )"
            );
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException('The selected faculty/student account was not found.');
            }
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
            )->execute([(int) $hod['id'], 'account.status_changed', 'user', (string) $id]);
            $pdo->commit();
            flash('success', 'Account status updated.');
            redirect('admin/department.php');
        }

        if ($action === 'assign_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $facultyId = (int) ($_POST['faculty_id'] ?? 0);
            $pdo->beginTransaction();
            $faculty = $pdo->prepare(
                "SELECT u.id, fp.max_students FROM users u
                 JOIN faculty_profiles fp ON fp.user_id = u.id
                 WHERE u.id = ? AND u.role = 'faculty' AND u.is_active = 1 FOR UPDATE"
            );
            $faculty->execute([$facultyId]);
            $facultyRow = $faculty->fetch();
            if (!$facultyRow) {
                throw new InvalidArgumentException('Select an active faculty member with a profile.');
            }
            $student = $pdo->prepare(
                "SELECT id FROM users WHERE id = ? AND role = 'student' AND is_active = 1 FOR UPDATE"
            );
            $student->execute([$studentId]);
            if (!$student->fetchColumn()) {
                throw new InvalidArgumentException('Select an active student.');
            }
            $assignedCount = $pdo->prepare('SELECT COUNT(*) FROM faculty_student_assignments WHERE faculty_id = ?');
            $assignedCount->execute([$facultyId]);
            $currentCount = (int) $assignedCount->fetchColumn();
            $oldFaculty = $pdo->prepare('SELECT faculty_id FROM faculty_student_assignments WHERE student_id = ? FOR UPDATE');
            $oldFaculty->execute([$studentId]);
            $oldFacultyId = $oldFaculty->fetchColumn();
            if ((int) $oldFacultyId !== $facultyId && $currentCount >= (int) $facultyRow['max_students']) {
                throw new InvalidArgumentException('This faculty member has reached their configured student capacity.');
            }
            $pdo->prepare(
                'INSERT INTO faculty_student_assignments (faculty_id, student_id, assigned_by) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE faculty_id = VALUES(faculty_id), assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP'
            )->execute([$facultyId, $studentId, (int) $hod['id']]);
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)'
            )->execute([(int) $hod['id'], 'student.assigned', 'student', (string) $studentId, 'faculty_id=' . $facultyId]);
            $pdo->commit();
            flash('success', 'Student assigned to faculty.');
            redirect('admin/department.php');
        }

        if ($action === 'unassign_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $pdo->beginTransaction();
            $student = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' FOR UPDATE");
            $student->execute([$studentId]);
            if (!$student->fetchColumn()) {
                throw new InvalidArgumentException('The selected student was not found.');
            }
            $pdo->prepare('DELETE FROM faculty_student_assignments WHERE student_id = ?')->execute([$studentId]);
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
            )->execute([(int) $hod['id'], 'student.unassigned', 'student', (string) $studentId]);
            $pdo->commit();
            flash('success', 'Student unassigned.');
            redirect('admin/department.php');
        }

        throw new InvalidArgumentException('Unknown department action.');
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Department management failed: ' . $error->getMessage());
        $errors[] = $action === 'delete_user'
            ? 'The account could not be deleted. Its academic records were preserved; please try again.'
            : 'The change could not be saved. Check that the email and registration number are unique.';
    }
}

$editingId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editingId > 0) {
    $editQuery = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.college, u.mobile, u.role,
                fp.branch AS faculty_branch, fp.subject, fp.max_students,
                sp.registration_no, sp.branch AS student_branch, sp.semester, sp.academic_year
         FROM users u
         LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE u.id = ? AND u.role IN ('faculty','student')
           AND NOT EXISTS (
               SELECT 1 FROM audit_logs al
               WHERE al.action = 'account.deleted'
                 AND al.entity_type = u.role
                 AND al.entity_id = CAST(u.id AS CHAR)
           )"
    );
    $editQuery->execute([$editingId]);
    $editing = $editQuery->fetch() ?: null;
}

$faculty = $pdo->query(
    "SELECT u.id, u.name, u.email, u.is_active, fp.branch, fp.subject, fp.max_students,
            COUNT(a.student_id) AS student_count
     FROM users u JOIN faculty_profiles fp ON fp.user_id = u.id
     LEFT JOIN faculty_student_assignments a ON a.faculty_id = u.id
     WHERE u.role = 'faculty'
       AND NOT EXISTS (
           SELECT 1 FROM audit_logs al
           WHERE al.action = 'account.deleted' AND al.entity_type = 'faculty'
             AND al.entity_id = CAST(u.id AS CHAR)
       )
     GROUP BY u.id ORDER BY u.name"
)->fetchAll();
$students = $pdo->query(
    "SELECT u.id, u.name, u.email, u.is_active, sp.registration_no, sp.branch, sp.semester, sp.academic_year,
            fa.faculty_id, f.name AS faculty_name
     FROM users u
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     LEFT JOIN faculty_student_assignments fa ON fa.student_id = u.id
     LEFT JOIN users f ON f.id = fa.faculty_id
     WHERE u.role = 'student'
       AND NOT EXISTS (
           SELECT 1 FROM audit_logs al
           WHERE al.action = 'account.deleted' AND al.entity_type = 'student'
             AND al.entity_id = CAST(u.id AS CHAR)
       )
     ORDER BY u.name"
)->fetchAll();
$unassignedStudents = array_values(array_filter($students, static fn(array $student): bool => empty($student['faculty_id']) && (int) $student['is_active'] === 1));

$pageTitle = 'HOD · Department management';
include __DIR__ . '/../includes/header.php';
$adminActive = 'department';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div><h3 class="mb-1">Department management</h3><p class="text-muted mb-0">Manage faculty, student profiles, and faculty assignments.</p></div>
  </div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>

  <div class="row g-4 mb-4">
    <div class="col-lg-5">
      <div class="card p-4">
        <h5><?= $editing ? 'Edit account' : 'Create faculty or student' ?></h5>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="<?= $editing ? 'update_user' : 'create_user' ?>">
          <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Account type</label>
            <select class="form-select" name="role" id="accountRole" <?= $editing ? 'disabled' : '' ?> required>
              <option value="student" <?= ($editing['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
              <option value="faculty" <?= ($editing['role'] ?? '') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
            </select>
            <?php if ($editing): ?><input type="hidden" name="role" value="<?= e($editing['role']) ?>"><?php endif; ?>
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="name" required minlength="2" value="<?= e($editing['name'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required value="<?= e($editing['email'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">College</label><input class="form-control" name="college" value="<?= e($editing['college'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Mobile</label><input class="form-control" name="mobile" value="<?= e($editing['mobile'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">Password <?= $editing ? '(leave blank to keep current)' : '' ?></label><input class="form-control" type="password" name="password" <?= $editing ? '' : 'required' ?> minlength="8" data-password-policy autocomplete="new-password"><div class="form-text">At least 8 characters, including a number and one of @ # $ % &amp; * !. No maximum length. Leave blank to keep the current password when editing.</div></div>
          </div>
          <div class="department-profile mt-3" data-profile="faculty">
            <div class="row g-3">
              <div class="col-md-5"><label class="form-label">Branch</label><input class="form-control" name="faculty_branch" value="<?= e($editing['faculty_branch'] ?? '') ?>"></div>
              <div class="col-md-4"><label class="form-label">Subject</label><input class="form-control" name="subject" value="<?= e($editing['subject'] ?? '') ?>"></div>
              <div class="col-md-3"><label class="form-label">Student limit</label><input class="form-control" type="number" name="max_students" min="1" max="100" value="<?= (int) ($editing['max_students'] ?? 12) ?>"></div>
            </div>
          </div>
          <div class="department-profile mt-3" data-profile="student">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Registration number</label><input class="form-control" name="registration_no" value="<?= e($editing['registration_no'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">Branch</label><input class="form-control" name="student_branch" value="<?= e($editing['student_branch'] ?? '') ?>"></div>
              <div class="col-md-4"><label class="form-label">Semester</label><input class="form-control" type="number" name="semester" min="1" max="12" value="<?= e(isset($editing['semester']) ? (string) $editing['semester'] : '') ?>"></div>
              <div class="col-md-8"><label class="form-label">Academic year</label><input class="form-control" name="academic_year" placeholder="e.g. 2026-27" value="<?= e($editing['academic_year'] ?? '') ?>"></div>
            </div>
          </div>
          <button class="btn btn-primary mt-4" type="submit"><?= $editing ? 'Save changes' : 'Create account' ?></button>
          <?php if ($editing): ?><a class="btn btn-outline-secondary mt-4" href="<?= e(base_url('admin/department.php')) ?>">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card p-4">
        <h5>Assign a student to faculty</h5>
        <form method="post" class="row g-3 align-items-end">
          <?= csrf_field() ?><input type="hidden" name="action" value="assign_student">
          <div class="col-md-5"><label class="form-label">Unassigned student</label>
            <select class="form-select" name="student_id" required>
              <option value="">Select student</option>
              <?php foreach ($unassignedStudents as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['name'] . ($student['registration_no'] ? ' · ' . $student['registration_no'] : '')) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5"><label class="form-label">Faculty</label>
            <select class="form-select" name="faculty_id" required><option value="">Select faculty</option>
              <?php foreach ($faculty as $member): if (!(int) $member['is_active'] || (int) $member['student_count'] >= (int) $member['max_students']) continue; ?>
                <option value="<?= (int) $member['id'] ?>"><?= e($member['name'] . ' · ' . $member['student_count'] . '/' . $member['max_students']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Assign</button></div>
        </form>
        <small class="text-muted mt-2">Capacity defaults to 12 students per faculty member and can be changed in the faculty profile.</small>
      </div>
    </div>
  </div>

  <div class="card p-3 mb-4">
    <h5 class="px-2 pt-2">Faculty workload</h5>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Faculty</th><th>Branch / subject</th><th>Assigned</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($faculty as $member): ?><tr>
        <td><?= e($member['name']) ?><div class="small text-muted"><?= e($member['email']) ?></div></td>
        <td><?= e(trim(($member['branch'] ?? '') . ' / ' . ($member['subject'] ?? ''), ' /') ?: '—') ?></td>
        <td><?= (int) $member['student_count'] ?> / <?= (int) $member['max_students'] ?></td>
        <td><span class="badge bg-<?= $member['is_active'] ? 'success' : 'secondary' ?>"><?= $member['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/department.php?edit=' . (int) $member['id'])) ?>">Edit</a>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="<?= (int) $member['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= $member['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
          <form method="post" class="d-inline" data-delete-confirm data-delete-name="<?= e($member['name']) ?>" data-delete-email="<?= e($member['email']) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $member['id'] ?>"><input type="hidden" name="confirm_delete" value=""><button class="btn btn-sm btn-danger" type="submit" aria-label="Delete faculty account for <?= e($member['name']) ?>">Delete</button></form>
        </td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
  </div>

  <div class="card p-3">
    <h5 class="px-2 pt-2">Students</h5>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Student</th><th>Registration</th><th>Branch / semester</th><th>Assigned faculty</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($students as $student): ?><tr>
        <td><?= e($student['name']) ?><div class="small text-muted"><?= e($student['email']) ?></div></td>
        <td><?= e($student['registration_no'] ?: '—') ?></td>
        <td><?= e(($student['branch'] ?: '—') . ($student['semester'] ? ' · Sem ' . $student['semester'] : '')) ?></td>
        <td><?= e($student['faculty_name'] ?: 'Unassigned') ?></td>
        <td><span class="badge bg-<?= $student['is_active'] ? 'success' : 'secondary' ?>"><?= $student['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/department.php?edit=' . (int) $student['id'])) ?>">Edit</a>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= $student['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
          <?php if (!empty($student['faculty_id'])): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="unassign_student"><input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Unassign</button></form><?php endif; ?>
          <form method="post" class="d-inline" data-delete-confirm data-delete-name="<?= e($student['name']) ?>" data-delete-email="<?= e($student['email']) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><input type="hidden" name="confirm_delete" value=""><button class="btn btn-sm btn-danger" type="submit" aria-label="Delete student account for <?= e($student['name']) ?>">Delete</button></form>
        </td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<dialog id="departmentDeleteDialog" class="p-0 border-0 rounded-3 shadow" aria-labelledby="departmentDeleteTitle" style="max-width: 28rem; width: calc(100% - 2rem);">
  <div class="p-4">
    <h2 class="h5 mb-3" id="departmentDeleteTitle">Delete account</h2>
    <p class="mb-1 fw-semibold" id="departmentDeleteName"></p>
    <p class="text-muted small mb-3" id="departmentDeleteEmail"></p>
    <p class="mb-4">Are you sure you want to delete this account?</p>
    <div class="d-flex justify-content-end gap-2">
      <button class="btn btn-outline-secondary" type="button" data-delete-cancel>Cancel</button>
      <button class="btn btn-danger" type="button" data-delete-approve>Confirm Delete</button>
    </div>
  </div>
</dialog>
<script>
(() => {
  const deleteDialog = document.getElementById('departmentDeleteDialog');
  const deleteName = document.getElementById('departmentDeleteName');
  const deleteEmail = document.getElementById('departmentDeleteEmail');
  let pendingDeleteForm = null;

  document.querySelectorAll('form[data-delete-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      pendingDeleteForm = form;
      deleteName.textContent = form.dataset.deleteName || 'Selected account';
      deleteEmail.textContent = form.dataset.deleteEmail || '';
      deleteDialog.showModal();
    });
  });

  document.querySelector('[data-delete-cancel]').addEventListener('click', () => {
    pendingDeleteForm = null;
    deleteDialog.close();
  });
  document.querySelector('[data-delete-approve]').addEventListener('click', () => {
    if (!pendingDeleteForm) return;
    pendingDeleteForm.querySelector('[name="confirm_delete"]').value = '1';
    pendingDeleteForm.submit();
  });
  deleteDialog.addEventListener('close', () => {
    pendingDeleteForm = null;
  });

  const role = document.getElementById('accountRole');
  if (!role) return;
  const updateProfiles = () => document.querySelectorAll('.department-profile').forEach((section) => {
    const active = section.dataset.profile === role.value;
    section.hidden = !active;
    section.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
  });
  role.addEventListener('change', updateProfiles);
  updateProfiles();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
