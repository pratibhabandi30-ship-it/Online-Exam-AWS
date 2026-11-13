<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

function add_exam_workflow(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS exam_workflows (
            quiz_id INT UNSIGNED NOT NULL,
            faculty_id INT UNSIGNED NOT NULL,
            status ENUM('draft','pending','approved','rejected','published','completed','cancelled') NOT NULL DEFAULT 'draft',
            scheduled_start DATETIME DEFAULT NULL,
            scheduled_end DATETIME DEFAULT NULL,
            instructions TEXT DEFAULT NULL,
            hod_remarks VARCHAR(1000) DEFAULT NULL,
            reviewed_by INT UNSIGNED DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            requested_at DATETIME DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            results_published_by INT UNSIGNED DEFAULT NULL,
            results_published_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (quiz_id),
            KEY idx_exam_workflows_faculty_status (faculty_id, status),
            KEY idx_exam_workflows_schedule (status, scheduled_start, scheduled_end),
            CONSTRAINT fk_exam_workflow_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_workflow_faculty FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_workflow_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_exam_workflow_results_publisher FOREIGN KEY (results_published_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS exam_assigned_students (
            quiz_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (quiz_id, student_id),
            KEY idx_exam_assigned_student (student_id, quiz_id),
            CONSTRAINT fk_exam_assigned_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_assigned_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(60) NOT NULL,
            title VARCHAR(180) NOT NULL,
            message VARCHAR(1000) NOT NULL,
            link VARCHAR(500) DEFAULT NULL,
            read_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_user_read_created (user_id, read_at, created_at),
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS result_publications (
            quiz_id INT UNSIGNED NOT NULL,
            published_by INT UNSIGNED NOT NULL,
            published_at DATETIME NOT NULL,
            PRIMARY KEY (quiz_id),
            KEY idx_result_publications_publisher (published_by, published_at),
            CONSTRAINT fk_result_publication_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
            CONSTRAINT fk_result_publication_user FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS exam_autosaves (
            quiz_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            selected_option_id INT UNSIGNED NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (quiz_id, student_id, question_id),
            KEY idx_exam_autosaves_student (student_id, quiz_id),
            CONSTRAINT fk_exam_autosaves_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_autosaves_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_autosaves_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
            CONSTRAINT fk_exam_autosaves_option FOREIGN KEY (selected_option_id) REFERENCES options(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        add_exam_workflow(db());
        echo "Exam workflow migration completed.\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'Exam workflow migration failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
