<?php
require_once __DIR__ . '/security.php';
$role = current_role();
$showAdmin = is_logged_in()
    && in_array(normalized_role($role), ['admin', 'super-admin'], true);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(APP_URL) ?>/public/index.php">
      <img src="<?= e(APP_URL) ?>/Images/ScholarshipLogo.png"
           alt="" width="40" height="40">
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
         <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/scholarship-facts_Version2.php">Scholarship Facts</a></li>

        <?php if (is_logged_in()): ?>
          <li class="nav-item">
            <form method="post" action="<?= e(APP_URL) ?>/public/logout.php" class="d-flex">
              <?= csrf_field() ?>
              <button class="nav-link btn btn-link border-0" type="submit">Log out</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/login.php">Log in</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/public/register.php">Register</a></li>
        <?php endif; ?>

        <?php if ($showAdmin): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button"
               data-bs-toggle="dropdown" aria-expanded="false">Admin</a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/scholarships.php">Manage scholarships</a></li>
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/data_quality.php">Data quality dashboard</a></li>
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/coverage_report.php">Coverage Report</a></li>
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/providers.php">Manage providers</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/import_stage.php">Stage import</a></li>
              <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/admin/import_report.php">Validation report</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
