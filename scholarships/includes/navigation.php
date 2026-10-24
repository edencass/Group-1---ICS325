<?php
require_once __DIR__ . '/security.php';
$role = current_role();
$showAdmin = APP_DEV_MODE || in_array(strtolower($role), ['admin', 'administrator', 'super-admin'], true);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold" href="<?= e(APP_URL) ?>/public/index.php">
      <?= e(APP_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#mainNav" aria-controls="mainNav"
            aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/faq.php">FAQ</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/contact.php">Contact</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/search.php">Search</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/admin/import_stage.php">Import</a></li>


        <?php if ($showAdmin): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button"
               data-bs-toggle="dropdown" aria-expanded="false">Admin</a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/scholarships.php">Manage scholarships</a></li>
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/import_stage.php">Stage import</a></li>
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/import_report.php">Validation report</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>