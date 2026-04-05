<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpSpreadsheet\IOFactory;

function setLastExtractionError(?string $message): void
{
    $GLOBALS['copcsdm_last_extraction_error'] = $message;
}

function getLastExtractionError(): ?string
{
    return $GLOBALS['copcsdm_last_extraction_error'] ?? null;
}

function normalizeExtractedText(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/\t+/", ' ', $text);
    $text = preg_replace("/[ ]{2,}/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function commandExists(string $command): bool
{
    if (!function_exists('exec')) {
        return false;
    }
    $output = [];
    $code = 1;
    @exec('where ' . escapeshellarg($command) . ' 2>NUL', $output, $code);
    return $code === 0 && !empty($output);
}

function resolveBinaryPath(string $command, array $candidates = []): ?string
{
    if (function_exists('exec')) {
        $output = [];
        $code = 1;
        @exec('where ' . escapeshellarg($command) . ' 2>NUL', $output, $code);
        if ($code === 0 && !empty($output)) {
            return trim((string) $output[0]);
        }
    }

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function getTesseractBinary(): ?string
{
    static $resolved = false;
    static $path = null;

    if (!$resolved) {
        $path = resolveBinaryPath('tesseract', [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        ]);
        $resolved = true;
    }

    return $path;
}

function getPdftoppmBinary(): ?string
{
    static $resolved = false;
    static $path = null;

    if (!$resolved) {
        $path = resolveBinaryPath('pdftoppm', [
            'C:\\Program Files\\poppler\\Library\\bin\\pdftoppm.exe',
            'C:\\Program Files\\poppler\\bin\\pdftoppm.exe',
            'C:\\Program Files (x86)\\poppler\\Library\\bin\\pdftoppm.exe',
            'C:\\Program Files (x86)\\poppler\\bin\\pdftoppm.exe',
            'C:\\laragon\\bin\\poppler\\Library\\bin\\pdftoppm.exe',
            'C:\\laragon\\bin\\poppler\\bin\\pdftoppm.exe',
        ]);
        $resolved = true;
    }

    return $path;
}

function getPdfOcrDependencyError(): ?string
{
    $missing = [];
    if (getTesseractBinary() === null) {
        $missing[] = 'Tesseract OCR';
    }
    if (getPdftoppmBinary() === null) {
        $missing[] = 'Poppler pdftoppm';
    }

    if (empty($missing)) {
        return null;
    }

    return 'OCR fallback is unavailable. Missing: ' . implode(' and ', $missing) . '.';
}

function hasSubstantialText(string $text): bool
{
    $lettersOnly = preg_replace('/[^a-z0-9]+/i', '', $text);
    return strlen($lettersOnly) >= 80;
}

function extractPdfTextViaOcr(string $filePath): ?string
{
    $pdftoppmBinary = getPdftoppmBinary();
    $tesseractBinary = getTesseractBinary();
    if ($pdftoppmBinary === null || $tesseractBinary === null) {
        setLastExtractionError(getPdfOcrDependencyError());
        return null;
    }

    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copcsdm_pdf_' . bin2hex(random_bytes(6));
    if (!@mkdir($tempDir) && !is_dir($tempDir)) {
        return null;
    }

    try {
        $imageBase = $tempDir . DIRECTORY_SEPARATOR . 'page';
        $pdfToImageCmd = escapeshellarg($pdftoppmBinary) . ' -png ' . escapeshellarg($filePath) . ' ' . escapeshellarg($imageBase) . ' 2>&1';
        $output = [];
        $code = 1;
        @exec($pdfToImageCmd, $output, $code);
        if ($code !== 0) {
            setLastExtractionError('PDF-to-image conversion failed: ' . trim(implode("\n", $output)));
            return null;
        }

        $imageFiles = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        sort($imageFiles);
        if (empty($imageFiles)) {
            return null;
        }

        $pages = [];
        foreach ($imageFiles as $index => $imageFile) {
            $ocrBase = $tempDir . DIRECTORY_SEPARATOR . 'ocr_' . $index;
            $ocrCmd = escapeshellarg($tesseractBinary) . ' ' . escapeshellarg($imageFile) . ' ' . escapeshellarg($ocrBase) . ' 2>&1';
            $ocrOutput = [];
            $ocrCode = 1;
            @exec($ocrCmd, $ocrOutput, $ocrCode);
            if ($ocrCode !== 0) {
                continue;
            }

            $textFile = $ocrBase . '.txt';
            if (file_exists($textFile)) {
                $pageText = trim((string) file_get_contents($textFile));
                if ($pageText !== '') {
                    $pages[] = $pageText;
                }
            }
        }

        if (empty($pages)) {
            setLastExtractionError('OCR ran but no readable text was produced.');
            return null;
        }

        return implode("\n\n", $pages);
    } catch (\Throwable $e) {
        setLastExtractionError('PDF OCR extraction failed: ' . $e->getMessage());
        error_log("PDF OCR extraction failed for {$filePath}: " . $e->getMessage());
        return null;
    } finally {
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $tmpFile) {
            @unlink($tmpFile);
        }
        @rmdir($tempDir);
    }
}

function extractExemptionStudentList(string $text): string
{
    return implode("\n", extractExemptionStudentNames($text));
}

function extractExemptionStudentNames(string $text): array
{
    $normalized = normalizeExtractedText($text);
    if ($normalized === '') {
        return [];
    }

    $candidates = [$normalized];
    if (preg_match('/namely\s*:\s*(.+?)(relative to this|further,|nonetheless|thank you|very truly|very truly yours)/is', $normalized, $matches)) {
        $candidates[] = trim($matches[1]);
    }

    $names = [];
    foreach ($candidates as $candidate) {
        foreach (extractNumberedStudentNames($candidate) as $name) {
            $names[] = $name;
        }

        if (!empty($names)) {
            break;
        }
    }

    if (empty($names)) {
        foreach ($candidates as $candidate) {
            foreach (extractLineBasedStudentNames($candidate) as $name) {
                $names[] = $name;
            }

            if (!empty($names)) {
                break;
            }
        }
    }

    $uniqueNames = [];
    foreach ($names as $name) {
        $cleaned = trim(preg_replace('/\s+/', ' ', $name));
        $cleaned = rtrim($cleaned, " \t\n\r\0\x0B,;");
        if ($cleaned === '') {
            continue;
        }

        $key = mb_strtolower($cleaned, 'UTF-8');
        if (!isset($uniqueNames[$key])) {
            $uniqueNames[$key] = $cleaned;
        }
    }

    return array_values($uniqueNames);
}

function extractNumberedStudentNames(string $text): array
{
    $flattened = preg_replace('/\s+/', ' ', $text);
    preg_match_all(
        '/(?:^|\s)(\d+)[\.\)]\s*([A-Z][A-Za-z\'\-.]+,\s*[A-Z][A-Za-z .\'\-]+?)(?=\s+\d+[\.\)]|\s+(?:relative to this|further,|nonetheless|thank you|very truly|very truly yours)\b|$)/i',
        $flattened,
        $matches,
        PREG_SET_ORDER
    );

    $orderedNames = [];
    foreach ($matches as $match) {
        $index = (int) ($match[1] ?? 0);
        $name = trim($match[2] ?? '');
        if ($index > 0 && $name !== '') {
            $orderedNames[$index] = $name;
        }
    }

    ksort($orderedNames);
    return array_values($orderedNames);
}

function extractLineBasedStudentNames(string $text): array
{
    $lines = preg_split('/\n+|;/', $text) ?: [];
    $names = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
        if (preg_match('/^[A-Z][A-Za-z\'\-.]+,\s*[A-Z][A-Za-z .\'\-]+$/', $line)) {
            $names[] = $line;
        }
    }

    return $names;
}

function cleanOcrLine(string $line): string
{
    $line = str_replace(["[", "]", "|", "_", "“", "”", "‘", "’"], ' ', $line);
    $line = preg_replace('/\b[Il](?=(College|University|School|Institute|Academy|Education|Criminology|Entrepreneurship|Bachelor|Master|Doctor|Diploma|Certificate)\b)/i', '', (string) $line);
    $line = preg_replace('/\s+/', ' ', $line);
    return trim((string) $line, " \t\n\r\0\x0B-");
}

function containsInstitutionKeyword(string $text): bool
{
    return preg_match('/\b(University|College|School|Institute|Academy)\b/i', $text) === 1;
}

function containsDegreeKeyword(string $text): bool
{
    return preg_match('/\b(Bachelor|Master|Doctor|Diploma|Certificate)\b/i', $text) === 1;
}

function looksLikeSchoolPrefix(string $text): bool
{
    if ($text === '' || containsDegreeKeyword($text)) {
        return false;
    }

    $tokens = preg_split('/\s+/', trim($text)) ?: [];
    return count($tokens) >= 2 && count($tokens) <= 8;
}

function cleanProgramCandidate(string $program): string
{
    $program = preg_replace('/\bI?C?OPC\s*No\..*$/i', '', $program);
    $program = preg_replace('/\bS\.\s*\d{4}\b/i', '', (string) $program);
    $program = preg_replace('/\s+/', ' ', (string) $program);
    $program = trim((string) $program, " \t\n\r\0\x0B,.;:-");

    if (preg_match('/\b(Bachelor of Science in|Bachelor of Secondary|Bachelor of Elementary|Bachelor of Agricultural|Bachelor of Technology|Master of|Doctor of|Certificate of)\s*$/i', $program)) {
        return $program;
    }

    return $program;
}

function cleanSchoolCandidate(string $school): string
{
    $school = preg_replace('/\bI?C?OPC\s*No\..*$/i', '', $school);
    $school = preg_replace('/\s+/', ' ', (string) $school);
    return trim((string) $school, " \t\n\r\0\x0B,.;:-");
}

function splitCopcLineFragments(string $line): array
{
    $line = cleanOcrLine($line);
    $line = preg_replace('/\bI?C?OPC\s*No\..*$/i', '', $line);
    $line = trim((string) $line);

    $school = '';
    $program = '';

    if ($line === '') {
        return ['school' => '', 'program' => ''];
    }

    if (preg_match('/\b(Bachelor|Master|Doctor|Diploma|Certificate)\b/i', $line, $degreeMatch, PREG_OFFSET_CAPTURE)) {
        $degreePos = $degreeMatch[0][1];
        if ($degreePos > 0) {
            $left = trim(substr($line, 0, $degreePos));
            $right = trim(substr($line, $degreePos));

            if (containsInstitutionKeyword($left) || looksLikeSchoolPrefix($left)) {
                $school = $left;
                $program = $right;
            } else {
                $program = $line;
            }
        } else {
            $program = $line;
        }
    } elseif (containsInstitutionKeyword($line)) {
        if (preg_match('/^(.+?\b(?:Branch|Campus|Main)\b)(.*)$/i', $line, $matches)) {
            $school = trim((string) $matches[1]);
            $program = trim((string) $matches[2]);
        } elseif (preg_match('/^(.+?\b(?:University|College|School|Institute|Academy)\b)(.*)$/i', $line, $matches)) {
            $school = trim((string) $matches[1]);
            $program = trim((string) $matches[2]);
        } else {
            $school = $line;
        }
    } elseif (containsDegreeKeyword($line)) {
        $program = $line;
    } elseif (looksLikeSchoolPrefix($line)) {
        $school = $line;
    } else {
        $program = $line;
    }

    return [
        'school' => cleanSchoolCandidate($school),
        'program' => cleanProgramCandidate($program),
    ];
}

function combineUniqueFragments(array $parts): string
{
    $result = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        $key = mb_strtolower($part, 'UTF-8');
        if (!isset($result[$key])) {
            $result[$key] = $part;
        }
    }

    return implode(' ', array_values($result));
}

function splitInstitutionAndSuffix(string $line): array
{
    $line = cleanOcrLine($line);
    $line = preg_replace('/\bI?C?OPC\s*No\..*$/i', '', $line);
    $line = trim((string) $line);

    if ($line === '') {
        return ['', ''];
    }

    if (preg_match('/^(.+?\b(?:Branch|Campus|Main)\b)\s*(.*)$/i', $line, $matches)) {
        return [cleanSchoolCandidate($matches[1]), cleanProgramCandidate($matches[2])];
    }

    if (preg_match('/^(.+?\b(?:University|College|School|Institute|Academy)\b)\s*(.*)$/i', $line, $matches)) {
        return [cleanSchoolCandidate($matches[1]), cleanProgramCandidate($matches[2])];
    }

    return ['', cleanProgramCandidate($line)];
}

function buildCopcRecordFromContext(array $contextParts, string $fileName, string $globalRegion = ''): array
{
    $previousLine = $contextParts[count($contextParts) - 2] ?? '';
    $olderLine = $contextParts[count($contextParts) - 3] ?? '';
    $currentLine = $contextParts[count($contextParts) - 1] ?? '';

    [$currentSchool, $currentProgramSuffix] = splitInstitutionAndSuffix($currentLine);

    $schoolPrefix = '';
    $programPrefix = '';

    if ($previousLine !== '') {
        $previousLine = cleanOcrLine($previousLine);
        if (containsDegreeKeyword($previousLine)) {
            if (preg_match('/\b(Bachelor|Master|Doctor|Diploma|Certificate)\b/i', $previousLine, $matches, PREG_OFFSET_CAPTURE)) {
                $degreePos = $matches[0][1];
                if ($degreePos > 0) {
                    $schoolPrefix = trim(substr($previousLine, 0, $degreePos));
                    $programPrefix = trim(substr($previousLine, $degreePos));
                } else {
                    $programPrefix = $previousLine;
                }
            }
        } elseif (!containsInstitutionKeyword($previousLine)) {
            $programPrefix = $previousLine;
        } elseif ($currentSchool === '') {
            $schoolPrefix = $previousLine;
        }
    }

    if ($olderLine !== '' && $schoolPrefix === '' && preg_match('/\b(Branch|Campus|Main)\b/i', $currentSchool)) {
        $olderLine = cleanOcrLine($olderLine);
        if (looksLikeSchoolPrefix($olderLine)) {
            $schoolPrefix = $olderLine;
        }
    }

    $school = cleanSchoolCandidate(trim($schoolPrefix . ' ' . $currentSchool));
    $program = cleanProgramCandidate(trim($programPrefix . ' ' . $currentProgramSuffix));

    $metadataContext = implode("\n", array_filter([$previousLine, $currentLine]));
    $metadata = parseMetadataFromText($metadataContext, $fileName);

    if ($globalRegion !== '' && empty($metadata['region'])) {
        $metadata['region'] = $globalRegion;
    }

    if ($school === '' && !empty($metadata['school_name'])) {
        $school = $metadata['school_name'];
    }
    if ($program === '' && !empty($metadata['program'])) {
        $program = $metadata['program'];
    }

    $dateApproved = extractCopcDateFromContext($metadataContext) ?? $metadata['date_approved'] ?? date('Y-m-d');

    return [
        'school_name' => $school ?: 'Unknown Institution (' . $fileName . ')',
        'program' => $program ?: 'Unknown Program',
        'region' => $metadata['region'] ?: ($globalRegion ?: 'UNKNOWN'),
        'date_approved' => $dateApproved,
    ];
}

function shouldPreferCandidate(string $current, string $candidate): bool
{
    $current = trim($current);
    $candidate = trim($candidate);

    if ($candidate === '') {
        return false;
    }
    if ($current === '') {
        return true;
    }
    if (mb_strlen($candidate, 'UTF-8') <= mb_strlen($current, 'UTF-8')) {
        return false;
    }

    $currentLower = mb_strtolower($current, 'UTF-8');
    $candidateLower = mb_strtolower($candidate, 'UTF-8');
    return str_contains($candidateLower, $currentLower);
}

function isValidCopcRecord(array $record): bool
{
    $school = trim((string) ($record['school_name'] ?? ''));
    $program = trim((string) ($record['program'] ?? ''));

    if ($school === '' || $program === '' || $program === 'Unknown Program') {
        return false;
    }

    if (preg_match('/Program Major|ICOPC No|^COPC No/i', $school)) {
        return false;
    }

    if (!containsInstitutionKeyword($school) && !preg_match('/\b(Branch|Campus|Main)\b/i', $school)) {
        return false;
    }

    return true;
}

function isCopcNoiseLine(string $line): bool
{
    if ($line === '') {
        return true;
    }

    $noisePatterns = [
        '/^COMMISSION ON HIGHER EDUCATION$/i',
        '/^REGIONAL OFFICE\b/i',
        '/^SUCs with COPC$/i',
        '/Program Major/i',
        '/University of Southeastern Philippines Compound/i',
        '/www\./i',
        '/chedro/i',
        '/^\W+$/',
    ];

    foreach ($noisePatterns as $pattern) {
        if (preg_match($pattern, $line)) {
            return true;
        }
    }

    return false;
}

function extractCopcSchoolCandidate(string $context): string
{
    $patterns = [
        '/([A-Z][A-Za-z0-9&.,\' -]*(?:University|College|School|Institute|Academy)(?:[- ][A-Za-z0-9&.,\' -]+){0,4})/i',
        '/([A-Z][A-Za-z0-9&.,\' -]*Campus(?:[- ][A-Za-z0-9&.,\' -]+){0,3})/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $context, $matches) && !empty($matches[1])) {
            return trim((string) end($matches[1]));
        }
    }

    return '';
}

function extractCopcProgramCandidate(string $context, string $schoolCandidate = ''): string
{
    $context = preg_replace('/\s+/', ' ', $context);
    $context = preg_replace('/\bC?OPC\s*No\..*$/i', '', (string) $context);
    if ($schoolCandidate !== '') {
        $context = str_ireplace($schoolCandidate, ' ', $context);
        $context = preg_replace('/\s+/', ' ', (string) $context);
    }

    $patterns = [
        '/((?:Bachelor|Master|Doctor|Diploma|Certificate)\s+[A-Za-z0-9&.,\'\/ -]{4,140})/i',
        '/((?:Education|Criminology|Entrepreneurship|Nursing|Accountancy|Mathematics|Biology|Geology|Statistics|Agronomy|Forestry|Agriculture|Engineering|Technology|Administration|Communication|Hospitality Management|Tourism Management|Social Work)[A-Za-z0-9&.,\'\/ -]{0,120})/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $context, $matches)) {
            $program = preg_replace('/\bC?OPC\s*No\..*$/i', '', (string) $matches[1]);
            return trim((string) preg_replace('/\s+/', ' ', $program));
        }
    }

    return '';
}

function extractCopcDateFromContext(string $context): ?string
{
    if (preg_match('/\b((?:19|20)\d{2})\d?\b/', $context, $matches)) {
        return $matches[1] . '-01-01';
    }

    return null;
}

function extractBulkPdfRecords(string $text, string $fileName, string $category = 'COPC', string $globalRegion = ''): array
{
    if (!in_array(strtoupper($category), ['COPC', 'GR'], true)) {
        return [];
    }

    $lines = preg_split('/\n+/', normalizeExtractedText($text)) ?: [];
    $records = [];
    $recentContext = [];

    foreach ($lines as $rawLine) {
        $line = cleanOcrLine($rawLine);
        if ($line === '') {
            continue;
        }

        $isNoise = isCopcNoiseLine($line);
        $hasCopc = preg_match('/I?C?OPC\s*No\./i', $line) === 1;

        if ($hasCopc) {
            $contextParts = array_slice($recentContext, -2);
            $contextParts[] = $line;
            $candidateRecord = buildCopcRecordFromContext($contextParts, $fileName, $globalRegion);

            if (isValidCopcRecord($candidateRecord)) {
                $key = mb_strtolower(
                    trim($candidateRecord['school_name']) . '|' .
                    trim($candidateRecord['program']) . '|' .
                    trim($candidateRecord['date_approved']),
                    'UTF-8'
                );
                $records[$key] = $candidateRecord;
            }
        }

        if (!$isNoise && !$hasCopc) {
            $recentContext[] = $line;
            if (count($recentContext) > 3) {
                array_shift($recentContext);
            }
        }
    }

    return array_values($records);
}

function parseMetadataFromText(string $text, string $fileName): array
{
    $results = [
        'school_name' => '',
        'program' => '',
        'region' => '',
        'date_approved' => date('Y-m-d'),
        'confidence' => 0,
    ];

    // 1. Region Detection (Filename has priority)
    if (preg_match('/(CHEDRO[-_ ]*[A-Z0-9]+|REGION[-_ ]+[A-Z0-9IVX-]+)/i', $fileName, $matches)) {
        $results['region'] = strtoupper(str_replace(' ', '-', trim($matches[1])));
        $results['region'] = str_replace('_', '-', $results['region']);
    } elseif (preg_match('/(CHEDRO[-_ ]*[A-Z0-9]+|REGION[-_ ]+[A-Z0-9IVX-]+)/i', $text, $matches)) {
        $results['region'] = strtoupper(str_replace(' ', '-', trim($matches[1])));
        $results['region'] = str_replace('_', '-', $results['region']);
    }

    // 2. School Name Detection
    if (preg_match('/(?:certify\s+that\s+)\s*([^,\n]+ (College|University|School|Institute|Academy|State College))/i', $text, $matches)) {
        $results['school_name'] = trim($matches[1]);
    } elseif (preg_match('/([^,\n]+ (College|University|School|Institute|Academy|State College))/i', $text, $matches)) {
        $results['school_name'] = trim($matches[1]);
    }

    // 3. Program Detection (Bachelor, Master, etc.)
    // Look for common program prefixes but avoid "Certificate of Program Compliance"
    if (preg_match('/((Bachelor|Master|Diploma|Doctor)\s+[Oo]f\s+[^,\n.\d]{5,100})/i', $text, $matches)) {
        $results['program'] = trim($matches[1]);
    } elseif (preg_match('/(?:in|for)\s+((?:Bachelor|Master|Diploma|Certificate)\s+[Oo]f\s+[^,\n.\d]{5,100})/i', $text, $matches)) {
         $results['program'] = trim($matches[1]);
    }

    // 4. Date Detection
    $months = '(January|February|March|April|May|June|July|August|September|October|November|December)';
    if (preg_match("/$months\s+\d{1,2},?\s+\d{4}/i", $text, $matches)) {
        $timestamp = strtotime($matches[0]);
        if ($timestamp) {
            $results['date_approved'] = date('Y-m-d', $timestamp);
        }
    }

    // Basic confidence score
    if ($results['school_name']) $results['confidence'] += 40;
    if ($results['program']) $results['confidence'] += 40;
    if ($results['region']) $results['confidence'] += 20;

    return $results;
}

/**
 * Extracts text from a given file
 * Supports PDF, XLSX, JPG, PNG
 * 
 * @param string $filePath Absolute path to file
 * @param string $fileType 'pdf', 'xlsx', 'csv', 'jpg', 'png'
 * @return string|null Extracted text or null on failure
 */
function extractText($filePath, $fileType) {
    setLastExtractionError(null);

    if (!file_exists($filePath)) {
        setLastExtractionError('The source file does not exist.');
        return null;
    }

    $ext = strtolower($fileType);
    $text = '';

    try {
        if ($ext === 'pdf') {
            try {
                $parser = new PdfParser();
                $pdf    = $parser->parseFile($filePath);
                $text   = $pdf->getText();
            } catch (\Throwable $e) {
                error_log("PDF parser failed for {$filePath}: " . $e->getMessage());
                setLastExtractionError('PDF parser failed: ' . $e->getMessage());
                $text = '';
            }

            if (!hasSubstantialText($text)) {
                $ocrText = extractPdfTextViaOcr($filePath);
                if ($ocrText !== null && hasSubstantialText($ocrText)) {
                    $text = $ocrText;
                } elseif (getLastExtractionError() === null) {
                    setLastExtractionError('The PDF did not contain enough machine-readable text, and OCR fallback did not return usable text.');
                }
            }
        } 
        elseif ($ext === 'xlsx') {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet   = $spreadsheet->getActiveSheet();
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false); 
                foreach ($cellIterator as $cell) {
                    $text .= $cell->getValue() . " ";
                }
                $text .= "\n";
            }
        }
        elseif ($ext === 'csv') {
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                setLastExtractionError('Unable to open CSV file for extraction.');
                return null;
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (!is_array($row)) {
                    continue;
                }
                $text .= implode(' ', array_map(static fn($value) => trim((string) $value), $row)) . "\n";
            }
            fclose($handle);
        }
        elseif ($ext === 'jpg' || $ext === 'png' || $ext === 'jpeg') {
            $tesseractBinary = getTesseractBinary();
            if ($tesseractBinary !== null) {
                // Execute Tesseract OCR
                // Create a temporary output file sans extension as tesseract adds .txt
                $tmpOut = tempnam(sys_get_temp_dir(), 'ocr_') . '_out';
                $cmd = escapeshellarg($tesseractBinary) . " " . escapeshellarg($filePath) . " " . escapeshellarg($tmpOut) . " 2>&1";
                exec($cmd, $output, $returnVar);

                $resultFile = $tmpOut . '.txt';
                if ($returnVar === 0 && file_exists($resultFile)) {
                    $text = file_get_contents($resultFile);
                    unlink($resultFile);
                } else {
                    setLastExtractionError('Image OCR failed to produce text.');
                }
                // cleanup
                @unlink($tmpOut);
            } else {
                setLastExtractionError('OCR extraction failed: Tesseract is not available on the server.');
                return null;
            }
        } else {
            setLastExtractionError('Unsupported file type for extraction.');
            return null;
        }
        
    } catch (\Exception $e) {
        setLastExtractionError('Extraction failed: ' . $e->getMessage());
        error_log("Extraction failed for {$filePath}: " . $e->getMessage());
        return null;
    }

    // Clean up excessive whitespace
    $normalizedText = normalizeExtractedText($text);
    if ($normalizedText === '' && getLastExtractionError() === null) {
        setLastExtractionError('Extraction completed but returned empty text.');
    }

    return $normalizedText;
}
