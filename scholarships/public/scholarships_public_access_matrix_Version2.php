<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

$page_title = 'Access Control Test Matrix';

$roles = [
    'Logged out',
    'Visitor',
    'Student',
    'Parent',
    'Administrator',
    'Super-admin',
];

$pages = [
    'public/index.php' => [
        'Logged out' => 'Allowed',
        'Visitor' => 'Allowed',
        'Student' => 'Allowed',
        'Parent' => 'Allowed',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'public/about.php' => [
        'Logged out' => 'Allowed',
        'Visitor' => 'Allowed',
        'Student' => 'Allowed',
        'Parent' => 'Allowed',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'public/search.php' => [
        'Logged out' => 'Allowed',
        'Visitor' => 'Allowed',
        'Student' => 'Allowed',
        'Parent' => 'Allowed',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'public/profile.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => 'Allowed',
        'Parent' => 'Allowed',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'public/change_password.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => 'Allowed',
        'Parent' => 'Allowed',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'admin/scholarships.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'admin/data_quality.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'admin/providers.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'admin/reference_data.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'admin/audit_log.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'admin/import_stage.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
];

$actions = [
    'POST /admin/scholarship_save.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'POST /admin/provider_form.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'POST /admin/reference_data_form.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'POST /admin/scholarship_delete.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => '403',
        'Parent' => '403',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
    'POST /public/change_password.php' => [
        'Logged out' => 'Redirected',
        'Visitor' => '403',
        'Student' => 'Allowed',
        'Parent' => 'Allowed',
        'Administrator' => 'Allowed',
        'Super-admin' => 'Allowed',
    ],
];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
  <h1 class="h3 mb-3">Access Control Test Matrix</h1>

  <div class="alert alert-info">
    Expected result values: Allowed, Redirected, or 403.
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-bordered">
      <thead>
        <tr>
          <th>Page / Action</th>
          <?php foreach ($roles as $role): ?>
            <th><?= e($role) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $page => $outcomes): ?>
          <tr>
            <td><?= e($page) ?></td>
            <?php foreach ($roles as $role): ?>
              <td><?= e($outcomes[$role] ?? '—') ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>

        <?php foreach ($actions as $action => $outcomes): ?>
          <tr>
            <td><?= e($action) ?></td>
            <?php foreach ($roles as $role): ?>
              <td><?= e($outcomes[$role] ?? '—') ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>