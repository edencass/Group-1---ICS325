<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$page_title = 'Stage Import';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-3">Stage scholarship files</h1>
<p class="text-muted">
  Files are parsed in your browser with SheetJS, then cleaned and validated on the server.
  Nothing touches the live <code>scholarships</code> table until you review the report and click <em>Commit</em>.
</p>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<form id="importForm" method="post" action="<?= e(APP_URL) ?>/admin/import_stage_handler.php">
  <?= csrf_field() ?>
  <input type="hidden" name="payload" id="payload">
  <div class="mb-3">
    <label class="form-label" for="files">Scholarship files (.xlsx or .csv)</label>
    <input class="form-control" type="file" id="files" name="files" multiple
           accept=".xlsx, .csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv" required>
    <div class="form-text">Max <?= (int)(MAX_UPLOAD_BYTES / 1024 / 1024) ?> MB per file.</div>
  </div>
  <button class="btn btn-primary" type="submit">Stage files</button>
  <a class="btn btn-link" href="<?= e(APP_URL) ?>/admin/import_report.php">View pending batches</a>
</form>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
document.getElementById('importForm').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const input = document.getElementById('files');
  if (!input.files.length) { alert('Choose at least one file.'); return; }

  const payload = [];
  try {
    for (const f of input.files) {
      const buf  = await f.arrayBuffer();
      // Added raw: true to prevent CSV data from being mangled by SheetJS
      const wb   = XLSX.read(buf, { type: 'array', cellDates: true, raw: true });
      const ws   = wb.Sheets[wb.SheetNames[0]];
      const rows = XLSX.utils.sheet_to_json(ws, { defval: '', raw: false, dateNF: 'yyyy-mm-dd' });
      if (!rows.length) continue;
      payload.push({ name: f.name, rows });
    }
  } catch (e) {
    alert('Could not read one of the files: ' + e.message);
    return;
  }
  if (!payload.length) { alert('No data rows found in the selected files.'); return; }

  document.getElementById('payload').value = JSON.stringify(payload);
  ev.target.submit();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>