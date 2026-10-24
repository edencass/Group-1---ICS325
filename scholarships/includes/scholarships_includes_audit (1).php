<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * Log a lifecycle event for audit tracking.
 * The schema already contains: audit_log(user_id, action, table_name, created_at)
 * Placeholder user is current_user_id(), which is set to 1 in security.php.
 */
function log_audit_event(PDO $pdo, string $action, string $table_name, ?int $userId = null): void
{
    $userId = $userId ?? current_user_id();

    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (user_id, action, table_name) VALUES (?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $table_name]);
}