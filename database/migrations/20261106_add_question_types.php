<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

function question_type_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function add_question_types(PDO $pdo): void
{
    $questionColumns = [
        'question_type' => "ENUM('mcq','fill_blank','match','true_false','coding') NOT NULL DEFAULT 'mcq'",
        'programming_language' => 'VARCHAR(20) DEFAULT NULL',
        'sample_input' => 'MEDIUMTEXT DEFAULT NULL',
        'expected_output' => 'MEDIUMTEXT DEFAULT NULL',
        'coding_constraints' => 'TEXT DEFAULT NULL',
    ];
    foreach ($questionColumns as $column => $definition) {
        if (!question_type_column_exists($pdo, 'questions', $column)) {
            $pdo->exec("ALTER TABLE questions ADD COLUMN `$column` $definition");
        }
    }

    $answerColumns = [
        'answer_text' => 'LONGTEXT DEFAULT NULL',
        'awarded_marks' => 'SMALLINT UNSIGNED DEFAULT NULL',
        'feedback' => 'TEXT DEFAULT NULL',
    ];
    foreach ($answerColumns as $column => $definition) {
        if (!question_type_column_exists($pdo, 'attempt_answers', $column)) {
            $pdo->exec("ALTER TABLE attempt_answers ADD COLUMN `$column` $definition");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS question_matching_pairs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id INT UNSIGNED NOT NULL,
            left_text VARCHAR(500) NOT NULL,
            right_text VARCHAR(500) NOT NULL,
            marks TINYINT UNSIGNED NOT NULL DEFAULT 1,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_matching_pairs_question (question_id, position),
            CONSTRAINT fk_matching_pairs_question
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS question_coding_test_cases (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id INT UNSIGNED NOT NULL,
            input_data MEDIUMTEXT NOT NULL,
            expected_output MEDIUMTEXT NOT NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_coding_test_cases_question (question_id, position),
            CONSTRAINT fk_coding_test_cases_question
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS exam_answer_autosaves (
            quiz_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            answer_json LONGTEXT NOT NULL,
            marked_for_review TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (quiz_id, student_id, question_id),
            KEY idx_answer_autosaves_student (student_id, quiz_id),
            CONSTRAINT fk_answer_autosaves_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
            CONSTRAINT fk_answer_autosaves_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_answer_autosaves_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec(
        "INSERT IGNORE INTO exam_answer_autosaves (quiz_id, student_id, question_id, answer_json, marked_for_review)
         SELECT quiz_id, student_id, question_id, CONCAT('{\"option_id\":', selected_option_id, '}'), 0
         FROM exam_autosaves"
    );
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        add_question_types(db());
        echo "Question type migration completed.\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'Question type migration failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
