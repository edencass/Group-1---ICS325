<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';
$email = '';
$registered = ($_GET['registered'] ?? '') === '1';
$sessionExpired = !empty($_SESSION['session_expired']);
unset($_SESSION['session_expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $emailValue = $_POST['email'] ?? null;
    $passwordValue = $_POST['password'] ?? null;
    $email = is_string($emailValue) ? strtolower(trim($emailValue)) : '';
    $password = is_string($passwordValue) ? $passwordValue : '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === '' || strlen($password) > 72) {
        $error = 'Enter a valid email address and password.';
    } else {
        $query = db()->prepare(
            'SELECT u.user_id, u.password_hash, r.role_name
               FROM users u
               JOIN roles r ON r.role_id = u.role_id
              WHERE u.email = ?
              LIMIT 1'
        );
        $query->execute([$email]);
        $user = $query->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user((int) $user['user_id'], (string) $user['role_name']);
            header('Location: ' . APP_URL . '/public/index.php', true, 303);
            exit;
        }

        $error = 'Email or password is incorrect.';
    }
}

$page_title = 'Log In';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Log In</h1>
<?php if ($registered): ?>
  <div class="alert alert-success">Account created. You can now log in.</div>
<?php endif; ?>
<?php if ($sessionExpired): ?>
  <div class="alert alert-warning">You were logged out after a period of inactivity. Please log in again.</div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="row g-3" autocomplete="on">
  <?= csrf_field() ?>
  <div class="col-md-6">
    <label class="form-label" for="email">Email</label>
    <input class="form-control" type="email" id="email" name="email" required maxlength="255"
           autocomplete="username" value="<?= e($email) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="password">Password</label>
    <input class="form-control" type="password" id="password" name="password" required
           autocomplete="current-password">
  </div>
  <div class="col-12">
    <button class="btn btn-primary" type="submit">Log in</button>
    <a class="btn btn-link" href="<?= e(APP_URL) ?>/public/register.php">Create an account</a>
  </div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
