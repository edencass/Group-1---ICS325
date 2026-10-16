<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
$composer_autoload = __DIR__ . '/../vendor/autoload.php';
$xlsx_runtime_ready = extension_loaded('zip') && is_file($composer_autoload);
if ($xlsx_runtime_ready) {
    require_once $composer_autoload;
}
require_once __DIR__ . '/../includes/scholarship_excel_importer.php';

$page_title = 'Upload Scholarships';
$errors = [];
$imported_count = null;
$can_upload = APP_DEV_MODE || (
    is_logged_in()
    && in_array(strtolower(current_role()), ['admin', 'super-admin'], true)
);

if (!$can_upload) {
    http_response_code(403);
}

if ($can_upload && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $file = $_FILES['scholarships_excel'] ?? null;
    if (!$xlsx_runtime_ready) {
        $errors[] = extension_loaded('zip')
            ? 'Excel import support is not installed. Run Composer install in the PHP application folder.'
            : 'Excel uploads require the PHP ZIP extension. Enable extension=zip in php.ini and restart Apache.';
    } elseif (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Choose an Excel workbook to upload.';
    } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
        $errors[] = 'The Excel file exceeds the ' . (int) (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB upload limit.';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $errors[] = 'The uploaded file must use the .xlsx extension.';
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'The uploaded file could not be verified.';
    } else {
        try {
            $rows = ScholarshipExcelImporter::read($file['tmp_name']);
        } catch (Throwable $exception) {
            error_log('Scholarship Excel validation failed: ' . $exception->getMessage());
            $errors[] = $exception->getMessage();
        }
    }

    if ($errors === []) {
                try {
                    $pdo = db();
                    $pdo->beginTransaction();

                    $states = $pdo->query('SELECT state_id, state_name, state_code FROM states')->fetchAll();

                    $batch_statement = $pdo->prepare(
                        'INSERT INTO import_batches (user_id, status) VALUES (:user_id, :status)'
                    );
                    $batch_statement->execute([
                        'user_id' => current_user_id(),
                        'status' => 'completed',
                    ]);
                    $batch_id = (int) $pdo->lastInsertId();

                    $find_type = $pdo->prepare(
                        'SELECT provider_type_id FROM provider_type WHERE provider_type_name = :name LIMIT 1'
                    );
                    $add_type = $pdo->prepare(
                        'INSERT INTO provider_type (provider_type_name) VALUES (:name)'
                    );
                    $find_provider = $pdo->prepare(
                        'SELECT provider_id FROM providers WHERE provider_name = :name AND provider_type_id = :type_id LIMIT 1'
                    );
                    $add_provider = $pdo->prepare(
                        'INSERT INTO providers (provider_type_id, provider_name, website) VALUES (:type_id, :name, :website)'
                    );
                    $add_scholarship = $pdo->prepare(
                        'INSERT INTO scholarships (provider_id, state_id, title, description, amount, deadline) '
                        . 'VALUES (:provider_id, :state_id, :title, :description, :amount, :deadline)'
                    );
                    $add_staging_row = $pdo->prepare(
                        'INSERT INTO import_staging_rows (batch_id, raw_data, validation_status) '
                        . 'VALUES (:batch_id, :raw_data, :validation_status)'
                    );

                    foreach ($rows as $row) {
                        $find_type->execute(['name' => $row['provider_type']]);
                        $type_id = $find_type->fetchColumn();
                        if ($type_id === false) {
                            $add_type->execute(['name' => $row['provider_type']]);
                            $type_id = $pdo->lastInsertId();
                        }

                        $find_provider->execute([
                            'name' => $row['provider_name'],
                            'type_id' => $type_id,
                        ]);
                        $provider_id = $find_provider->fetchColumn();
                        if ($provider_id === false) {
                            $add_provider->execute([
                                'type_id' => $type_id,
                                'name' => $row['provider_name'],
                                'website' => $row['website'] !== '' ? $row['website'] : null,
                            ]);
                            $provider_id = $pdo->lastInsertId();
                        }

                        $state_id = ScholarshipExcelImporter::stateIdForRegion(
                            $row['state_region'],
                            $row['scope'],
                            $states
                        );

                        $add_scholarship->execute([
                            'provider_id' => $provider_id,
                            'state_id' => $state_id,
                            'title' => $row['title'],
                            'description' => $row['description'] !== '' ? $row['description'] : null,
                            'amount' => $row['amount'],
                            'deadline' => $row['deadline'],
                        ]);
                        $add_staging_row->execute([
                            'batch_id' => $batch_id,
                            'raw_data' => json_encode($row['raw_data'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                            'validation_status' => 'imported',
                        ]);
                    }

                    $pdo->commit();
                    $imported_count = count($rows);
                } catch (Throwable $exception) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Scholarship Excel import failed: ' . $exception->getMessage());
                    $errors[] = $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'The scholarships could not be imported. Check the database configuration and try again.';
                }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="mb-4">
  <h1 class="h2">Upload Scholarships</h1>
    <p class="text-muted">Import up to 1,000 scholarship records from the provided Excel workbook. The import is all-or-nothing: any invalid row prevents the batch from being saved.</p>
</section>

<?php if (!$can_upload): ?>
  <div class="alert alert-danger" role="alert">Only administrators can upload scholarships.</div>
<?php else: ?>
  <?php if (APP_DEV_MODE): ?>
    <div class="alert alert-warning" role="alert">Development mode is enabled. Upload access is not restricted by sign-in.</div>
  <?php endif; ?>

    <?php if (!extension_loaded('zip')): ?>
        <div class="alert alert-warning" role="alert">Excel uploads require the PHP ZIP extension. Enable <code>extension=zip</code> in <code>php.ini</code> and restart Apache.</div>
    <?php elseif (!is_file($composer_autoload)): ?>
        <div class="alert alert-warning" role="alert">Run <code>composer install</code> in the PHP application folder to install Excel import support.</div>
    <?php endif; ?>

  <?php if ($imported_count !== null): ?>
    <div class="alert alert-success" role="status">
      Successfully imported <?= (int) $imported_count ?> scholarship<?= $imported_count === 1 ? '' : 's' ?>.
    </div>
  <?php endif; ?>

  <?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
    <p class="fw-semibold mb-2">The Excel workbook was not imported:</p>
      <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
          <li><?= e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mb-4">
    <?= csrf_field() ?>
    <div class="mb-3">
            <label for="scholarships_excel" class="form-label">Scholarship Excel workbook</label>
            <input class="form-control" type="file" id="scholarships_excel" name="scholarships_excel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
      <div class="form-text">Maximum size: <?= (int) (MAX_UPLOAD_BYTES / 1024 / 1024) ?> MB.</div>
    </div>
    <button class="btn btn-primary" type="submit">Upload and import</button>
        <a class="btn btn-outline-secondary ms-2" href="scholarship-upload-template.xlsx" download>Download Excel template</a>
  </form>

    <section aria-labelledby="excel-format-heading">
        <h2 class="h5" id="excel-format-heading">Workbook format</h2>
        <p>Use the attached 29-column template. Keep its header names unchanged; scholarship name, provider/organization, and provider type are required. Other cells may be left blank.</p>
        <p>The remaining eligibility, award, and source details are retained with each imported scholarship. National awards do not need a state value.</p>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>