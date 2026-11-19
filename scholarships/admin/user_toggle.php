<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: users.php'); exit; }
csrf_verify();

$id = (int)($_POST['user_id'] ?? 0);
if ($id < 1) { header('Location: users.php'); exit; }

$pdo = db();
$stmt = $pdo->prepare('SELECT role_id, is_active FROM users WHERE user_id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { header('Location: users.php'); exit; }

$superAdminRoleId = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Super-admin'")->fetchColumn();
$isSuperAdmin = ((int)$user['role_id'] === (int)$superAdminRoleId);

if ((int)$user['is_active'] === 1) {
    if ($isSuperAdmin) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1");
        $countStmt->execute([$superAdminRoleId]);
        if ((int)$countStmt->fetchColumn() <= 1) {
            $_SESSION['flash_error'] = 'Cannot deactivate the last active Super-admin.';
            header('Location: users.php'); exit;
        }
    }
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$id]);
    $_SESSION['flash_success'] = 'User deactivated.';
} else {
    $pdo->prepare('UPDATE users SET is_active = 1 WHERE user_id = ?')->execute([$id]);
    $_SESSION['flash_success'] = 'User activated.';
}

header('Location: users.php');
exit;