<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function notify_user(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $title, $message, $link]);
}

function notify_role(PDO $pdo, string $role, string $type, string $title, string $message, ?string $link = null): void
{
    $users = $pdo->prepare('SELECT id FROM users WHERE role = ? AND is_active = 1');
    $users->execute([$role]);
    foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        notify_user($pdo, (int) $userId, $type, $title, $message, $link);
    }
}

function validate_exam_schedule(?string $start, ?string $end, int $durationMinutes, bool $requireFuture = true): array
{
    if ($start === null || $end === null || $start === '' || $end === '') {
        throw new InvalidArgumentException('Choose both a start and end time before requesting approval.');
    }

    $startAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start);
    $endAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$startAt || !$endAt || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        throw new InvalidArgumentException('Enter a valid examination schedule.');
    }
    if ($requireFuture && $startAt <= new DateTimeImmutable()) {
        throw new InvalidArgumentException('Examinations cannot be scheduled in the past.');
    }
    if ($endAt <= $startAt || $endAt->getTimestamp() - $startAt->getTimestamp() < $durationMinutes * 60) {
        throw new InvalidArgumentException('The scheduled window must be at least as long as the exam duration.');
    }

    return [$startAt, $endAt];
}

function ensure_no_exam_schedule_conflict(PDO $pdo, int $quizId, DateTimeImmutable $startAt, DateTimeImmutable $endAt): void
{
    $currentExam = $pdo->prepare(
        'SELECT faculty_id FROM exam_workflows WHERE quiz_id = ? FOR UPDATE'
    );
    $currentExam->execute([$quizId]);
    $facultyId = $currentExam->fetchColumn();
    if ($facultyId === false) {
        throw new InvalidArgumentException('This examination no longer has a faculty owner.');
    }

    $assignedStudents = $pdo->prepare(
        'SELECT u.id FROM users u
         JOIN exam_assigned_students assigned ON assigned.student_id = u.id
         WHERE assigned.quiz_id = ? ORDER BY u.id FOR UPDATE'
    );
    $assignedStudents->execute([$quizId]);
    $assignedStudents->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare(
        "SELECT other.quiz_id
         FROM exam_workflows current_exam
         JOIN exam_assigned_students current_students ON current_students.quiz_id = current_exam.quiz_id
         JOIN exam_assigned_students other_students ON other_students.student_id = current_students.student_id
         JOIN exam_workflows other ON other.quiz_id = other_students.quiz_id
         WHERE current_exam.quiz_id = ?
           AND other.quiz_id <> ?
           AND other.status IN ('pending','approved','published')
           AND other.scheduled_start < ?
           AND other.scheduled_end > ?
         LIMIT 1"
    );
    $stmt->execute([
        $quizId,
        $quizId,
        $endAt->format('Y-m-d H:i:s'),
        $startAt->format('Y-m-d H:i:s'),
    ]);
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException('One or more assigned students already have an examination scheduled in this time window.');
    }

    $facultyEventConflict = $pdo->prepare(
        'SELECT id FROM faculty_timetable
         WHERE faculty_id = ? AND starts_at < ? AND ends_at > ? LIMIT 1 FOR UPDATE'
    );
    $facultyEventConflict->execute([
        (int) $facultyId,
        $endAt->format('Y-m-d H:i:s'),
        $startAt->format('Y-m-d H:i:s'),
    ]);
    if ($facultyEventConflict->fetchColumn()) {
        throw new InvalidArgumentException('The faculty member already has a timetable event during this examination window.');
    }
}
