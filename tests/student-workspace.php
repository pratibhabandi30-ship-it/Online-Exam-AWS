<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/faculty-tools.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $insertUser->execute(['Student Workspace Faculty', 'student-workspace-faculty-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'faculty']);
    $facultyId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Student Workspace Other Faculty', 'student-workspace-other-faculty-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'faculty']);
    $otherFacultyId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Student Workspace Student', 'student-workspace-student-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $studentId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Student Workspace Other Student', 'student-workspace-other-student-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $otherStudentId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Student Workspace HOD', 'student-workspace-hod-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'admin']);
    $hodId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO faculty_profiles (user_id, subject) VALUES (?, ?)')->execute([$facultyId, 'Workspace Subject']);
    $pdo->prepare('INSERT INTO faculty_profiles (user_id, subject) VALUES (?, ?)')->execute([$otherFacultyId, 'Other Subject']);
    $pdo->prepare('INSERT INTO faculty_student_assignments (faculty_id, student_id) VALUES (?, ?)')->execute([$facultyId, $studentId]);
    $pdo->prepare('INSERT INTO faculty_student_assignments (faculty_id, student_id) VALUES (?, ?)')->execute([$otherFacultyId, $otherStudentId]);

    $startsAt = (new DateTimeImmutable('+2 days'))->setTime(10, 0);
    $endsAt = $startsAt->modify('+1 hour');
    $pdo->prepare(
        "INSERT INTO faculty_timetable (faculty_id, title, event_type, starts_at, ends_at, location)
         VALUES (?, 'Assigned class', 'class', ?, ?, 'Room 10')"
    )->execute([$facultyId, $startsAt->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s')]);
    $pdo->prepare(
        "INSERT INTO faculty_timetable (faculty_id, title, event_type, starts_at, ends_at)
         VALUES (?, 'Unassigned class', 'class', ?, ?)"
    )->execute([$otherFacultyId, $startsAt->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s')]);
    $pdo->prepare(
        'INSERT INTO faculty_attendance (faculty_id, student_id, attendance_date, status, remarks)
         VALUES (?, ?, CURDATE(), ?, ?)'
    )->execute([$facultyId, $studentId, 'late', 'Workspace attendance']);
    $pdo->prepare(
        'INSERT INTO faculty_attendance (faculty_id, student_id, attendance_date, status)
         VALUES (?, ?, CURDATE(), ?)'
    )->execute([$otherFacultyId, $otherStudentId, 'present']);
    $pdo->prepare(
        'INSERT INTO faculty_announcements (faculty_id, title, body, category) VALUES (?, ?, ?, ?)'
    )->execute([$facultyId, 'Assigned announcement', 'Only for this student', 'general']);
    $pdo->prepare(
        'INSERT INTO faculty_announcements (faculty_id, title, body, category) VALUES (?, ?, ?, ?)'
    )->execute([$otherFacultyId, 'Unassigned announcement', 'Not for this student', 'general']);

    $insertQuiz = $pdo->prepare(
        'INSERT INTO quizzes (title, tag, duration_minutes, passing_percent, difficulty, is_published, created_by)
         VALUES (?, ?, 30, 50, ?, 1, ?)'
    );
    $insertQuiz->execute(['Student Workspace Published Exam', 'Workspace Subject', 'Beginner', $facultyId]);
    $publishedQuizId = (int) $pdo->lastInsertId();
    $insertQuiz->execute(['Student Workspace Pending Exam', 'Workspace Subject', 'Beginner', $facultyId]);
    $pendingQuizId = (int) $pdo->lastInsertId();
    $insertQuiz->execute(['Student Workspace Other Student Exam', 'Other Subject', 'Beginner', $otherFacultyId]);
    $otherQuizId = (int) $pdo->lastInsertId();
    $workflow = $pdo->prepare(
        'INSERT INTO exam_workflows (quiz_id, faculty_id, status, scheduled_start, scheduled_end)
         VALUES (?, ?, ?, ?, ?)'
    );
    $workflow->execute([$publishedQuizId, $facultyId, 'published', $startsAt->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s')]);
    $workflow->execute([$pendingQuizId, $facultyId, 'approved', $startsAt->modify('+1 day')->format('Y-m-d H:i:s'), $endsAt->modify('+1 day')->format('Y-m-d H:i:s')]);
    $workflow->execute([$otherQuizId, $otherFacultyId, 'published', $startsAt->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s')]);
    $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)')->execute([$publishedQuizId, $studentId]);
    $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)')->execute([$pendingQuizId, $studentId]);
    $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)')->execute([$otherQuizId, $otherStudentId]);
    $pdo->prepare('INSERT INTO questions (quiz_id, text, marks, position) VALUES (?, ?, 10, 1)')
        ->execute([$publishedQuizId, 'Workspace question']);

    $pdo->prepare(
        'INSERT INTO attempts (user_id, quiz_id, score, total_marks, submitted_at) VALUES (?, ?, 8, 10, NOW())'
    )->execute([$studentId, $publishedQuizId]);
    $attemptId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO result_publications (quiz_id, published_by, published_at) VALUES (?, ?, NOW())'
    )->execute([$publishedQuizId, $hodId]);
    $pdo->prepare(
        'INSERT INTO attempts (user_id, quiz_id, score, total_marks, submitted_at) VALUES (?, ?, 10, 10, NOW())'
    )->execute([$studentId, $pendingQuizId]);

    $eventQuery = $pdo->prepare(
        'SELECT t.title FROM faculty_timetable t
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = t.faculty_id
         WHERE fsa.student_id = ? AND t.ends_at >= NOW()'
    );
    $eventQuery->execute([$studentId]);
    if ($eventQuery->fetchAll(PDO::FETCH_COLUMN) !== ['Assigned class']) {
        throw new RuntimeException('Student timetable returned an unassigned faculty event.');
    }

    $attendanceQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM faculty_attendance a
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = a.faculty_id AND fsa.student_id = a.student_id
         WHERE a.student_id = ?'
    );
    $attendanceQuery->execute([$studentId]);
    if ((int) $attendanceQuery->fetchColumn() !== 1) {
        throw new RuntimeException('Student attendance is not scoped to the current assignment.');
    }

    $announcementQuery = $pdo->prepare(
        'SELECT a.title FROM faculty_announcements a
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = a.faculty_id AND fsa.student_id = ?
         ORDER BY a.title'
    );
    $announcementQuery->execute([$studentId]);
    if ($announcementQuery->fetchAll(PDO::FETCH_COLUMN) !== ['Assigned announcement']) {
        throw new RuntimeException('Student announcements returned an unassigned faculty message.');
    }

    $scheduledQuery = $pdo->prepare(
        "SELECT q.id FROM quizzes q
         JOIN exam_workflows w ON w.quiz_id = q.id
         JOIN exam_assigned_students eas ON eas.quiz_id = q.id AND eas.student_id = ?
         JOIN faculty_student_assignments fsa ON fsa.faculty_id = w.faculty_id AND fsa.student_id = ?
         WHERE q.is_published = 1 AND w.status = 'published' AND w.scheduled_end >= NOW()"
    );
    $scheduledQuery->execute([$studentId, $studentId]);
    if (array_map('intval', $scheduledQuery->fetchAll(PDO::FETCH_COLUMN)) !== [$publishedQuizId]) {
        throw new RuntimeException('Student upcoming examinations included an unapproved or unassigned exam.');
    }

    $publishedResultsQuery = $pdo->prepare(
        'SELECT a.id FROM attempts a
         WHERE a.user_id = ? AND EXISTS (SELECT 1 FROM result_publications rp WHERE rp.quiz_id = a.quiz_id)
         ORDER BY a.id'
    );
    $publishedResultsQuery->execute([$studentId]);
    if (array_map('intval', $publishedResultsQuery->fetchAll(PDO::FETCH_COLUMN)) !== [$attemptId]) {
        throw new RuntimeException('Student result list exposed an unpublished attempt.');
    }

    notify_faculty_students($pdo, $facultyId, 'workspace_test', 'Workspace test', 'Test notification', 'timetable.php');
    $notificationQuery = $pdo->prepare('SELECT user_id FROM notifications WHERE type = ? AND title = ?');
    $notificationQuery->execute(['workspace_test', 'Workspace test']);
    if (array_map('intval', $notificationQuery->fetchAll(PDO::FETCH_COLUMN)) !== [$studentId]) {
        throw new RuntimeException('Student workspace notifications were sent outside the faculty assignment.');
    }

    echo "Student timetable, attendance, announcements, exam access, published results, and notifications passed.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
