<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'copcsdm');
define('DB_USER', 'root');
define('DB_PASS', '');

// InfinityFree Database configuration (Online)
// define('DB_HOST', 'sql200.infinityfree.com');
// define('DB_NAME', 'if0_41540587_copcsdm');
// define('DB_USER', 'if0_41540587');
// define('DB_PASS', 'BueR8jZHmazQnU');

// Application constants
define('APP_NAME', 'COPC DMS');

// Auto-detect BASE_URL based on the application root (solves sub-directories like /admin)
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$appRoot = str_replace('\\', '/', dirname(__DIR__));
$baseUrl = str_replace($docRoot, '', $appRoot);
define('BASE_URL', $baseUrl);
define('UPLOAD_MAX_SIZE_MB', 20); // 20 MB

// File paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('COPC_DIR', UPLOAD_DIR . '/copc');
define('EXEMPTIONS_DIR', UPLOAD_DIR . '/exemptions');

// Session settings
define('SESSION_TIMEOUT', 3600); // 60 minutes
