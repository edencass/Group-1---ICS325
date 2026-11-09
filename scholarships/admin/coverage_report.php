<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Coverage Report';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();

// 1. Get count by State (including National)
$stateCoverage = $pdo->query(
    "SELECT st.state_name, st.state_code, COUNT(s.scholarship_id) AS total
       FROM states st
       LEFT JOIN scholarships s ON s.state_id = st.state_id OR (s.is_national = 1 AND st.state_code = 'NA')
      GROUP BY st.state_id, st.state_name, st.state_code
      ORDER BY total ASC, st.state_name ASC"
)->fetchAll();

// 2. Get count by National Theme
// Since theme is not a column, we use the national_themes table and search for the theme name in the scholarships description JSON.
$themes = $pdo->query("SELECT theme_name FROM national_themes ORDER BY theme_name")->fetchAll();
$themeCoverage = [];

foreach ($themes as $t) {
    $themeName = $t['theme_name'];
    // Use LIKE to search the JSON blob for the theme name
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scholarships WHERE description LIKE ?");
    $stmt->execute(['%' . $themeName . '%']);
    $count = (int)$stmt->fetchColumn();
    
    $themeCoverage[] = [
        'theme_name' => $themeName,
        'total' => $count
    ];
}

// Sort themes by count (ascending) so gaps show at the top
usort($themeCoverage, fn($a, $b) => $a['total'] <=> $b['total']);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Coverage Report</h1>
    <p class="text-muted mb-0">Identify states and national themes that are missing data.</p>
  </div>
  <a class="btn btn-success" href="<?= e(APP_URL) ?>/admin/export_csv.php">
    Export Full Dataset (CSV)
  </a>
</div>

<div class="row g-4">
  <!-- State Coverage -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white">
        <h2 class="h5 mb-0">Coverage by State</h2>
      </div>
      <div class="card-body" style="max-height: 600px; overflow-y: auto;">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr><th>State</th><th>Scholarships</th><th>Gap Status</th></tr>
          </thead>
          <tbody>
          <?php foreach ($stateCoverage as $s): ?>
            <tr>
              <td><?= e($s['state_name']) ?> (<?= e($s['state_code']) ?>)</td>
              <td><?= (int)$s['total'] ?></td>
              <td>
                <?php if ($s['total'] == 0): ?>
                  <span class="badge bg-danger">Empty</span>
                <?php elseif ($s['total'] < 5): ?>
                  <span class="badge bg-warning text-dark">Needs Data</span>
                <?php else: ?>
                  <span class="badge bg-success">Good</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Theme Coverage -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white">
        <h2 class="h5 mb-0">Coverage by National Theme</h2>
      </div>
      <div class="card-body" style="max-height: 600px; overflow-y: auto;">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr><th>Theme</th><th>Scholarships</th><th>Gap Status</th></tr>
          </thead>
          <tbody>
          <?php foreach ($themeCoverage as $t): ?>
            <tr>
              <td><?= e($t['theme_name']) ?></td>
              <td><?= (int)$t['total'] ?></td>
              <td>
                <?php if ($t['total'] == 0): ?>
                  <span class="badge bg-danger">Empty</span>
                <?php elseif ($t['total'] < 3): ?>
                  <span class="badge bg-warning text-dark">Needs Data</span>
                <?php else: ?>
                  <span class="badge bg-success">Good</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>