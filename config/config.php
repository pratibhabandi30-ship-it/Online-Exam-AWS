<?php
/**
 * Online Exam System – Application config.
 *
 * Copy this file to config.local.php to override per-environment.
 * Anything in config.local.php is gitignored.
 */

declare(strict_types=1);

// ---------- Database ----------
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'online_exam_system';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// ---------- Application ----------
const APP_NAME       = 'Online Exam System';
const APP_TAGLINE    = 'A modern, secure platform for college quizzes & exams';
const APP_TIMEZONE   = 'Asia/Kolkata';
const APP_DEBUG      = true; // set false in production
const SESSION_NAME   = 'oes_session';
const SESSION_LIFETIME = 60 * 60 * 4; // 4 hours

// ---------- Security ----------
const PASSWORD_ALGO  = PASSWORD_BCRYPT;
const CSRF_TOKEN_KEY = 'csrf_token';

// Pull local overrides if present
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require_once $local;
}

date_default_timezone_set(APP_TIMEZONE);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
