<?php
$page_title = 'Home';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$states = $pdo->query(
    "SELECT state_id, state_name, state_code FROM states
      ORDER BY state_code = 'NA' DESC, state_name"
)->fetchAll();
$fields = $pdo->query(
    "SELECT field_id, field_name FROM fields_of_study ORDER BY field_name"
)->fetchAll();
?>

<section class="bg-light py-5 mb-5">
  <div class="text-center py-md-4">
    <h1 class="display-5 fw-bold">Find Scholarships That Match You</h1>
    <p class="lead mt-3 mb-0">
      Search for scholarships and find opportunities that fit your education and goals.
    </p>
    <a href="<?= e(APP_URL) ?>/public/search.php" class="btn btn-primary btn-lg mt-4">
      Search Scholarships
    </a>
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

<section class="border-top py-4 mb-4">
  <h2 class="h4 text-center mb-3">Narrow Your Search</h2>
  <form class="row justify-content-center g-2"
        action="<?= e(APP_URL) ?>/public/search.php" method="get">
    <div class="col-md-3">
      <label for="state" class="visually-hidden">State</label>
      <select name="state" id="state" class="form-select">
        <option value="">Any state</option>
        <?php foreach ($states as $s): ?>
          <option value="<?= e($s['state_code']) ?>"><?= e($s['state_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label for="grade" class="visually-hidden">Grade</label>
      <select name="grade" id="grade" class="form-select">
        <option value="">Any grade</option>
        <?php for ($g = 6; $g <= 12; $g++): ?>
          <option value="<?= $g ?>"><?= $g ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="field" class="visually-hidden">Field of Study</label>
      <select name="field" id="field" class="form-select">
        <option value="">Any field of study</option>
        <?php foreach ($fields as $f): ?>
          <option value="<?= (int)$f['field_id'] ?>"><?= e($f['field_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">Search</button>
    </div>
  </form>
</section>

<section class="border-top py-4">
  <form class="row justify-content-center g-2"
        action="<?= e(APP_URL) ?>/public/search.php" method="get">
    <div class="col-md-8">
      <label for="q" class="visually-hidden">Search scholarships</label>
      <input type="text" name="q" id="q" class="form-control form-control-lg"
             placeholder="Search scholarships by name, provider, or keyword...">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-outline-primary btn-lg w-100">Search by keyword</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
