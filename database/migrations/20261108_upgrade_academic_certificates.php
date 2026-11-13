<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

function academic_certificate_column_type(PDO $pdo): string
{
    $query = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certificates' AND COLUMN_NAME = 'certificate_type'"
    );
    return (string) $query->fetchColumn();
}

function academic_certificate_index_exists(PDO $pdo, string $indexName): bool
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $query->execute(['certificates', $indexName]);
    return (int) $query->fetchColumn() > 0;
}

function academic_certificate_revoke_duplicates(PDO $pdo): void
{
    $duplicates = $pdo->query("
        SELECT student_id, exam_id
        FROM certificates
        WHERE status = 'issued'
        GROUP BY student_id, exam_id
        HAVING COUNT(*) > 1
    ")->fetchAll();
    $records = $pdo->prepare("
        SELECT c.id
        FROM certificates c
        JOIN attempts a ON a.id = c.attempt_id
        WHERE c.student_id = ? AND c.exam_id = ? AND c.status = 'issued'
        ORDER BY a.score / a.total_marks DESC, c.issue_date ASC, c.id ASC
    ");
    $revoke = $pdo->prepare("
        UPDATE certificates
        SET status = 'revoked',
            revocation_reason = COALESCE(revocation_reason, 'Superseded by the highest-scoring certificate for this examination.'),
            revoked_at = COALESCE(revoked_at, NOW())
        WHERE id = ? AND status = 'issued'
    ");

    foreach ($duplicates as $duplicate) {
        $records->execute([(int) $duplicate['student_id'], (int) $duplicate['exam_id']]);
        $issued = $records->fetchAll();
        foreach (array_slice($issued, 1) as $record) {
            $revoke->execute([(int) $record['id']]);
        }
    }
}

function upgrade_academic_certificates(PDO $pdo): void
{
    if ($academicType = academic_certificate_column_type($pdo)) {
        $pdo->exec("
            UPDATE certificates c
            JOIN attempts a ON a.id = c.attempt_id
            SET c.certificate_type = CASE
                WHEN a.total_marks > 0 AND a.score <= a.total_marks
                     AND a.score * 100 >= a.total_marks * 80 THEN 'Gold'
                WHEN a.total_marks > 0 AND a.score <= a.total_marks
                     AND a.score * 100 >= a.total_marks * 50 THEN 'Silver'
                ELSE 'Silver'
            END
        ");
        $pdo->exec("
            UPDATE certificates c
            JOIN attempts a ON a.id = c.attempt_id
            SET c.status = 'revoked',
                c.revocation_reason = COALESCE(c.revocation_reason, 'No longer eligible under the current certificate criteria.'),
                c.revoked_at = COALESCE(c.revoked_at, NOW())
            WHERE c.status = 'issued'
              AND (a.total_marks <= 0 OR a.score < 0 OR a.score > a.total_marks
                   OR a.score * 100 < a.total_marks * 50)
        ");

        if (strtolower($academicType) !== "enum('silver','gold')") {
            $pdo->exec("ALTER TABLE certificates MODIFY certificate_type ENUM('Silver','Gold') NOT NULL");
        }
    }

    academic_certificate_revoke_duplicates($pdo);

    if (!academic_certificate_index_exists($pdo, 'idx_certificates_student_exam')) {
        $pdo->exec('ALTER TABLE certificates ADD KEY idx_certificates_student_exam (student_id, exam_id)');
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        upgrade_academic_certificates(db());
        echo "Academic certificate migration completed.\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'Academic certificate migration failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
