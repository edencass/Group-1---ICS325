<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in to save.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';
$scholarshipId = (int)($_POST['scholarship_id'] ?? 0);
$savedId = (int)($_POST['saved_id'] ?? 0);
$userId = current_user_id();
$pdo = db();

try {
    if ($action === 'save' && $scholarshipId > 0) {
        // Check if already saved
        $check = $pdo->prepare('SELECT saved_id FROM saved_scholarships WHERE user_id = ? AND scholarship_id = ?');
        $check->execute([$userId, $scholarshipId]);
        $existing = $check->fetchColumn();

        if ($existing) {
            echo json_encode(['success' => true, 'action' => 'already_saved']);
        } else {
            $stmt = $pdo->prepare('INSERT INTO saved_scholarships (user_id, scholarship_id, status) VALUES (?, ?, "Interested")');
            $stmt->execute([$userId, $scholarshipId]);
            echo json_encode(['success' => true, 'action' => 'saved']);
        }
    } elseif ($action === 'unsave' && $savedId > 0) {
        // Verify ownership before deleting
        $stmt = $pdo->prepare('DELETE FROM saved_scholarships WHERE saved_id = ? AND user_id = ?');
        $stmt->execute([$savedId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'action' => 'unsaved']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Record not found or permission denied.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action or IDs.']);
    }
} catch (Throwable $e) {
    error_log('Toggle save error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}