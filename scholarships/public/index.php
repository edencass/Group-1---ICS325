<?php
$page_title = 'Home';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();

// Fetch summary statistics for the cards
$summary = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(is_national = 1), 0) AS national,
            COALESCE(SUM(is_national = 0), 0) AS local,
            AVG(amount) AS average_award
       FROM scholarships"
)->fetch(PDO::FETCH_ASSOC) ?: [];
?>

<section class="bg-light py-5 mb-5">
  <div class="row align-items-center g-4 py-md-4">
    <div class="col-lg-7 text-center text-lg-start">
      <h1 class="display-5 fw-bold">Find Scholarships That Match You</h1>
      <p class="lead mt-3 mb-0">
        Search for scholarships and find opportunities that fit your education and goals.
      </p>
      <a href="<?= e(APP_URL) ?>/public/search.php" class="btn btn-primary btn-lg mt-4">
        Search Scholarships
      </a>
    </div>
    <div class="col-lg-5 text-center">
      <img src="<?= e(APP_URL) ?>/Images/ScholarshipLogo.png"
           alt="Scholarship finder logo" width="240" height="240" class="img-fluid">
    </div>
  </div>
</section>

<section class="row text-center g-4 mb-5" aria-label="How it works">
  <div class="col-md-4">
    <h2 class="h3">Search</h2>
    <p class="text-muted mb-0">
      Search for scholarships based on your needs, education, and interests.
    </p>
  </div>
  <div class="col-md-4">
    <h2 class="h3">Find Matches</h2>
    <p class="text-muted mb-0">
      Find scholarships that match your information and eligibility.
    </p>
  </div>
  <div class="col-md-4">
    <h2 class="h3">Apply</h2>
    <p class="text-muted mb-0">
      Review scholarship details and apply for opportunities that interest you.
    </p>
  </div>
</section>

<!-- ADDED: Scholarship Facts Summary Cards -->
<section class="py-5 mb-5 bg-light rounded-3">
  <div class="container">
    <h2 class="text-center mb-4">Scholarship Facts</h2>
    
    <div class="row g-3 justify-content-center">
      <div class="col-sm-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <div class="text-muted small">Scholarships listed</div>
            <div class="display-6"><?= (int)($summary['total'] ?? 0) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <div class="text-muted small">National</div>
            <div class="display-6"><?= (int)($summary['national'] ?? 0) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <div class="text-muted small">State/local</div>
            <div class="display-6"><?= (int)($summary['local'] ?? 0) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <div class="text-muted small">Average listed award</div>
            <div class="display-6">
              <?php if ($summary['average_award'] !== null): ?>
                $<?= number_format((float)$summary['average_award'], 0) ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="text-center mt-4">
      <a href="<?= e(APP_URL) ?>/public/scholarship-facts_Version2.php" class="btn btn-outline-primary">
        View detailed charts
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
