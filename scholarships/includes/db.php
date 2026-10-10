<?php
/**
 * db.php — single PDO connection, reused everywhere.
 * Every query in the app must use prepared statements.
 */

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
            }
            error_log('DB connection failed: ' . $e->getMessage());
            die('A database error occurred. Please try again later.');
        }
    }

    return $pdo;
}