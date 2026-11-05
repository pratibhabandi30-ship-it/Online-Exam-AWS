<?php
/**
 * Online Exam System – Application config.
 *
 * Copy this file to config.local.php to override per-environment.
 * Anything in config.local.php is gitignored.
 */

declare(strict_types=1);

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require_once $local;
}

// ---------- Database ----------
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', 3306);
defined('DB_NAME') || define('DB_NAME', 'online_exam_system');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// ---------- Application ----------
defined('APP_NAME') || define('APP_NAME', 'Online Exam System');
defined('APP_TAGLINE') || define('APP_TAGLINE', 'A modern, secure platform for college quizzes & exams');
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Kolkata');
defined('APP_DEBUG') || define('APP_DEBUG', true);
defined('SESSION_NAME') || define('SESSION_NAME', 'oes_session');
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', 60 * 60 * 4);

// ---------- Security ----------
defined('PASSWORD_ALGO') || define('PASSWORD_ALGO', PASSWORD_BCRYPT);
defined('CSRF_TOKEN_KEY') || define('CSRF_TOKEN_KEY', 'csrf_token');

date_default_timezone_set(APP_TIMEZONE);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
