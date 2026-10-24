<?php
// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'scholarship_finder');
define('DB_USER', 'root');
define('DB_PASS', '');          // your local MySQL password
define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
define('APP_NAME', 'Pathways to Scholarships');
define('APP_URL',  'http://localhost/scholarships');  // match your XAMPP folder

// ---- App mode ----
define('APP_DEV_MODE', true);
define('APP_DEBUG', true);

// ---- Session ----
define('SESSION_TIMEOUT', 1800);

// ---- Uploads ----
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);