<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/extraction.php';

requireAdmin();

function buildExemptionMigrationPreview(PDO $pdo, int $page = 1, int $limit = 20): array
{
    $stmt = $pdo->query("SELECT id, student_list, extracted_text FROM copc_documents WHERE category = 'COPC Exemption' ORDER BY id ASC");
    $records = $stmt->fetchAll();

    $preview = [];
    $rowsNeedingMigration = 0;
    $additionalRows = 0;
    $totalCount = count($records);
    
    $offset = ($page - 1) * $limit;
    $end = $offset + $limit;
    $i = 0;

    foreach ($records as $record) {
        $studentNames = extractExemptionStudentNames((string) ($record['student_list'] ?? ''));
        if (empty($studentNames)) {
            $studentNames = extractExemptionStudentNames((string) ($record['extracted_text'] ?? ''));
        }

        $studentCount = count($studentNames);
        $needsMigration = $studentCount > 1;
        if ($needsMigration) {
            $rowsNeedingMigration++;
            $additionalRows += ($studentCount - 1);
        }

        if ($i >= $offset && $i < $end) {
            $preview[] = [
                'id' => (int) $record['id'],
                'student_count' => $studentCount,
                'needs_migration' => $needsMigration,
                'sample_names' => array_slice($studentNames, 0, 3),
            ];
        }
        $i++;
    }

    return [
        'rows_needing_migration' => $rowsNeedingMigration,
        'additional_rows' => $additionalRows,
        'total_count' => $totalCount,
        'total_pages' => ceil($totalCount / $limit),
        'preview' => $preview,
    ];
}

function getMigrationPageUrl($p) {
    return '?p=' . $p;
}

$pageTitle = "Migrate Exemptions";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = getDBConnection();
$csrfToken = generateCsrfToken();
$error = '';
$success = '';
$migrationStats = [
    'processed' => 0,
    'updated' => 0,
    'inserted' => 0,
    'skipped' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF validation failed.';
    } else {
        try {
            $stmt = $pdo->query("SELECT * FROM copc_documents WHERE category = 'COPC Exemption' ORDER BY id ASC");
            $records = $stmt->fetchAll();

            $insertSql = "INSERT INTO copc_documents
                (school_name, program, region, category, date_approved, status, student_list, file_path, file_type, file_name, file_size_kb, extracted_text, notes, entry_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $updateStmt = $pdo->prepare("UPDATE copc_documents SET student_list = ? WHERE id = ?");

            $pdo->beginTransaction();

            foreach ($records as $record) {
                $migrationStats['processed']++;

                $studentNames = extractExemptionStudentNames((string) ($record['student_list'] ?? ''));
                if (empty($studentNames)) {
                    $studentNames = extractExemptionStudentNames((string) ($record['extracted_text'] ?? ''));
                }

                if (count($studentNames) <= 1) {
                    $migrationStats['skipped']++;
                    continue;
                }

                $updateStmt->execute([$studentNames[0], $record['id']]);
                $migrationStats['updated']++;

                for ($i = 1; $i < count($studentNames); $i++) {
                    $insertStmt->execute([
                        $record['school_name'],
                        $record['program'],
                        $record['region'],
                        $record['category'],
                        $record['date_approved'],
                        $record['status'],
                        $studentNames[$i],
                        $record['file_path'],
                        $record['file_type'],
                        $record['file_name'],
                        $record['file_size_kb'],
                        $record['extracted_text'],
                        $record['notes'],
                        $record['entry_type'],
                        $record['uploaded_by'],
                    ]);
                    $migrationStats['inserted']++;
                }
            }

            $pdo->commit();
            $success = 'Migration complete. Existing exemption rows were expanded into individual student records.';
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Migration failed: ' . $e->getMessage();
        }
    }
}

$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$previewData = buildExemptionMigrationPreview($pdo, $page, $limit);
?>

<div class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-prcnavy">Exemption Migration</h1>
            <p class="mt-2 text-sm text-gray-500">Convert old COPC exemption records from one row per file into one row per student.</p>
        </div>
        <a href="records.php" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-3 text-xs font-bold uppercase tracking-widest text-prcnavy transition hover:bg-prclight">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back To Records
        </a>
    </div>

    <?php if ($error !== ''): ?>
    <div class="mb-6 rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-700">
        <strong class="font-bold">Error:</strong> <?php echo h($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
    <div class="mb-6 rounded-2xl border border-green-100 bg-green-50 px-5 py-4 text-sm text-green-700">
        <strong class="font-bold">Success:</strong> <?php echo h($success); ?>
        <div class="mt-2 text-xs text-green-800">
            Processed: <?php echo number_format($migrationStats['processed']); ?> |
            Updated: <?php echo number_format($migrationStats['updated']); ?> |
            Inserted: <?php echo number_format($migrationStats['inserted']); ?> |
            Skipped: <?php echo number_format($migrationStats['skipped']); ?>
        </div>
    </div>
    <?php endif; ?>

    <section class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="text-xs font-bold uppercase tracking-widest text-gray-400">Rows Needing Migration</div>
            <div class="mt-3 text-3xl font-extrabold text-prcnavy"><?php echo number_format($previewData['rows_needing_migration']); ?></div>
        </div>
        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="text-xs font-bold uppercase tracking-widest text-gray-400">Additional Student Rows</div>
            <div class="mt-3 text-3xl font-extrabold text-prcgold"><?php echo number_format($previewData['additional_rows']); ?></div>
        </div>
        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="text-xs font-bold uppercase tracking-widest text-gray-400">Total Exemption Rows</div>
            <div class="mt-3 text-3xl font-extrabold text-prcnavy"><?php echo number_format($previewData['total_count']); ?></div>
        </div>
    </section>

    <section class="mb-8 rounded-3xl border border-amber-100 bg-amber-50 p-6 shadow-soft">
        <h2 class="text-lg font-bold text-prcnavy">What This Will Do</h2>
        <p class="mt-2 text-sm leading-relaxed text-gray-600">
            Each old exemption record that still contains multiple student names will be split so each student gets a separate database row.
            The original file link, metadata, and extracted text will be kept on every generated student row.
            Records that already contain one student only will be skipped.
        </p>

        <form method="POST" action="" class="mt-5">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <button type="submit" class="inline-flex items-center rounded-2xl bg-prcnavy px-6 py-4 text-sm font-extrabold uppercase tracking-widest text-white transition hover:bg-prcaccent">
                <i class="fa-solid fa-shuffle mr-2 text-prcgold"></i> Run Migration
            </button>
        </form>
    </section>

    <section class="rounded-3xl border border-gray-100 bg-white shadow-soft">
        <div class="border-b border-gray-100 px-6 py-5">
            <h2 class="text-lg font-bold text-prcnavy">Preview</h2>
            <p class="mt-1 text-sm text-gray-500">Sample of current exemption rows and how many student names were detected.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-prclight text-xs font-bold uppercase tracking-widest text-prcnavy">
                    <tr>
                        <th class="px-6 py-4">Record ID</th>
                        <th class="px-6 py-4">Detected Students</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Sample Names</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($previewData['preview'])): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center italic text-gray-400">No exemption records found.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($previewData['preview'] as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-bold text-prcnavy">#<?php echo number_format($row['id']); ?></td>
                            <td class="px-6 py-4 text-gray-700"><?php echo number_format($row['student_count']); ?></td>
                            <td class="px-6 py-4">
                                <?php if ($row['needs_migration']): ?>
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-amber-800">Needs Migration</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-green-700">Already Fine</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <?php echo h(implode(', ', $row['sample_names']) ?: 'No student names detected'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Scalable Pagination UI -->
        <?php if ($previewData['total_pages'] > 1): ?>
        <div class="px-6 py-6 border-t border-gray-100 bg-gray-50 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-sm font-medium text-gray-500">
                Showing <span class="text-prcnavy font-bold"><?php echo ($offset + 1); ?></span> to <span class="text-prcnavy font-bold"><?php echo min($previewData['total_count'], $offset + $limit); ?></span> of <span class="text-prcnavy font-bold"><?php echo $previewData['total_count']; ?></span> records
            </div>
            
            <nav class="flex items-center -space-x-px" aria-label="Pagination">
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="<?php echo getMigrationPageUrl($page - 1); ?>" class="relative inline-flex items-center px-4 py-2 rounded-l-xl border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">
                        <i class="fa-solid fa-chevron-left mr-2 text-[10px]"></i> Prev
                    </a>
                <?php else: ?>
                    <span class="relative inline-flex items-center px-4 py-2 rounded-l-xl border border-gray-200 bg-gray-50 text-sm font-bold text-gray-300 cursor-not-allowed">
                        <i class="fa-solid fa-chevron-left mr-2 text-[10px]"></i> Prev
                    </span>
                <?php endif; ?>

                <?php
                $sidePages = 2;
                $startPage = max(1, $page - $sidePages);
                $endPage = min($previewData['total_pages'], $page + $sidePages);

                if ($startPage > 1) {
                    echo '<a href="'.getMigrationPageUrl(1).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">1</a>';
                    if ($startPage > 2) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-400">...</span>';
                }

                for ($i = $startPage; $i <= $endPage; $i++) {
                    $isCurrent = ($i === $page);
                    $activeClass = $isCurrent ? 'bg-prcnavy text-white hover:bg-prcnavy' : 'bg-white text-gray-500 hover:bg-prclight hover:text-prcnavy';
                    echo '<a href="'.getMigrationPageUrl($i).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 text-sm font-bold transition '.$activeClass.' shadow-sm">'.$i.'</a>';
                }

                if ($endPage < $previewData['total_pages']) {
                    if ($endPage < $previewData['total_pages'] - 1) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-400">...</span>';
                    echo '<a href="'.getMigrationPageUrl($previewData['total_pages']).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">'.$previewData['total_pages'].'</a>';
                }
                ?>

                <!-- Next Button -->
                <?php if ($page < $previewData['total_pages']): ?>
                    <a href="<?php echo getMigrationPageUrl($page + 1); ?>" class="relative inline-flex items-center px-4 py-2 rounded-r-xl border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">
                        Next <i class="fa-solid fa-chevron-right ml-2 text-[10px]"></i>
                    </a>
                <?php else: ?>
                    <span class="relative inline-flex items-center px-4 py-2 rounded-r-xl border border-gray-200 bg-gray-50 text-sm font-bold text-gray-300 cursor-not-allowed">
                        Next <i class="fa-solid fa-chevron-right ml-2 text-[10px]"></i>
                    </span>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
