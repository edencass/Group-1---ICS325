<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Admin Dashboard';
$pdo = db();

$summary = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(is_national = 1), 0) AS national,
            COALESCE(SUM(is_national = 0), 0) AS local,
            COALESCE(SUM(amount IS NOT NULL), 0) AS with_award
       FROM scholarships"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$categoryRows = $pdo->query(
    "SELECT c.category_name AS label, COUNT(DISTINCT sc.scholarship_id) AS total
       FROM categories c
       JOIN scholarship_categories sc ON sc.category_id = c.category_id
      GROUP BY c.category_id, c.category_name
      ORDER BY total DESC, label
      LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

$scopeRows = $pdo->query(
    "SELECT CASE WHEN is_national = 1 THEN 'National' ELSE 'State/local' END AS label,
            COUNT(*) AS total
       FROM scholarships
      GROUP BY label
      ORDER BY label"
)->fetchAll(PDO::FETCH_ASSOC);

$statusRows = $pdo->query(
    "SELECT validation_status AS label, COUNT(*) AS total
       FROM import_staging_rows
      GROUP BY validation_status
      ORDER BY validation_status"
)->fetchAll(PDO::FETCH_ASSOC);

$recentBatches = $pdo->query(
    "SELECT b.batch_id, b.import_date, b.status, COUNT(r.row_id) AS row_count
       FROM import_batches b
       LEFT JOIN import_staging_rows r ON r.batch_id = b.batch_id
      GROUP BY b.batch_id, b.import_date, b.status
      ORDER BY b.batch_id DESC
      LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$chartJson = static function (array $value): string {
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?: '[]';
};

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h2 mb-1">Admin Dashboard</h1>
    <p class="text-muted mb-0">Scholarship catalog and import overview.</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="<?= e(APP_URL) ?>/admin/import_stage.php">Load data files</a>
    <a class="btn btn-outline-primary" href="<?= e(APP_URL) ?>/admin/import_report.php">Validation reports</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 shadow-sm"><div class="card-body">
      <div class="text-muted">Scholarships</div>
      <div class="display-6"><?= (int)($summary['total'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 shadow-sm"><div class="card-body">
      <div class="text-muted">National</div>
      <div class="display-6"><?= (int)($summary['national'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 shadow-sm"><div class="card-body">
      <div class="text-muted">State/local</div>
      <div class="display-6"><?= (int)($summary['local'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 shadow-sm"><div class="card-body">
      <div class="text-muted">Records with award amounts</div>
      <div class="display-6"><?= (int)($summary['with_award'] ?? 0) ?></div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <section class="card h-100 shadow-sm"><div class="card-body">
      <h2 class="h5">Scholarships by category</h2>
      <?php if ($categoryRows): ?>
        <canvas id="adminCategoryChart" aria-label="Scholarships by category" role="img"></canvas>
      <?php else: ?>
        <p class="text-muted mb-0">No category data is available yet.</p>
      <?php endif; ?>
    </div></section>
  </div>
  <div class="col-lg-6">
    <section class="card h-100 shadow-sm"><div class="card-body">
      <h2 class="h5">National vs. state/local</h2>
      <?php if ($scopeRows): ?>
        <canvas id="adminScopeChart" aria-label="National and state/local scholarships" role="img"></canvas>
      <?php else: ?>
        <p class="text-muted mb-0">No scholarship data is available yet.</p>
      <?php endif; ?>
    </div></section>
  </div>
  <div class="col-lg-6">
    <section class="card h-100 shadow-sm"><div class="card-body">
      <h2 class="h5">Staged import row statuses</h2>
      <?php if ($statusRows): ?>
        <canvas id="adminStatusChart" aria-label="Staged import statuses" role="img"></canvas>
      <?php else: ?>
        <p class="text-muted mb-0">No staged import rows are available.</p>
      <?php endif; ?>
    </div></section>
  </div>
  <div class="col-lg-6">
    <section class="card h-100 shadow-sm"><div class="card-body">
      <h2 class="h5">Recent import batches</h2>
      <?php if ($recentBatches): ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Batch</th><th>Date</th><th>Status</th><th>Rows</th></tr></thead>
            <tbody>
            <?php foreach ($recentBatches as $batch): ?>
              <tr>
                <td>
                  <a href="<?= e(APP_URL) ?>/admin/import_report.php?batches=<?= (int)$batch['batch_id'] ?>">
                    #<?= (int)$batch['batch_id'] ?>
                  </a>
                </td>
                <td><?= e($batch['import_date'] ?? '') ?></td>
                <td><?= e($batch['status'] ?? '') ?></td>
                <td><?= (int)$batch['row_count'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0">No import batches yet.</p>
      <?php endif; ?>
    </div></section>
  </div>
</div>

<?php
$categoryLabels = array_column($categoryRows, 'label');
$categoryValues = array_map('intval', array_column($categoryRows, 'total'));
$scopeLabels = array_column($scopeRows, 'label');
$scopeValues = array_map('intval', array_column($scopeRows, 'total'));
$statusLabels = array_column($statusRows, 'label');
$statusValues = array_map('intval', array_column($statusRows, 'total'));

$extra_scripts_html =
    '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>'
    . '<script>
    document.addEventListener("DOMContentLoaded", function () {
      const categories = document.getElementById("adminCategoryChart");
      if (categories) {
        new Chart(categories, {
          type: "bar",
          data: {
            labels: ' . $chartJson($categoryLabels) . ',
            datasets: [{
              label: "Scholarships",
              data: ' . $chartJson($categoryValues) . ',
              backgroundColor: "#3b82f6"
            }]
          },
          options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
          }
        });
      }

      const scopes = document.getElementById("adminScopeChart");
      if (scopes) {
        new Chart(scopes, {
          type: "doughnut",
          data: {
            labels: ' . $chartJson($scopeLabels) . ',
            datasets: [{
              data: ' . $chartJson($scopeValues) . ',
              backgroundColor: ["#2563eb", "#14b8a6"]
            }]
          },
          options: { responsive: true }
        });
      }

      const statuses = document.getElementById("adminStatusChart");
      if (statuses) {
        new Chart(statuses, {
          type: "bar",
          data: {
            labels: ' . $chartJson($statusLabels) . ',
            datasets: [{
              label: "Rows",
              data: ' . $chartJson($statusValues) . ',
              backgroundColor: ["#22c55e", "#f59e0b", "#64748b", "#ef4444"]
            }]
          },
          options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
          }
        });
      }
    });
    </script>';

require_once __DIR__ . '/../includes/footer.php';
?>