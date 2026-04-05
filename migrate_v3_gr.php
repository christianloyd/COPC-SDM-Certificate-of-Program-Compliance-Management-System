<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDBConnection();
    $pdo->exec("ALTER TABLE copc_documents MODIFY COLUMN category ENUM('COPC', 'COPC Exemption', 'HEI List', 'GR') NOT NULL");
    echo "Updated category enum to include GR.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
