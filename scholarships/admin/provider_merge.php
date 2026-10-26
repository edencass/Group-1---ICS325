<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarships_includes_audit (1).php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: providers.php'); exit; }
csrf_verify();

$sourceId = (int)($_POST['source_id'] ?? 0);
$targetId = (int)($_POST['target_id'] ?? 0);

if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
    $_SESSION['flash_error'] = 'Pick two different providers.';
    header('Location: providers.php'); exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    // 1. Verify both exist and lock rows
    $check = $pdo->prepare('SELECT provider_id, provider_name FROM providers WHERE provider_id IN (?,?) FOR UPDATE');
    $check->execute([$sourceId, $targetId]);
    $found = [];
    foreach ($check->fetchAll() as $row) {
        $found[(int)$row['provider_id']] = $row['provider_name'];
    }
    if (!isset($found[$sourceId]) || !isset($found[$targetId])) {
        throw new RuntimeException('One of the providers no longer exists.');
    }

    // 2. Move all scholarships from source to target
    $pdo->prepare('UPDATE scholarships SET provider_id = ? WHERE provider_id = ?')->execute([$targetId, $sourceId]);

    // 3. Delete the source provider
    $pdo->prepare('DELETE FROM providers WHERE provider_id = ?')->execute([$sourceId]);

    // 4. Audit log
    log_audit_event($pdo, 'merge_providers', 'providers', current_user_id());

    $pdo->commit();
    $_SESSION['flash_success'] = 'Merged "' . $found[$sourceId] . '" into "' . $found[$targetId] . '".';
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Provider merge failed: ' . $ex->getMessage());
    $_SESSION['flash_error'] = 'Merge failed: ' . $ex->getMessage();
}

header('Location: providers.php');
exit;