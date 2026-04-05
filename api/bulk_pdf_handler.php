<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/extraction.php';

// Must be admin
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

$category = trim($_POST['category'] ?? 'COPC');
$status = trim($_POST['status'] ?? 'NEW');
$globalRegion = trim($_POST['global_region'] ?? '');
$uploadedBy = $_SESSION['username'] ?? 'admin';

try {
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed or missing.");
    }
    
    $file = $_FILES['document_file'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($fileExt !== 'pdf') {
        throw new Exception("Only PDF files are supported for bulk document extraction.");
    }

    // Directory routing
    $targetDir = ($category === 'COPC Exemption') ? EXEMPTIONS_DIR : COPC_DIR;
    $dbDirPrefix = ($category === 'COPC Exemption') ? 'exemptions/' : 'copc/';

    // Generate safe filename
    $safeName = getSafeFileName($file['name'], $fileExt);
    $absolutePath = $targetDir . '/' . $safeName;
    $dbPath = $dbDirPrefix . $safeName;

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new Exception("Failed to move uploaded file.");
    }

    // 1. Extraction
    $extractedText = extractText($absolutePath, 'pdf');
    if (!$extractedText) {
        $details = getLastExtractionError();
        $message = 'Could not extract text from PDF (OCR/Parser failed).';
        if ($details) {
            $message .= ' ' . $details;
        }
        throw new Exception($message);
    }

    // 2. Metadata Parsing
    $records = extractBulkPdfRecords($extractedText, $file['name'], $category, $globalRegion);
    if (empty($records)) {
        $metadata = parseMetadataFromText($extractedText, $file['name']);

        if ($globalRegion !== '' && empty($metadata['region'])) {
            $metadata['region'] = $globalRegion;
        }

        $records = [[
            'school_name' => $metadata['school_name'] ?: 'Unknown Institution (' . $file['name'] . ')',
            'program' => $metadata['program'] ?: 'Unknown Program',
            'region' => $metadata['region'] ?: ($globalRegion ?: 'UNKNOWN'),
            'date_approved' => $metadata['date_approved'],
        ]];
    }

    $primaryRecord = $records[0];

    // 3. Exemption Student List Extraction if needed
    $studentNames = [];
    $studentList = '';
    if ($category === 'COPC Exemption') {
        $studentNames = extractExemptionStudentNames($extractedText);
        if (!empty($studentNames)) {
            $studentList = implode("\n", $studentNames);
        }
    }

    // 4. Persistence
    $pdo = getDBConnection();
    $sql = "INSERT INTO copc_documents 
            (school_name, program, region, category, date_approved, status, student_list, file_path, file_type, file_name, file_size_kb, extracted_text, entry_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pdf', ?, ?, ?, 'upload', ?)";

    $stmt = $pdo->prepare($sql);
    $fileSizeKb = ceil($file['size'] / 1024);
    $originalName = mb_substr($file['name'], 0, 250);

    $pdo->beginTransaction();
    if ($category === 'COPC Exemption' && !empty($studentNames)) {
        $school = $primaryRecord['school_name'];
        $program = $primaryRecord['program'];
        $region = $primaryRecord['region'];
        $dateApproved = $primaryRecord['date_approved'];
        foreach ($studentNames as $studentName) {
            $stmt->execute([ $school, $program, $region, $category, $dateApproved, $status, $studentName, $dbPath, $originalName, $fileSizeKb, $extractedText, $uploadedBy ]);
        }
    } else {
        foreach ($records as $record) {
            $stmt->execute([
                $record['school_name'],
                $record['program'],
                $record['region'],
                $category,
                $record['date_approved'],
                $status,
                $studentList,
                $dbPath,
                $originalName,
                $fileSizeKb,
                $extractedText,
                $uploadedBy
            ]);
        }
    }
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'fileName' => $file['name'],
        'extracted' => [
            'school' => $primaryRecord['school_name'],
            'program' => $primaryRecord['program'],
            'region' => $primaryRecord['region'],
            'date' => $primaryRecord['date_approved']
        ],
        'count' => ($category === 'COPC Exemption' && !empty($studentNames)) ? count($studentNames) : count($records)
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'fileName' => $_FILES['document_file']['name'] ?? 'unknown']);
}
