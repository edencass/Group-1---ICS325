<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Validation Report';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$batchIds = [];
if (!empty($_GET['batches'])) {
    $batchIds = array_values(array_filter(
        array_map('intval', explode(',', (string)$_GET['batches']))
    ));
}

if ($batchIds) {
    $in = implode(',', array_fill(0, count($batchIds), '?'));
    $stmt = $pdo->prepare(
      "SELECT b.*, u.email FROM import_batches b
         LEFT JOIN users u ON u.user_id = b.user_id
        WHERE b.batch_id IN ($in)
        ORDER BY b.batch_id DESC"
    );
    $stmt->execute($batchIds);
} else {
    $stmt = $pdo->query(
      "SELECT b.*, u.email FROM import_batches b
         LEFT JOIN users u ON u.user_id = b.user_id
        WHERE b.status LIKE 'staged%'
        ORDER BY b.batch_id DESC"
    );
}
$batches = $stmt->fetchAll();
?>

<h1 class="h3 mb-4">Validation Report</h1>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (!$batches): ?>
  <div class="alert alert-info">
    No staged batches. <a href="<?= e(APP_URL) ?>/admin/import_stage.php">Upload files</a> first.
  </div>
<?php endif; ?>

<?php foreach ($batches as $b):
    $rows = $pdo->prepare(
      'SELECT row_id, raw_data, validation_status
         FROM import_staging_rows
        WHERE batch_id = ?
        ORDER BY row_id'
    );
    $rows->execute([$b['batch_id']]);
    $rows = $rows->fetchAll();

    // ---- counts per status (the "summary at the top") ----
    $counts = ['Ready'=>0, 'Warning'=>0, 'Duplicate'=>0, 'Error'=>0];
    $fileName = '';
    if ($rows) {
        $first    = json_decode((string)$rows[0]['raw_data'], true);
        $fileName = $first['_file'] ?? '';
    }
    foreach ($rows as $r) {
        $counts[$r['validation_status']] =
            ($counts[$r['validation_status']] ?? 0) + 1;
    }
?>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <strong>Batch #<?= (int)$b['batch_id'] ?></strong>
        — <?= e($fileName) ?>
        <span class="badge bg-info ms-2"><?= e($b['status']) ?></span>
      </div>
      <div class="small text-muted"><?= e($b['import_date'] ?? '') ?></div>
    </div>
    <div class="card-body">

      <!-- ---- four counts, one per required status ---- -->
      <p class="mb-3">
        <span class="badge bg-success">Ready: <?= $counts['Ready'] ?></span>
        <span class="badge bg-warning text-dark">Warning: <?= $counts['Warning'] ?></span>
        <span class="badge bg-secondary">Duplicate: <?= $counts['Duplicate'] ?></span>
        <span class="badge bg-danger">Error: <?= $counts['Error'] ?></span>
      </p>

      <!-- ---- per-row report ---- -->
      <div class="table-responsive mb-3">
        <table class="table table-sm table-striped align-middle">
          <thead>
            <tr>
              <th>Row</th>
              <th>Name</th>
              <th>Provider</th>
              <th>State</th>
              <th>URL</th>
              <th>Status</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r):
              $d      = json_decode((string)$r['raw_data'], true) ?: [];
              $c      = $d['_cleaned'] ?? [];
              $reason = $d['_reason'] ?? '';

              // Badge color per required status
              $badge = match ($r['validation_status']) {
                'Ready'     => 'bg-success',
                'Warning'   => 'bg-warning text-dark',
                'Duplicate' => 'bg-secondary',
                'Error'     => 'bg-danger',
                default     => 'bg-light text-dark',
              };
          ?>
            <tr>
              <td><?= (int)($d['_row'] ?? 0) ?></td>
              <td><?= e($c['name'] ?? '(unknown)') ?></td>
              <td><?= e($c['provider_name'] ?? '') ?></td>
              <td><?= e($c['state_display'] ?? '') ?></td>
              <td class="text-truncate" style="max-width:220px">
                <?php if (!empty($c['official_url'])): ?>
                  <a href="<?= e($c['official_url']) ?>" target="_blank" rel="noopener">
                    <?= e($c['official_url']) ?>
                  </a>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $badge ?>">
                  <?= e($r['validation_status']) ?>
                </span>
              </td>
              <td class="small"><?= e($reason) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- ---- commit / discard actions ---- -->
      <?php if (strpos($b['status'], 'staged') === 0): ?>
      <div class="d-flex gap-2">
        <form method="post" action="import_commit.php"
              onsubmit="return confirm('Commit this batch? Only Ready and Warning rows will be inserted.');">
          <?= csrf_field() ?>
          <input type="hidden" name="batch_id" value="<?= (int)$b['batch_id'] ?>">
          <button class="btn btn-primary">
            Commit <?= $counts['Ready'] + $counts['Warning'] ?> rows
          </button>
        </form>
        <form method="post" action="import_discard.php"
              onsubmit="return confirm('Discard this batch? Nothing will be inserted.');">
          <?= csrf_field() ?>
          <input type="hidden" name="batch_id" value="<?= (int)$b['batch_id'] ?>">
          <button class="btn btn-outline-danger">Discard</button>
        </form>
      </div>
      <?php endif; ?>

    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
