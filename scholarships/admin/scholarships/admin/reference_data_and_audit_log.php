<?php
/**
 * Shared entry point for the Reference Data and Audit Log admin screens.
 * Use ?view=reference or ?view=audit to select a screen.
 */
$view = (string)($_GET['view'] ?? 'reference');

if ($view === 'audit') {
    require __DIR__ . '/scholarships_admin_audit_log.php';
    exit;
}

// Default to reference data so the bare URL remains useful.
require __DIR__ . '/scholarships_admin_reference_data_form.php';
