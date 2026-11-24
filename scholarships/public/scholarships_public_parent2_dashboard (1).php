<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';

require_role(['Parent']);

$userId = current_user_id();
if ($userId === null) {
    http_response_code(401);
    exit('Please sign in to view the parent dashboard.');
}

$pdo = db();
$page_title = 'Children’s Scholarships';

$childrenQuery = $pdo->prepare(
    "SELECT DISTINCT
            u.user_id,
            u.email,
            p.first_name,
            p.last_name,
            psr.relationship_type
       FROM parent_student_relationships psr
       JOIN users u
         ON u.user_id = psr.student_user_id
       LEFT JOIN profiles p
         ON p.user_id = u.user_id
      WHERE psr.parent_user_id = ?
        AND u.is_active = 1
      ORDER BY p.first_name, p.last_name, u.email"
);
$childrenQuery->execute([$userId]);
$children = $childrenQuery->fetchAll(PDO::FETCH_ASSOC);

$savedQuery = $pdo->prepare(
    "SELECT
            ss.saved_id,
            ss.saved_at,
            ss.user_id AS student_user_id,
            s.scholarship_id,
            s.title,
            s.amount,
            s.deadline,
            s.official_url,
            p.provider_name,
            pr.first_name,
            pr.last_name,
            pr.email
       FROM parent_student_relationships psr
       JOIN saved_scholarships ss
         ON ss.user_id = psr.student_user_id
       JOIN users pr
         ON pr.user_id = psr.student_user_id
       LEFT JOIN profiles prf
         ON prf.user_id = psr.student_user_id
       JOIN scholarships s
         ON s.scholarship_id = ss.scholarship_id
       JOIN providers p
         ON p.provider_id = s.provider_id
      WHERE psr.parent_user_id = ?
      ORDER BY
        CASE
          WHEN s.deadline IS NULL THEN 1
          ELSE 0
        END,
        s.deadline ASC,
        prf.first_name ASC,
        s.title ASC"
);
$savedQuery->execute([$userId]);
$rows = $savedQuery->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Children’s Scholarships</h1>
    <p class="text-muted mb-0">
      All saved scholarships and deadlines for your linked students.
    </p>
  </div>
  <a class="btn btn-outline-primary" href="<?= e(APP_URL) ?>/public/profile.php">
    My Profile
  </a>
</div>

<?php if (!$children): ?>
  <div class="alert alert-info">
    No student accounts are currently linked to your parent account.
  </div>
<?php else: ?>
  <?php
  $childCount = count($children);
  $deadlineCount = 0;
  $expiredCount = 0;
  $today = date('Y-m-d');

  foreach ($rows as $row):
    if ($row['deadline']) {
      $deadlineCount++;
      if ($row['deadline'] < $today) {
        $expiredCount++;
      }
    }
  endforeach;
  ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="text-muted">Linked students</div>
          <div class="display-6"><?= $childCount ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="text-muted">Saved scholarships</div>
          <div class="display-6"><?= count($rows) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="text-muted">Expired deadlines</div>
          <div class="display-6 text-danger"><?= $expiredCount ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-secondary">
      Your linked students have not saved any scholarships yet.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th>Student</th>
            <th>Scholarship</th>
            <th>Provider</th>
            <th>Award</th>
            <th>Deadline</th>
            <th>Status</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
            $studentName = trim(
              (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))
            );

            if ($studentName === '') {
              $studentName = $row['email'];
            }

            $deadline = $row['deadline'];
            $status = 'Rolling';
            $statusClass = 'secondary';

            if ($deadline) {
              if ($deadline < $today) {
                $status = 'Expired';
                $statusClass = 'danger';
              } elseif ($deadline === $today) {
                $status = 'Due today';
                $statusClass = 'warning';
              } elseif ($deadline <= date('Y-m-d', strtotime('+30 days'))) {
                $status = 'Due soon';
                $statusClass = 'warning';
              } else {
                $status = 'Upcoming';
                $statusClass = 'success';
              }
            }
          ?>
            <tr>
              <td><?= e($studentName) ?></td>
              <td><?= e($row['title']) ?></td>
              <td><?= e($row['provider_name']) ?></td>
              <td>
                <?= $row['amount'] !== null
                  ? '$' . number_format((float)$row['amount'])
                  : 'Varies' ?>
              </td>
              <td>
                <?= $deadline
                  ? e(format_date_us($deadline))
                  : 'Rolling' ?>
              </td>
              <td>
                <span class="badge text-bg-<?= $statusClass ?>">
                  <?= e($status) ?>
                </span>
              </td>
              <td>
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(APP_URL) ?>/public/scholarship_detail.php?id=<?= (int)$row['scholarship_id'] ?>">
                  View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
