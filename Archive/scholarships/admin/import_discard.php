<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: import_report.php'); exit; }
csrf_verify();

$batchId = (int)($_POST['batch_id'] ?? 0);
if ($batchId) {
    $pdo = db();
    $pdo->prepare('DELETE FROM import_staging_rows WHERE batch_id = ?')->execute([$batchId]);
    $pdo->prepare('DELETE FROM import_batches WHERE batch_id = ?')->execute([$batchId]);
}
header('Location: import_report.php');
exit;