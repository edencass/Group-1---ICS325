<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Data Quality Dashboard';
$pdo = db();

$expired = $pdo->query(
    "SELECT s.scholarship_id, s.title, p.provider_name, s.deadline
       FROM scholarships s
       JOIN providers p ON p.provider_id = s.provider_id
      WHERE s.deadline IS NOT NULL
        AND s.deadline < CURRENT_DATE
      ORDER BY s.deadline DESC, s.title"
)->fetchAll();

$stale = $pdo->query(
    "SELECT s.scholarship_id, s.title, p.provider_name, s.verification_date
       FROM scholarships s
       JOIN providers p ON p.provider_id = s.provider_id
      WHERE s.verification_date IS NULL
         OR s.verification_date < DATE_SUB(CURRENT_DATE, INTERVAL 180 DAY)
      ORDER BY s.verification_date IS NOT NULL, s.verification_date, s.title"
)->fetchAll();

$missing = $pdo->query(
    "SELECT s.scholarship_id, s.title, p.provider_name, s.official_url
       FROM scholarships s
       JOIN providers p ON p.provider_id = s.provider_id
      WHERE NULLIF(TRIM(s.title), '') IS NULL
         OR NULLIF(TRIM(p.provider_name), '') IS NULL
         OR NULLIF(TRIM(s.official_url), '') IS NULL
      ORDER BY s.title, p.provider_name"
)->fetchAll();

$duplicates = $pdo->query(
    "SELECT s.scholarship_id, s.title, p.provider_name, s.official_url,
            matches.duplicate_count
       FROM scholarships s
       JOIN providers p ON p.provider_id = s.provider_id
       JOIN (
            SELECT provider_id, LOWER(TRIM(title)) AS normalized_title,
                   COUNT(*) AS duplicate_count
              FROM scholarships
             WHERE NULLIF(TRIM(title), '') IS NOT NULL
             GROUP BY provider_id, LOWER(TRIM(title))
            HAVING COUNT(*) > 1
       ) matches
         ON matches.provider_id = s.provider_id
        AND matches.normalized_title = LOWER(TRIM(s.title))
      ORDER BY p.provider_name, s.title, s.scholarship_id"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Data Quality Dashboard</h1>
    <p class="text-muted mb-0">Review scholarship records that may need attention.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?= e(APP_URL) ?>/admin/scholarships.php">Manage scholarships</a>
</div>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['expired', 'Expired deadlines', count($expired), 'text-danger'],
      ['stale', 'Stale or missing verification', count($stale), 'text-warning'],
      ['missing', 'Missing key fields', count($missing), 'text-primary'],
      ['duplicates', 'Possible duplicate entries', count($duplicates), 'text-secondary'],
  ] as [$anchor, $label, $count, $color]): ?>
    <div class="col-sm-6 col-xl-3">
      <a class="card h-100 text-decoration-none" href="#<?= e($anchor) ?>">
        <div class="card-body">
          <div class="small text-muted"><?= e($label) ?></div>
          <div class="display-6 fw-semibold <?= e($color) ?>"><?= (int)$count ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<p class="small text-muted mb-4">
  Rules: expired deadlines are before today. Verification is stale when missing or more than 180 days old.
  Key fields are title, provider, and official URL. Duplicate candidates have the same provider and
  title after trimming whitespace and ignoring letter case; review before removing any records.
</p>

<section id="expired" class="mb-5">
  <h2 class="h4">Expired deadlines <span class="badge bg-danger"><?= count($expired) ?></span></h2>
  <?php if ($expired): ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead><tr><th>Scholarship</th><th>Provider</th><th>Deadline</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($expired as $row): ?>
          <tr>
            <td><?= e($row['title']) ?></td>
            <td><?= e($row['provider_name']) ?></td>
            <td><?= e((string)$row['deadline']) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/admin/scholarship_form.php?id=<?= (int)$row['scholarship_id'] ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-muted">No expired deadlines found.</p>
  <?php endif; ?>
</section>

<section id="stale" class="mb-5">
  <h2 class="h4">Stale or missing verification dates <span class="badge bg-warning text-dark"><?= count($stale) ?></span></h2>
  <?php if ($stale): ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead><tr><th>Scholarship</th><th>Provider</th><th>Verification date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($stale as $row): ?>
          <tr>
            <td><?= e($row['title']) ?></td>
            <td><?= e($row['provider_name']) ?></td>
            <td><?= e($row['verification_date'] === null ? 'Not recorded' : (string)$row['verification_date']) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/admin/scholarship_form.php?id=<?= (int)$row['scholarship_id'] ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-muted">No stale or missing verification dates found.</p>
  <?php endif; ?>
</section>

<section id="missing" class="mb-5">
  <h2 class="h4">Records missing key fields <span class="badge bg-primary"><?= count($missing) ?></span></h2>
  <?php if ($missing): ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead><tr><th>Scholarship</th><th>Provider</th><th>Missing fields</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($missing as $row):
            $missingFields = [];
            if (trim((string)$row['title']) === '') {
                $missingFields[] = 'Title';
            }
            if (trim((string)$row['provider_name']) === '') {
                $missingFields[] = 'Provider';
            }
            if (trim((string)$row['official_url']) === '') {
                $missingFields[] = 'Official URL';
            }
        ?>
          <tr>
            <td><?= e($row['title']) ?></td>
            <td><?= e($row['provider_name']) ?></td>
            <td><?= e(implode(', ', $missingFields)) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/admin/scholarship_form.php?id=<?= (int)$row['scholarship_id'] ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-muted">No records are missing the selected key fields.</p>
  <?php endif; ?>
</section>

<section id="duplicates" class="mb-5">
  <h2 class="h4">Possible duplicate entries <span class="badge bg-secondary"><?= count($duplicates) ?></span></h2>
  <?php if ($duplicates): ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead><tr><th>Scholarship</th><th>Provider</th><th>Matching records</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($duplicates as $row): ?>
          <tr>
            <td><?= e($row['title']) ?></td>
            <td><?= e($row['provider_name']) ?></td>
            <td><?= (int)$row['duplicate_count'] ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/admin/scholarship_form.php?id=<?= (int)$row['scholarship_id'] ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-muted">No possible duplicates found.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
