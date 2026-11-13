<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

function faculty_workspace_column_exists(PDO $pdo, string $table, string $column): bool
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $query->execute([$table, $column]);
    return (int) $query->fetchColumn() > 0;
}

function faculty_workspace_constraint_exists(PDO $pdo, string $constraint): bool
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $query->execute(['exam_workflows', $constraint]);
    return (int) $query->fetchColumn() > 0;
}

function add_faculty_workspace(PDO $pdo): void
{
    $resultColumns = [
        'result_status' => "ENUM('not_submitted','pending','rejected','approved') NOT NULL DEFAULT 'not_submitted'",
        'result_remarks' => 'VARCHAR(1000) DEFAULT NULL',
        'result_reviewed_by' => 'INT UNSIGNED DEFAULT NULL',
        'result_reviewed_at' => 'DATETIME DEFAULT NULL',
        'result_requested_at' => 'DATETIME DEFAULT NULL',
    ];
    foreach ($resultColumns as $column => $definition) {
        if (!faculty_workspace_column_exists($pdo, 'exam_workflows', $column)) {
            $pdo->exec("ALTER TABLE exam_workflows ADD COLUMN `$column` $definition");
        }
    }
    if (!faculty_workspace_constraint_exists($pdo, 'fk_exam_workflow_result_reviewer')) {
        $pdo->exec(
            'ALTER TABLE exam_workflows ADD CONSTRAINT fk_exam_workflow_result_reviewer
             FOREIGN KEY (result_reviewed_by) REFERENCES users(id) ON DELETE SET NULL'
        );
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faculty_attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            faculty_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            attendance_date DATE NOT NULL,
            status ENUM('present','absent','late') NOT NULL,
            remarks VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_faculty_attendance_day (faculty_id, student_id, attendance_date),
            KEY idx_faculty_attendance_date (faculty_id, attendance_date),
            CONSTRAINT fk_faculty_attendance_faculty FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_faculty_attendance_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faculty_announcements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            faculty_id INT UNSIGNED NOT NULL,
            title VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            category ENUM('general','exam_schedule','assignment','important') NOT NULL DEFAULT 'general',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_faculty_announcements_owner (faculty_id, created_at),
            CONSTRAINT fk_faculty_announcements_owner FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faculty_timetable (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            faculty_id INT UNSIGNED NOT NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT DEFAULT NULL,
            event_type ENUM('class','exam','assignment','other') NOT NULL DEFAULT 'class',
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            location VARCHAR(180) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_faculty_timetable_owner_time (faculty_id, starts_at, ends_at),
            CONSTRAINT fk_faculty_timetable_owner FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faculty_question_bank (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            faculty_id INT UNSIGNED NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'General',
            question_type ENUM('mcq','fill_blank','match','true_false','coding') NOT NULL,
            question_data LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_faculty_question_bank_owner (faculty_id, category, question_type),
            CONSTRAINT fk_faculty_question_bank_owner FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        add_faculty_workspace(db());
        echo "Faculty workspace migration completed.\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'Faculty workspace migration failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
