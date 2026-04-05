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

$absolutePath = null;
$dbPath = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Invalid method']));
}

// CSRF Validate
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    die(json_encode(['success' => false, 'error' => 'CSRF Token Invalid.']));
}

try {
    $pdo = getDBConnection();
    
    // Clean inputs
    $school = trim($_POST['school_name'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $dateApproved = trim($_POST['date_approved'] ?? '');
    $status = trim($_POST['status'] ?? 'NEW');
    $notes = trim($_POST['notes'] ?? '');
    $uploadedBy = $_SESSION['username'] ?? 'admin';

    // Validate requirements
    if (!$school || !$program || !$category || !$dateApproved) {
        throw new Exception("Missing required metadata fields.");
    }
    
    // Check Date format (YYYY-MM-DD)
    $d = DateTime::createFromFormat('Y-m-d', $dateApproved);
    if (!$d || $d->format('Y-m-d') !== $dateApproved) {
        throw new Exception("Invalid date format.");
    }

    // Check File Upload
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception("No file was uploaded.");
    }
    
    $file = $_FILES['document_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload error code: " . $file['error']);
    }

    // Validate size
    $maxSize = UPLOAD_MAX_SIZE_MB * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception("File size exceeds " . UPLOAD_MAX_SIZE_MB . "MB limit.");
    }

    // Validate MIME / extension
    $allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream' // sometimes xlsx comes as this
    ];
    
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'csv'];
    
    $fileMime = mime_content_type($file['tmp_name']);
    if (!$fileMime) $fileMime = $file['type'];
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExts, true)) {
        throw new Exception("Invalid file type. Allowed: PDF, JPG, PNG, XLSX, CSV.");
    }

    // Spreadsheet files are commonly reported with different MIME types across browsers/Windows setups.
    if (!in_array($fileExt, ['xlsx', 'csv'], true) && !in_array($fileMime, $allowedMimes, true)) {
        throw new Exception("Invalid file type. Allowed: PDF, JPG, PNG, XLSX, CSV.");
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
        throw new Exception("Failed to move uploaded file to secure storage.");
    }

    // Background Extraction Attempt
    $extractedText = null;
    try {
        $extractedText = extractText($absolutePath, $fileExt);
    } catch (\Exception $ex) {
        error_log("Upload extraction hard crash: " . $ex->getMessage());
        $extractedText = "Extraction failed: " . $ex->getMessage();
    }

    $status = trim($_POST['status'] ?? 'NEW');
    $studentList = trim($_POST['student_list'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $uploadedBy = $_SESSION['username'] ?? 'admin';

    $studentNames = [];
    if ($category === 'COPC Exemption') {
        if ($studentList !== '') {
            $studentNames = extractExemptionStudentNames($studentList);
        }

        if (empty($studentNames) && is_string($extractedText) && $extractedText !== '') {
            $studentNames = extractExemptionStudentNames($extractedText);
        }

        if (!empty($studentNames)) {
            $studentList = implode("\n", $studentNames);
        } elseif ($studentList === '' && is_string($extractedText) && $extractedText !== '') {
            $studentList = extractExemptionStudentList($extractedText);
        }
    }

    $sql = "INSERT INTO copc_documents 
            (school_name, program, region, category, date_approved, status, student_list, file_path, file_type, file_name, file_size_kb, extracted_text, notes, entry_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upload', ?)";

    $stmt = $pdo->prepare($sql);
    $fileSizeKb = ceil($file['size'] / 1024);
    $originalName = mb_substr($file['name'], 0, 250);
    $fileTypeForDb = ($fileExt == 'jpeg' ? 'jpg' : $fileExt);
    $recordIds = [];

    $pdo->beginTransaction();
    if ($category === 'COPC Exemption' && !empty($studentNames)) {
        foreach ($studentNames as $studentName) {
            $stmt->execute([
                $school,
                $program,
                $region,
                $category,
                $dateApproved,
                $status,
                $studentName,
                $dbPath,
                $fileTypeForDb,
                $originalName,
                $fileSizeKb,
                $extractedText,
                $notes,
                $uploadedBy
            ]);
            $recordIds[] = (int) $pdo->lastInsertId();
        }
    } else {
        $stmt->execute([
            $school,
            $program,
            $region,
            $category,
            $dateApproved,
            $status,
            $studentList,
            $dbPath,
            $fileTypeForDb,
            $originalName,
            $fileSizeKb,
            $extractedText,
            $notes,
            $uploadedBy
        ]);
        $recordIds[] = (int) $pdo->lastInsertId();
    }
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'record_id' => $recordIds[0] ?? null,
        'record_ids' => $recordIds,
        'message' => ($category === 'COPC Exemption' && count($recordIds) > 1)
            ? ('Upload successful. Created ' . count($recordIds) . ' student exemption records from the uploaded file.')
            : 'Upload successful.'
    ]);

} catch (\Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($absolutePath && file_exists($absolutePath)) {
        $countStmt = isset($pdo) && $pdo instanceof PDO
            ? $pdo->prepare("SELECT COUNT(*) FROM copc_documents WHERE file_path = ?")
            : null;

        $linkedCount = 0;
        if ($countStmt) {
            $countStmt->execute([$dbPath]);
            $linkedCount = (int) $countStmt->fetchColumn();
        }

        if ($linkedCount === 0) {
            @unlink($absolutePath);
        }
    }

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
