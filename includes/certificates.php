<?php
/**
 * Certificate eligibility, issuance and record validation.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function certificate_type_for_percentage(float $percentage): ?string
{
    return match (true) {
        $percentage >= 80 && $percentage <= 100 => 'Platinum',
        $percentage >= 50 && $percentage < 80 => 'Gold',
        $percentage >= 35 && $percentage < 50 => 'Silver',
        default => null,
    };
}

function issue_certificate(PDO $pdo, int $attemptId, int $studentId, int $examId, int $score, int $totalMarks): ?string
{
    if ($totalMarks <= 0 || $score < 0 || $score > $totalMarks) {
        return null;
    }

    $percentage = $score * 100 / $totalMarks;
    $type = certificate_type_for_percentage($percentage);
    if ($type === null) {
        return null;
    }

    $certificateId = 'OES-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
    $verificationToken = bin2hex(random_bytes(32));
    $grade = grade_letter(round($percentage, 1));

    $stmt = $pdo->prepare("
        INSERT INTO certificates
            (certificate_id, student_id, exam_id, attempt_id, certificate_type, percentage, grade, verification_token)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $certificateId,
        $studentId,
        $examId,
        $attemptId,
        $type,
        number_format($percentage, 5, '.', ''),
        $grade,
        $verificationToken,
    ]);

    return $certificateId;
}

function backfill_eligible_certificates(PDO $pdo, ?int $studentId = null): int
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Certificate backfill must run outside an existing transaction.');
    }

    $sql = "
        SELECT a.id, a.user_id, a.quiz_id, a.score, a.total_marks
        FROM attempts a
        LEFT JOIN certificates c ON c.attempt_id = a.id
        WHERE c.id IS NULL
          AND a.total_marks > 0
          AND a.score <= a.total_marks
          AND a.score * 100 >= a.total_marks * 35
    ";
    $params = [];
    if ($studentId !== null) {
        $sql .= " AND a.user_id = ?";
        $params[] = $studentId;
    }
    $sql .= " ORDER BY a.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attempts = $stmt->fetchAll();
    if (!$attempts) {
        return 0;
    }

    $pdo->beginTransaction();
    $issued = 0;
    $existing = $pdo->prepare("SELECT id FROM certificates WHERE attempt_id = ? LIMIT 1");

    try {
        foreach ($attempts as $attempt) {
            try {
                issue_certificate(
                    $pdo,
                    (int) $attempt['id'],
                    (int) $attempt['user_id'],
                    (int) $attempt['quiz_id'],
                    (int) $attempt['score'],
                    (int) $attempt['total_marks']
                );
                $issued++;
            } catch (PDOException $error) {
                if (($error->errorInfo[1] ?? null) !== 1062) {
                    throw $error;
                }

                $existing->execute([$attempt['id']]);
                if (!$existing->fetchColumn()) {
                    throw $error;
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return $issued;
}

function find_certificate(PDO $pdo, string $certificateId): ?array
{
    $stmt = $pdo->prepare("
        SELECT c.*, u.name AS student_name, q.title AS exam_name,
               a.user_id, a.quiz_id, a.score, a.total_marks, a.submitted_at
        FROM certificates c
        JOIN users u ON u.id = c.student_id
        JOIN quizzes q ON q.id = c.exam_id
        JOIN attempts a ON a.id = c.attempt_id
        WHERE c.certificate_id = ?
        LIMIT 1
    ");
    $stmt->execute([$certificateId]);
    $certificate = $stmt->fetch();

    return $certificate ?: null;
}

function certificate_record_matches_attempt(array $certificate): bool
{
    foreach (['score', 'total_marks', 'certificate_type', 'percentage', 'grade', 'student_id', 'user_id', 'exam_id', 'quiz_id'] as $field) {
        if (!isset($certificate[$field])) {
            return false;
        }
    }

    $score = (int) $certificate['score'];
    $totalMarks = (int) $certificate['total_marks'];
    if ($totalMarks <= 0 || $score < 0 || $score > $totalMarks) {
        return false;
    }

    $percentage = $score * 100 / $totalMarks;
    $expectedType = certificate_type_for_percentage($percentage);

    return $expectedType !== null
        && $certificate['certificate_type'] === $expectedType
        && abs((float) $certificate['percentage'] - $percentage) < 0.00001
        && $certificate['grade'] === grade_letter(round($percentage, 1))
        && (int) $certificate['student_id'] === (int) $certificate['user_id']
        && (int) $certificate['exam_id'] === (int) $certificate['quiz_id'];
}
