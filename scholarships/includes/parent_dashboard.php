<?php if ($isLoggedIn): ?>
  <?php if ($normalizedRole === 'parent'): ?>
    <li class="nav-item">
      <a class="nav-link" href="<?= e(APP_URL) ?>/public/parent_dashboard.php">
        Family Scholarships
      </a>
    </li>
  <?php endif; ?>

  <li class="nav-item">
    <a class="nav-link" href="<?= e(APP_URL) ?>/public/profile.php">My Profile</a>
  </li>
