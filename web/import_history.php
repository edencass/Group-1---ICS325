<?php
// Simple import history viewer for the demo SQLite DB (batch.db in repo root).
// Run with PHP built-in server: php -S localhost:8000 -t web
try {
    $db = new PDO('sqlite:../batch.db');
    $stmt = $db->query('SELECT * FROM import_history ORDER BY created_at DESC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
    $error = $e->getMessage();
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Import History</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
    </style>
</head>
<body>
<h1>Import History</h1>
<?php if (!empty($error)): ?>
    <p style="color: red;">Error connecting to DB: <?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if (empty($rows)): ?>
    <p>No import history found.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Batch ID</th>
            <th>File Name</th>
            <th>Contributor</th>
            <th>Rows Processed</th>
            <th>Rows Failed</th>
            <th>Status</th>
            <th>Error</th>
            <th>Created At</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['id']) ?></td>
                <td><?= htmlspecialchars($r['batch_id']) ?></td>
                <td><?= htmlspecialchars($r['file_name']) ?></td>
                <td><?= htmlspecialchars(($r['contributor_last'] ?? '') . ' ' . ($r['contributor_first'] ?? '')) ?></td>
                <td><?= htmlspecialchars($r['rows_processed']) ?></td>
                <td><?= htmlspecialchars($r['rows_failed']) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
                <td><?= htmlspecialchars($r['error_message']) ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>
