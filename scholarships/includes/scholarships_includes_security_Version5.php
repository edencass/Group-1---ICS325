<?php
/**
 * security.php — output escaping, CSRF, session, and access-control helpers.
 * Safe to require_once from any file. Idempotent.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $cookie = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $timeout = defined('SESSION_TIMEOUT') ? (int) SESSION_TIMEOUT : 1800;
    if (isset($_SESSION['user_id'])) {
        $lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
        if ($timeout <= 0 || $lastActivity === false || time() - $lastActivity >= $timeout) {
            $_SESSION = [];
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Unable to rotate the expired session ID.');
            }
            $_SESSION['session_expired'] = true;
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
}

/** Escape any value before printing it into HTML. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* --------- CSRF --------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        die('Invalid or expired CSRF token. Please reload the page and try again.');
    }
}

/* --------- Authentication and access control --------- */

function normalized_role(string $role): string
{
    $role = strtolower(str_replace(['_', ' '], '-', trim($role)));
    return match ($role) {
        'administrator' => 'admin',
        'superadmin' => 'super-admin',
        default => $role,
    };
}

function require_role(array $roles): void
{
    if (!is_logged_in()) {
        $loginUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/public/login.php';
        header('Location: ' . $loginUrl, true, 302);
        exit;
    }

    $currentRole = normalized_role(current_role());
    foreach ($roles as $role) {
        if (is_string($role) && normalized_role($role) === $currentRole) {
            return;
        }
    }

    http_response_code(403);
    exit('You do not have permission to access this page.');
}

function current_user_id(): ?int
{
    if (!is_logged_in()) {
        return null;
    }

    $userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
    return $userId !== false && $userId > 0 ? $userId : null;
}

function current_role(): string
{
    return is_logged_in() && is_string($_SESSION['role_name'] ?? null)
        ? $_SESSION['role_name']
        : 'Visitor';
}

function is_logged_in(): bool
{
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    return $userId !== false && $userId > 0
        && is_string($_SESSION['role_name'] ?? null)
        && $_SESSION['role_name'] !== '';
}

function login_user(int $userId, string $roleName): void
{
    if ($userId <= 0 || trim($roleName) === '') {
        throw new InvalidArgumentException('A valid user ID and role are required to start a session.');
    }
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to rotate the session ID during login.');
    }

    $_SESSION = [
        'user_id' => $userId,
        'role_name' => $roleName,
        'last_activity' => time(),
    ];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Lax',
        ]);
    }

    if (!session_destroy()) {
        throw new RuntimeException('Unable to destroy the current session.');
    }
}
