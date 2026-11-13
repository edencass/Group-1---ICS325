<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$userId = current_user_id();
if (!$userId) {
    http_response_code(401);
    exit('Please sign in to change your password.');
}

$pdo = db();
$page_title = 'Change Password';
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $errors[] = 'Enter your current password.';
    }

    if (strlen($newPassword) < 12) {
        $errors[] = 'Your new password must be at least 12 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'The new password and confirmation do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'SELECT password_hash FROM users WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, (string)$user['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            if ($newHash === false) {
                $errors[] = 'The password could not be updated. Please try again.';
            } else {
                try {
                    $update = $pdo->prepare(
                        'UPDATE users SET password_hash = ? WHERE user_id = ?'
                    );
                    $update->execute([$newHash, $userId]);

                    $success = 'Your password has been changed.';
                } catch (Throwable $exception) {
                    error_log('Password change failed: ' . $exception->getMessage());
                    $errors[] = 'The password could not be updated. Please try again.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Change Password</h1>
    <p class="text-muted mb-0">Choose a strong password that you do not use elsewhere.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/public/profile.php">Back to profile</a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?>
        <li><?= e($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(APP_URL) ?>/public/change_password.php" autocomplete="off">
  <?= csrf_field() ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="mb-3">
        <label for="current_password" class="form-label">Current password</label>
        <input
          type="password"
          class="form-control"
          id="current_password"
          name="current_password"
          autocomplete="current-password"
          required
        >
      </div>

      <div class="mb-3">
        <label for="new_password" class="form-label">New password</label>
        <input
          type="password"
          class="form-control"
          id="new_password"
          name="new_password"
          minlength="12"
          autocomplete="new-password"
          required
        >
        <div class="form-text">Use at least 12 characters.</div>
      </div>

      <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirm new password</label>
        <input
          type="password"
          class="form-control"
          id="confirm_password"
          name="confirm_password"
          minlength="12"
          autocomplete="new-password"
          required
        >
      </div>
    </div>

    <div class="card-footer d-flex justify-content-end">
      <button type="submit" class="btn btn-primary">Update password</button>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>