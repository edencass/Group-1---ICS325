<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_repository.php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: import_report.php'); exit; }
csrf_verify();

$pdo = db();
$batchId = (int)($_POST['batch_id'] ?? 0);
if (!$batchId) { header('Location: import_report.php'); exit; }

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT row_id, raw_data FROM import_staging_rows WHERE batch_id = ? AND validation_status IN ('Ready','Warning') ORDER BY row_id");
    $stmt->execute([$batchId]);
    $rows = $stmt->fetchAll();

    if (!$rows) throw new RuntimeException('Nothing to commit — no Ready or Warning rows.');

    $updStage = $pdo->prepare("UPDATE import_staging_rows SET validation_status = 'imported' WHERE row_id = ?");

    foreach ($rows as $r) {
        $payload = json_decode((string)$r['raw_data'], true);
        $c = $payload['_cleaned'] ?? null;
        if (!is_array($c)) {
            throw new RuntimeException('A staged row is missing its cleaned scholarship data.');
        }

        persist_scholarship($pdo, $c, null, $payload['_validation_messages'] ?? []);
        $updStage->execute([(int)$r['row_id']]);
    }

    $pdo->prepare("UPDATE import_batches SET status = 'completed' WHERE batch_id = ?")->execute([$batchId]);
    $pdo->commit();
    unset($_SESSION['flash_error']);
    header('Location: import_report.php?batches=' . $batchId);
    exit;

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'Commit failed: ' . $ex->getMessage();
    header('Location: import_report.php?batches=' . $batchId);
    exit;
}