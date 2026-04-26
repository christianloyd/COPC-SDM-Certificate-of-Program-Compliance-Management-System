<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    die("CSRF validation failed.");
}

$recordIds = [];

if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $recordIds = array_map('intval', $_POST['ids']);
} elseif (isset($_POST['id'])) {
    $recordIds = [(int) $_POST['id']];
}

$recordIds = array_values(array_filter(array_unique($recordIds), static fn ($id) => $id > 0));

if (empty($recordIds)) {
    die("Invalid record selection.");
}

try {
    $pdo = getDBConnection();
    $placeholders = implode(',', array_fill(0, count($recordIds), '?'));

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, file_path FROM copc_documents WHERE id IN ($placeholders)");
    $stmt->execute($recordIds);
    $documents = $stmt->fetchAll();

    if (empty($documents)) {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/records.php');
        exit;
    }

    $filePaths = [];
    foreach ($documents as $document) {
        $filePath = trim((string) ($document['file_path'] ?? ''));
        if ($filePath !== '') {
            $filePaths[$filePath] = true;
        }
    }

    $deleteStmt = $pdo->prepare("DELETE FROM copc_documents WHERE id IN ($placeholders)");
    $deleteStmt->execute($recordIds);
    $deletedCount = $deleteStmt->rowCount();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM copc_documents WHERE file_path = ?");
    foreach (array_keys($filePaths) as $filePath) {
        $countStmt->execute([$filePath]);
        if ((int) $countStmt->fetchColumn() === 0) {
            $fullPath = UPLOAD_DIR . '/' . trim($filePath, '/');
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    $pdo->commit();
    setFlashMessage(
        'success',
        $deletedCount === 1
            ? 'Record deleted successfully.'
            : $deletedCount . ' records deleted successfully.',
        'Record Vault Updated'
    );
    
    header('Location: ' . BASE_URL . '/admin/records.php');
    exit;
    
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlashMessage('error', $e->getMessage(), 'Delete Failed');
    header('Location: ' . BASE_URL . '/admin/records.php');
    exit;
}
