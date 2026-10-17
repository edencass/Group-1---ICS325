<?php
/**
 * security.php — output escaping, CSRF tokens, and access-control helpers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape any value before printing it into HTML.
 * Use this for EVERY piece of dynamic output.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------------------------------------------------------
 | CSRF protection
 * --------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Print a hidden CSRF input inside every state-changing form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verify the token on POST. Dies on failure. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        die('Invalid or expired CSRF token. Please reload the page and try again.');
    }
}

/* ---------------------------------------------------------------
 | Access-control helpers
 | Currently placeholders. Pages can call them now so that the
 | call sites are already in place when login is added later.
 * --------------------------------------------------------------- */

/**
 * Placeholder: does nothing yet.
 * Call at the top of any page that should be restricted.
 *
 * @param array $roles e.g. ['admin','superadmin']
 */
function require_role(array $roles): void
{
    // Placeholder — no enforcement yet.
}

/** Returns a placeholder user id. */
function current_user_id(): ?int
{
    return 1;
}

/** Returns the current role. */
function current_role(): string
{
    return 'visitor';
}

/** Returns whether anyone is logged in. */
function is_logged_in(): bool
{
    return false;
}