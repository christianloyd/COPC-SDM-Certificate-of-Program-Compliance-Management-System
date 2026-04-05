<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/extraction.php';

$pageTitle = "Edit Record";
require_once __DIR__ . '/../includes/admin_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid record ID");
}

$error = '';
$success = '';

$pdo = getDBConnection();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF validation failed.";
    } else {
        $school = trim($_POST['school_name'] ?? '');
        $program = trim($_POST['program'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dateApproved = trim($_POST['date_approved'] ?? '');
        $status = trim($_POST['status'] ?? 'NEW');
        $studentList = trim($_POST['student_list'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            // First, get current record
            $stmt = $pdo->prepare("SELECT * FROM copc_documents WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            
            if (!$current) {
                throw new Exception("Record not found.");
            }

            // Update metadata including new fields
            $updateSql = "UPDATE copc_documents SET school_name=?, program=?, region=?, category=?, date_approved=?, status=?, student_list=?, notes=? WHERE id=?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$school, $program, $region, $category, $dateApproved, $status, $studentList, $notes, $id]);
            $success = "Metadata updated successfully.";

            // Handle file attachment for manual entry -> upload upgrade
            if ($current['entry_type'] === 'manual' && isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // ... (existing upload logic) ...
                $file = $_FILES['document_file'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("File upload error code: " . $file['error']);
                }

                $maxSize = UPLOAD_MAX_SIZE_MB * 1024 * 1024;
                if ($file['size'] > $maxSize) {
                    throw new Exception("File size exceeds " . UPLOAD_MAX_SIZE_MB . "MB limit.");
                }

                $fileMime = mime_content_type($file['tmp_name']) ?: $file['type'];
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx'];
                if (!in_array($fileExt, $allowedExts)) {
                    throw new Exception("Invalid file type. Allowed: PDF, JPG, PNG, XLSX.");
                }

                $targetDir = ($category === 'COPC Exemption') ? EXEMPTIONS_DIR : COPC_DIR;
                $dbDirPrefix = ($category === 'COPC Exemption') ? 'exemptions/' : 'copc/';
                
                $safeName = getSafeFileName($file['name'], $fileExt);
                $absolutePath = $targetDir . '/' . $safeName;
                $dbPath = $dbDirPrefix . $safeName;

                if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                    throw new Exception("Failed to move uploaded file.");
                }

                $extractedText = null;
                try {
                    $extractedText = extractText($absolutePath, $fileExt);
                } catch (\Exception $ex) {
                    error_log("Upload extraction failed on edit: " . $ex->getMessage());
                    $extractedText = "Extraction failed: " . $ex->getMessage();
                }

                $fileExtDb = ($fileExt == 'jpeg') ? 'jpg' : $fileExt;
                $fileSizeKb = ceil($file['size'] / 1024);
                $studentNames = [];

                if ($category === 'COPC Exemption') {
                    if ($studentList !== '') {
                        $studentNames = extractExemptionStudentNames($studentList);
                    }

                    if (empty($studentNames) && is_string($extractedText) && $extractedText !== '') {
                        $studentNames = extractExemptionStudentNames($extractedText);
                    }

                    if (!empty($studentNames)) {
                        $studentList = $studentNames[0];
                    } elseif ($studentList === '' && is_string($extractedText) && $extractedText !== '') {
                        $studentList = extractExemptionStudentList($extractedText);
                    }

                    $updateStmt->execute([$school, $program, $region, $category, $dateApproved, $status, $studentList, $notes, $id]);
                }
                
                $upgradeSql = "UPDATE copc_documents 
                               SET file_path=?, file_type=?, file_name=?, file_size_kb=?, extracted_text=?, entry_type='upload' 
                               WHERE id=?";
                $upgradeStmt = $pdo->prepare($upgradeSql);
                $upgradeStmt->execute([
                    $dbPath, 
                    $fileExtDb, 
                    mb_substr($file['name'], 0, 250), 
                    $fileSizeKb, 
                    $extractedText, 
                    $id
                ]);

                if ($category === 'COPC Exemption' && count($studentNames) > 1) {
                    $insertSql = "INSERT INTO copc_documents
                        (school_name, program, region, category, date_approved, status, student_list, file_path, file_type, file_name, file_size_kb, extracted_text, notes, entry_type, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upload', ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    $uploadedBy = $_SESSION['username'] ?? 'admin';

                    for ($i = 1; $i < count($studentNames); $i++) {
                        $insertStmt->execute([
                            $school,
                            $program,
                            $region,
                            $category,
                            $dateApproved,
                            $status,
                            $studentNames[$i],
                            $dbPath,
                            $fileExtDb,
                            mb_substr($file['name'], 0, 250),
                            $fileSizeKb,
                            $extractedText,
                            $notes,
                            $uploadedBy
                        ]);
                    }
                }
                
                $success .= " Digital file successfully linked.";
                if ($category === 'COPC Exemption' && count($studentNames) > 1) {
                    $success .= ' Added ' . count($studentNames) . ' student records from the uploaded exemption file.';
                }
            }

        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch form data
$stmt = $pdo->prepare("SELECT * FROM copc_documents WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Record not found.");
}
$csrfToken = generateCsrfToken();
?>

<div class="max-w-5xl mx-auto">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-prcnavy tracking-tight italic">Revision Console <span class="text-prcgold opacity-50 not-italic ml-2">#<?php echo $id; ?></span></h1>
            <div class="flex items-center mt-2 gap-3">
                <span class="px-3 py-1 bg-prclight text-prcnavy rounded-full text-[10px] font-bold uppercase tracking-widest border border-gray-100">
                    <?php echo h($record['entry_type']); ?> Mode
                </span>
                <span class="text-xs text-gray-400 font-medium tracking-wide">Modified: <?php echo date('M d, H:i', strtotime($record['updated_at'] ?: $record['created_at'])); ?></span>
            </div>
        </div>
        <a href="records.php" class="inline-flex items-center justify-center px-6 py-2.5 bg-white border border-gray-200 text-prcnavy font-bold text-xs rounded-xl hover:bg-prclight transition shadow-sm uppercase tracking-widest">
            <i class="fa-solid fa-arrow-left mr-2"></i> All Records
        </a>
    </div>

    <!-- ... (show errors/success) ... -->

    <form method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-3xl shadow-soft border border-gray-100 overflow-hidden">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
        
        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8 border-b border-gray-50">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Administrative Region</label>
                <input type="text" name="region" value="<?php echo h($record['region']); ?>" class="w-full bg-gray-50 border-none rounded-2xl py-3 px-4 text-prcnavy font-semibold focus:ring-2 focus:ring-prcgold focus:outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">School Entity *</label>
                <input type="text" name="school_name" value="<?php echo h($record['school_name']); ?>" required class="w-full bg-gray-50 border-none rounded-2xl py-3 px-4 text-prcnavy font-semibold focus:ring-2 focus:ring-prcgold focus:outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Degree Program *</label>
                <input type="text" name="program" value="<?php echo h($record['program']); ?>" required class="w-full bg-gray-50 border-none rounded-2xl py-3 px-4 text-prcnavy font-semibold focus:ring-2 focus:ring-prcgold focus:outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Category Selection *</label>
                <select id="categoryEdit" name="category" required class="w-full bg-gray-50 border-none rounded-2xl py-3 px-4 text-prcnavy font-bold focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    <option value="COPC" <?php echo $record['category'] === 'COPC' ? 'selected' : ''; ?>>COPC Compliance</option>
                    <option value="GR" <?php echo $record['category'] === 'GR' ? 'selected' : ''; ?>>Government Recognition (GR)</option>
                    <option value="COPC Exemption" <?php echo $record['category'] === 'COPC Exemption' ? 'selected' : ''; ?>>COPC Exemption</option>
                    <option value="HEI List" <?php echo $record['category'] === 'HEI List' ? 'selected' : ''; ?>>HEI List</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Approval Date *</label>
                <input type="date" name="date_approved" value="<?php echo h($record['date_approved']); ?>" required class="w-full bg-gray-50 border-none rounded-2xl py-3 px-4 text-prcnavy font-semibold focus:ring-2 focus:ring-prcgold focus:outline-none transition">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Database Status</label>
                <select name="status" class="w-full bg-gray-50 border-none rounded-2xl py-3 px-4 text-prcnavy font-bold focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    <option value="NEW" <?php echo $record['status'] === 'NEW' ? 'selected' : ''; ?>>NEW RECORD</option>
                    <option value="OLD" <?php echo $record['status'] === 'OLD' ? 'selected' : ''; ?>>OLD ARCHIVE</option>
                </select>
            </div>

            <div id="studentListContainerEdit" class="md:col-span-2 <?php echo $record['category'] !== 'COPC Exemption' ? 'hidden' : ''; ?>">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Exempted Student List</label>
                <textarea name="student_list" rows="6" class="w-full bg-gray-50 border-none rounded-2xl py-4 px-5 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition placeholder-gray-300" placeholder="List names or SRNs here..."><?php echo h($record['student_list']); ?></textarea>
                <p class="text-[10px] text-gray-400 mt-2 italic px-2">Updating this list will automatically index names for the search engine.</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Internal Remarks</label>
                <textarea name="notes" rows="3" class="w-full bg-gray-50 border-none rounded-2xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition"><?php echo h($record['notes']); ?></textarea>
            </div>
        </div>

        <?php if ($record['entry_type'] === 'manual'): ?>
        <div class="p-8 bg-amber-50/50 border-b border-gray-100">
            <h3 class="font-extrabold text-prcnavy flex items-center mb-1 text-base tracking-tight">
                <i class="fa-solid fa-cloud-arrow-up text-prcgold mr-2"></i> Elevate to Digital Record
            </h3>
            <p class="text-sm text-gray-500 mb-6">Attach a PDF, Image, or Excel file to link physical evidence to this entry.</p>
            
            <div class="relative">
                <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.xlsx" class="block w-full text-sm text-gray-400 file:mr-6 file:py-3 file:px-8 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:uppercase file:tracking-widest file:bg-prcnavy file:text-white hover:file:bg-prcaccent file:transition-all cursor-pointer bg-white rounded-2xl py-2 px-2 border border-dashed border-gray-300">
            </div>
        </div>
        <?php else: ?>
        <div class="p-8 bg-prclight/30 flex items-center justify-between border-b border-gray-50">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center text-prcgold mr-5">
                    <i class="fa-solid fa-file-circle-check text-2xl"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-prcnavy tracking-tight text-base"><?php echo h($record['file_name']); ?></h3>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-[10px] font-bold text-gray-400 bg-white px-2 py-0.5 rounded border border-gray-100 uppercase"><?php echo h($record['file_type']); ?></span>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter"><?php echo h(formatBytes($record['file_size_kb']*1024, 0)); ?></span>
                    </div>
                </div>
            </div>
            <a href="../download.php?id=<?php echo $id; ?>" class="inline-flex items-center justify-center px-6 py-3 bg-white border border-gray-100 text-prcnavy font-extrabold text-xs rounded-xl hover:bg-prcnavy hover:text-white transition shadow-sm uppercase tracking-widest">
                <i class="fa-solid fa-cloud-arrow-down mr-2 opacity-70"></i> Full Access
            </a>
        </div>
        <?php endif; ?>

        <div class="px-8 py-6 bg-gray-50 flex items-center justify-end">
            <button type="submit" class="bg-prcnavy hover:bg-prcaccent text-white font-extrabold py-4 px-12 rounded-2xl shadow-lg transition-all transform hover:-translate-y-1 flex items-center">
                <i class="fa-solid fa-circle-check mr-2 text-prcgold"></i> Sync Changes
            </button>
        </div>
    </form>
</div>

<script>
    document.getElementById('categoryEdit').addEventListener('change', function() {
        const container = document.getElementById('studentListContainerEdit');
        if (this.value === 'COPC Exemption') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>
