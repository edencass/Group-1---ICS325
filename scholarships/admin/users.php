<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Super-admin']);

$page_title = 'User Management';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$users = $pdo->query(
    "SELECT u.user_id, u.email, u.created_at, r.role_name, u.is_active
       FROM users u
       JOIN roles r ON r.role_id = u.role_id
      ORDER BY u.user_id ASC"
)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 mb-1">User Management</h1>
    <p class="text-muted mb-0">Create users, change roles, and manage access.</p>
  </div>
  <a class="btn btn-primary" href="user_form.php">Add User</a>
</div>

<?php if ($flash_success): ?><div class="alert alert-success"><?= e($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-danger"><?= e($flash_error) ?></div><?php endif; ?>

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead>
      <tr><th>ID</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['user_id'] ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge bg-secondary"><?= e($u['role_name']) ?></span></td>
        <td>
          <span class="badge bg-<?= $u['is_active'] ? 'success' : 'danger' ?>">
            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
        </td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="user_form.php?id=<?= (int)$u['user_id'] ?>">Edit</a>
          <?php if ((int)$u['user_id'] !== current_user_id()): ?>
            <form class="d-inline" method="post" action="user_toggle.php">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
              <button class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>" type="submit">
                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>