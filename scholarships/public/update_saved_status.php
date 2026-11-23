<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method.']);
    exit;
}

csrf_verify();

$savedId = (int)($_POST['saved_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$userId = current_user_id();

$validStatuses = ['Interested', 'In Progress', 'Submitted', 'Awarded', 'Not Awarded'];

if ($savedId <= 0 || !in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$pdo = db();
try {
    // Only update if the saved record belongs to the current user
    $stmt = $pdo->prepare('UPDATE saved_scholarships SET status = ? WHERE saved_id = ? AND user_id = ?');
    $stmt->execute([$status, $savedId, $userId]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Update status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}