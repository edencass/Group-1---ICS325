<?php
$page_title = 'Scholarship Details';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($id < 1) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Scholarship not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT s.scholarship_id, s.title, s.official_url, s.verification_date,
           s.amount, s.deadline, s.state_code,
           p.provider_name
    FROM scholarships s
    JOIN providers p ON p.provider_id = s.provider_id
    WHERE s.scholarship_id = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$scholarship = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$scholarship) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Scholarship not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$hasUrl = !empty($scholarship['official_url']);
$verification = $scholarship['verification_date'] ?? null;
?>

<h1 class="mb-4"><?= e($scholarship['title']) ?></h1>

<div class="d-md-none">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h5 class="card-title"><?= e($scholarship['title']) ?></h5>
      <p class="mb-2"><strong>Provider:</strong> <?= e($scholarship['provider_name']) ?></p>
      <p class="mb-2"><strong>Official URL:</strong>
        <?php if ($hasUrl): ?>
          <a href="<?= e($scholarship['official_url']) ?>" target="_blank" rel="noopener">
            <?= e($scholarship['official_url']) ?>
          </a>
        <?php else: ?>
          Not provided
        <?php endif; ?>
      </p>
      <p class="mb-0"><strong>Verification date:</strong>
        <?= $verification ? e(format_date_us((string)$verification)) : 'Unverified' ?>
      </p>
    </div>
  </div>
</div>

<div class="d-none d-md-block">
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Scholarship</th>
          <th>Provider</th>
          <th>Official URL</th>
          <th>Verification date</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= e($scholarship['title']) ?></td>
          <td><?= e($scholarship['provider_name']) ?></td>
          <td>
            <?php if ($hasUrl): ?>
              <a href="<?= e($scholarship['official_url']) ?>" target="_blank" rel="noopener">
                Official site
              </a>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><?= $verification ? e(format_date_us((string)$verification)) : 'Unverified' ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<a class="btn btn-secondary mt-3" href="<?= e(APP_URL) ?>/public/search.php">Back to search</a>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
