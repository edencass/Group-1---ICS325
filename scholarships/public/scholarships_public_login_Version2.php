<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

$page_title = 'Login';
$error = '';

if (is_logged_in()) {
    $redirect = $_SESSION['redirect_after_login'] ?? APP_URL . '/public/index.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT u.user_id, u.password_hash, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE LOWER(u.email) = LOWER(?)'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, (string)$user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['role_name'] = (string)$user['role_name'];

            $redirect = $_SESSION['redirect_after_login'] ?? APP_URL . '/public/index.php';
            unset($_SESSION['redirect_after_login']);

            header('Location: ' . $redirect);
            exit;
        }

        $error = 'Invalid email or password.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h3 mb-3">Login</h1>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(APP_URL) ?>/public/login.php">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input
              type="email"
              class="form-control"
              id="email"
              name="email"
              value="<?= e($_POST['email'] ?? '') ?>"
              required
            >
          </div>

          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input
              type="password"
              class="form-control"
              id="password"
              name="password"
              required
            >
          </div>

          <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>