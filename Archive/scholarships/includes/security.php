<?php
/**
 * security.php — output escaping, CSRF, session, and access-control helpers.
 * Safe to require_once from any file. Idempotent.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

/* --------- Access-control stubs (implemented in Iteration 7) --------- */

/** Placeholder — enforces nothing yet. */
function require_role(array $roles): void
{
    // Iteration 7: if (!is_logged_in() || !in_array(current_role(), $roles, true)) { … }
}

/** Placeholder — returns the seeded dev user id. */
function current_user_id(): ?int
{
    return 1;
}

/** Placeholder — returns a role string. */
function current_role(): string
{
    return $_SESSION['role_name'] ?? 'Visitor';
}

/** Placeholder — always false until Iteration 7. */
function is_logged_in(): bool
{
    return false;
}