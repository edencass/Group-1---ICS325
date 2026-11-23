<?php if (!$rows): ?>
  <div class="alert alert-info">No scholarships match those filters yet. Try uploading some data via the Admin Import page!</div>
<?php else: ?>
  <!-- Mobile View: Cards -->
  <div class="d-md-none">
    <?php foreach ($rows as $r): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h5 class="card-title">
            <a href="scholarship_detail.php?id=<?= (int)$r['scholarship_id'] ?>"><?= e($r['title']) ?></a>
          </h5>
          <p class="mb-2"><strong>Provider:</strong> <?= e($r['provider_name']) ?></p>
          <p class="mb-2"><strong>State:</strong> <?= $r['is_national'] ? 'National' : e($r['state_name'] ?? 'State/Local') ?></p>
          <p class="mb-2"><strong>Award:</strong>
            <?= $r['amount'] !== null ? '$' . number_format((float)$r['amount']) : 'Varies' ?>
          </p>
          <p class="mb-2"><strong>Deadline:</strong>
            <?= $r['deadline'] !== null ? e(format_date_us((string)$r['deadline'])) : 'Rolling' ?>
          </p>
          <p class="mb-2"><strong>Official URL:</strong>
            <?php if (!empty($r['official_url'])): ?>
              <a href="<?= e($r['official_url']) ?>" target="_blank" rel="noopener"><?= e($r['official_url']) ?></a>
            <?php else: ?>
              Not provided
            <?php endif; ?>
          </p>
          <p class="mb-0"><strong>Verification date:</strong>
            <?= !empty($r['verification_date']) ? e(format_date_us((string)$r['verification_date'])) : 'Unverified' ?>
          </p>
          
          <!-- ADDED: Save Button for Mobile -->
          <div class="mt-3">
            <?php if (is_logged_in()): ?>
              <button class="btn btn-sm btn-outline-primary save-btn w-100" data-scholarship-id="<?= (int)$r['scholarship_id'] ?>">Save Scholarship</button>
            <?php else: ?>
              <a href="login.php" class="btn btn-sm btn-outline-secondary w-100">Log in to save</a>
            <?php endif; ?>
          </div>
          
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Desktop View: Table -->
  <div class="d-none d-md-block">
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Provider</th>
            <th>State</th>
            <th>Award</th>
            <th>Deadline</th>
            <th>Official URL</th>
            <th>Verification date</th>
            <th>Save</th> <!-- ADDED -->
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <a href="scholarship_detail.php?id=<?= (int)$r['scholarship_id'] ?>"><strong><?= e($r['title']) ?></strong></a>
            </td>
            <td><?= e($r['provider_name']) ?></td>
            <td><?= $r['is_national'] ? 'National' : e($r['state_name'] ?? 'State/Local') ?></td>
            <td><?= $r['amount'] !== null ? '$' . number_format((float)$r['amount']) : 'Varies' ?></td>
            <td><?= $r['deadline'] !== null ? e(format_date_us((string)$r['deadline'])) : 'Rolling' ?></td>
            <td>
              <?php if (!empty($r['official_url'])): ?>
                <a href="<?= e($r['official_url']) ?>" target="_blank" rel="noopener">Official site</a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td><?= !empty($r['verification_date']) ? e(format_date_us((string)$r['verification_date'])) : 'Unverified' ?></td>
            
            <!-- ADDED: Save Button for Desktop -->
            <td>
              <?php if (is_logged_in()): ?>
                <button class="btn btn-sm btn-outline-primary save-btn" data-scholarship-id="<?= (int)$r['scholarship_id'] ?>">Save</button>
              <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-outline-secondary">Log in</a>
              <?php endif; ?>
            </td>
            
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- ADDED: JavaScript for AJAX Save -->
<script>
$(document).ready(function() {
  // Use event delegation on the document body so it works for AJAX-loaded content
  $(document).on('click', '.save-btn', function() {
    const btn = $(this);
    const schId = btn.data('scholarship-id');
    
    // Change button state to show it's working
    btn.prop('disabled', true).text('Saving...');
    
    $.ajax({
      url: 'toggle_save.php',
      type: 'POST',
      data: {
        scholarship_id: schId,
        action: 'save',
        csrf_token: '<?= csrf_token() ?>'
      },
      dataType: 'json',
      success: function(data) {
        if (data.success) {
          btn.text('Saved!').removeClass('btn-outline-primary').addClass('btn-success');
        } else {
          alert('Error: ' + data.error);
          btn.prop('disabled', false).text('Save');
        }
      },
      error: function() {
        alert('Network error. Please check the console.');
        btn.prop('disabled', false).text('Save');
      }
    });
  });
});
</script>
