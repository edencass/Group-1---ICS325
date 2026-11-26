<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Super-admin']);

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$isNew = ($id === 0);
$roles = $pdo->query('SELECT role_id, role_name FROM roles ORDER BY role_id')->fetchAll();
$data = ['email' => '', 'role_id' => '', 'password' => ''];
$errors = [];

if (!$isNew) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit('User not found.'); }
    $data = ['email' => $row['email'], 'role_id' => (string)$row['role_id'], 'password' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string)($_POST['email'] ?? ''));
    $roleId = (int)($_POST['role_id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

    $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?');
    $dup->execute([$email, $id]);
    if ((int)$dup->fetchColumn() > 0) $errors[] = 'Email already in use.';

    if ($roleId <= 0) $errors[] = 'Select a role.';
    if ($isNew && strlen($password) < 8) $errors[] = 'Password must be 8+ chars.';

    if (!$errors) {
        if ($isNew) {
            $stmt = $pdo->prepare('INSERT INTO users (email, role_id, password_hash, is_active) VALUES (?,?,?,1)');
            $stmt->execute([$email, $roleId, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['flash_success'] = 'User created.';
        } else {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET email = ?, role_id = ?, password_hash = ? WHERE user_id = ?');
                $stmt->execute([$email, $roleId, password_hash($password, PASSWORD_DEFAULT), $id]);
                $_SESSION['flash_success'] = 'User updated and password reset.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET email = ?, role_id = ? WHERE user_id = ?');
                $stmt->execute([$email, $roleId, $id]);
                $_SESSION['flash_success'] = 'User updated.';
            }
        }
        header('Location: users.php'); exit;
    }
    $data = ['email' => $email, 'role_id' => (string)$roleId];
}

$page_title = $isNew ? 'Add User' : 'Edit User';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4"><?= e($page_title) ?></h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3" style="max-width: 600px;">
  <?= csrf_field() ?>
  <div class="col-12">
    <label class="form-label" for="email">Email Address</label>
    <input type="email" class="form-control" id="email" name="email" value="<?= e($data['email']) ?>" required>
  </div>
  <div class="col-12">
    <label class="form-label" for="role_id">Role</label>
    <select class="form-select" id="role_id" name="role_id" required>
      <option value="">-- choose --</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int)$r['role_id'] ?>" <?= ((string)$r['role_id'] === $data['role_id']) ? 'selected' : '' ?>>
          <?= e($r['role_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12">
    <label class="form-label" for="password"><?= $isNew ? 'Password' : 'Reset Password (leave blank to keep)' ?></label>
    <input type="password" class="form-control" id="password" name="password" <?= $isNew ? 'required' : '' ?>>
  </div>
  <div class="col-12 mt-4">
    <button class="btn btn-primary" type="submit">Save User</button>
    <a class="btn btn-link" href="users.php">Cancel</a>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>