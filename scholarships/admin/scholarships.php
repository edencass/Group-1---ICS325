<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Manage Scholarships';
$extra_head_html = '<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$rows = $pdo->query(
    "SELECT s.scholarship_id, s.title, s.official_url, s.scope, s.amount, s.deadline,
            p.provider_name, st.state_code, s.validation_messages,
            (SELECT GROUP_CONCAT(c.category_name ORDER BY c.category_name SEPARATOR ', ')
               FROM scholarship_categories sc
               JOIN categories c ON c.category_id = sc.category_id
              WHERE sc.scholarship_id = s.scholarship_id) AS categories
       FROM scholarships s
       JOIN providers p ON p.provider_id = s.provider_id
       LEFT JOIN states st ON st.state_id = s.state_id
      ORDER BY s.scholarship_id DESC"
)->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Scholarships</h1>
    <p class="text-muted mb-0">Create, review, and maintain scholarship records.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(APP_URL) ?>/admin/scholarship_form.php">Add scholarship</a>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success"><?= e($_SESSION['flash_success']) ?></div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="table-responsive">
  <table id="scholarshipsTable" class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <th>ID</th><th>Scholarship</th><th>Provider</th><th>Scope</th>
        <th>Category</th><th>Award</th><th>Deadline</th><th>Validation</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row):
        $messages = json_decode((string)($row['validation_messages'] ?? ''), true);
        $messageCount = is_array($messages) ? count($messages) : 0;
    ?>
      <tr>
        <td><?= (int)$row['scholarship_id'] ?></td>
        <td>
          <?php if (!empty($row['official_url'])): ?>
            <a href="<?= e($row['official_url']) ?>" target="_blank" rel="noopener"><?= e($row['title']) ?></a>
          <?php else: ?>
            <?= e($row['title']) ?>
          <?php endif; ?>
        </td>
        <td><?= e($row['provider_name']) ?></td>
        <td><?= e($row['scope'] === 'National' ? 'National' : 'State/Local' . ($row['state_code'] ? ' (' . $row['state_code'] . ')' : '')) ?></td>
        <td><?= e($row['categories'] ?? '') ?></td>
        <td><?= $row['amount'] !== null ? '$' . number_format((float)$row['amount'], 2) : 'Varies' ?></td>
        <td><?= e($row['deadline'] !== null ? format_date_us((string)$row['deadline']) : 'Rolling') ?></td>
        <td>
          <?php if ($messageCount): ?>
            <span class="badge bg-warning text-dark" title="<?= e(implode(' ', $messages)) ?>">
              <?= $messageCount ?> message<?= $messageCount === 1 ? '' : 's' ?>
            </span>
          <?php else: ?>
            <span class="badge bg-success">Valid</span>
          <?php endif; ?>
        </td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/admin/scholarship_form.php?id=<?= (int)$row['scholarship_id'] ?>">Edit</a>
          <form class="d-inline" method="post" action="<?= e(APP_URL) ?>/admin/scholarship_delete.php"
                onsubmit="return confirm('Delete this scholarship and its saved-scholarship links?');">
            <?= csrf_field() ?>
            <input type="hidden" name="scholarship_id" value="<?= (int)$row['scholarship_id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$extra_scripts_html = '<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>'
    . '<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>'
    . '<script>document.addEventListener("DOMContentLoaded",function(){new DataTable("#scholarshipsTable",{pageLength:25,order:[[0,"desc"]]});});</script>';
require_once __DIR__ . '/../includes/footer.php';
?>
