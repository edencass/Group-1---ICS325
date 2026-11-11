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
        'route' => 'reference_data_form.php',
    ],
    'provider_type' => [
        'label' => 'Provider Types',
        'table' => 'provider_type',
        'id_col' => 'provider_type_id',
        'name_col' => 'provider_type_name',
        'sort' => 'provider_type_name',
        'route' => 'reference_data_form.php',
    ],
    'categories' => [
        'label' => 'Categories',
        'table' => 'categories',
        'id_col' => 'category_id',
        'name_col' => 'category_name',
        'sort' => 'category_name',
        'route' => 'reference_data_form.php',
    ],
    'fields_of_study' => [
        'label' => 'Fields of Study',
        'table' => 'fields_of_study',
        'id_col' => 'field_id',
        'name_col' => 'field_name',
        'sort' => 'field_name',
        'route' => 'reference_data_form.php',
    ],
    'grade_levels' => [
        'label' => 'Grade Levels',
        'table' => 'grade_levels',
        'id_col' => 'grade_level_id',
        'name_col' => 'grade_name',
        'sort' => 'grade_name',
        'route' => 'reference_data_form.php',
    ],
    'national_themes' => [
        'label' => 'National Themes',
        'table' => 'national_themes',
        'id_col' => 'theme_id',
        'name_col' => 'theme_name',
        'sort' => 'theme_name',
        'route' => 'reference_data_form.php',
    ],
];

$entity = $_GET['entity'] ?? 'states';
if (!isset($entityMap[$entity])) {
    $entity = 'states';
}

$spec = $entityMap[$entity];
$table = $spec['table'];
$idCol = $spec['id_col'];
$nameCol = $spec['name_col'];
$sort = $spec['sort'];

$rows = $pdo->query(
    "SELECT * FROM {$table} ORDER BY {$sort}"
)->fetchAll();

// Count summary for all reference tables
$summary = [];
foreach ($entityMap as $key => $cfg) {
    $count = $pdo->query("SELECT COUNT(*) FROM {$cfg['table']}")->fetchColumn();
    $summary[$key] = [
        'label' => $cfg['label'],
        'count' => (int)$count,
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Reference Data Management</h1>
    <p class="text-muted mb-0">Maintain lookup values used throughout the scholarship system.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(APP_URL) ?>/admin/reference_data_form.php?entity=<?= e($entity) ?>">Add new</a>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($summary as $key => $item): ?>
    <div class="col-sm-6 col-md-4 col-xl-3">
      <a class="card h-100 text-decoration-none <?= $key === $entity ? 'border-primary' : '' ?>"
         href="<?= e(APP_URL) ?>/admin/reference_data.php?entity=<?= e($key) ?>">
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
    <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/admin/reference_data_form.php?entity=<?= e($entity) ?>">
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
              <tr>
                <td><?= (int)$row[$idCol] ?></td>
                <td><?= e((string)($row[$nameCol] ?? '')) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="<?= e(APP_URL) ?>/admin/reference_data_form.php?entity=<?= e($entity) ?>&id=<?= (int)$row[$idCol] ?>">
                    Edit
                  </a>
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