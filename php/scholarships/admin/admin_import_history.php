<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Import History';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();

$stmt = $pdo->query("
    SELECT ih.*,
           b.status AS batch_status,
           b.import_date,
           b.source_file
      FROM import_history ih
      LEFT JOIN import_batches b ON b.batch_id = ih.batch_id
     ORDER BY ih.imported_at DESC
");

$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="h3 mb-4">Import History</h1>

<div class="table-responsive">
  <table class="table table-sm table-striped align-middle">
    <thead>
      <tr>
        <th>Batch</th>
        <th>File</th>
        <th>Contributor</th>
        <th>Rows</th>
        <th>Status</th>
        <th>Imported</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$history): ?>
        <tr>
          <td colspan="6" class="text-muted">No import history yet.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($history as $h): ?>
          <tr>
            <td>#<?= (int)($h['batch_id'] ?? 0) ?></td>
            <td><?= e($h['source_file'] ?? $h['file_name'] ?? '') ?></td>
            <td><?= e($h['contributor_student'] ?? '') ?></td>
            <td><?= (int)($h['rows_imported'] ?? 0) ?></td>
            <td>
              <span class="badge <?= ($h['batch_status'] ?? '') === 'imported' ? 'bg-success' : 'bg-secondary' ?>">
                <?= e($h['batch_status'] ?? $h['status'] ?? '') ?>
              </span>
            </td>
            <td><?= e($h['imported_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>