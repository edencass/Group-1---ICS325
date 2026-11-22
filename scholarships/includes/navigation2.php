<li class="nav-item">
  <a class="nav-link" href="<?= e(APP_URL) ?>/public/my_matches.php">My Matches</a>
</li>

<?php if ($normalizedRole === 'parent'): ?>
  <li class="nav-item">
    <a class="nav-link" href="<?= e(APP_URL) ?>/public/scholarships_public_parent_dashboard.php">
      Parent Dashboard
    </a>
  </li>
<?php endif; ?>
