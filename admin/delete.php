<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    die("CSRF validation failed.");
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    die("Invalid ID");
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT file_path FROM copc_documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        $delStmt = $pdo->prepare("DELETE FROM copc_documents WHERE id = ?");
        $delStmt->execute([$id]);

        if (!empty($doc['file_path'])) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM copc_documents WHERE file_path = ?");
            $countStmt->execute([$doc['file_path']]);
            $remainingLinks = (int) $countStmt->fetchColumn();

            if ($remainingLinks === 0) {
                $fullPath = UPLOAD_DIR . '/' . trim($doc['file_path'], '/');
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        }
    }
    
    header('Location: ' . BASE_URL . '/admin/records.php');
    exit;
    
} catch (\Exception $e) {
    die("Failed to delete record: " . $e->getMessage());
}
