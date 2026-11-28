<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

require_role(['Administrator', 'Super-admin']);

$page_title = 'Admin Usage Statistics';
$pdo = db();

// Include roles even when they currently have no users.
$usersByRole = $pdo->query(
    "SELECT r.role_name, COUNT(u.user_id) AS user_count
       FROM roles r
       LEFT JOIN users u ON u.role_id = r.role_id
      GROUP BY r.role_id, r.role_name
      ORDER BY user_count DESC, r.role_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Count saved records for each scholarship.
$mostSaved = $pdo->query(
    "SELECT s.scholarship_id, s.title, COUNT(ss.saved_id) AS save_count
       FROM scholarships s
       JOIN saved_scholarships ss ON ss.scholarship_id = s.scholarship_id
      GROUP BY s.scholarship_id, s.title
      ORDER BY save_count DESC, s.title
      LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

// Count searches that included a state filter.
$mostSearchedStates = $pdo->query(
    "SELECT asl.state_code,
            COALESCE(st.state_name, asl.state_code) AS state_name,
            COUNT(*) AS search_count
       FROM anonymous_search_log asl
       LEFT JOIN states st ON st.state_code = asl.state_code
      WHERE asl.state_code IS NOT NULL
        AND asl.state_code <> ''
      GROUP BY asl.state_code, st.state_name
      ORDER BY search_count DESC, state_name
      LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

// Count searches that included a field-of-study filter.
$mostSearchedFields = $pdo->query(
    "SELECT fos.field_name, COUNT(*) AS search_count
       FROM anonymous_search_log asl
       JOIN fields_of_study fos ON fos.field_id = asl.field_id
      WHERE asl.field_id IS NOT NULL
      GROUP BY fos.field_id, fos.field_name
      ORDER BY search_count DESC, fos.field_name
      LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h3 mb-1">Admin Usage Statistics</h1>
  <p class="text-muted mb-0">
    Summary of user roles, saved scholarships, and scholarship search filters.
  </p>
</div>

<section class="mb-5" aria-labelledby="users-by-role-heading">
  <h2 class="h4" id="users-by-role-heading">Users by role</h2>

  <?php if (!$usersByRole): ?>
    <p class="text-muted">No user or role data is available.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <caption class="visually-hidden">Number of users assigned to each role</caption>
        <thead>
          <tr>
            <th scope="col">Role</th>
            <th scope="col">Users</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($usersByRole as $row): ?>
          <tr>
            <th scope="row"><?= e($row['role_name']) ?></th>
            <td><?= (int)$row['user_count'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="mb-5" aria-labelledby="most-saved-heading">
  <h2 class="h4" id="most-saved-heading">Most-saved scholarships</h2>

  <?php if (!$mostSaved): ?>
    <p class="text-muted">No scholarships have been saved yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <caption class="visually-hidden">Top scholarships by number of saves</caption>
        <thead>
          <tr>
            <th scope="col">Scholarship</th>
            <th scope="col">Saves</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($mostSaved as $row): ?>
          <tr>
            <th scope="row"><?= e($row['title']) ?></th>
            <td><?= (int)$row['save_count'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="mb-5" aria-labelledby="most-searched-states-heading">
  <h2 class="h4" id="most-searched-states-heading">Most-searched states</h2>

  <?php if (!$mostSearchedStates): ?>
    <p class="text-muted">No state-filter searches have been recorded yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <caption class="visually-hidden">Top states by number of searches</caption>
        <thead>
          <tr>
            <th scope="col">State</th>
            <th scope="col">Searches</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($mostSearchedStates as $row): ?>
          <tr>
            <th scope="row">
              <?= e($row['state_name']) ?>
              <span class="text-muted">(<?= e($row['state_code']) ?>)</span>
            </th>
            <td><?= (int)$row['search_count'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="mb-5" aria-labelledby="most-searched-fields-heading">
  <h2 class="h4" id="most-searched-fields-heading">Most-searched fields of study</h2>

  <?php if (!$mostSearchedFields): ?>
    <p class="text-muted">No field-of-study searches have been recorded yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <caption class="visually-hidden">Top fields of study by number of searches</caption>
        <thead>
          <tr>
            <th scope="col">Field of study</th>
            <th scope="col">Searches</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($mostSearchedFields as $row): ?>
          <tr>
            <th scope="row"><?= e($row['field_name']) ?></th>
            <td><?= (int)$row['search_count'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>