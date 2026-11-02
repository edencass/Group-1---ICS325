<?php
$page_title = 'Scholarship Details';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare(
    "SELECT s.*, p.provider_name, pt.provider_type_name, st.state_name
       FROM scholarships s
       JOIN providers p ON p.provider_id = s.provider_id
       JOIN provider_type pt ON pt.provider_type_id = p.provider_type_id
       LEFT JOIN states st ON st.state_id = s.state_id
      WHERE s.scholarship_id = ?"
);
$stmt->execute([$id]);
$s = $stmt->fetch();

if (!$s) {
    echo '<div class="alert alert-danger">Scholarship not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$details = json_decode((string)$s['description'], true) ?: [];
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="search.php">Search</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e($s['title']) ?></li>
  </ol>
</nav>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <h1 class="card-title h3 mb-3"><?= e($s['title']) ?></h1>
    <h6 class="card-subtitle mb-4 text-muted">Provided by: <strong><?= e($s['provider_name']) ?></strong> (<?= e($s['provider_type_name']) ?>)</h6>

    <div class="row mb-4">
      <div class="col-md-6">
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><strong>Award Amount:</strong> <?= $s['amount'] !== null ? '$' . number_format((float)$s['amount']) : 'Varies' ?></li>
          <li class="list-group-item"><strong>Deadline:</strong> <?= e($s['deadline'] ?? 'Rolling') ?></li>
          <li class="list-group-item"><strong>Location:</strong> <?= $s['is_national'] ? 'National' : e($s['state_name'] ?? 'State/Local') ?></li>
        </ul>
      </div>
      <div class="col-md-6">
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><strong>Essay Required:</strong> <?= e($details['essay'] ?? 'Unknown') ?></li>
          <li class="list-group-item"><strong>Financial Need:</strong> <?= e($details['financial_need'] ?? 'Unknown') ?></li>
          <li class="list-group-item"><strong>Minimum GPA:</strong> <?= e($details['minimum_gpa'] ?? 'None stated') ?></li>
        </ul>
      </div>
    </div>

    <div class="alert alert-info">
      <strong>Data Accuracy:</strong> This record was last verified on 
      <strong><?= e($s['verification_date'] ?? 'Unknown') ?></strong>. 
      Always confirm details at the official provider URL before applying.
    </div>

    <div class="mt-4">
      <a href="<?= e($s['official_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-lg w-100">
        Visit Official Scholarship Page &rarr;
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>