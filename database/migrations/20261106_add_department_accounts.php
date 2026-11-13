<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

function add_department_accounts(PDO $pdo): void
{
    $role = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'"
    )->fetchColumn();

    if (!is_string($role)) {
        throw new RuntimeException('The users.role column is missing; the existing schema must be installed first.');
    }
    if (strpos($role, "'faculty'") === false) {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('student','faculty','admin') NOT NULL DEFAULT 'student'");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faculty_profiles (
            user_id INT UNSIGNED NOT NULL,
            branch VARCHAR(120) DEFAULT NULL,
            subject VARCHAR(120) DEFAULT NULL,
            max_students SMALLINT UNSIGNED NOT NULL DEFAULT 12,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            CONSTRAINT fk_faculty_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_profiles (
            user_id INT UNSIGNED NOT NULL,
            registration_no VARCHAR(60) DEFAULT NULL,
            branch VARCHAR(120) DEFAULT NULL,
            semester TINYINT UNSIGNED DEFAULT NULL,
            academic_year VARCHAR(20) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            UNIQUE KEY uniq_student_registration_no (registration_no),
            KEY idx_student_profiles_branch_semester (branch, semester),
            CONSTRAINT fk_student_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faculty_student_assignments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            faculty_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            assigned_by INT UNSIGNED DEFAULT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_assignment_student (student_id),
            KEY idx_assignment_faculty (faculty_id),
            CONSTRAINT fk_assignment_faculty FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_assignment_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_assignment_admin FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id VARCHAR(80) DEFAULT NULL,
            details TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_audit_actor_created (actor_id, created_at),
            KEY idx_audit_entity (entity_type, entity_id),
            CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec(
        "INSERT IGNORE INTO student_profiles (user_id)
         SELECT id FROM users WHERE role = 'student'"
    );
    $pdo->exec(
        "INSERT IGNORE INTO faculty_profiles (user_id)
         SELECT id FROM users WHERE role = 'faculty'"
    );
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        add_department_accounts(db());
        echo "Department account migration completed.\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'Department account migration failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
