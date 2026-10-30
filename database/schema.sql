-- ============================================================
-- Online Exam System (OES) – Database schema + sample content
-- ============================================================
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ (PHP 8.0+).
-- Engine: InnoDB | Charset: utf8mb4
-- ------------------------------------------------------------
-- To install:
--   1) Create the DB (this script does it automatically), OR
--      import via phpMyAdmin (select "online_exam_system" first).
--   2) Run install.php in your browser to create the admin user
--      with a securely hashed password.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `online_exam_system`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `online_exam_system`;

-- ------------------------------------------------------------
-- USERS (students + admins; differentiated by role)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(80)  NOT NULL,
  `email`         VARCHAR(120) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `gender`        ENUM('M','F','O') DEFAULT NULL,
  `college`       VARCHAR(120) DEFAULT NULL,
  `mobile`        VARCHAR(20)  DEFAULT NULL,
  `role`          ENUM('student','admin') NOT NULL DEFAULT 'student',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- QUIZZES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `quizzes`;
CREATE TABLE `quizzes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(150) NOT NULL,
  `tag`             VARCHAR(60)  DEFAULT NULL,
  `intro`           TEXT         DEFAULT NULL,
  `image_path`      VARCHAR(255) DEFAULT NULL,
  `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
  `passing_percent` TINYINT UNSIGNED NOT NULL DEFAULT 50,
  `difficulty`      ENUM('Beginner','Intermediate') NOT NULL DEFAULT 'Beginner',
  `is_published`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`      INT UNSIGNED DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quizzes_tag` (`tag`),
  KEY `idx_quizzes_published` (`is_published`),
  CONSTRAINT `fk_quizzes_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- QUESTIONS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quiz_id`    INT UNSIGNED NOT NULL,
  `text`       TEXT         NOT NULL,
  `marks`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `difficulty` ENUM('Beginner','Intermediate') NOT NULL DEFAULT 'Beginner',
  `position`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questions_quiz` (`quiz_id`),
  CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- OPTIONS (multiple choices per question; one or more correct)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `options`;
CREATE TABLE `options` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `text`        VARCHAR(500) NOT NULL,
  `is_correct`  TINYINT(1)   NOT NULL DEFAULT 0,
  `position`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_options_question` (`question_id`),
  CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ATTEMPTS (one row per submitted exam attempt)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `attempts`;
CREATE TABLE `attempts` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `quiz_id`          INT UNSIGNED NOT NULL,
  `score`            INT UNSIGNED NOT NULL DEFAULT 0,
  `total_marks`      INT UNSIGNED NOT NULL DEFAULT 0,
  `correct_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `wrong_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `unanswered_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `time_taken_sec`   INT UNSIGNED NOT NULL DEFAULT 0,
  `started_at`       TIMESTAMP    NULL DEFAULT NULL,
  `submitted_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_user` (`user_id`),
  KEY `idx_attempts_quiz` (`quiz_id`),
  KEY `idx_attempts_submitted` (`submitted_at`),
  CONSTRAINT `fk_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ATTEMPT_ANSWERS (per-question record of what the user picked)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `attempt_answers`;
CREATE TABLE `attempt_answers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`       INT UNSIGNED NOT NULL,
  `question_id`      INT UNSIGNED NOT NULL,
  `selected_option_id` INT UNSIGNED DEFAULT NULL,
  `is_correct`       TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_aa_attempt` (`attempt_id`),
  KEY `idx_aa_question` (`question_id`),
  CONSTRAINT `fk_aa_attempt`  FOREIGN KEY (`attempt_id`)  REFERENCES `attempts`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_aa_question` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aa_option`   FOREIGN KEY (`selected_option_id`) REFERENCES `options`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FEEDBACK
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `name`       VARCHAR(80)  NOT NULL,
  `email`      VARCHAR(120) NOT NULL,
  `subject`    VARCHAR(200) NOT NULL,
  `message`    TEXT         NOT NULL,
  `rating`     TINYINT UNSIGNED DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_feedback_user` (`user_id`),
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CERTIFICATES (one issued certificate per eligible attempt)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `certificate_id` VARCHAR(32) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `exam_id` INT UNSIGNED NOT NULL,
  `attempt_id` INT UNSIGNED NOT NULL,
  `certificate_type` ENUM('Silver','Gold','Platinum') NOT NULL,
  `percentage` DECIMAL(8,5) NOT NULL,
  `grade` VARCHAR(3) NOT NULL,
  `issue_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verification_token` CHAR(64) NOT NULL,
  `status` ENUM('issued','revoked') NOT NULL DEFAULT 'issued',
  `revocation_reason` VARCHAR(500) DEFAULT NULL,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_certificates_certificate_id` (`certificate_id`),
  UNIQUE KEY `uniq_certificates_attempt_id` (`attempt_id`),
  UNIQUE KEY `uniq_certificates_verification_token` (`verification_token`),
  KEY `idx_certificates_student` (`student_id`),
  KEY `idx_certificates_exam` (`exam_id`),
  KEY `idx_certificates_type_status` (`certificate_type`, `status`),
  CONSTRAINT `fk_certificates_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificates_exam` FOREIGN KEY (`exam_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificates_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SAMPLE CONTENT – 5 quizzes / 15 questions / 60 options
-- ============================================================
INSERT INTO `quizzes` (`id`, `title`, `tag`, `intro`, `duration_minutes`, `passing_percent`, `is_published`) VALUES
(1, 'PHP Fundamentals',          'PHP',        'Test your grasp of PHP basics: syntax, variables, control flow.', 5, 50, 1),
(2, 'JavaScript Essentials',     'JavaScript', 'Variables, scope, ES6+ features, and the DOM.',                   6, 50, 1),
(3, 'Database & SQL',            'Database',   'Relational basics, joins, and common SQL idioms.',                 5, 60, 1),
(4, 'Computer Networks',         'Networking', 'IP, OSI, TCP/UDP, masks, and the basics of the modern stack.',     5, 50, 1),
(5, 'Data Structures Quick Quiz','DSA',        'Arrays, linked lists, stacks, queues, hash maps & complexity.',    6, 60, 1);

INSERT INTO `questions` (`id`, `quiz_id`, `text`, `marks`, `position`) VALUES
(1,  1, 'Which symbol is used to declare a variable in PHP?',           1, 1),
(2,  1, 'Which function outputs text to the browser?',                  1, 2),
(3,  1, 'What does the `===` operator check?',                          1, 3),
(4,  2, 'Which keyword declares a block-scoped variable?',              1, 1),
(5,  2, 'What will `typeof null` return in JavaScript?',                1, 2),
(6,  2, 'Which method adds an item to the end of an array?',            1, 3),
(7,  3, 'Which SQL keyword removes duplicate rows from a result set?',  1, 1),
(8,  3, 'Which JOIN returns only matching rows in both tables?',        1, 2),
(9,  3, 'Which clause filters groups produced by GROUP BY?',            1, 3),
(10, 4, 'Default subnet mask for a Class C network?',                   1, 1),
(11, 4, 'Which layer of the OSI model handles routing?',                1, 2),
(12, 4, 'TCP is a ___ protocol.',                                       1, 3),
(13, 5, 'Average-case time complexity of hash-map lookup?',             1, 1),
(14, 5, 'Which data structure uses LIFO order?',                        1, 2),
(15, 5, 'Best-case time complexity of bubble sort?',                    1, 3);

INSERT INTO `options` (`question_id`, `text`, `is_correct`, `position`) VALUES
(1,  '$',                          1, 1), (1,  '@',                  0, 2), (1,  '#',                0, 3), (1,  '&',                0, 4),
(2,  'echo',                       1, 1), (2,  'print_line',         0, 2), (2,  'console.log',      0, 3), (2,  'write',            0, 4),
(3,  'Equal value AND type',       1, 1), (3,  'Equal value only',   0, 2), (3,  'Assignment',       0, 3), (3,  'Not equal',        0, 4),
(4,  'let',                        1, 1), (4,  'var',                0, 2), (4,  'def',              0, 3), (4,  'static',           0, 4),
(5,  '"object"',                   1, 1), (5,  '"null"',             0, 2), (5,  '"undefined"',      0, 3), (5,  '"number"',         0, 4),
(6,  'push()',                     1, 1), (6,  'add()',              0, 2), (6,  'append()',         0, 3), (6,  'pushBack()',       0, 4),
(7,  'DISTINCT',                   1, 1), (7,  'UNIQUE',             0, 2), (7,  'ONLY',             0, 3), (7,  'DEDUPE',           0, 4),
(8,  'INNER JOIN',                 1, 1), (8,  'LEFT JOIN',          0, 2), (8,  'FULL JOIN',        0, 3), (8,  'CROSS JOIN',       0, 4),
(9,  'HAVING',                     1, 1), (9,  'WHERE',              0, 2), (9,  'FILTER',           0, 3), (9,  'GROUP',            0, 4),
(10, '255.255.255.0',              1, 1), (10, '255.255.0.0',        0, 2), (10, '255.0.0.0',        0, 3), (10, '0.0.0.0',          0, 4),
(11, 'Network',                    1, 1), (11, 'Transport',          0, 2), (11, 'Data link',        0, 3), (11, 'Session',          0, 4),
(12, 'Connection-oriented',        1, 1), (12, 'Connectionless',     0, 2), (12, 'Stateless',        0, 3), (12, 'Broadcast',        0, 4),
(13, 'O(1)',                       1, 1), (13, 'O(log n)',           0, 2), (13, 'O(n)',             0, 3), (13, 'O(n log n)',       0, 4),
(14, 'Stack',                      1, 1), (14, 'Queue',              0, 2), (14, 'Deque',            0, 3), (14, 'Heap',             0, 4),
(15, 'O(n)',                       1, 1), (15, 'O(1)',               0, 2), (15, 'O(log n)',         0, 3), (15, 'O(n^2)',           0, 4);
