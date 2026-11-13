<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$errors = [];
$firstName = '';
$lastName = '';
$email = '';
$roleName = 'Student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $firstNameValue = $_POST['first_name'] ?? null;
    $lastNameValue = $_POST['last_name'] ?? null;
    $emailValue = $_POST['email'] ?? null;
    $roleValue = $_POST['role'] ?? null;
    $passwordValue = $_POST['password'] ?? null;
    $passwordConfirmationValue = $_POST['password_confirmation'] ?? null;
    $firstName = is_string($firstNameValue) ? trim($firstNameValue) : '';
    $lastName = is_string($lastNameValue) ? trim($lastNameValue) : '';
    $email = is_string($emailValue) ? strtolower(trim($emailValue)) : '';
    $roleName = is_string($roleValue) ? $roleValue : '';
    $password = is_string($passwordValue) ? $passwordValue : '';
    $passwordConfirmation = is_string($passwordConfirmationValue)
        ? $passwordConfirmationValue
        : '';

    if (!preg_match('/\A.{1,100}\z/us', $firstName)) {
        $errors[] = 'First name is required and must be 100 characters or fewer.';
    }
    if (!preg_match('/\A.{1,100}\z/us', $lastName)) {
        $errors[] = 'Last name is required and must be 100 characters or fewer.';
    }
    if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address (255 characters or fewer).';
    }
    if (!in_array($roleName, ['Student', 'Parent'], true)) {
        $errors[] = 'Choose Student or Parent as your account type.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be between 8 and 72 bytes.';
    }
    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $pdo = db();
        $roleQuery = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ?');
        $roleQuery->execute([$roleName]);
        $roleId = $roleQuery->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('The Student and Parent roles must exist in the roles table.');
        }

        $emailQuery = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $emailQuery->execute([$email]);
        if ($emailQuery->fetchColumn() !== false) {
            $errors[] = 'An account with that email address already exists.';
        } else {
            try {
                $pdo->beginTransaction();
                $insertUser = $pdo->prepare(
                    'INSERT INTO users (role_id, email, password_hash) VALUES (?, ?, ?)'
                );
                $insertUser->execute([
                    $roleId,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();

                $insertProfile = $pdo->prepare(
                    'INSERT INTO profiles (user_id, first_name, last_name) VALUES (?, ?, ?)'
                );
                $insertProfile->execute([$userId, $firstName, $lastName]);
                $pdo->commit();

                header('Location: ' . APP_URL . '/public/login.php?registered=1', true, 303);
                exit;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (($exception->errorInfo[1] ?? null) === 1062) {
                    $errors[] = 'An account with that email address already exists.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$page_title = 'Create an Account';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Create an Account</h1>
<p class="text-muted">Register as a student or parent to get started.</p>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3" id="registration-form">
  <?= csrf_field() ?>
  <div class="col-md-6">
    <label class="form-label" for="first_name">First name</label>
    <input class="form-control" id="first_name" name="first_name" required maxlength="100"
           autocomplete="given-name" value="<?= e($firstName) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="last_name">Last name</label>
    <input class="form-control" id="last_name" name="last_name" required maxlength="100"
           autocomplete="family-name" value="<?= e($lastName) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="email">Email</label>
    <input class="form-control" type="email" id="email" name="email" required maxlength="255"
           autocomplete="email" value="<?= e($email) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="role">Account type</label>
    <select class="form-select" id="role" name="role" required>
      <option value="Student"<?= $roleName === 'Student' ? ' selected' : '' ?>>Student</option>
      <option value="Parent"<?= $roleName === 'Parent' ? ' selected' : '' ?>>Parent</option>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="password">Password</label>
    <input class="form-control" type="password" id="password" name="password" required
           minlength="8" maxlength="72" autocomplete="new-password">
    <div class="form-text">Use 8–72 bytes (up to 72 ASCII characters).</div>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="password_confirmation">Confirm password</label>
    <input class="form-control" type="password" id="password_confirmation"
           name="password_confirmation" required minlength="8" maxlength="72"
           autocomplete="new-password">
  </div>
  <div class="col-12">
    <button class="btn btn-primary" type="submit">Create account</button>
    <a class="btn btn-link" href="<?= e(APP_URL) ?>/public/login.php">Already registered? Log in</a>
  </div>
</form>
<script>
(() => {
  const form = document.getElementById('registration-form');
  const password = document.getElementById('password');
  const confirmation = document.getElementById('password_confirmation');
  const validateConfirmation = () => {
    confirmation.setCustomValidity(
      confirmation.value === password.value ? '' : 'Passwords do not match.'
    );
  };
  password.addEventListener('input', validateConfirmation);
  confirmation.addEventListener('input', validateConfirmation);
  form.addEventListener('submit', validateConfirmation);
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
