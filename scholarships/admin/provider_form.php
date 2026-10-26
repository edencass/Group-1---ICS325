<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarships_includes_audit (1).php';
require_role(['Administrator', 'Super-admin']);

$pdo   = db();
$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$isNew = ($id === 0);

$types = $pdo->query('SELECT provider_type_id, provider_type_name FROM provider_type ORDER BY provider_type_name')->fetchAll();
$data = ['provider_name' => '', 'provider_type_id' => '', 'website' => ''];
$errors = [];

// Load existing provider
if (!$isNew) {
    $stmt = $pdo->prepare('SELECT * FROM providers WHERE provider_id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit('Provider not found.'); }
    $data = ['provider_name' => $row['provider_name'], 'provider_type_id' => (string)$row['provider_type_id'], 'website' => (string)$row['website']];
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim((string)($_POST['provider_name'] ?? ''));
    $typeId  = (int)($_POST['provider_type_id'] ?? 0);
    $website = trim((string)($_POST['website'] ?? ''));

    // SERVER-SIDE VALIDATION
    if ($name === '') {
        $errors['provider_name'] = 'Provider name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors['provider_name'] = 'Provider name must be 255 characters or fewer.';
    } else {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM providers WHERE provider_name = ? AND provider_id <> ?');
        $dup->execute([$name, $id]);
        if ((int)$dup->fetchColumn() > 0) $errors['provider_name'] = 'A provider with that name already exists. Use Merge instead.';
    }

    if ($typeId <= 0) $errors['provider_type_id'] = 'Choose a provider type.';
    
    if ($website !== '') {
        if (!filter_var($website, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($website, PHP_URL_SCHEME)), ['http','https'], true)) {
            $errors['website'] = 'Website must be a valid http(s) URL.';
        }
    }

    // Save if valid
    if (!$errors) {
        if ($isNew) {
            $stmt = $pdo->prepare('INSERT INTO providers (provider_type_id, provider_name, website) VALUES (?,?,?)');
            $stmt->execute([$typeId, $name, $website !== '' ? $website : null]);
            log_audit_event($pdo, 'create_provider', 'providers', current_user_id());
            $_SESSION['flash_success'] = 'Provider created.';
        } else {
            $stmt = $pdo->prepare('UPDATE providers SET provider_type_id = ?, provider_name = ?, website = ? WHERE provider_id = ?');
            $stmt->execute([$typeId, $name, $website !== '' ? $website : null, $id]);
            log_audit_event($pdo, 'update_provider', 'providers', current_user_id());
            $_SESSION['flash_success'] = 'Provider updated.';
        }
        header('Location: providers.php'); exit;
    }
    $data = ['provider_name' => $name, 'provider_type_id' => (string)$typeId, 'website' => $website];
}

$page_title = $isNew ? 'Add Provider' : 'Edit Provider';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4"><?= e($page_title) ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger"><strong>Please fix the highlighted fields.</strong></div>
<?php endif; ?>

<form method="post" id="providerForm" novalidate>
  <?= csrf_field() ?>

  <div class="mb-3">
    <label class="form-label" for="provider_name">Provider name *</label>
    <input type="text" class="form-control <?= isset($errors['provider_name']) ? 'is-invalid' : '' ?>"
           id="provider_name" name="provider_name" maxlength="255" value="<?= e($data['provider_name']) ?>" required>
    <div class="invalid-feedback" id="err_name"><?= e($errors['provider_name'] ?? '') ?></div>
  </div>

  <div class="mb-3">
    <label class="form-label" for="provider_type_id">Provider type *</label>
    <select class="form-select <?= isset($errors['provider_type_id']) ? 'is-invalid' : '' ?>" id="provider_type_id" name="provider_type_id" required>
      <option value="">-- choose --</option>
      <?php foreach ($types as $t): ?>
        <option value="<?= (int)$t['provider_type_id'] ?>" <?= ((string)$t['provider_type_id'] === $data['provider_type_id']) ? 'selected' : '' ?>>
          <?= e($t['provider_type_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="invalid-feedback" id="err_type"><?= e($errors['provider_type_id'] ?? '') ?></div>
  </div>

  <div class="mb-3">
    <label class="form-label" for="website">Website (optional)</label>
    <input type="url" class="form-control <?= isset($errors['website']) ? 'is-invalid' : '' ?>"
           id="website" name="website" maxlength="255" value="<?= e($data['website']) ?>">
    <div class="invalid-feedback" id="err_website"><?= e($errors['website'] ?? '') ?></div>
  </div>

  <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create provider' : 'Save changes' ?></button>
  <a class="btn btn-link" href="<?= e(APP_URL) ?>/admin/providers.php">Cancel</a>
</form>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {
  function showError($field, $feedback, message) {
    $field.addClass('is-invalid');
    $feedback.text(message);
  }
  function clearError($field, $feedback) {
    $field.removeClass('is-invalid');
    $feedback.text('');
  }

  // CLIENT-SIDE VALIDATION (Runs before the form posts)
  $('#providerForm').on('submit', function (ev) {
    let ok = true;
    const $name  = $('#provider_name');
    const $type  = $('#provider_type_id');
    const $site  = $('#website');

    clearError($name, $('#err_name'));
    clearError($type, $('#err_type'));
    clearError($site, $('#err_website'));

    if ($.trim($name.val()) === '') {
      showError($name, $('#err_name'), 'Provider name is required.');
      ok = false;
    }

    if ($type.val() === '') {
      showError($type, $('#err_type'), 'Choose a provider type.');
      ok = false;
    }

    const url = $.trim($site.val());
    if (url !== '') {
      const isHttp = /^https?:\/\//i.test(url);
      let valid = false;
      try { new URL(url); valid = true; } catch (e) {}
      if (!isHttp || !valid) {
        showError($site, $('#err_website'), 'Website must be a valid http(s) URL.');
        ok = false;
      }
    }

    if (!ok) ev.preventDefault();
  });

  $('#provider_name, #website').on('input', function () {
    $(this).removeClass('is-invalid').siblings('.invalid-feedback').text('');
  });
  $('#provider_type_id').on('change', function () {
    $(this).removeClass('is-invalid').siblings('.invalid-feedback').text('');
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>