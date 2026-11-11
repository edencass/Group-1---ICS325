<?php
$page_title = 'Home';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
