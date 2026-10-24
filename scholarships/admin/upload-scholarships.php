<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

header('Location: ' . APP_URL . '/admin/import_stage.php');
exit;
