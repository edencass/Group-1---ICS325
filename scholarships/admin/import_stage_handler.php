<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';

require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: import_stage.php'); exit;
}
csrf_verify();

$rawPayload = (string)($_POST['payload'] ?? '');
$payload    = json_decode($rawPayload, true);
if (!is_array($payload) || !$payload) {
    $_SESSION['flash_error'] = 'No file data received.';
    header('Location: import_stage.php'); exit;
}

$pdo    = db();
$states = $pdo->query('SELECT state_id, state_name, state_code FROM states')
              ->fetchAll(PDO::FETCH_ASSOC);

$stagedBatchIds = [];
$seenInBatch    = [];

foreach ($payload as $file) {
    $name = (string)($file['name'] ?? '');
    $rows = $file['rows'] ?? [];
    if (!is_array($rows) || !$rows) continue;

    // --- create a batch record ---
    // Extract LastName_FirstName from the file name
    $base = pathinfo($name, PATHINFO_FILENAME);
    $parts = preg_split('/[_\-\s]+/', $base);
    $contributor = count($parts) >= 2 ? $parts[0] . '_' . $parts[1] : 'Unknown';

    // Save the contributor in the status field (since we don't have a dedicated column)
    $pdo->prepare('INSERT INTO import_batches (user_id, status) VALUES (?, ?)')
        ->execute([current_user_id(), 'staged: ' . $contributor]);

    $batchId = (int)$pdo->lastInsertId();
    $stagedBatchIds[] = $batchId;

    $ins = $pdo->prepare(
      'INSERT INTO import_staging_rows (batch_id, raw_data, validation_status)
       VALUES (:b, :r, :s)'
    );

    foreach ($rows as $idx => $row) {
        $rowNumber = $idx + 2;   // +1 header, +1 for 1-based
        $row = array_filter(
            $row,
            static fn($key): bool => strcasecmp(trim((string)$key), 'Description') !== 0,
            ARRAY_FILTER_USE_KEY
        );

        // ---- 1. clean + validate (this is where Warning/Error originate) ----
        $res = clean_scholarship_row($row, $states);

        $status = 'Ready';
        $reason = '';

        if ($res['has_error']) {
            // FR-IMP-07: Error classification
            $status = 'Error';
            $reason = implode(' ', $res['issues']);
        } else {
            $nurl = $res['normalized_url'];
            $existing = null;

            // ---- 2. FR-IMP-06 scope 1 & 2: within file + across files ----
            if ($nurl && isset($seenInBatch[$nurl])) {
                $status = 'Duplicate';
                $reason = 'Duplicate of ' . $seenInBatch[$nurl] . ' (same normalized URL).';
            } else {
                // ---- 3. FR-IMP-06 scope 3: against records already loaded ----
                if ($nurl) {
                    // Pre-filter with LIKE (fast), then exact compare in PHP
                    // because scholarships.description holds JSON, not a real column.
                    $like = '%"normalized_url":"' . $nurl . '"%';
                    $stmt = $pdo->prepare(
                      "SELECT scholarship_id, title, description
                         FROM scholarships
                        WHERE description LIKE :like
                        LIMIT 25"
                    );
                    $stmt->execute(['like' => $like]);
                    foreach ($stmt->fetchAll() as $hit) {
                        $hitJson = json_decode((string)$hit['description'], true);
                        if (is_array($hitJson)
                            && ($hitJson['normalized_url'] ?? null) === $nurl) {
                            $existing = $hit;
                            break;
                        }
                    }
                }

                if ($existing) {
                    $status = 'Duplicate';
                    $reason = 'Already in database as scholarship #' . (int)$existing['scholarship_id']
                            . ' ("' . $existing['title'] . '").';
                } elseif ($res['issues'] !== []) {
                    // ---- 4. FR-IMP-07: Warning classification ----
                    $status = 'Warning';
                    $reason = implode(' ', $res['issues']);
                }
                // else: $status stays 'Ready'

                // Record URL in batch map so later rows in later files
                // can detect duplicates against this row (scope 2).
                if ($nurl && !$existing) {
                    $seenInBatch[$nurl] = $name . ' row ' . $rowNumber;
                }
            }
        }

        // ---- 5. persist the staged row with its status + reason ----
        $payloadRow = $row;
        $payloadRow['_cleaned'] = $res['cleaned'];
        $payloadRow['_reason']  = $reason;
        $payloadRow['_validation_messages'] = $res['issues'];
        $payloadRow['_row']     = $rowNumber;
        $payloadRow['_file']    = $name;

        $ins->execute([
            'b' => $batchId,
            'r' => json_encode($payloadRow, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            's' => $status,   // 'Ready' | 'Warning' | 'Duplicate' | 'Error'
        ]);
    }
}

header('Location: import_report.php?batches=' . urlencode(implode(',', $stagedBatchIds)));
exit;