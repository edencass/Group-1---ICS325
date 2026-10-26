<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarships_includes_audit (1).php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Provider Management';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch providers and count how many scholarships they have
$providers = $pdo->query(
    "SELECT p.provider_id, p.provider_name, p.website,
            pt.provider_type_name,
            (SELECT COUNT(*) FROM scholarships s WHERE s.provider_id = p.provider_id) AS n_sch
     FROM providers p
     JOIN provider_type pt ON pt.provider_type_id = p.provider_type_id
     ORDER BY p.provider_name"
)->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">Providers</h1>
    <p class="text-muted mb-0">Manage provider organizations and merge duplicates.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(APP_URL) ?>/admin/provider_form.php">Add provider</a>
</div>

<?php if ($flash_success): ?>
  <div class="alert alert-success"><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-danger"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Type</th><th>Website</th><th>Scholarships</th><th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($providers as $p): ?>
      <tr>
        <td><?= (int)$p['provider_id'] ?></td>
        <td><?= e($p['provider_name']) ?></td>
        <td><?= e($p['provider_type_name']) ?></td>
        <td class="text-truncate" style="max-width:250px">
          <?php if ($p['website']): ?><a href="<?= e($p['website']) ?>" target="_blank" rel="noopener"><?= e($p['website']) ?></a><?php endif; ?>
        </td>
        <td><?= (int)$p['n_sch'] ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/admin/provider_form.php?id=<?= (int)$p['provider_id'] ?>">Edit</a>
          <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#mergeModal"
                  data-provider-id="<?= (int)$p['provider_id'] ?>" data-provider-name="<?= e($p['provider_name']) ?>">
            Merge
          </button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Merge Modal -->
<div class="modal fade" id="mergeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="provider_merge.php" class="modal-content" id="mergeForm">
      <?= csrf_field() ?>
      <input type="hidden" name="source_id" id="mergeSourceId">
      <div class="modal-header">
        <h5 class="modal-title">Merge provider</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Move all scholarships from <strong id="mergeSourceName"></strong> to another provider, then delete the duplicate.</p>
        <div class="mb-3">
          <label class="form-label" for="target_id">Merge into</label>
          <select class="form-select" id="target_id" name="target_id" required>
            <option value="">-- choose a provider --</option>
            <?php foreach ($providers as $p): ?>
              <option value="<?= (int)$p['provider_id'] ?>"><?= e($p['provider_name']) ?> (<?= (int)$p['n_sch'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Merge and delete</button>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {
  const $modal = $('#mergeModal');
  $modal.on('show.bs.modal', function (ev) {
    const $btn = $(ev.relatedTarget);
    const id = $btn.data('provider-id');
    $('#mergeSourceId').val(id);
    $('#mergeSourceName').text($btn.data('provider-name'));
    $('#target_id').val('');
    $('#target_id option').show().filter('[value="' + id + '"]').hide();
  });
  $('#mergeForm').on('submit', function (ev) {
    if ($('#target_id').val() === '') { ev.preventDefault(); return; }
    return confirm('Move all scholarships and permanently delete the source provider?');
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>