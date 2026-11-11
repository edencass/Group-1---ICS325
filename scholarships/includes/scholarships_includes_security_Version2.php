<?php
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function normalize_role_name(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $value = strtolower(trim($value));
    $value = str_replace(['-', '_'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_role(): string
{
    return (string)($_SESSION['role_name'] ?? 'Visitor');
}

function is_logged_in(): bool
{
    return current_user_id() !== null && current_role() !== '';
}

function require_auth(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? APP_URL . '/public/index.php';
        header('Location: ' . APP_URL . '/public/login.php');
        exit;
    }
}

function require_role(array $roles): void
{
    require_auth();

    $allowed = array_map(
        static fn($role) => normalize_role_name((string)$role),
        $roles
    );

    $current = normalize_role_name(current_role());

    if (!in_array($current, $allowed, true)) {
        http_response_code(403);
        echo '<h1>Forbidden</h1><p>You do not have permission to view this page.</p>';
        exit;
    }
}