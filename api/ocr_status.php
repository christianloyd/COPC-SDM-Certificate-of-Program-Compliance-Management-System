<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/extraction.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$tesseractBinary = getTesseractBinary();
$pdftoppmBinary = getPdftoppmBinary();
$diagnostic = getPdfOcrDependencyError();

echo json_encode([
    'success' => true,
    'ocr_ready' => $diagnostic === null,
    'diagnostic' => $diagnostic,
    'tools' => [
        'tesseract' => [
            'available' => $tesseractBinary !== null,
            'path' => $tesseractBinary,
        ],
        'pdftoppm' => [
            'available' => $pdftoppmBinary !== null,
            'path' => $pdftoppmBinary,
        ],
    ],
]);
