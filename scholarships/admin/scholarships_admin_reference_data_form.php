<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarships_includes_audit (1).php';
require_role(['Administrator', 'Super-admin']);

$pdo = db();

$entityMap = [
    'states' => [
        'label' => 'State',
        'table' => 'states',
        'id_col' => 'state_id',
        'name_col' => 'state_name',
        'extra' => [
            'state_code' => ['label' => 'State Code', 'maxlength' => 2, 'required' => true],
        ],
    ],
    'provider_type' => [
        'label' => 'Provider Type',
        'table' => 'provider_type',
        'id_col' => 'provider_type_id',
        'name_col' => 'provider_type_name',
        'extra' => [],
    ],
    'categories' => [
        'label' => 'Category',
        'table' => 'categories',
        'id_col' => 'category_id',
        'name_col' => 'category_name',
        'extra' => [],
    ],
    'fields_of_study' => [
        'label' => 'Field of Study',
        'table' => 'fields_of_study',
        'id_col' => 'field_id',
        'name_col' => 'field_name',
        'extra' => [],
    ],
    'grade_levels' => [
        'label' => 'Grade Level',
        'table' => 'grade_levels',
        'id_col' => 'grade_level_id',
        'name_col' => 'grade_name',
        'extra' => [],
    ],
    'national_themes' => [
        'label' => 'National Theme',
        'table' => 'national_themes',
        'id_col' => 'theme_id',
        'name_col' => 'theme_name',
        'extra' => [],
    ],
];

$entity = $_GET['entity'] ?? $_POST['entity'] ?? 'states';
if (!isset($entityMap[$entity])) {
    http_response_code(404);
    exit('Invalid reference data group.');
}

$spec = $entityMap[$entity];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: (int)($_POST['id'] ?? 0);
$isNew = ($id <= 0);

$page_title = ($isNew ? 'Add ' : 'Edit ') . $spec['label'];
$pdo = db();

$fields = [
    $spec['name_col'] => trim((string)($_POST[$spec['name_col']] ?? '')),
];
foreach ($spec['extra'] as $col => $meta) {
    $fields[$col] = trim((string)($_POST[$col] ?? ''));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $entityValue = trim((string)($_POST[$spec['name_col']] ?? ''));
    if ($entityValue === '') {
        $errors[$spec['name_col']] = ucfirst(str_replace('_', ' ', $spec['name_col'])) . ' is required.';
    }

    foreach ($spec['extra'] as $col => $meta) {
        $value = trim((string)($_POST[$col] ?? ''));
        if (($meta['required'] ?? false) && $value === '') {
            $errors[$col] = $meta['label'] . ' is required.';
        }
        if (!empty($meta['maxlength']) && mb_strlen($value) > (int)$meta['maxlength']) {
            $errors[$col] = $meta['label'] . ' must be ' . (int)$meta['maxlength'] . ' characters or fewer.';
        }
    }

    if (!$errors) {
        $columns = [];
        $placeholders = [];
        $values = [];

        $columns[] = $spec['name_col'];
        $placeholders[] = '?';
        $values[] = $entityValue;

        foreach ($spec['extra'] as $col => $meta) {
            $columns[] = $col;
            $placeholders[] = '?';
            $values[] = trim((string)($_POST[$col] ?? ''));
        }

        if ($isNew) {
            $sql = 'INSERT INTO ' . $spec['table'] . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            log_audit_event($pdo, 'create_reference_data', $spec['table'], current_user_id());
            $_SESSION['flash_success'] = ucfirst($spec['label']) . ' created.';
        } else {
            $sql = 'UPDATE ' . $spec['table'] .
                   ' SET ' . $spec['name_col'] . ' = ?';
            $params = [$entityValue];

            foreach ($spec['extra'] as $col => $meta) {
                $sql .= ', ' . $col . ' = ?';
                $params[] = trim((string)($_POST[$col] ?? ''));
            }

            $sql .= ' WHERE ' . $spec['id_col'] . ' = ?';
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            log_audit_event($pdo, 'update_reference_data', $spec['table'], current_user_id());
            $_SESSION['flash_success'] = ucfirst($spec['label']) . ' updated.';
        }

        header('Location: ' . APP_URL . '/admin/reference_data.php?entity=' . urlencode($entity));
        exit;
    }
}

if (!$isNew) {
    $stmt = $pdo->prepare('SELECT * FROM ' . $spec['table'] . ' WHERE ' . $spec['id_col'] . ' = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Record not found.');
    }

    $fields[$spec['name_col']] = (string)($row[$spec['name_col']] ?? '');
    foreach ($spec['extra'] as $col => $meta) {
        $fields[$col] = (string)($row[$col] ?? '');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= e($isNew ? 'Add' : 'Edit') ?> <?= e($spec['label']) ?></h1>
    <p class="text-muted mb-0">Manage a reference value used by the application.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/admin/reference_data.php?entity=<?= e($entity) ?>">Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?>
        <li><?= e($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(APP_URL) ?>/admin/reference_data_form.php?entity=<?= e($entity) ?><?= $isNew ? '' : '&id=' . (int)$id ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="entity" value="<?= e($entity) ?>">
  <?php if (!$isNew): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="<?= e($spec['name_col']) ?>" class="form-label">
            <?= e(ucfirst(str_replace('_', ' ', $spec['name_col']))) ?>
          </label>
          <input
            type="text"
            class="form-control"
            id="<?= e($spec['name_col']) ?>"
            name="<?= e($spec['name_col']) ?>"
            value="<?= e($fields[$spec['name_col']] ?? '') ?>"
            maxlength="255"
            required
          >
        </div>

        <?php foreach ($spec['extra'] as $col => $meta): ?>
          <div class="col-md-6">
            <label for="<?= e($col) ?>" class="form-label"><?= e($meta['label']) ?></label>
            <input
              type="text"
              class="form-control"
              id="<?= e($col) ?>"
              name="<?= e($col) ?>"
              value="<?= e($fields[$col] ?? '') ?>"
              maxlength="<?= (int)($meta['maxlength'] ?? 255) ?>"
              <?= !empty($meta['required']) ? 'required' : '' ?>
            >
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/admin/reference_data.php?entity=<?= e($entity) ?>">Cancel</a>
      <button type="submit" class="btn btn-primary"><?= e($isNew ? 'Create' : 'Update') ?></button>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>