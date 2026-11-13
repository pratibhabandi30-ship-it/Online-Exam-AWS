<?php
/**
 * Session, CSRF and auth helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function account_password_is_valid(string $password): bool
{
    return mb_strlen($password, 'UTF-8') >= 8
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[@#$%&*!]/', $password) === 1;
}

function account_password_policy_message(): string
{
    return 'Password must be at least 8 characters and contain at least one number and one special character (@, #, $, %, &, *, or !).';
}

function hash_account_password(string $password): string
{
    $prehash = rtrim(strtr(
        base64_encode(hash('sha384', "online-exam-system-account-password-v1\0" . $password, true)),
        '+/',
        '-_'
    ), '=');
    $hash = password_hash($prehash, PASSWORD_ALGO);
    if (!is_string($hash)) {
        throw new RuntimeException('Unable to secure the account password.');
    }

    return '$oes-sha384$' . $hash;
}

function verify_account_password(string $password, string $storedHash): bool
{
    $prefix = '$oes-sha384$';
    if (str_starts_with($storedHash, $prefix)) {
        $prehash = rtrim(strtr(
            base64_encode(hash('sha384', "online-exam-system-account-password-v1\0" . $password, true)),
            '+/',
            '-_'
        ), '=');
        return password_verify($prehash, substr($storedHash, strlen($prefix)));
    }

    return password_verify($password, $storedHash);
}

function account_password_needs_rehash(string $storedHash): bool
{
    $prefix = '$oes-sha384$';
    if (!str_starts_with($storedHash, $prefix)) {
        return true;
    }

    return password_needs_rehash(substr($storedHash, strlen($prefix)), PASSWORD_ALGO);
}

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    $now = time();
    if (isset($_SESSION['_last_activity']) && $now - (int) $_SESSION['_last_activity'] > SESSION_LIFETIME) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['_last_activity'] = $now;

    if (!isset($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string
{
    start_session();
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function verify_csrf(): void
{
    start_session();
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION[CSRF_TOKEN_KEY] ?? '', $token)) {
        http_response_code(419);
        die('Invalid or expired CSRF token. Please go back and try again.');
    }
}

// ---------- User auth ----------
function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT id, name, email, gender, college, mobile, role, is_active, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();
        if (!$u || !(int) $u['is_active']) {
            return null;
        }
        return $u;
    } catch (Throwable $e) {
        // DB not ready (e.g. running install.php on a fresh setup). Treat as logged out.
        return null;
    }
}

function require_login(string $redirect = 'login.php'): array
{
    $u = current_user();
    if (!$u) {
        header('Location: ' . base_url($redirect));
        exit;
    }
    return $u;
}

function require_admin(string $redirect = 'admin/login.php'): array
{
    $u = require_login($redirect);
    if (($u['role'] ?? '') !== 'admin') {
        header('Location: ' . base_url($redirect));
        exit;
    }
    return $u;
}

function require_faculty(string $redirect = 'login.php'): array
{
    $u = require_login($redirect);
    if (($u['role'] ?? '') !== 'faculty') {
        redirect($u['role'] === 'admin' ? 'admin/dashboard.php' : $redirect);
    }
    return $u;
}

function require_student(string $redirect = 'login.php'): array
{
    $u = require_login($redirect);
    if (($u['role'] ?? '') !== 'student') {
        redirect(match ($u['role']) {
            'admin' => 'admin/dashboard.php',
            'faculty' => 'faculty/dashboard.php',
            default => $redirect,
        });
    }
    return $u;
}

function login_user(int $userId): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------- Helpers ----------
function base_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');

    // Pages in role-specific directories still use project-root-relative paths.
    $base = preg_replace('~/(?:admin|faculty)\z~', '', $base) ?? $base;

    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $value = null): ?string
{
    start_session();
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $v = $_SESSION['_flash'][$key] ?? null;
    if ($v !== null) {
        unset($_SESSION['_flash'][$key]);
    }
    return $v;
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}
