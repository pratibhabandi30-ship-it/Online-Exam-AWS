<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/faculty-tools.php';
require_once __DIR__ . '/../includes/exam-workflows.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $insertUser->execute(['Workspace Faculty One', 'workspace-faculty-one-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'faculty']);
    $facultyId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Workspace Faculty Two', 'workspace-faculty-two-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'faculty']);
    $otherFacultyId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Workspace Assigned Student', 'workspace-student-one-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $studentId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Workspace Other Student', 'workspace-student-two-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $otherStudentId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO faculty_student_assignments (faculty_id, student_id) VALUES (?, ?)')
        ->execute([$facultyId, $studentId]);

    if (!faculty_assigned_student($pdo, $facultyId, $studentId)
        || faculty_assigned_student($pdo, $facultyId, $otherStudentId)
        || faculty_assigned_student($pdo, $otherFacultyId, $studentId)) {
        throw new RuntimeException('Faculty assignment scoping failed.');
    }

    $startsAt = (new DateTimeImmutable('+10 days'))->setTime(10, 0);
    $endsAt = $startsAt->modify('+1 hour');
    validate_faculty_timetable_slot(
        $pdo,
        $facultyId,
        $startsAt->format('Y-m-d\TH:i'),
        $endsAt->format('Y-m-d\TH:i')
    );
    $pdo->prepare(
        'INSERT INTO faculty_timetable (faculty_id, title, event_type, starts_at, ends_at)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $facultyId,
        'Workspace conflict event',
        'class',
        $startsAt->format('Y-m-d H:i:s'),
        $endsAt->format('Y-m-d H:i:s'),
    ]);
    $conflictDetected = false;
    try {
        validate_faculty_timetable_slot(
            $pdo,
            $facultyId,
            $startsAt->modify('+30 minutes')->format('Y-m-d\TH:i'),
            $endsAt->modify('+30 minutes')->format('Y-m-d\TH:i')
        );
    } catch (InvalidArgumentException) {
        $conflictDetected = true;
    }
    if (!$conflictDetected) {
        throw new RuntimeException('Overlapping faculty timetable entries were not detected.');
    }
    $pdo->prepare(
        'INSERT INTO quizzes (title, duration_minutes, passing_percent, difficulty, is_published, created_by)
         VALUES (?, 30, 50, ?, 0, ?)'
    )->execute(['Workspace exam conflict ' . $suffix, 'Beginner', $facultyId]);
    $quizId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO exam_workflows (quiz_id, faculty_id, status, scheduled_start, scheduled_end)
         VALUES (?, ?, 'pending', ?, ?)"
    )->execute([
        $quizId,
        $facultyId,
        $startsAt->format('Y-m-d H:i:s'),
        $endsAt->format('Y-m-d H:i:s'),
    ]);
    $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)')
        ->execute([$quizId, $studentId]);
    $examEventConflict = false;
    try {
        ensure_no_exam_schedule_conflict($pdo, $quizId, $startsAt, $endsAt);
    } catch (InvalidArgumentException) {
        $examEventConflict = true;
    }
    if (!$examEventConflict) {
        throw new RuntimeException('Examination approval did not detect a conflicting academic event.');
    }

    $attendanceDate = date('Y-m-d');
    $attendanceSave = $pdo->prepare(
        'INSERT INTO faculty_attendance (faculty_id, student_id, attendance_date, status)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)'
    );
    $attendanceSave->execute([$facultyId, $studentId, $attendanceDate, 'present']);
    $attendanceSave->execute([$facultyId, $studentId, $attendanceDate, 'late']);
    $attendanceCheck = $pdo->prepare(
        'SELECT status FROM faculty_attendance WHERE faculty_id = ? AND student_id = ? AND attendance_date = ?'
    );
    $attendanceCheck->execute([$facultyId, $studentId, $attendanceDate]);
    if ($attendanceCheck->fetchColumn() !== 'late') {
        throw new RuntimeException('Date-wise attendance upsert failed.');
    }

    $pdo->prepare(
        'INSERT INTO faculty_announcements (faculty_id, title, body, category) VALUES (?, ?, ?, ?)'
    )->execute([$facultyId, 'Workspace announcement', 'Assigned student only', 'general']);
    $visibleQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM faculty_announcements a
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = a.faculty_id AND fsa.student_id = ?
         WHERE a.faculty_id = ?'
    );
    $visibleQuery->execute([$studentId, $facultyId]);
    $visibleToAssigned = (int) $visibleQuery->fetchColumn();
    $visibleQuery->execute([$otherStudentId, $facultyId]);
    $visibleToUnassigned = (int) $visibleQuery->fetchColumn();
    if ($visibleToAssigned !== 1 || $visibleToUnassigned !== 0) {
        throw new RuntimeException('Announcement visibility is not restricted to assigned students.');
    }

    $pdo->prepare(
        'INSERT INTO faculty_question_bank (faculty_id, category, question_type, question_data)
         VALUES (?, ?, ?, ?)'
    )->execute([$facultyId, 'Testing', 'mcq', '{"type":"mcq","text":"Which answer?","marks":1,"options":[],"correct":0}']);
    $bankOwnership = $pdo->prepare('SELECT COUNT(*) FROM faculty_question_bank WHERE faculty_id = ?');
    $bankOwnership->execute([$otherFacultyId]);
    if ((int) $bankOwnership->fetchColumn() !== 0) {
        throw new RuntimeException('Question bank ownership scope failed.');
    }

    $workflowColumns = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_workflows'
           AND COLUMN_NAME IN ('result_status','result_remarks','result_reviewed_by','result_reviewed_at','result_requested_at')"
    )->fetchColumn();
    if ((int) $workflowColumns !== 5) {
        throw new RuntimeException('Result approval workflow fields are missing.');
    }

    echo "Faculty workspace assignment, timetable, announcement, bank ownership, and result schema checks passed.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
