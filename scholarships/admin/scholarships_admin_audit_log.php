<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Audit Log';
$pdo = db();

$actionFilter = $_GET['action'] ?? '';
$tableFilter = $_GET['table'] ?? '';
$userFilter = $_GET['user'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$where = ['1=1'];
$params = [];

if ($actionFilter !== '') {
    $where[] = 'al.action = ?';
    $params[] = $actionFilter;
}

if ($tableFilter !== '') {
    $where[] = 'al.table_name = ?';
    $params[] = $tableFilter;
}

if ($userFilter !== '') {
    $where[] = 'u.email = ?';
    $params[] = $userFilter;
}

if ($startDate !== '') {
    $where[] = 'DATE(al.created_at) >= ?';
    $params[] = $startDate;
}

if ($endDate !== '') {
    $where[] = 'DATE(al.created_at) <= ?';
    $params[] = $endDate;
}

$sql = '
    SELECT
        al.audit_id,
        al.action,
        al.table_name,
        al.created_at,
        u.email AS user_email
    FROM audit_log al
    LEFT JOIN users u ON u.user_id = al.user_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY al.created_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$availableActions = $pdo->query(
    'SELECT DISTINCT action FROM audit_log ORDER BY action'
)->fetchAll();

$availableTables = $pdo->query(
    'SELECT DISTINCT table_name FROM audit_log ORDER BY table_name'
)->fetchAll();

$availableUsers = $pdo->query(
    'SELECT DISTINCT u.email
     FROM audit_log al
     JOIN users u ON u.user_id = al.user_id
     WHERE u.email IS NOT NULL
     ORDER BY u.email'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Audit Log</h1>
    <p class="text-muted mb-0">Track changes to scholarship data and reference tables.</p>
  </div>
</div>

<form method="get" class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label" for="action">Action</label>
        <select class="form-select" id="action" name="action">
          <option value="">All actions</option>
          <?php foreach ($availableActions as $row): ?>
            <option value="<?= e($row['action']) ?>" <?= $actionFilter === $row['action'] ? 'selected' : '' ?>>
              <?= e($row['action']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label" for="table">Table</label>
        <select class="form-select" id="table" name="table">
          <option value="">All tables</option>
          <?php foreach ($availableTables as $row): ?>
            <option value="<?= e($row['table_name']) ?>" <?= $tableFilter === $row['table_name'] ? 'selected' : '' ?>>
              <?= e($row['table_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label" for="user">User</label>
        <select class="form-select" id="user" name="user">
          <option value="">All users</option>
          <?php foreach ($availableUsers as $row): ?>
            <option value="<?= e($row['email']) ?>" <?= $userFilter === $row['email'] ? 'selected' : '' ?>>
              <?= e($row['email']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label" for="start_date">From</label>
        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= e($startDate) ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label" for="end_date">To</label>
        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= e($endDate) ?>">
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
      <a class="btn btn-outline-secondary me-2" href="<?= e(APP_URL) ?>/admin/audit_log.php">Clear filters</a>
      <button type="submit" class="btn btn-primary">Apply filters</button>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-header">
    <h2 class="h5 mb-0">Audit entries</h2>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Action</th>
            <th>Table</th>
            <th>User</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= (int)$row['audit_id'] ?></td>
                <td><?= e((string)$row['action']) ?></td>
                <td><?= e((string)$row['table_name']) ?></td>
                <td><?= e((string)($row['user_email'] ?? 'System')) ?></td>
                <td><?= e((string)$row['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">No audit activity matches your filters.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
