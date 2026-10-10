<?php
/**
 * config.example.php
 * Copy this file to config.php and fill in your real values. put this into a file called config.php
 * config.php must never be committed.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'scholarships');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
define('APP_NAME', 'Pathways to Scholarships');
define('APP_URL',  'http://localhost/scholarships');    //change this if needed

// Development mode: shows the red banner and relaxes auth 
define('APP_DEV_MODE', true);

// Set to false in production
define('APP_DEBUG', true);

// ---- Session ----
define('SESSION_TIMEOUT', 1800); // 30 minutes

// ---- Uploads ----
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB