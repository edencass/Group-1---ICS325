<?php
$page_title = 'Search Scholarships';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$states = $pdo->query("SELECT state_code, state_name FROM states ORDER BY state_code = 'NA' DESC, state_name")->fetchAll();
$fields = $pdo->query("SELECT field_id, field_name FROM fields_of_study ORDER BY field_name")->fetchAll();

// Read inputs
$stateCode = trim((string)($_GET['state'] ?? ''));
$grade     = (int)($_GET['grade'] ?? 0);
$fieldId   = (int)($_GET['field'] ?? 0);
$keyword   = trim((string)($_GET['q'] ?? ''));
?>

<h1 class="mb-4">Search Scholarships</h1>

<form id="searchForm" class="row g-2 mb-4 bg-light p-3 rounded">
  <div class="col-md-3">
    <label class="form-label" for="state">State</label>
    <select class="form-select" id="state" name="state">
      <option value="">Any</option>
      <?php foreach ($states as $s): ?>
        <option value="<?= e($s['state_code']) ?>" <?= $s['state_code'] === $stateCode ? 'selected' : '' ?>>
          <?= e($s['state_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label" for="grade">Grade</label>
    <select class="form-select" id="grade" name="grade">
      <option value="">Any</option>
      <?php for ($g = 6; $g <= 12; $g++): ?>
        <option value="<?= $g ?>" <?= $grade === $g ? 'selected' : '' ?>><?= $g ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label" for="field">Field of study</label>
    <select class="form-select" id="field" name="field">
      <option value="">Any</option>
      <?php foreach ($fields as $f): ?>
        <option value="<?= (int)$f['field_id'] ?>" <?= $fieldId === (int)$f['field_id'] ? 'selected' : '' ?>>
          <?= e($f['field_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label" for="q">Keyword</label>
    <input class="form-control" id="q" name="q" value="<?= e($keyword) ?>" placeholder="e.g. STEM">
  </div>
  <div class="col-12 mt-3 d-flex justify-content-between align-items-center">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="show_expired" name="show_expired" value="1">
      <label class="form-check-label" for="show_expired">Show expired scholarships</label>
    </div>
    <div>
      <button class="btn btn-primary" type="submit">Search</button>
      <a class="btn btn-link" href="<?= e(APP_URL) ?>/public/search.php">Reset</a>
    </div>
  </div>
</form>

<!-- AJAX Results Container -->
<div id="resultsContainer">
  <div class="text-center py-5 text-muted">Loading results...</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
  function fetchResults() {
    const formData = $('#searchForm').serialize();
    $('#resultsContainer').css('opacity', '0.5');

    $.ajax({
      url: 'search_results.php',
      type: 'GET',
      data: formData,
      success: function(response) {
        $('#resultsContainer').html(response).css('opacity', '1');
      },
      error: function() {
        $('#resultsContainer').html('<div class="alert alert-danger">Error loading results.</div>').css('opacity', '1');
      }
    });
  }

  $('#searchForm').on('submit', function(e) {
    e.preventDefault();
    fetchResults();
  });

  $('#state, #grade, #field, #show_expired').on('change', function() {
    fetchResults();
  });

  fetchResults();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>