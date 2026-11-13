<?php
declare(strict_types=1);

function faculty_csv_safe_cell(mixed $value): string
{
    $text = (string) ($value ?? '');
    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $text)) {
        return "'" . $text;
    }
    return $text;
}

function notify_faculty_students(
    PDO $pdo,
    int $facultyId,
    string $type,
    string $title,
    string $message,
    string $link
): void {
    $recipients = $pdo->prepare(
        "SELECT fsa.student_id
         FROM faculty_student_assignments fsa
         JOIN users u ON u.id = fsa.student_id AND u.role = 'student' AND u.is_active = 1
         WHERE fsa.faculty_id = ?"
    );
    $recipients->execute([$facultyId]);
    $insert = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($recipients->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
        $insert->execute([(int) $studentId, $type, $title, $message, $link]);
    }
}

function faculty_assigned_student(PDO $pdo, int $facultyId, int $studentId): bool
{
    $query = $pdo->prepare(
        'SELECT 1 FROM faculty_student_assignments WHERE faculty_id = ? AND student_id = ? LIMIT 1'
    );
    $query->execute([$facultyId, $studentId]);
    return (bool) $query->fetchColumn();
}

function validate_faculty_timetable_slot(
    PDO $pdo,
    int $facultyId,
    string $startsAt,
    string $endsAt,
    int $ignoreEventId = 0
): void {
    $start = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startsAt);
    $startErrors = DateTimeImmutable::getLastErrors();
    $end = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endsAt);
    $endErrors = DateTimeImmutable::getLastErrors();
    if (!$start || !$end
        || ($startErrors !== false && ($startErrors['warning_count'] || $startErrors['error_count']))
        || ($endErrors !== false && ($endErrors['warning_count'] || $endErrors['error_count']))
        || $start->format('Y-m-d\TH:i') !== $startsAt
        || $end->format('Y-m-d\TH:i') !== $endsAt
        || $end <= $start) {
        throw new InvalidArgumentException('Choose a valid start and end time for the timetable event.');
    }

    $overlap = $pdo->prepare(
        'SELECT COUNT(*) FROM faculty_timetable
         WHERE faculty_id = ? AND starts_at < ? AND ends_at > ? AND id <> ?'
    );
    $overlap->execute([
        $facultyId,
        $end->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s'),
        $ignoreEventId,
    ]);
    if ((int) $overlap->fetchColumn() > 0) {
        throw new InvalidArgumentException('This event overlaps another item in your timetable.');
    }

    $examOverlap = $pdo->prepare(
        "SELECT COUNT(DISTINCT w.quiz_id)
         FROM exam_workflows w
         JOIN exam_assigned_students eas ON eas.quiz_id = w.quiz_id
         JOIN faculty_student_assignments fsa
           ON fsa.student_id = eas.student_id AND fsa.faculty_id = ?
         WHERE w.status IN ('pending','approved','published')
           AND w.scheduled_start < ? AND w.scheduled_end > ?"
    );
    $examOverlap->execute([
        $facultyId,
        $end->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s'),
    ]);
    if ((int) $examOverlap->fetchColumn() > 0) {
        throw new InvalidArgumentException('This event overlaps an approved examination for one or more of your students.');
    }
}
