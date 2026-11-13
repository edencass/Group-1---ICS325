<?php
require_once __DIR__ . '/../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the log out button to end your session.');
}

csrf_verify();
logout_user();
header('Location: ' . APP_URL . '/public/index.php', true, 303);
exit;
