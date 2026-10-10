<?php
$role = current_role();
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
        <li class="nav-item">
          <a class="nav-link" href="<?= e(APP_URL) ?>/public/index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= e(APP_URL) ?>/public/login.php">Login</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= e(APP_URL) ?>/public/search.php">Search</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= e(APP_URL) ?>/public/my-matches.php">My Matches</a>
        </li>
      </ul>
    </div>

  </div>
</nav>