<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/scholarships.php');
    exit;
}
csrf_verify();

$id = filter_input(INPUT_POST, 'scholarship_id', FILTER_VALIDATE_INT) ?: 0;
if ($id < 1) {
    $_SESSION['flash_error'] = 'Select a valid scholarship to delete.';
    header('Location: ' . APP_URL . '/admin/scholarships.php');
    exit;
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM saved_scholarships WHERE scholarship_id = ?')->execute([$id]);
    $delete = $pdo->prepare('DELETE FROM scholarships WHERE scholarship_id = ?');
    $delete->execute([$id]);
    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('The scholarship no longer exists.');
    }
    $pdo->commit();
    $_SESSION['flash_success'] = 'Scholarship deleted.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Scholarship delete failed: ' . $exception->getMessage());
    $_SESSION['flash_error'] = 'The scholarship could not be deleted. Please try again.';
}

header('Location: ' . APP_URL . '/admin/scholarships.php');
exit;
