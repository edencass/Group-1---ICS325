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

<section class="bg-primary text-white rounded-3 p-4 p-md-5 mb-5">
  <div class="row align-items-center g-4">
    <div class="col-md-7">
      <h1 class="display-4 fw-bold mb-4">Find Your Scholarship</h1>
      <form class="row g-2" action="<?= e(APP_URL) ?>/public/search.php" method="get">
        <div class="col-md-4">
          <label for="state" class="visually-hidden">State</label>
          <select name="state" id="state" class="form-select">
            <option value="">State</option>
            <?php foreach ($states as $s): ?>
              <option value="<?= e($s['state_code']) ?>"><?= e($s['state_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="grade" class="visually-hidden">Grade</label>
          <select name="grade" id="grade" class="form-select">
            <option value="">Grade</option>
            <?php for ($g = 6; $g <= 12; $g++): ?>
              <option value="<?= $g ?>"><?= $g ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="field" class="visually-hidden">Field of Study</label>
          <select name="field" id="field" class="form-select">
            <option value="">Field of Study</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['field_id'] ?>"><?= e($f['field_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-light w-100">Search</button>
        </div>
      </form>
    </div>
    <div class="col-md-5">
      <div class="rounded-3 d-flex align-items-center justify-content-center"
           style="background-color: #0d3b66; min-height: 220px;">
        <span class="text-white fw-bold fs-4 text-center px-3">Scholarship Finder</span>
      </div>
    </div>
  </div>
</section>

<section class="border-top border-bottom py-4 mb-5">
  <form class="row justify-content-center g-2"
        action="<?= e(APP_URL) ?>/public/search.php" method="get">
    <div class="col-md-8">
      <label for="q" class="visually-hidden">Search scholarships</label>
      <input type="text" name="q" id="q" class="form-control form-control-lg"
             placeholder="Search scholarships by name, provider, or keyword...">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary btn-lg w-100">Search</button>
    </div>
  </form>
</section>

<section class="py-5 mb-5">
  <h2 class="text-center mb-4">Scholarship Facts</h2>
  <p class="text-center text-muted">
    Statistics and charts will appear here once scholarship data is loaded.
  </p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>