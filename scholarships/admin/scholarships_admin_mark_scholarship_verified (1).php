<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/scholarships.php');
    exit;
}

csrf_verify();

$pdo = db();
$id = filter_input(INPUT_POST, 'scholarship_id', FILTER_VALIDATE_INT) ?: 0;

if ($id < 1) {
    $_SESSION['flash_error'] = 'Select a valid scholarship to verify.';
    header('Location: ' . APP_URL . '/admin/scholarships.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'UPDATE scholarships
           SET verification_date = COALESCE(verification_date, CURRENT_DATE)
         WHERE scholarship_id = ?'
    );
    $stmt->execute([$id]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The scholarship could not be found.');
    }

    log_audit_event($pdo, 'verify', 'scholarships', current_user_id());

    $pdo->commit();
    $_SESSION['flash_success'] = 'Scholarship marked as verified.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        log_audit_event($pdo, 'rollback', 'scholarships', current_user_id());
    }

    error_log('Scholarship verification failed: ' . $exception->getMessage());
    $_SESSION['flash_error'] = 'The scholarship could not be marked as verified.';
}

header('Location: ' . APP_URL . '/admin/scholarships.php');
exit;