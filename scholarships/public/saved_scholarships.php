<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';
require_role(['Student', 'Parent', 'Administrator', 'Super-admin']);

$page_title = 'Saved Scholarships & Deadlines';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$userId = current_user_id();

// Fetch the user's saved scholarships (joining with scholarship details)
$stmt = $pdo->prepare(
    "SELECT ss.saved_id, ss.status, ss.saved_at, 
            s.scholarship_id, s.title, s.amount, s.deadline, s.official_url,
            p.provider_name
       FROM saved_scholarships ss
       JOIN scholarships s ON s.scholarship_id = ss.scholarship_id
       JOIN providers p ON p.provider_id = s.provider_id
      WHERE ss.user_id = ?
      ORDER BY s.deadline ASC"
);
$stmt->execute([$userId]);
$saved = $stmt->fetchAll();

// Process upcoming deadlines (30/60/90 days)
$today = new DateTime();
$upcoming = ['30' => [], '60' => [], '90' => []];
$other = [];

foreach ($saved as $row) {
    if ($row['deadline']) {
        $deadline = new DateTime($row['deadline']);
        $diff = $today->diff($deadline)->days;
        
        if ($deadline < $today) {
            $other[] = $row; // Expired
        } elseif ($diff <= 30) {
            $upcoming['30'][] = $row;
        } elseif ($diff <= 60) {
            $upcoming['60'][] = $row;
        } elseif ($diff <= 90) {
            $upcoming['90'][] = $row;
        } else {
            $other[] = $row;
        }
    } else {
        $other[] = $row;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">My Saved Scholarships</h1>
  <a href="search.php" class="btn btn-primary">Find more</a>
</div>

<?php if (empty($saved)): ?>
  <div class="alert alert-info">You haven't saved any scholarships yet. <a href="search.php">Search for scholarships</a> to get started.</div>
<?php else: ?>
  
  <!-- Upcoming Deadlines Section -->
  <div class="row g-3 mb-5">
    <?php foreach (['30' => 'Due in 30 Days', '60' => 'Due in 60 Days', '90' => 'Due in 90 Days'] as $days => $label): ?>
      <div class="col-md-4">
        <div class="card shadow-sm h-100 border-<?= $days == '30' ? 'danger' : ($days == '60' ? 'warning' : 'info') ?>">
          <div class="card-header bg-<?= $days == '30' ? 'danger' : ($days == '60' ? 'warning' : 'info') ?> text-white">
            <h5 class="mb-0"><?= $label ?></h5>
          </div>
          <div class="card-body">
            <?php if (empty($upcoming[$days])): ?>
              <p class="text-muted mb-0">No deadlines in this window.</p>
            <?php else: ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($upcoming[$days] as $item): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div class="text-truncate" style="max-width: 70%;">
                      <a href="scholarship_detail.php?id=<?= (int)$item['scholarship_id'] ?>" class="text-decoration-none">
                        <?= e($item['title']) ?>
                      </a>
                    </div>
                    <span class="badge bg-secondary"><?= e(format_date_us($item['deadline'])) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Full Saved List with Status Tracking -->
  <h2 class="h4 mb-3">All Saved Scholarships</h2>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Scholarship</th>
          <th>Deadline</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($saved as $row): ?>
          <tr>
            <td>
              <a href="scholarship_detail.php?id=<?= (int)$row['scholarship_id'] ?>" class="fw-bold">
                <?= e($row['title']) ?>
              </a>
              <div class="small text-muted"><?= e($row['provider_name']) ?></div>
            </td>
            <td><?= $row['deadline'] ? e(format_date_us($row['deadline'])) : 'Rolling' ?></td>
            <td>
              <select class="form-select form-select-sm status-select" data-saved-id="<?= (int)$row['saved_id'] ?>" style="width: auto;">
                <?php 
                $statuses = ['Interested', 'In Progress', 'Submitted', 'Awarded', 'Not Awarded'];
                foreach ($statuses as $status): 
                ?>
                  <option value="<?= e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>>
                    <?= e($status) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-danger unsave-btn" data-saved-id="<?= (int)$row['saved_id'] ?>">
                Remove
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Handle status change
  document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
      const savedId = this.dataset.savedId;
      const newStatus = this.value;
      
      fetch('update_saved_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `saved_id=${savedId}&status=${newStatus}&csrf_token=<?= csrf_token() ?>`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Optional: flash green border
          this.classList.add('is-valid');
          setTimeout(() => this.classList.remove('is-valid'), 1000);
        } else {
          alert('Failed to update status: ' + data.error);
        }
      });
    });
  });

  // Handle unsave
  document.querySelectorAll('.unsave-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      if (!confirm('Remove this scholarship from your saved list?')) return;
      
      const savedId = this.dataset.savedId;
      
      fetch('toggle_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `scholarship_id=0&saved_id=${savedId}&action=unsave&csrf_token=<?= csrf_token() ?>`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          this.closest('tr').remove();
          // Optionally reload to update the 30/60/90 boxes
          location.reload();
        } else {
          alert('Failed to remove: ' + data.error);
        }
      });
    });
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>