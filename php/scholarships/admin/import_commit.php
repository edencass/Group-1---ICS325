<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

function extract_contributor_from_filename(string $fileName): string
{
    $base = basename(trim($fileName));
    $base = preg_replace('/\.[^.]+$/', '', $base);
    $base = trim($base);

    // Expected format: LastName_FirstName
    if (preg_match('/^(?<last>[^_]+)_(?<first>.+)$/u', $base, $m)) {
        $last  = trim($m['last']);
        $first = trim($m['first']);
        return $first . ' ' . $last;
    }

    // Fallback: keep the base name readable
    return $base;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: validation_report.php');
    exit;
}

$batchId = (int)($_POST['batch_id'] ?? 0);
if ($batchId <= 0) {
    $_SESSION['flash_error'] = 'Missing batch id.';
    header('Location: validation_report.php');
    exit;
}

try {
    // Lock the batch row so only one commit can happen at a time
    $batchStmt = $pdo->prepare(
        'SELECT * FROM import_batches WHERE batch_id = ? FOR UPDATE'
    );
    $batchStmt->execute([$batchId]);
    $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new RuntimeException('Batch not found.');
    }

    if (!str_starts_with((string)$batch['status'], 'staged')) {
        throw new RuntimeException('This batch is no longer in a staged state.');
    }

    $rowsStmt = $pdo->prepare(
        'SELECT row_id, raw_data, validation_status
           FROM import_staging_rows
          WHERE batch_id = ?
            AND validation_status IN (\'Ready\', \'Warning\')
          ORDER BY row_id'
    );
    $rowsStmt->execute([$batchId]);
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        $pdo->prepare('UPDATE import_batches SET status = ? WHERE batch_id = ?')
            ->execute(['imported_no_changes', $batchId]);
        $_SESSION['flash_info'] = 'No Ready/Warning rows were available to import.';
        header('Location: validation_report.php?batches=' . $batchId);
        exit;
    }

    $firstRow = json_decode((string)$rows[0]['raw_data'], true) ?: [];
    $sourceFile = (string)($firstRow['_file'] ?? '');
    $contributor = extract_contributor_from_filename($sourceFile);

    $pdo->beginTransaction();

    // Optional: mark batch as in-progress
    $pdo->prepare('UPDATE import_batches SET status = ?, contributor_student = ? WHERE batch_id = ?')
        ->execute(['committing', $contributor, $batchId]);

    $insertedCount = 0;

    foreach ($rows as $row) {
        $raw = json_decode((string)$row['raw_data'], true) ?: [];
        $clean = $raw['_cleaned'] ?? [];

        // Example final insert. Adjust columns to your scholarships table.
        // You can also keep a batch_id on the scholarship row for traceability.
        $sql = "
            INSERT INTO scholarships (
                name, provider_name, provider_type, official_url, state_id,
                state_display, is_national, amount, maximum_value, award_raw,
                deadline, open_date, verification_date, eligibility_notes,
                imported_by, created_at, batch_id, contributor_student
            ) VALUES (
                :name, :provider_name, :provider_type, :official_url, :state_id,
                :state_display, :is_national, :amount, :maximum_value, :award_raw,
                :deadline, :open_date, :verification_date, :eligibility_notes,
                :imported_by, NOW(), :batch_id, :contributor_student
            )
        ";

        $insert = $pdo->prepare($sql);

        $insert->execute([
            ':name'                 => $clean['name'] ?? null,
            ':provider_name'        => $clean['provider_name'] ?? null,
            ':provider_type'        => $clean['provider_type'] ?? null,
            ':official_url'         => $clean['normalized_url'] ?? $clean['official_url'] ?? null,
            ':state_id'             => $clean['state_id'] ?? null,
            ':state_display'        => $clean['state_display'] ?? null,
            ':is_national'          => (int)($clean['is_national'] ?? 0),
            ':amount'               => $clean['amount'] ?? null,
            ':maximum_value'        => $clean['maximum_value'] ?? null,
            ':award_raw'            => $clean['award_raw'] ?? null,
            ':deadline'             => $clean['deadline'] ?? null,
            ':open_date'            => $clean['open_date'] ?? null,
            ':verification_date'    => $clean['verification_date'] ?? null,
            ':eligibility_notes'    => $clean['notes'] ?? null,
            ':imported_by'          => $_SESSION['user_id'] ?? null,
            ':batch_id'             => $batchId,
            ':contributor_student'  => $contributor,
        ]);

        $insertedCount++;

        // Mark the staging row as imported
        $pdo->prepare(
            'UPDATE import_staging_rows
                SET imported_at = NOW(),
                    validation_status = ?
              WHERE row_id = ?'
        )->execute(['Imported', $row['row_id']]);
    }

    // Insert a history record
    $pdo->prepare(
        'INSERT INTO import_history (
            batch_id, source_file, contributor_student, rows_imported,
            imported_by, imported_at, status
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?)'
    )->execute([
        $batchId,
        $sourceFile,
        $contributor,
        $insertedCount,
        $_SESSION['user_id'] ?? null,
        'completed',
    ]);

    // Final batch status
    $pdo->prepare(
        'UPDATE import_batches
            SET status = ?, contributor_student = ?, imported_rows = ?, imported_at = NOW()
          WHERE batch_id = ?'
    )->execute([
        'imported',
        $contributor,
        $insertedCount,
        $batchId,
    ]);

    $pdo->commit();

    $_SESSION['flash_success'] = 'Batch #' . $batchId . ' committed successfully. ' . $insertedCount . ' rows imported.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $pdo->prepare(
        'UPDATE import_batches
            SET status = ?, error_message = ?
          WHERE batch_id = ?'
    )->execute(['failed', $e->getMessage(), $batchId]);

    $_SESSION['flash_error'] = 'Commit failed for batch #' . $batchId . '. All inserts for that batch were rolled back.';
}

header('Location: validation_report.php?batches=' . $batchId);
exit;