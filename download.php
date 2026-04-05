<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid request");
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT file_path, file_name, file_type, category FROM copc_documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if (!$doc || empty($doc['file_path'])) {
        die("File not found or record is a manual entry without a file.");
    }

    $filepath = UPLOAD_DIR . '/' . trim($doc['file_path'], '/');

    if (!file_exists($filepath)) {
        die("File is missing from storage.");
    }

    // Determine mime
    $ext = strtolower($doc['file_type']);
    $mimes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    $mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

    // Serve file
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . addslashes($doc['file_name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    
    // Clear output buffer and read file
    while (ob_get_level()) {
        ob_end_clean();
    }
    readfile($filepath);
    exit;

} catch (Exception $e) {
    die("A system error occurred.");
}
