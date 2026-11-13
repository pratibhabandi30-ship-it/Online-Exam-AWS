<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active)
         VALUES (?, ?, ?, ?, 1)'
    );
    $insertUser->execute([
        'Attendance Test Faculty',
        'attendance-faculty-' . $suffix . '@oes.test',
        password_hash('test-password', PASSWORD_BCRYPT),
        'faculty',
    ]);
    $facultyId = (int) $pdo->lastInsertId();
    $insertUser->execute([
        'Attendance Test Student',
        'attendance-student-' . $suffix . '@oes.test',
        password_hash('test-password', PASSWORD_BCRYPT),
        'student',
    ]);
    $studentId = (int) $pdo->lastInsertId();
    $insertUser->execute([
        'Attendance Unassigned Student',
        'attendance-unassigned-' . $suffix . '@oes.test',
        password_hash('test-password', PASSWORD_BCRYPT),
        'student',
    ]);
    $unassignedStudentId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO faculty_profiles (user_id, subject) VALUES (?, ?)')
        ->execute([$facultyId, 'Attendance Testing']);
    $pdo->prepare('INSERT INTO student_profiles (user_id, registration_no) VALUES (?, ?)')
        ->execute([$studentId, 'ATT-' . $suffix]);
    $pdo->prepare('INSERT INTO student_profiles (user_id, registration_no) VALUES (?, ?)')
        ->execute([$unassignedStudentId, 'UNASSIGNED-' . $suffix]);
    $pdo->prepare(
        'INSERT INTO faculty_student_assignments (faculty_id, student_id) VALUES (?, ?)'
    )->execute([$facultyId, $studentId]);

    $attendanceDate = (new DateTimeImmutable('+40 days'))->format('Y-m-d');
    $save = $pdo->prepare(
        'INSERT INTO faculty_attendance (faculty_id, student_id, attendance_date, status, remarks)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)'
    );
    $save->execute([$facultyId, $studentId, $attendanceDate, 'present', 'Initial mark']);
    $save->execute([$facultyId, $studentId, $attendanceDate, 'late', 'Updated mark']);
    $secondAttendanceDate = (new DateTimeImmutable('+41 days'))->format('Y-m-d');
    $save->execute([$facultyId, $studentId, $secondAttendanceDate, 'absent', 'Second saved day']);

    $recordCount = $pdo->prepare(
        'SELECT COUNT(*) FROM faculty_attendance
         WHERE faculty_id = ? AND student_id = ? AND attendance_date = ?'
    );
    $recordCount->execute([$facultyId, $studentId, $attendanceDate]);
    if ((int) $recordCount->fetchColumn() !== 1) {
        throw new RuntimeException('Saving or editing attendance created duplicate daily entries.');
    }

    $savedRecord = $pdo->prepare(
        'SELECT status, remarks FROM faculty_attendance
         WHERE faculty_id = ? AND student_id = ? AND attendance_date = ?'
    );
    $savedRecord->execute([$facultyId, $studentId, $attendanceDate]);
    $saved = $savedRecord->fetch(PDO::FETCH_ASSOC);
    if ($saved['status'] !== 'late' || $saved['remarks'] !== 'Updated mark') {
        throw new RuntimeException('The saved attendance update did not persist the latest status and remarks.');
    }

    $percentageQuery = $pdo->prepare(
        "SELECT COUNT(*) AS total, SUM(status IN ('present','late')) AS attended
         FROM faculty_attendance WHERE faculty_id = ? AND student_id = ?"
    );
    $percentageQuery->execute([$facultyId, $studentId]);
    $percentage = $percentageQuery->fetch(PDO::FETCH_ASSOC);
    $percent = round((int) $percentage['attended'] * 100 / (int) $percentage['total'], 1);
    if ($percent !== 50.0) {
        throw new RuntimeException('Student attendance percentage did not reflect the saved records.');
    }

    $pdo->prepare('DELETE FROM faculty_student_assignments WHERE faculty_id = ? AND student_id = ?')
        ->execute([$facultyId, $studentId]);
    $studentHistory = $pdo->prepare(
        'SELECT COUNT(*) FROM faculty_attendance WHERE student_id = ?'
    );
    $studentHistory->execute([$studentId]);
    if ((int) $studentHistory->fetchColumn() !== 2) {
        throw new RuntimeException('Student attendance history was not retained after unassignment.');
    }

    $assignmentLookup = $pdo->prepare(
        'SELECT student_id FROM faculty_student_assignments WHERE faculty_id = ?'
    );
    $assignmentLookup->execute([$facultyId]);
    if (in_array($unassignedStudentId, array_map('intval', $assignmentLookup->fetchAll(PDO::FETCH_COLUMN)), true)) {
        throw new RuntimeException('An unassigned student passed the faculty assignment check.');
    }

    echo "Faculty attendance save, update, uniqueness, percentage, history, and assignment-scope checks passed.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
