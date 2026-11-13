<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "Running Multiple Faculty-Student Assignments test suite...\n\n";

// 1. Check existing users
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'student'");
$stmt->execute(['dileep123@gmail.com']);
$dileep = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dileep) {
    throw new RuntimeException("Student Dileep not found in database.");
}
$dileepId = (int) $dileep['id'];

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'faculty'");
$stmt->execute(['kavi89@gmail.com']);
$kavitha = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$kavitha) {
    throw new RuntimeException("Faculty Kavitha not found in database.");
}
$kavithaId = (int) $kavitha['id'];

$stmt->execute(['anjali@gmail.com']);
$anjali = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$anjali) {
    throw new RuntimeException("Faculty Anjali not found in database.");
}
$anjaliId = (int) $anjali['id'];

echo "Found users: Dileep (ID: $dileepId), Kavitha (ID: $kavithaId), Anjali (ID: $anjaliId)\n";

// Ensure initial state: Dileep is assigned to Kavitha, and not to Anjali
$pdo->prepare('DELETE FROM faculty_student_assignments WHERE student_id = ? AND faculty_id = ?')
    ->execute([$dileepId, $anjaliId]);

$ensureKavitha = $pdo->prepare(
    'INSERT INTO faculty_student_assignments (faculty_id, student_id, assigned_by)
     VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by)'
);
$ensureKavitha->execute([$kavithaId, $dileepId]);

// Verify initial state: Dileep is assigned ONLY to Kavitha
$checkInitial = $pdo->prepare('SELECT faculty_id FROM faculty_student_assignments WHERE student_id = ?');
$checkInitial->execute([$dileepId]);
$faculties = array_map('intval', $checkInitial->fetchAll(PDO::FETCH_COLUMN));
if ($faculties !== [$kavithaId]) {
    throw new RuntimeException("Initial condition failed: Dileep should only be assigned to Kavitha.");
}
echo "Step 1 Passed: Dileep is initially assigned to Kavitha.\n";

// Step 2: Test Student Directory Query from Anjali's perspective
// Anjali should see Dileep in directory, marked selectable, with assigned faculty 'kavitha', is_assigned_to_me = 0
$dirQuery = $pdo->prepare(
    "SELECT u.id, u.name,
            EXISTS (
                SELECT 1 FROM faculty_student_assignments my_fsa
                WHERE my_fsa.faculty_id = ? AND my_fsa.student_id = u.id
            ) AS is_assigned_to_me,
            (
                SELECT GROUP_CONCAT(f.name ORDER BY f.name SEPARATOR ', ')
                FROM faculty_student_assignments fsa
                JOIN users f ON f.id = fsa.faculty_id
                WHERE fsa.student_id = u.id
            ) AS assigned_faculty_names
     FROM users u
     WHERE u.id = ?"
);
$dirQuery->execute([$anjaliId, $dileepId]);
$anjaliViewDileep = $dirQuery->fetch(PDO::FETCH_ASSOC);
if (!$anjaliViewDileep) {
    throw new RuntimeException("Dileep not found in Student Directory.");
}
if ((int) $anjaliViewDileep['is_assigned_to_me'] !== 0) {
    throw new RuntimeException("Dileep should not yet be assigned to Anjali.");
}
if (stripos($anjaliViewDileep['assigned_faculty_names'], 'kavitha') === false) {
    throw new RuntimeException("Student Directory should display assigned faculty 'kavitha'.");
}
echo "Step 2 Passed: Student Directory displays all registered students with assigned faculty names.\n";

// Step 3: Anjali selects Dileep and assigns to herself
$alreadyAssignedQuery = $pdo->prepare(
    "SELECT student_id FROM faculty_student_assignments
     WHERE faculty_id = ? AND student_id IN (?)"
);
$alreadyAssignedQuery->execute([$anjaliId, $dileepId]);
$alreadyAssignedIds = array_map('intval', $alreadyAssignedQuery->fetchAll(PDO::FETCH_COLUMN));
$newStudentIds = array_values(array_diff([$dileepId], $alreadyAssignedIds));

if (count($newStudentIds) !== 1 || $newStudentIds[0] !== $dileepId) {
    throw new RuntimeException("Dileep should be identified as a new assignment for Anjali.");
}

$insertAssignment = $pdo->prepare(
    'INSERT INTO faculty_student_assignments (faculty_id, student_id, assigned_by)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP'
);
$insertAssignment->execute([$anjaliId, $dileepId, $anjaliId]);

// Step 4: Verify duplicate prevention between the same faculty and student
$dupBlocked = false;
try {
    $pdo->prepare('INSERT INTO faculty_student_assignments (faculty_id, student_id, assigned_by) VALUES (?, ?, ?)')
        ->execute([$anjaliId, $dileepId, $anjaliId]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $dupBlocked = true;
    }
}
if (!$dupBlocked) {
    throw new RuntimeException("Duplicate assignment between the same faculty and student was not prevented!");
}
echo "Step 3 Passed: Anjali assigned Dileep to herself and duplicate assignment is prevented.\n";

// Step 5: Dileep must now appear in BOTH Anjali's and Kavitha's My Students pages
// 5a: From Anjali's view
$dirQuery->execute([$anjaliId, $dileepId]);
$anjaliViewAfter = $dirQuery->fetch(PDO::FETCH_ASSOC);
if ((int) $anjaliViewAfter['is_assigned_to_me'] !== 1) {
    throw new RuntimeException("Dileep should now be marked as assigned to Anjali.");
}
if (stripos($anjaliViewAfter['assigned_faculty_names'], 'Anjali') === false ||
    stripos($anjaliViewAfter['assigned_faculty_names'], 'kavitha') === false) {
    throw new RuntimeException("Student Directory should display both Anjali and Kavitha: " . $anjaliViewAfter['assigned_faculty_names']);
}

// 5b: From Kavitha's view
$dirQuery->execute([$kavithaId, $dileepId]);
$kavithaViewAfter = $dirQuery->fetch(PDO::FETCH_ASSOC);
if ((int) $kavithaViewAfter['is_assigned_to_me'] !== 1) {
    throw new RuntimeException("Dileep should remain marked as assigned to Kavitha.");
}

// 5c: Check Dashboard "My students" queries
$dashboardStudentQuery = $pdo->prepare(
    "SELECT u.id, u.name
     FROM faculty_student_assignments assignment
     JOIN users u ON u.id = assignment.student_id AND u.is_active = 1
     WHERE assignment.faculty_id = ? AND u.id = ?"
);

$dashboardStudentQuery->execute([$anjaliId, $dileepId]);
if (!$dashboardStudentQuery->fetch()) {
    throw new RuntimeException("Dileep does not appear in Anjali's dashboard My Students.");
}

$dashboardStudentQuery->execute([$kavithaId, $dileepId]);
if (!$dashboardStudentQuery->fetch()) {
    throw new RuntimeException("Dileep does not appear in Kavitha's dashboard My Students.");
}
echo "Step 4 Passed: Dileep appears in BOTH Anjali's and Kavitha's My Students pages.\n";

// Step 6: Test independent attendance for both faculties
$today = date('Y-m-d');
$saveAttendance = $pdo->prepare(
    'INSERT INTO faculty_attendance (faculty_id, student_id, attendance_date, status, remarks)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)'
);

// Kavitha marks Present
$saveAttendance->execute([$kavithaId, $dileepId, $today, 'present', 'Kavitha class']);
// Anjali marks Late
$saveAttendance->execute([$anjaliId, $dileepId, $today, 'late', 'Anjali class']);

// Verify attendance records are independent and don't overwrite
$attQuery = $pdo->prepare('SELECT status, remarks FROM faculty_attendance WHERE faculty_id = ? AND student_id = ? AND attendance_date = ?');
$attQuery->execute([$kavithaId, $dileepId, $today]);
$kavithaAtt = $attQuery->fetch(PDO::FETCH_ASSOC);

$attQuery->execute([$anjaliId, $dileepId, $today]);
$anjaliAtt = $attQuery->fetch(PDO::FETCH_ASSOC);

if ($kavithaAtt['status'] !== 'present' || $anjaliAtt['status'] !== 'late') {
    throw new RuntimeException("Attendance records interfered with each other!");
}
echo "Step 5 Passed: Faculty attendance records remain independent and do not overwrite each other.\n";

// Step 7: Anjali removes Dileep from her assignments
$pdo->prepare('DELETE FROM faculty_student_assignments WHERE faculty_id = ? AND student_id = ?')
    ->execute([$anjaliId, $dileepId]);

// Step 8: Dileep must remain assigned to Kavitha
$checkFinal = $pdo->prepare('SELECT faculty_id FROM faculty_student_assignments WHERE student_id = ?');
$checkFinal->execute([$dileepId]);
$remainingFaculties = array_map('intval', $checkFinal->fetchAll(PDO::FETCH_COLUMN));

if ($remainingFaculties !== [$kavithaId]) {
    throw new RuntimeException("Dileep should remain assigned to Kavitha after Anjali unassigned him. Current: " . json_encode($remainingFaculties));
}

// Check Dashboard after unassignment
$dashboardStudentQuery->execute([$anjaliId, $dileepId]);
if ($dashboardStudentQuery->fetch()) {
    throw new RuntimeException("Dileep should no longer appear in Anjali's dashboard My Students.");
}

$dashboardStudentQuery->execute([$kavithaId, $dileepId]);
if (!$dashboardStudentQuery->fetch()) {
    throw new RuntimeException("Dileep should still appear in Kavitha's dashboard My Students.");
}
echo "Step 6 Passed: Dileep was removed from Anjali's assignments and remains assigned to Kavitha.\n";

// Clean up attendance record for testing
$pdo->prepare('DELETE FROM faculty_attendance WHERE student_id = ? AND attendance_date = ?')
    ->execute([$dileepId, $today]);

echo "\nALL TESTS PASSED SUCCESSFULLY! Exact scenario verified.\n";
