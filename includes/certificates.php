<?php
/**
 * Certificate eligibility, issuance and record validation.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function certificate_type_for_percentage(float $percentage): ?string
{
    return match (true) {
        $percentage >= 80 && $percentage <= 100 => 'Gold',
        $percentage >= 50 && $percentage < 80 => 'Silver',
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

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $studentLock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $studentLock->execute([$studentId]);
        if (!$studentLock->fetchColumn()) {
            throw new InvalidArgumentException('Cannot issue a certificate for an unknown student.');
        }

        $existing = $pdo->prepare(
            'SELECT id FROM certificates WHERE student_id = ? AND exam_id = ? LIMIT 1 FOR UPDATE'
        );
        $existing->execute([$studentId, $examId]);
        if ($existing->fetchColumn()) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
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
        if ($ownsTransaction) {
            $pdo->commit();
        }

        return $certificateId;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function backfill_eligible_certificates(PDO $pdo, ?int $studentId = null): int
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Certificate backfill must run outside an existing transaction.');
    }

    $sql = "
        SELECT a.id, a.user_id, a.quiz_id, a.score, a.total_marks
        FROM attempts a
        WHERE NOT EXISTS (
              SELECT 1 FROM certificates c
              WHERE c.student_id = a.user_id AND c.exam_id = a.quiz_id
          )
          AND a.total_marks > 0
          AND a.score <= a.total_marks
          AND a.score * 100 >= a.total_marks * 50
          AND (
              NOT EXISTS (SELECT 1 FROM exam_workflows w WHERE w.quiz_id = a.quiz_id)
              OR EXISTS (
                  SELECT 1 FROM result_publications rp
                  WHERE rp.quiz_id = a.quiz_id AND rp.published_at IS NOT NULL
              )
          )
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
    try {
        foreach ($attempts as $attempt) {
            $certificateId = issue_certificate(
                $pdo,
                (int) $attempt['id'],
                (int) $attempt['user_id'],
                (int) $attempt['quiz_id'],
                (int) $attempt['score'],
                (int) $attempt['total_marks']
            );
            if ($certificateId !== null) {
                $issued++;
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
        LEFT JOIN exam_workflows w ON w.quiz_id = c.exam_id
        LEFT JOIN result_publications rp ON rp.quiz_id = c.exam_id
        WHERE c.certificate_id = ?
          AND (w.quiz_id IS NULL OR rp.published_at IS NOT NULL)
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
