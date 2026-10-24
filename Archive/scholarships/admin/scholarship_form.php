<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$record = null;
$details = [];
$selectedGrades = [];
$selectedCategories = [];
$selectedFields = [];

if ($id > 0) {
    $stmt = $pdo->prepare(
        'SELECT s.*, p.provider_name, pt.provider_type_name
           FROM scholarships s
           JOIN providers p ON p.provider_id = s.provider_id
           JOIN provider_type pt ON pt.provider_type_id = p.provider_type_id
          WHERE s.scholarship_id = ?'
    );
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        http_response_code(404);
        exit('Scholarship not found.');
    }
    $details = json_decode((string)$record['description'], true) ?: [];

    $stmt = $pdo->prepare(
        'SELECT gl.grade_name FROM scholarship_grades sg
         JOIN grade_levels gl ON gl.grade_level_id = sg.grade_level_id
         WHERE sg.scholarship_id = ?'
    );
    $stmt->execute([$id]);
    $selectedGrades = array_column($stmt->fetchAll(), 'grade_name');

    $stmt = $pdo->prepare(
        'SELECT c.category_name FROM scholarship_categories sc
         JOIN categories c ON c.category_id = sc.category_id
         WHERE sc.scholarship_id = ?'
    );
    $stmt->execute([$id]);
    $selectedCategories = array_column($stmt->fetchAll(), 'category_name');

    $stmt = $pdo->prepare(
        'SELECT f.field_name FROM scholarship_fields sf
         JOIN fields_of_study f ON f.field_id = sf.field_id
         WHERE sf.scholarship_id = ?'
    );
    $stmt->execute([$id]);
    $selectedFields = array_column($stmt->fetchAll(), 'field_name');
}

$states = $pdo->query('SELECT state_id, state_name, state_code FROM states ORDER BY state_name')->fetchAll();
$categories = $pdo->query('SELECT category_name FROM categories ORDER BY category_name')->fetchAll();
$fields = $pdo->query('SELECT field_name FROM fields_of_study ORDER BY field_name')->fetchAll();
$officialUrl = (string)($record['official_url'] ?? $details['official_url'] ?? '');
if ($officialUrl !== '' && !preg_match('~^https?://~i', $officialUrl)) {
    $officialUrl = 'https://' . $officialUrl;
}
$page_title = $id ? 'Edit Scholarship' : 'Add Scholarship';
require_once __DIR__ . '/../includes/header.php';
$value = static fn(string $key, mixed $default = ''): string =>
    e((string)($details[$key] ?? ($record[$key] ?? $default)));
?>

<h1 class="h3 mb-4"><?= e($page_title) ?></h1>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<form method="post" action="<?= e(APP_URL) ?>/admin/scholarship_save.php">
  <?= csrf_field() ?>
  <input type="hidden" name="scholarship_id" value="<?= $id ?>">
  <div class="row g-3">
    <div class="col-md-8">
      <label class="form-label" for="name">Scholarship name *</label>
      <input class="form-control" id="name" name="name" maxlength="255" required value="<?= $value('name', $record['title'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="scope">Classification *</label>
      <?php $scope = $details['scope'] ?? $record['scope'] ?? 'State/Local'; ?>
      <select class="form-select" id="scope" name="scope" required>
        <option value="National" <?= $scope === 'National' ? 'selected' : '' ?>>National</option>
        <option value="State/Local" <?= $scope !== 'National' ? 'selected' : '' ?>>State/Local</option>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="provider_name">Provider/organization *</label>
      <input class="form-control" id="provider_name" name="provider_name" maxlength="255" required value="<?= e((string)($record['provider_name'] ?? '')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="provider_type">Provider type *</label>
      <input class="form-control" id="provider_type" name="provider_type" maxlength="100" required value="<?= e((string)($record['provider_type_name'] ?? '')) ?>">
    </div>
    <div class="col-md-8">
      <label class="form-label" for="official_url">Official scholarship URL *</label>
      <input class="form-control" type="url" id="official_url" name="official_url" maxlength="2048" required value="<?= e($officialUrl) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="state_id">State</label>
      <select class="form-select" id="state_id" name="state_id">
        <option value="">Not applicable / National</option>
        <?php foreach ($states as $state): ?>
          <option value="<?= (int)$state['state_id'] ?>" <?= (int)($record['state_id'] ?? 0) === (int)$state['state_id'] ? 'selected' : '' ?>>
            <?= e($state['state_name'] . ' (' . $state['state_code'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Required for State/Local scholarships.</div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="amount">Award amount</label>
      <input class="form-control" id="amount" name="amount" value="<?= e((string)($record['amount'] ?? '')) ?>" placeholder="e.g. $1,000">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="maximum_value">Maximum potential value</label>
      <input class="form-control" id="maximum_value" name="maximum_value" value="<?= $value('maximum_value_raw', $details['maximum_value'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="number_of_awards">Number of awards</label>
      <input class="form-control" id="number_of_awards" name="number_of_awards" value="<?= $value('number_of_awards') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="open_date">Application open date</label>
      <input class="form-control" type="date" id="open_date" name="open_date" value="<?= e((string)($record['open_date'] ?? $details['open_date'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="deadline">Application deadline</label>
      <input class="form-control" type="date" id="deadline" name="deadline" value="<?= e((string)($record['deadline'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="verification_date">Verification date</label>
      <input class="form-control" type="date" id="verification_date" name="verification_date" value="<?= e((string)($record['verification_date'] ?? $details['verification_date'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="eligible_grades">Eligible grades</label>
      <input class="form-control" id="eligible_grades" name="eligible_grades" value="<?= e(implode(', ', array_map(static fn(string $grade): string => preg_replace('/\D+/', '', $grade), $selectedGrades)) ?: (string)($details['eligible_grades'] ?? '')) ?>" placeholder="e.g. 9-12">
      <div class="form-text">Use grades 6–12, comma-separated ranges, or terms such as Middle school.</div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="category">Categories</label>
      <input class="form-control" id="category" name="category" list="category-options" value="<?= e(implode('; ', $selectedCategories) ?: (string)($details['category'] ?? '')) ?>" placeholder="Separate categories with semicolons">
      <datalist id="category-options"><?php foreach ($categories as $category): ?><option value="<?= e($category['category_name']) ?>"><?php endforeach; ?></datalist>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="field_of_interest">Fields of study</label>
      <input class="form-control" id="field_of_interest" name="field_of_interest" list="field-options" value="<?= e(implode('; ', $selectedFields) ?: (string)($details['field_of_interest'] ?? '')) ?>" placeholder="Separate fields with semicolons">
      <datalist id="field-options"><?php foreach ($fields as $field): ?><option value="<?= e($field['field_name']) ?>"><?php endforeach; ?></datalist>
    </div>
    <?php foreach ([
        'renewable' => 'Renewable',
        'financial_need' => 'Financial need required',
        'essay' => 'Essay required',
        'recommendation' => 'Recommendation required',
        'transcript_test' => 'Transcript/test score required',
        'community_service' => 'Community service requirement',
        'leadership' => 'Leadership requirement',
    ] as $key => $label): ?>
      <div class="col-md-3">
        <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
        <?php $selected = $details[$key] ?? 'Unknown'; ?>
        <select class="form-select" id="<?= e($key) ?>" name="<?= e($key) ?>">
          <?php foreach (['Unknown', 'Yes', 'No'] as $option): ?>
            <option value="<?= e($option) ?>" <?= $selected === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>
    <div class="col-md-4">
      <label class="form-label" for="school_level">Middle / high school</label>
      <input class="form-control" id="school_level" name="school_level" value="<?= $value('school_level') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="minimum_gpa">Minimum GPA</label>
      <input class="form-control" id="minimum_gpa" name="minimum_gpa" value="<?= $value('minimum_gpa') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="residency">Residency requirement</label>
      <input class="form-control" id="residency" name="residency" value="<?= $value('residency') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="application_method">Application method</label>
      <input class="form-control" id="application_method" name="application_method" value="<?= $value('application_method') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="discovery_source">Discovery source</label>
      <input class="form-control" id="discovery_source" name="discovery_source" value="<?= $value('discovery_source') ?>">
    </div>
    <div class="col-12">
      <label class="form-label" for="other_requirements">Other eligibility requirements</label>
      <textarea class="form-control" id="other_requirements" name="other_requirements" rows="3"><?= $value('other_requirements') ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label" for="notes">Notes</label>
      <textarea class="form-control" id="notes" name="notes" rows="3"><?= $value('notes') ?></textarea>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit">Save scholarship</button>
      <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/admin/scholarships.php">Cancel</a>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
