<?php
/**
 * Migration: Add copc_no column to copc_documents
 * Run this once via browser or CLI: php migrate_add_copc_no.php
 *
 * Safe to run multiple times — checks if column already exists first.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();

    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM copc_documents LIKE 'copc_no'");
    if ($check->rowCount() > 0) {
        echo "✅ Column 'copc_no' already exists. Nothing to do.\n";
        exit;
    }

    // Add after 'category' column
    $pdo->exec("ALTER TABLE copc_documents ADD COLUMN copc_no VARCHAR(100) NULL DEFAULT NULL COMMENT 'COPC certificate/resolution number (e.g. COPC No. 30 S. 2021)' AFTER category");

    echo "✅ Column 'copc_no' added successfully to copc_documents.\n";

} catch (\Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
