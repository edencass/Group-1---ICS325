<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$page_title = 'Scholarship Facts';
$pdo = db();

$summary = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(is_national = 1), 0) AS national,
            COALESCE(SUM(is_national = 0), 0) AS local,
            AVG(amount) AS average_award
       FROM scholarships"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$categories = $pdo->query(
    "SELECT c.category_name AS label, COUNT(DISTINCT sc.scholarship_id) AS total
       FROM categories c
       JOIN scholarship_categories sc ON sc.category_id = c.category_id
      GROUP BY c.category_id, c.category_name
      ORDER BY total DESC, label
      LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

$regions = $pdo->query(
    "SELECT CASE
              WHEN s.is_national = 1 THEN 'National'
              ELSE COALESCE(NULLIF(st.state_code, ''), 'State/local')
            END AS label,
            COUNT(*) AS total
       FROM scholarships s
       LEFT JOIN states st ON st.state_id = s.state_id
      GROUP BY label
      ORDER BY total DESC, label
      LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

$chartJson = static function (array $value): string {
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?: '[]';
};

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h2 mb-2">Scholarship Facts</h1>
<p class="text-muted mb-4">
  Explore the scholarship opportunities currently listed in our database.
  Confirm details with each scholarship provider before applying.
</p>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 shadow-sm"><div class="card-body">
      <div class="text-muted">Scholarships listed</div>
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
      <div class="text-muted">Average listed award</div>
      <div class="display-6">
        <?php if ($summary['average_award'] !== null): ?>
          $<?= number_format((float)$summary['average_award'], 0) ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <section class="card h-100 shadow-sm">
      <div class="card-body">
        <h2 class="h5">Scholarships by category</h2>
        <?php if ($categories): ?>
          <canvas id="factsCategoryChart" aria-label="Scholarships by category" role="img"></canvas>
        <?php else: ?>
          <p class="text-muted mb-0">Category data will appear when it is available.</p>
        <?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-lg-6">
    <section class="card h-100 shadow-sm">
      <div class="card-body">
        <h2 class="h5">Scholarships by region</h2>
        <?php if ($regions): ?>
          <canvas id="factsRegionChart" aria-label="Scholarships by region" role="img"></canvas>
        <?php else: ?>
          <p class="text-muted mb-0">Region data will appear when it is available.</p>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<?php
$categoryLabels = array_column($categories, 'label');
$categoryValues = array_map('intval', array_column($categories, 'total'));
$regionLabels = array_column($regions, 'label');
$regionValues = array_map('intval', array_column($regions, 'total'));

$extra_scripts_html =
    '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>'
    . '<script>
    document.addEventListener("DOMContentLoaded", function () {
      const categoryCanvas = document.getElementById("factsCategoryChart");
      if (categoryCanvas) {
        new Chart(categoryCanvas, {
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

      const regionCanvas = document.getElementById("factsRegionChart");
      if (regionCanvas) {
        new Chart(regionCanvas, {
          type: "doughnut",
          data: {
            labels: ' . $chartJson($regionLabels) . ',
            datasets: [{
              data: ' . $chartJson($regionValues) . ',
              backgroundColor: ["#2563eb", "#14b8a6", "#f59e0b", "#8b5cf6",
                                "#ef4444", "#06b6d4", "#84cc16", "#f97316",
                                "#64748b", "#ec4899"]
            }]
          },
          options: { responsive: true }
        });
      }
    });
    </script>';

require_once __DIR__ . '/../includes/footer.php';
?>