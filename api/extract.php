<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/extraction.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized Access.']));
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Invalid method']));
}

// CSRF Validate
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    die(json_encode(['success' => false, 'error' => 'CSRF Token Invalid.']));
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    die(json_encode(['success' => false, 'error' => 'Missing DB ID']));
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT file_path, file_type, entry_type, category, student_list FROM copc_documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        throw new Exception("Record not found.");
    }
    
    if ($doc['entry_type'] === 'manual' || empty($doc['file_path'])) {
        throw new Exception("Cannot extract text from a manual entry without a file.");
    }

    $absolutePath = UPLOAD_DIR . '/' . trim($doc['file_path'], '/');
    if (!file_exists($absolutePath)) {
        throw new Exception("File not found on server.");
    }

    $ext = strtolower($doc['file_type']);
    $extractedText = extractText($absolutePath, $ext);

    if ($extractedText === null || $extractedText === '') {
        throw new Exception("Extraction returned empty result or failed.");
    }

    $studentList = trim((string) ($doc['student_list'] ?? ''));
    if ($doc['category'] === 'COPC Exemption' && $studentList === '') {
        $studentList = extractExemptionStudentList($extractedText);
    }

    $upd = $pdo->prepare("UPDATE copc_documents SET extracted_text = ?, student_list = ? WHERE id = ?");
    $upd->execute([$extractedText, $studentList, $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Text extracted and updated successfully.'
    ]);

} catch (\Exception $e) {
    error_log("Manual Extraction triggered error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
