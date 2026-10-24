<?php
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = db();
    echo "Connected OK\n";
    echo "Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
    echo "states: " . $pdo->query('SELECT COUNT(*) FROM states')->fetchColumn() . "\n";
    echo "fields: " . $pdo->query('SELECT COUNT(*) FROM fields_of_study')->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}