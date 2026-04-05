<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDBConnection();
    
    // Check columns
    $stmt = $pdo->query("DESCRIBE copc_documents");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('region', $columns)) {
        $pdo->exec("ALTER TABLE copc_documents ADD COLUMN region VARCHAR(100) DEFAULT NULL AFTER program");
        echo "Added 'region' column.\n";
    }
    
    if (!in_array('status', $columns)) {
        $pdo->exec("ALTER TABLE copc_documents ADD COLUMN status ENUM('OLD', 'NEW') DEFAULT 'NEW' AFTER date_approved");
        echo "Added 'status' column.\n";
    }

    if (!in_array('student_list', $columns)) {
        $pdo->exec("ALTER TABLE copc_documents ADD COLUMN student_list LONGTEXT DEFAULT NULL AFTER status");
        echo "Added 'student_list' column.\n";
    }

    // Update FULLTEXT index to include region and student_list
    try {
        $pdo->exec("ALTER TABLE copc_documents DROP INDEX ft_search");
    } catch (Exception $e) {}
    
    $pdo->exec("ALTER TABLE copc_documents ADD FULLTEXT INDEX ft_search (school_name, program, region, extracted_text, student_list)");
    echo "Updated FULLTEXT index with student_list.\n";

    echo "Migration completed successfully.";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
