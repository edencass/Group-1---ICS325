<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Reference Data Management';
$pdo = db();

$entityMap = [
    'states' => [
        'label' => 'States',
        'table' => 'states',
        'id_col' => 'state_id',
        'name_col' => 'state_name',
        'sort' => 'state_name',
    ],
    'provider_type' => [
        'label' => 'Provider Types',
        'table' => 'provider_type',
        'id_col' => 'provider_type_id',
        'name_col' => 'provider_type_name',
        'sort' => 'provider_type_name',
    ],
    'categories' => [
        'label' => 'Categories',
        'table' => 'categories',
        'id_col' => 'category_id',
        'name_col' => 'category_name',
        'sort' => 'category_name',
    ],
    'fields_of_study' => [
        'label' => 'Fields of Study',
        'table' => 'fields_of_study',
        'id_col' => 'field_id',
        'name_col' => 'field_name',
        'sort' => 'field_name',
    ],
    'grade_levels' => [
        'label' => 'Grade Levels',
        'table' => 'grade_levels',
        'id_col' => 'grade_level_id',
        'name_col' => 'grade_name',
        'sort' => 'grade_name',
    ],
    'national_themes' => [
        'label' => 'National Themes',
        'table' => 'national_themes',
        'id_col' => 'theme_id',
        'name_col' => 'theme_name',
        'sort' => 'theme_name',
    ],
];

$entity = (string)($_GET['entity'] ?? 'states');
if (!isset($entityMap[$entity])) {
    $entity = 'states';
}

$spec = $entityMap[$entity];

$rows = $pdo->query(
    "SELECT * FROM {$spec['table']} ORDER BY {$spec['sort']}"
)->fetchAll();

$summary = [];
foreach ($entityMap as $key => $cfg) {
    $count = $pdo->query("SELECT COUNT(*) FROM {$cfg['table']}")->fetchColumn();
    $summary[$key] = [
        'label' => $cfg['label'],
        'count' => (int)$count,
    ];
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Reference Data Management</h1>
    <p class="text-muted mb-0">Maintain lookup values used throughout the scholarship system.</p>
  </div>
  <a class="btn btn-primary"
     href="<?= e(APP_URL) ?>/admin/reference_data_form.php?entity=<?= e($entity) ?>">
    Add new
  </a>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger"><?= e($flashError) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ($summary as $key => $item): ?>
    <div class="col-sm-6 col-md-4 col-xl-3">
      <a class="card h-100 text-decoration-none <?= $key === $entity ? 'border-primary' : '' ?>"
         href="<?= e(APP_URL) ?>/admin/scholarships_admin_reference_data.php?entity=<?= e($key) ?>">
        <div class="card-body">
          <div class="small text-muted"><?= e($item['label']) ?></div>
          <div class="display-6 fw-semibold"><?= (int)$item['count'] ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h2 class="h5 mb-0"><?= e($spec['label']) ?></h2>
    <a class="btn btn-sm btn-outline-primary"
       href="<?= e(APP_URL) ?>/admin/reference_data_form.php?entity=<?= e($entity) ?>">
      Add new
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): ?>
            <?php foreach ($rows as $row): ?>
              <?php $id = (int)$row[$spec['id_col']]; ?>
              <tr>
                <td><?= $id ?></td>
                <td><?= e((string)($row[$spec['name_col']] ?? '')) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="<?= e(APP_URL) ?>/admin/reference_data_form.php?entity=<?= e($entity) ?>&amp;id=<?= $id ?>">
                    Edit
                  </a>

                  <form class="d-inline" method="post"
                        action="<?= e(APP_URL) ?>/admin/reference_data_delete.php"
                        onsubmit="return confirm('Delete this reference value?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="entity" value="<?= e($entity) ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="3" class="text-muted text-center py-4">No records found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
