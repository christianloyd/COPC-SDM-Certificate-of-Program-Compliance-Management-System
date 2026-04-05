<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/extraction.php';

requireAdmin();

function buildExemptionMigrationPreview(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, student_list, extracted_text FROM copc_documents WHERE category = 'COPC Exemption' ORDER BY id ASC");
    $records = $stmt->fetchAll();

    $preview = [];
    $rowsNeedingMigration = 0;
    $additionalRows = 0;

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

        $preview[] = [
            'id' => (int) $record['id'],
            'student_count' => $studentCount,
            'needs_migration' => $needsMigration,
            'sample_names' => array_slice($studentNames, 0, 3),
        ];
    }

    return [
        'rows_needing_migration' => $rowsNeedingMigration,
        'additional_rows' => $additionalRows,
        'preview' => $preview,
    ];
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

$previewData = buildExemptionMigrationPreview($pdo);
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
            <div class="mt-3 text-3xl font-extrabold text-prcnavy"><?php echo number_format(count($previewData['preview'])); ?></div>
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
    </section>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
