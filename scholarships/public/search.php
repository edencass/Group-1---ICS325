<?php
$page_title = 'Search Scholarships';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$states = $pdo->query("SELECT state_code, state_name FROM states ORDER BY state_code = 'NA' DESC, state_name")->fetchAll();
$fields = $pdo->query("SELECT field_id, field_name FROM fields_of_study ORDER BY field_name")->fetchAll();

// Read inputs
$stateCode = trim((string)($_GET['state'] ?? ''));
$grade     = (int)($_GET['grade'] ?? 0);
$fieldId   = (int)($_GET['field'] ?? 0);
$keyword   = trim((string)($_GET['q'] ?? ''));

$searchSubmitted = array_intersect(['state', 'grade', 'field', 'q'], array_keys($_GET)) !== [];
if ($searchSubmitted) {
  $validStateCodes = array_column($states, 'state_code');
  $validFieldIds = array_map('intval', array_column($fields, 'field_id'));
  $logStateCode = in_array($stateCode, $validStateCodes, true) ? $stateCode : null;
  $logGrade = ($grade >= 6 && $grade <= 12) ? $grade : null;
  $logFieldId = in_array($fieldId, $validFieldIds, true) ? $fieldId : null;

  try {
    $log = $pdo->prepare(
      'INSERT INTO anonymous_search_log (state_code, grade, field_id)
       VALUES (:state_code, :grade, :field_id)'
    );
    $log->bindValue(':state_code', $logStateCode, $logStateCode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $log->bindValue(':grade', $logGrade, $logGrade === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $log->bindValue(':field_id', $logFieldId, $logFieldId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $log->execute();
  } catch (PDOException $e) {
    error_log('Anonymous search logging failed: ' . $e->getMessage());
  }
}

$where  = ["s.scholarship_id IS NOT NULL"];
$params = [];

// Filter by State
if ($stateCode !== '') {
    $where[] = 's.state_code = :state';
    $params['state'] = $stateCode;
}

// Filter by Grade
if ($grade >= 6 && $grade <= 12) {
    $where[] = "EXISTS (
        SELECT 1 FROM scholarship_grades sg
        JOIN grade_levels gl ON gl.grade_level_id = sg.grade_level_id
        WHERE sg.scholarship_id = s.scholarship_id AND gl.grade_name = :grade_name
    )";
    $params['grade_name'] = 'Grade ' . $grade;
}

// Filter by Field of Interest
if ($fieldId > 0) {
    $where[] = "EXISTS (
        SELECT 1 FROM scholarship_fields sf
        WHERE sf.scholarship_id = s.scholarship_id AND sf.field_id = :field_id
    )";
    $params['field_id'] = $fieldId;
}

// Keyword Search
if ($keyword !== '') {
  $where[] = '(s.title LIKE :kw_title OR p.provider_name LIKE :kw_provider OR s.description LIKE :kw_description)';
  $keywordPattern = '%' . $keyword . '%';
  $params['kw_title'] = $keywordPattern;
  $params['kw_provider'] = $keywordPattern;
  $params['kw_description'] = $keywordPattern;
}

$sql = "SELECT s.scholarship_id, s.title, s.amount, s.deadline, s.description,
               s.official_url, s.state_code,
               p.provider_name
          FROM scholarships s
          JOIN providers p ON p.provider_id = s.provider_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY s.deadline IS NULL, s.deadline ASC
         LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<h1>Search Scholarships</h1>

<form class="row g-2 mb-4 bg-light p-3 rounded" method="get">
  <div class="col-md-3">
    <label class="form-label" for="state">State</label>
    <select class="form-select" id="state" name="state">
      <option value="">Any</option>
      <?php
        foreach ($states as $s):
          $sel = ($s['state_code'] === $stateCode) ? 'selected' : '';
      ?>
        <option value="<?= e($s['state_code']) ?>" <?= $sel ?>><?= e($s['state_name']) ?></option>
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
      <?php
        foreach ($fields as $f):
          $sel = ((int)$f['field_id'] === $fieldId) ? 'selected' : '';
      ?>
        <option value="<?= (int)$f['field_id'] ?>" <?= $sel ?>><?= e($f['field_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label" for="q">Keyword</label>
    <input class="form-control" id="q" name="q" value="<?= e($keyword) ?>" placeholder="e.g. STEM">
  </div>
  <div class="col-12 mt-3">
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn btn-link" href="<?= e(APP_URL) ?>/public/search.php">Reset</a>
  </div>
</form>

<p class="text-muted small"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?> shown (max 100).</p>

<?php if (!$rows): ?>
  <div class="alert alert-info">No scholarships match those filters yet. Try uploading some data via the Admin Import page!</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr><th>Name</th><th>Provider</th><th>State</th><th>Award</th><th>Deadline</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $d = json_decode((string)$r['description'], true) ?: [];
        $url = $r['official_url'] ?? $d['official_url'] ?? '';
      ?>
        <tr>
          <td>
            <?php if ($url): ?>
              <a href="<?= e($url) ?>" target="_blank" rel="noopener"><strong><?= e($r['title']) ?></strong></a>
            <?php else: ?>
              <strong><?= e($r['title']) ?></strong>
            <?php endif; ?>
          </td>
          <td><?= e($r['provider_name']) ?></td>
          <td><?= e($r['state_code'] ?: 'National') ?></td>
          <td><?= $r['amount'] !== null ? '$' . number_format((float)$r['amount']) : 'Varies' ?></td>
          <td><?= e($r['deadline'] !== null ? format_date_us((string)$r['deadline']) : 'Rolling') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>