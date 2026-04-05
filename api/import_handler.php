<?php
ob_start();

ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/extraction.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

header('Content-Type: application/json');

function importJsonResponse(int $statusCode, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function normalizeHeader(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim($value);
}

function normalizeTextCell($value): string
{
    return trim((string) $value);
}

function parseImportedDate($value): string
{
    if ($value === null || $value === '') {
        return date('Y-m-d');
    }

    if (is_numeric($value)) {
        return SpreadsheetDate::excelToDateTimeObject($value)->format('Y-m-d');
    }

    $text = trim((string) $value);
    $timestamp = strtotime($text);
    return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
}

function extractRegionFromFilename(string $fileName): string
{
    if (preg_match('/CHEDRO[- ]*([A-Z0-9]+)/i', $fileName, $matches)) {
        return 'CHEDRO-' . strtoupper($matches[1]);
    }

    return '';
}

function detectHeaderMap(array $rows): ?array
{
    $aliases = [
        'school' => ['school', 'hei', 'hei higher education institution', 'higher education institution', 'institution', 'institution name'],
        'program' => ['program course', 'program', 'program name', 'course'],
        'major' => ['major', 'major discipline', 'program major'],
        'date' => ['date of issuance', 'date approved', 'approval date', 'issued date'],
        'status' => ['status'],
        'student_list' => ['student list', 'exempted students', 'student exemption list'],
        'region' => ['region', 'address'],
        'category' => ['permit type', 'type', 'category'],
    ];

    foreach ($rows as $index => $row) {
        $map = [];

        foreach ($row as $columnIndex => $cellValue) {
            $normalized = normalizeHeader((string) $cellValue);
            if ($normalized === '') {
                continue;
            }

            foreach ($aliases as $key => $options) {
                if (in_array($normalized, $options, true) && !array_key_exists($key, $map)) {
                    $map[$key] = $columnIndex;
                    break;
                }
            }
        }

        if (isset($map['school'], $map['program'])) {
            return [
                'header_row' => $index,
                'map' => $map,
            ];
        }
    }

    return null;
}

function detectKnownHeadersWithoutProgram(array $rows): ?array
{
    $schoolAliases = ['school', 'hei', 'hei higher education institution', 'higher education institution', 'institution', 'institution name'];
    $programAliases = ['program course', 'program', 'program name', 'course'];

    foreach ($rows as $index => $row) {
        $hasSchool = false;
        $hasProgram = false;

        foreach ($row as $cellValue) {
            $normalized = normalizeHeader((string) $cellValue);
            if ($normalized === '') {
                continue;
            }

            if (in_array($normalized, $schoolAliases, true)) {
                $hasSchool = true;
            }

            if (in_array($normalized, $programAliases, true)) {
                $hasProgram = true;
            }
        }

        if ($hasSchool && !$hasProgram) {
            return [
                'header_row' => $index,
            ];
        }
    }

    return null;
}

function detectInstitutionOnlyHeaderMap(array $rows): ?array
{
    $aliases = [
        'school' => ['school', 'hei', 'hei higher education institution', 'higher education institution', 'institution', 'institution name'],
        'region' => ['region', 'address'],
        'institution_type' => ['institution type', 'type'],
        'province' => ['province'],
        'city' => ['municipality city', 'municipality', 'city', 'municipality town city'],
        'website' => ['website address', 'website', 'web address'],
        'contact' => ['fax telephone no', 'telephone', 'contact number', 'fax'],
    ];

    foreach ($rows as $index => $row) {
        $map = [];

        foreach ($row as $columnIndex => $cellValue) {
            $normalized = normalizeHeader((string) $cellValue);
            if ($normalized === '') {
                continue;
            }

            foreach ($aliases as $key => $options) {
                if (in_array($normalized, $options, true) && !array_key_exists($key, $map)) {
                    $map[$key] = $columnIndex;
                    break;
                }
            }
        }

        if (isset($map['school']) && !isset($map['program'])) {
            return [
                'header_row' => $index,
                'map' => $map,
            ];
        }
    }

    return null;
}

function buildInstitutionOnlyRecordFromRow(array $row, array $map, string $defaultCategory, string $fallbackRegion, string $defaultStatus): ?array
{
    $school = normalizeTextCell($row[$map['school']] ?? '');
    if ($school === '') {
        return null;
    }

    $region = isset($map['region']) ? normalizeTextCell($row[$map['region']] ?? '') : '';
    $institutionType = isset($map['institution_type']) ? normalizeTextCell($row[$map['institution_type']] ?? '') : '';
    $province = isset($map['province']) ? normalizeTextCell($row[$map['province']] ?? '') : '';
    $city = isset($map['city']) ? normalizeTextCell($row[$map['city']] ?? '') : '';
    $website = isset($map['website']) ? normalizeTextCell($row[$map['website']] ?? '') : '';
    $contact = isset($map['contact']) ? normalizeTextCell($row[$map['contact']] ?? '') : '';

    $programParts = ['Institution Profile'];
    if ($institutionType !== '') {
        $programParts[] = $institutionType;
    }
    if ($province !== '' || $city !== '') {
        $location = trim(implode(', ', array_filter([$city, $province], static fn($value) => $value !== '')));
        if ($location !== '') {
            $programParts[] = $location;
        }
    }

    $studentListParts = [];
    if ($website !== '') {
        $studentListParts[] = 'Website: ' . $website;
    }
    if ($contact !== '') {
        $studentListParts[] = 'Contact: ' . $contact;
    }

    return [
        'region' => $region !== '' ? $region : $fallbackRegion,
        'school_name' => $school,
        'program' => implode(' - ', $programParts),
        'date_approved' => date('Y-m-d'),
        'status' => $defaultStatus,
        'student_list' => implode(' | ', $studentListParts),
        'category' => $defaultCategory,
    ];
}

function buildRecordFromRow(array $row, array $map, string $defaultCategory, string $fallbackRegion, string $defaultStatus): ?array
{
    $school = normalizeTextCell($row[$map['school']] ?? '');
    $program = normalizeTextCell($row[$map['program']] ?? '');
    $major = isset($map['major']) ? normalizeTextCell($row[$map['major']] ?? '') : '';
    $region = isset($map['region']) ? normalizeTextCell($row[$map['region']] ?? '') : '';
    $studentList = isset($map['student_list']) ? normalizeTextCell($row[$map['student_list']] ?? '') : '';
    $categoryText = isset($map['category']) ? strtoupper(normalizeTextCell($row[$map['category']] ?? '')) : '';

    if ($school === '' || $program === '') {
        return null;
    }

    if ($major !== '') {
        $program .= ' - ' . $major;
    }

    $category = $defaultCategory;
    if (strpos($categoryText, 'EXEMPT') !== false) {
        $category = 'COPC Exemption';
    } elseif (strpos($categoryText, 'GOVERNMENT RECOGNITION') !== false || $categoryText === 'GR') {
        $category = 'GR';
    } elseif (strpos($categoryText, 'COPC') !== false) {
        $category = 'COPC';
    }

    $region = $region !== '' ? $region : $fallbackRegion;
    $date = isset($map['date']) ? parseImportedDate($row[$map['date']] ?? '') : date('Y-m-d');

    return [
        'region' => $region,
        'school_name' => $school,
        'program' => $program,
        'date_approved' => $date,
        'status' => $defaultStatus,
        'student_list' => $studentList,
        'category' => $category,
    ];
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    importJsonResponse(500, [
        'success' => false,
        'error' => 'PHP runtime error during import.',
        'details' => "$errstr in $errfile on line $errline",
    ]);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    if ($error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR)) {
        importJsonResponse(500, [
            'success' => false,
            'error' => 'Fatal server error during import.',
            'details' => "{$error['message']} in {$error['file']} on line {$error['line']}",
        ]);
    }
});

if (!isset($_SESSION['admin_id'])) {
    importJsonResponse(403, [
        'success' => false,
        'error' => 'Unauthorized',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    importJsonResponse(405, [
        'success' => false,
        'error' => 'Invalid request method',
    ]);
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    importJsonResponse(403, [
        'success' => false,
        'error' => 'CSRF validation failed',
    ]);
}

$category = trim($_POST['category'] ?? '');
if (!in_array($category, ['COPC', 'COPC Exemption', 'HEI List', 'GR'], true)) {
    importJsonResponse(422, [
        'success' => false,
        'error' => 'Invalid category selected',
    ]);
}

$batchStatus = strtoupper(trim($_POST['batch_status'] ?? 'NEW'));
if (!in_array($batchStatus, ['OLD', 'NEW'], true)) {
    importJsonResponse(422, [
        'success' => false,
        'error' => 'Invalid batch status selected',
    ]);
}

$manualRegion = trim($_POST['batch_region'] ?? '');

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    importJsonResponse(422, [
        'success' => false,
        'error' => 'No file uploaded or upload error',
    ]);
}

$fileTmpPath = $_FILES['import_file']['tmp_name'];
$fileName = $_FILES['import_file']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExtension, ['xlsx', 'csv'], true)) {
    importJsonResponse(422, [
        'success' => false,
        'error' => 'Invalid file type. Only XLSX and CSV are supported for bulk import.',
    ]);
}

try {
    $reader = IOFactory::createReaderForFile($fileTmpPath);
    $reader->setReadDataOnly(true);

    $spreadsheet = $reader->load($fileTmpPath);
    $fallbackRegion = $manualRegion !== '' ? $manualRegion : extractRegionFromFilename($fileName);

    $recordsToInsert = [];

    $missingProgramSheet = null;

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        if (!$worksheet instanceof Worksheet) {
            continue;
        }

        $rows = $worksheet->toArray(null, true, true, false);
        if (empty($rows)) {
            continue;
        }

        $headerInfo = detectHeaderMap($rows);
        if ($headerInfo === null) {
            $institutionOnlyHeaderInfo = detectInstitutionOnlyHeaderMap($rows);
            if ($institutionOnlyHeaderInfo !== null) {
                $headerRowIndex = $institutionOnlyHeaderInfo['header_row'];
                $map = $institutionOnlyHeaderInfo['map'];

                for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                    $record = buildInstitutionOnlyRecordFromRow($rows[$i], $map, $category, $fallbackRegion, $batchStatus);
                    if ($record !== null) {
                        $recordsToInsert[] = $record;
                    }
                }
                continue;
            }

            if ($missingProgramSheet === null && detectKnownHeadersWithoutProgram($rows) !== null) {
                $missingProgramSheet = $worksheet->getTitle();
            }
            continue;
        }

        $headerRowIndex = $headerInfo['header_row'];
        $map = $headerInfo['map'];

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $record = buildRecordFromRow($rows[$i], $map, $category, $fallbackRegion, $batchStatus);
            if ($record !== null) {
                $recordsToInsert[] = $record;
            }
        }
    }

    if (empty($recordsToInsert)) {
        if ($missingProgramSheet !== null) {
            importJsonResponse(422, [
                'success' => false,
                'error' => 'The uploaded file is missing a Program column.',
                'details' => "Sheet '{$missingProgramSheet}' contains institution/school data but no accepted Program header. Accepted program headers: Program, Program Name, Program Course, or Course.",
            ]);
        }

        importJsonResponse(422, [
            'success' => false,
            'error' => 'No valid rows were found in the uploaded workbook.',
        ]);
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO copc_documents
        (region, school_name, program, date_approved, status, student_list, category, entry_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'manual', ?)"
    );

    $uploadedBy = $_SESSION['username'] ?? 'admin';
    $count = 0;

    foreach ($recordsToInsert as $record) {
        $studentNames = [];
        if ($record['category'] === 'COPC Exemption') {
            $studentNames = extractExemptionStudentNames((string) ($record['student_list'] ?? ''));
        }

        if ($record['category'] === 'COPC Exemption' && !empty($studentNames)) {
            foreach ($studentNames as $studentName) {
                $stmt->execute([
                    $record['region'],
                    $record['school_name'],
                    $record['program'],
                    $record['date_approved'],
                    $record['status'],
                    $studentName,
                    $record['category'],
                    $uploadedBy,
                ]);
                $count++;
            }
            continue;
        }

        $stmt->execute([
            $record['region'],
            $record['school_name'],
            $record['program'],
            $record['date_approved'],
            $record['status'],
            $record['student_list'],
            $record['category'],
            $uploadedBy,
        ]);
        $count++;
    }

    $pdo->commit();

    importJsonResponse(200, [
        'success' => true,
        'message' => "Successfully imported $count records as $category.",
        'count' => $count,
    ]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    importJsonResponse(500, [
        'success' => false,
        'error' => 'Import failed.',
        'details' => $e->getMessage(),
    ]);
}
