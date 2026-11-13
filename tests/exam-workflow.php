<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/exam-workflows.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $insertUser = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)"
    );
    $insertUser->execute(['Workflow Faculty One', 'workflow-faculty-one-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'faculty']);
    $facultyOne = (int) $pdo->lastInsertId();
    $insertUser->execute(['Workflow Faculty Two', 'workflow-faculty-two-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'faculty']);
    $facultyTwo = (int) $pdo->lastInsertId();
    $insertUser->execute(['Workflow Student', 'workflow-student-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $studentId = (int) $pdo->lastInsertId();
    $insertUser->execute(['Workflow Other Student', 'workflow-other-student-' . $suffix . '@oes.test', password_hash('test-password', PASSWORD_BCRYPT), 'student']);
    $otherStudentId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO faculty_profiles (user_id) VALUES (?), (?)')->execute([$facultyOne, $facultyTwo]);
    $pdo->prepare('INSERT INTO student_profiles (user_id) VALUES (?), (?)')->execute([$studentId, $otherStudentId]);
    $pdo->prepare('INSERT INTO faculty_student_assignments (faculty_id, student_id) VALUES (?, ?)')->execute([$facultyOne, $studentId]);

    $insertQuiz = $pdo->prepare(
        'INSERT INTO quizzes (title, duration_minutes, passing_percent, difficulty, is_published, created_by)
         VALUES (?, 30, 50, ?, 0, ?)'
    );
    $insertQuiz->execute(['Workflow test one ' . $suffix, 'Beginner', $facultyOne]);
    $quizOne = (int) $pdo->lastInsertId();
    $insertQuiz->execute(['Workflow test two ' . $suffix, 'Beginner', $facultyTwo]);
    $quizTwo = (int) $pdo->lastInsertId();
    $insertQuiz->execute(['Workflow test published ' . $suffix, 'Beginner', $facultyOne]);
    $publishedQuiz = (int) $pdo->lastInsertId();

    $startOne = (new DateTimeImmutable('+2 days'))->setTime(10, 0);
    $endOne = $startOne->modify('+1 hour');
    $startTwo = $startOne->modify('+2 hours');
    $endTwo = $startTwo->modify('+1 hour');
    $insertWorkflow = $pdo->prepare(
        'INSERT INTO exam_workflows (quiz_id, faculty_id, status, scheduled_start, scheduled_end)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insertWorkflow->execute([$quizOne, $facultyOne, 'pending', $startOne->format('Y-m-d H:i:s'), $endOne->format('Y-m-d H:i:s')]);
    $insertWorkflow->execute([$quizTwo, $facultyTwo, 'approved', $startTwo->format('Y-m-d H:i:s'), $endTwo->format('Y-m-d H:i:s')]);
    $startPublished = $startOne->modify('+4 hours');
    $endPublished = $startPublished->modify('+1 hour');
    $insertWorkflow->execute([$publishedQuiz, $facultyOne, 'published', $startPublished->format('Y-m-d H:i:s'), $endPublished->format('Y-m-d H:i:s')]);
    $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?), (?, ?)')
        ->execute([$quizOne, $studentId, $quizTwo, $studentId]);
    $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)')->execute([$publishedQuiz, $studentId]);
    $pdo->prepare('UPDATE quizzes SET is_published = 1 WHERE id = ?')->execute([$publishedQuiz]);

    $examEligibility = $pdo->prepare(
        'SELECT 1 FROM exam_assigned_students WHERE quiz_id = ? AND student_id = ?'
    );
    $examEligibility->execute([$quizTwo, $studentId]);
    $facultyAssignment = $pdo->prepare(
        'SELECT faculty_id FROM faculty_student_assignments WHERE student_id = ?'
    );
    $facultyAssignment->execute([$studentId]);
    if (!$examEligibility->fetchColumn() || (int) $facultyAssignment->fetchColumn() !== $facultyOne) {
        throw new RuntimeException('Exam eligibility must not depend on or change the student faculty assignment.');
    }
    $examEligibility->execute([$quizTwo, $otherStudentId]);
    if ($examEligibility->fetchColumn()) {
        throw new RuntimeException('A student not selected for an examination was granted eligibility.');
    }
    $duplicateMappingRejected = false;
    try {
        $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)')
            ->execute([$quizTwo, $studentId]);
    } catch (PDOException $error) {
        if ($error->getCode() !== '23000') {
            throw $error;
        }
        $duplicateMappingRejected = true;
    }
    if (!$duplicateMappingRejected) {
        throw new RuntimeException('Duplicate student-to-exam mappings were not rejected.');
    }

    ensure_no_exam_schedule_conflict($pdo, $quizOne, $startOne, $endOne);
    $pdo->prepare('UPDATE exam_workflows SET scheduled_start = ?, scheduled_end = ? WHERE quiz_id = ?')
        ->execute([
            $startOne->modify('+30 minutes')->format('Y-m-d H:i:s'),
            $endOne->modify('+30 minutes')->format('Y-m-d H:i:s'),
            $quizTwo,
        ]);
    $conflictDetected = false;
    try {
        ensure_no_exam_schedule_conflict($pdo, $quizOne, $startOne, $endOne);
    } catch (InvalidArgumentException $error) {
        $conflictDetected = true;
    }
    if (!$conflictDetected) {
        throw new RuntimeException('An overlapping examination for an assigned student was not detected.');
    }

    $visibleExams = $pdo->prepare(
        "SELECT q.id FROM quizzes q LEFT JOIN exam_workflows w ON w.quiz_id = q.id
         WHERE q.is_published = 1 AND (w.quiz_id IS NULL OR (w.status = 'published' AND EXISTS (
             SELECT 1 FROM exam_assigned_students assigned WHERE assigned.quiz_id = q.id AND assigned.student_id = ?
         )))"
    );
    $visibleExams->execute([$studentId]);
    if (!in_array($publishedQuiz, array_map('intval', $visibleExams->fetchAll(PDO::FETCH_COLUMN)), true)) {
        throw new RuntimeException('A published exam was not visible to its assigned student.');
    }
    $visibleExams->execute([$otherStudentId]);
    if (in_array($publishedQuiz, array_map('intval', $visibleExams->fetchAll(PDO::FETCH_COLUMN)), true)) {
        throw new RuntimeException('A published exam was visible to a non-assigned student.');
    }

    $insertAttempt = $pdo->prepare(
        'INSERT INTO attempts (user_id, quiz_id, score, total_marks) VALUES (?, ?, 7, 10)'
    );
    $insertAttempt->execute([$studentId, $quizOne]);
    $insertAttempt->execute([$studentId, $publishedQuiz]);
    $visibleResults = $pdo->prepare(
        "SELECT COUNT(*) FROM attempts a WHERE a.user_id = ?
         AND (NOT EXISTS (SELECT 1 FROM exam_workflows w WHERE w.quiz_id = a.quiz_id)
              OR EXISTS (SELECT 1 FROM result_publications rp WHERE rp.quiz_id = a.quiz_id))"
    );
    $visibleResults->execute([$studentId]);
    if ((int) $visibleResults->fetchColumn() !== 0) {
        throw new RuntimeException('Unpublished managed results were exposed to the student.');
    }
    $pdo->prepare('INSERT INTO result_publications (quiz_id, published_by, published_at) VALUES (?, ?, NOW())')
        ->execute([$publishedQuiz, $facultyOne]);
    $visibleResults->execute([$studentId]);
    if ((int) $visibleResults->fetchColumn() !== 1) {
        throw new RuntimeException('Publishing a result did not make it visible to its student.');
    }

    notify_user($pdo, $studentId, 'workflow_test', 'Workflow test', 'This notification must be rolled back.');
    $notification = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?');
    $notification->execute([$studentId, 'workflow_test']);
    if ((int) $notification->fetchColumn() !== 1) {
        throw new RuntimeException('A user notification was not saved.');
    }

    echo "Cross-faculty exam selection, assignment preservation, schedule conflict, access, result visibility, and notification checks passed.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
