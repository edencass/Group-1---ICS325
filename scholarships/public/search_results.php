<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$stateCode = trim((string)($_GET['state'] ?? ''));
$grade     = (int)($_GET['grade'] ?? 0);
$fieldId   = (int)($_GET['field'] ?? 0);
$keyword   = trim((string)($_GET['q'] ?? ''));
$showExpired = isset($_GET['show_expired']) && $_GET['show_expired'] == '1';

$where  = ["s.scholarship_id IS NOT NULL"];
$params = [];

// Hide expired scholarships by default (FR-SRCH-06)
if (!$showExpired) {
    $where[] = "(s.deadline >= CURRENT_DATE OR s.deadline IS NULL)";
}

// Mandatory Filters (FR-SRCH-01)
if ($stateCode !== '') {
    $where[] = "(st.state_code = :state OR s.is_national = 1)";
    $params['state'] = $stateCode;
}
if ($grade >= 6 && $grade <= 12) {
    $where[] = "EXISTS (
        SELECT 1 FROM scholarship_grades sg
        JOIN grade_levels gl ON gl.grade_level_id = sg.grade_level_id
        WHERE sg.scholarship_id = s.scholarship_id AND gl.grade_name = :grade_name
    )";
    $params['grade_name'] = 'Grade ' . $grade;
}
if ($fieldId > 0) {
    $where[] = "EXISTS (
        SELECT 1 FROM scholarship_fields sf
        WHERE sf.scholarship_id = s.scholarship_id AND sf.field_id = :field_id
    )";
    $params['field_id'] = $fieldId;
}

// Keyword Search (FR-SRCH-03)
if ($keyword !== '') {
    $where[] = "(s.title LIKE :kw OR p.provider_name LIKE :kw OR s.description LIKE :kw)";
    $params['kw'] = '%' . $keyword . '%';
}

$sql = "SELECT s.scholarship_id, s.title, s.amount, s.deadline, s.description,
               s.official_url, s.scope, s.verification_date,
               p.provider_name, st.state_code, st.state_name
          FROM scholarships s
          JOIN providers p ON p.provider_id = s.provider_id
          LEFT JOIN states st ON st.state_id = s.state_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY s.deadline IS NULL, s.deadline ASC
         LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<p class="text-muted small mb-3"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?> shown (max 100).</p>

<?php if (!$rows): ?>
  <div class="alert alert-info">No scholarships match those filters yet.</div>
<?php else: ?>
  <!-- Desktop View: Table (FR-SRCH-08) -->
  <div class="table-responsive d-none d-md-block">
    <table class="table table-striped align-middle">
      <thead>
        <tr><th>Name</th><th>Provider</th><th>Scope / State</th><th>Award</th><th>Deadline</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $url = $r['official_url'] ?: '#';
      ?>
        <tr>
          <td><a href="detail.php?id=<?= (int)$r['scholarship_id'] ?>"><strong><?= e($r['title']) ?></strong></a></td>
          <td><?= e($r['provider_name']) ?></td>
          <td><?= e($r['state_code'] ?? 'National') ?></td>
          <td><?= $r['amount'] !== null ? '$' . number_format((float)$r['amount']) : 'Varies' ?></td>
          <td><?= e($r['deadline'] ?? 'Rolling') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile View: Cards (FR-SRCH-08) -->
  <div class="d-md-none">
    <?php foreach ($rows as $r): ?>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title"><a href="detail.php?id=<?= (int)$r['scholarship_id'] ?>"><?= e($r['title']) ?></a></h5>
          <h6 class="card-subtitle mb-2 text-muted"><?= e($r['provider_name']) ?></h6>
          <p class="card-text mb-1"><strong>Scope:</strong> <?= e($r['state_code'] ?? 'National') ?></p>
          <p class="card-text mb-1"><strong>Award:</strong> <?= $r['amount'] !== null ? '$' . number_format((float)$r['amount']) : 'Varies' ?></p>
          <p class="card-text"><strong>Deadline:</strong> <?= e($r['deadline'] ?? 'Rolling') ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>