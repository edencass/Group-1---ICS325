<?php if (!$rows): ?>
  <div class="alert alert-info">No scholarships match those filters yet. Try uploading some data via the Admin Import page!</div>
<?php else: ?>
  <div class="d-md-none">
    <?php foreach ($rows as $r):
      $d = json_decode((string)$r['description'], true) ?: [];
      $url = $r['official_url'] ?? $d['official_url'] ?? '';
      $verification = $r['verification_date'] ?? null;
    ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h5 class="card-title"><?= e($r['title']) ?></h5>
          <p class="mb-2"><strong>Provider:</strong> <?= e($r['provider_name']) ?></p>
          <p class="mb-2"><strong>State:</strong> <?= e($r['state_code'] ?: 'National') ?></p>
          <p class="mb-2"><strong>Award:</strong>
            <?= $r['amount'] !== null ? '$' . number_format((float)$r['amount']) : 'Varies' ?>
          </p>
          <p class="mb-2"><strong>Deadline:</strong>
            <?= $r['deadline'] !== null ? e(format_date_us((string)$r['deadline'])) : 'Rolling' ?>
          </p>
          <p class="mb-2"><strong>Official URL:</strong>
            <?php if ($url): ?>
              <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?></a>
            <?php else: ?>
              Not provided
            <?php endif; ?>
          </p>
          <p class="mb-0"><strong>Verification date:</strong>
            <?= $verification ? e(format_date_us((string)$verification)) : 'Unverified' ?>
          </p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

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
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $d = json_decode((string)$r['description'], true) ?: [];
          $url = $r['official_url'] ?? $d['official_url'] ?? '';
          $verification = $r['verification_date'] ?? null;
        ?>
          <tr>
            <td><?= e($r['title']) ?></td>
            <td><?= e($r['provider_name']) ?></td>
            <td><?= e($r['state_code'] ?: 'National') ?></td>
            <td><?= $r['amount'] !== null ? '$' . number_format((float)$r['amount']) : 'Varies' ?></td>
            <td><?= e($r['deadline'] !== null ? format_date_us((string)$r['deadline']) : 'Rolling') ?></td>
            <td>
              <?php if ($url): ?>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener">Official site</a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td><?= $verification ? e(format_date_us((string)$verification)) : 'Unverified' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>