<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * Log a lifecycle event for audit tracking.
 * The schema already contains: audit_log(user_id, action, table_name, created_at)
 * The default user is the authenticated user returned by current_user_id().
 */
function log_audit_event(PDO $pdo, string $action, string $table_name, ?int $userId = null): void
{
    $userId = $userId ?? current_user_id();

    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (user_id, action, table_name) VALUES (?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $table_name]);
}
