<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarships_includes_audit (1).php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/reference_data.php');
    exit;
}

csrf_verify();

$entity = trim((string)($_POST['entity'] ?? ''));
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

$entityMap = [
    'states' => ['table' => 'states', 'id_col' => 'state_id', 'name_col' => 'state_name'],
    'provider_type' => ['table' => 'provider_type', 'id_col' => 'provider_type_id', 'name_col' => 'provider_type_name'],
    'categories' => ['table' => 'categories', 'id_col' => 'category_id', 'name_col' => 'category_name'],
    'fields_of_study' => ['table' => 'fields_of_study', 'id_col' => 'field_id', 'name_col' => 'field_name'],
    'grade_levels' => ['table' => 'grade_levels', 'id_col' => 'grade_level_id', 'name_col' => 'grade_name'],
    'national_themes' => ['table' => 'national_themes', 'id_col' => 'theme_id', 'name_col' => 'theme_name'],
];

if (!isset($entityMap[$entity])) {
    http_response_code(404);
    exit('Invalid reference data group.');
}

$spec = $entityMap[$entity];
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM ' . $spec['table'] . ' WHERE ' . $spec['id_col'] . ' = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Record not found.');
}

try {
    $pdo->beginTransaction();

    $pdo->prepare('DELETE FROM ' . $spec['table'] . ' WHERE ' . $spec['id_col'] . ' = ?')->execute([$id]);

    log_audit_event($pdo, 'delete_reference_data', $spec['table'], current_user_id());

    $pdo->commit();
    $_SESSION['flash_success'] = ucfirst($spec['name_col']) . ' deleted.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Reference data delete failed: ' . $exception->getMessage());
    $_SESSION['flash_error'] = 'The reference value could not be deleted.';
}

header('Location: ' . APP_URL . '/admin/reference_data.php?entity=' . urlencode($entity));
exit;
