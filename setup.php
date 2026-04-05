<?php
// Run this file once to set up the database and required folders

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Connect without DB selected to create it if it doesn't exist
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read and execute schema
    $sql = file_get_contents(__DIR__ . '/db/schema.sql');
    $pdo->exec($sql);
    
    echo "Database setup completed successfully.<br>";

} catch (PDOException $e) {
    die("DB Setup failed: " . $e->getMessage());
}

// Create needed directories
$dirs = [
    __DIR__ . '/uploads/copc',
    __DIR__ . '/uploads/exemptions'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "Created directory: $dir<br>";
        } else {
            echo "Failed to create directory: $dir<br>";
        }
    } else {
        echo "Directory already exists: $dir<br>";
    }
}

echo "Setup complete. You can now delete this file or navigate to index.php.";
