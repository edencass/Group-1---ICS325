<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: import_report.php'); exit; }
csrf_verify();

$pdo = db();
$batchId = (int)($_POST['batch_id'] ?? 0);
if (!$batchId) { header('Location: import_report.php'); exit; }

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT row_id, raw_data FROM import_staging_rows WHERE batch_id = ? AND validation_status IN ('Ready','Warning') ORDER BY row_id");
    $stmt->execute([$batchId]);
    $rows = $stmt->fetchAll();

    if (!$rows) throw new RuntimeException('Nothing to commit — no Ready or Warning rows.');

    $findType = $pdo->prepare('SELECT provider_type_id FROM provider_type WHERE provider_type_name = ? LIMIT 1');
    $addType  = $pdo->prepare('INSERT INTO provider_type (provider_type_name) VALUES (?)');
    $findProv = $pdo->prepare('SELECT provider_id FROM providers WHERE provider_name = ? LIMIT 1');
    $addProv  = $pdo->prepare('INSERT INTO providers (provider_type_id, provider_name, website) VALUES (?,?,?)');
    
    $addSch   = $pdo->prepare('INSERT INTO scholarships
        (provider_id, state_id, state_code, title, description, amount, deadline, official_url, scope, is_national, open_date, verification_date, validation_messages)
        VALUES (:pid, :sid, :scode, :t, :d, :a, :dl, :url, :scope, :isnat, :od, :vd, :vmsg)');
        
    $updStage = $pdo->prepare("UPDATE import_staging_rows SET validation_status = 'imported' WHERE row_id = ?");

    $findGrade = $pdo->prepare('SELECT grade_level_id FROM grade_levels WHERE grade_name = ? LIMIT 1');
    $addSchGrade = $pdo->prepare('INSERT INTO scholarship_grades (scholarship_id, grade_level_id) VALUES (?,?)');

    $findField = $pdo->prepare('SELECT field_id FROM fields_of_study WHERE field_name = ? LIMIT 1');
    $addSchField = $pdo->prepare('INSERT INTO scholarship_fields (scholarship_id, field_id) VALUES (?,?)');
    
    $findCat = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
    $addSchCat = $pdo->prepare('INSERT INTO scholarship_categories (scholarship_id, category_id) VALUES (?,?)');


    foreach ($rows as $r) {
        $payload = json_decode((string)$r['raw_data'], true);
        $c = $payload['_cleaned'] ?? null;
        if (!$c) continue;

        $findType->execute([$c['provider_type']]);
        $typeId = $findType->fetchColumn();
        if ($typeId === false) {
            $addType->execute([$c['provider_type']]);
            $typeId = (int)$pdo->lastInsertId();
        }

        $findProv->execute([$c['provider_name']]);
        $provId = $findProv->fetchColumn();
        if ($provId === false) {
            $addProv->execute([(int)$typeId, $c['provider_name'], $c['official_url'] ?: null]);
            $provId = (int)$pdo->lastInsertId();
        }

        $descriptionBlob = json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $vMsg = json_encode($payload['_validation_messages'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // THIS IS THE FIX: Use state_display (which is "GA") instead of the full name
        $stateCodeToSave = $c['state_display'] ?? null;
        if ($stateCodeToSave === 'NA') $stateCodeToSave = null; // If it's National, keep it null

        $addSch->execute([
            'pid' => (int)$provId,
            'sid' => $c['state_id'] ? (int)$c['state_id'] : null,
            'scode' => $stateCodeToSave, // Saves "GA" or "FL"
            't'   => mb_substr($c['name'], 0, 255),
            'd'   => $descriptionBlob,
            'a'   => $c['amount'],
            'dl'  => $c['deadline'],
            'url' => $c['official_url'],
            'scope' => $c['scope'],
            'isnat' => (int)$c['is_national'],
            'od' => $c['open_date'],
            'vd' => $c['verification_date'],
            'vmsg' => $vMsg
        ]);
        
        $newSchId = (int)$pdo->lastInsertId();

        foreach (array_unique($c['grade_ids'] ?? []) as $g) {
            $findGrade->execute(['Grade ' . (int)$g]);
            $gid = $findGrade->fetchColumn();
            if ($gid !== false) $addSchGrade->execute([$newSchId, $gid]);
        }

        $fields = array_filter(array_map('trim', explode(';', $c['field_of_interest'] ?? '')));
        foreach ($fields as $f) {
            $findField->execute([$f]);
            $fid = $findField->fetchColumn();
            if ($fid !== false) $addSchField->execute([$newSchId, $fid]);
        }

        $cats = array_filter(array_map('trim', explode(';', $c['category'] ?? '')));
        foreach ($cats as $cat) {
            $findCat->execute([$cat]);
            $cid = $findCat->fetchColumn();
            if ($cid !== false) $addSchCat->execute([$newSchId, $cid]);
        }

        $updStage->execute([(int)$r['row_id']]);
    }

    $pdo->prepare("UPDATE import_batches SET status = 'completed' WHERE batch_id = ?")->execute([$batchId]);
    $pdo->commit();
    unset($_SESSION['flash_error']);
    header('Location: import_report.php?batches=' . $batchId);
    exit;

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'Commit failed: ' . $ex->getMessage();
    header('Location: import_report.php?batches=' . $batchId);
    exit;
}
