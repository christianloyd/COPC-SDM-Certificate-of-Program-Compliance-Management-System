<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = "Manual Entry";

$csrfToken = generateCsrfToken();
$error = '';
$success = '';

// Fetch all existing programs and schools for the searchable dropdowns
try {
    $pdo = getDBConnection();
    $allPrograms = $pdo->query("SELECT DISTINCT program FROM copc_documents WHERE program IS NOT NULL AND program != '' ORDER BY program ASC")->fetchAll(PDO::FETCH_COLUMN);
    $allSchools  = $pdo->query("SELECT DISTINCT school_name FROM copc_documents WHERE school_name IS NOT NULL AND school_name != '' ORDER BY school_name ASC")->fetchAll(PDO::FETCH_COLUMN);
    
    $allRegions = getPHRegions();
} catch (\Exception $e) {
    $allPrograms = [];
    $allSchools = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF validation failed. Please try again.";
    } else {
        try {
            $pdo = getDBConnection();
            
            $school = trim($_POST['school_name'] ?? '');
            $program = trim($_POST['program'] ?? '');
            $region = trim($_POST['region'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $dateApproved = trim($_POST['date_approved'] ?? '');
            $status = trim($_POST['status'] ?? 'NEW');
            $studentList = trim($_POST['student_list'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $copcNo = trim($_POST['copc_no'] ?? '');
            $uploadedBy = $_SESSION['username'] ?? 'admin';

            if (!$school || !$program || !$category || !$dateApproved) {
                throw new Exception("All required fields must be filled.");
            }

            // Check for duplicate: school_name + program + category + student_list
            $checkSql = "SELECT id FROM copc_documents WHERE school_name = ? AND program = ? AND category = ? AND student_list = ? LIMIT 1";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$school, $program, $category, $studentList]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $sql = "UPDATE copc_documents
                        SET region=?, copc_no=?, date_approved=?, status=?, student_list=?, notes=?, uploaded_by=?, updated_at=NOW()
                        WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $region,
                    $copcNo ?: null,
                    $dateApproved,
                    $status,
                    $studentList,
                    $notes,
                    $uploadedBy,
                    $existing['id']
                ]);

                setFlashMessage(
                    'success',
                    "A matching record for '{$school}' already existed and was updated with the new data.",
                    'Record Updated'
                );
            } else {
                // Insert new record
                $sql = "INSERT INTO copc_documents 
                        (school_name, program, region, category, copc_no, date_approved, status, student_list, file_path, file_type, file_name, file_size_kb, extracted_text, notes, entry_type, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, ?, 'manual', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $school,
                    $program,
                    $region,
                    $category,
                    $copcNo ?: null,
                    $dateApproved,
                    $status,
                    $studentList,
                    $notes,
                    $uploadedBy
                ]);

                setFlashMessage(
                    'success',
                    "Manual record for '{$school}' was successfully created.",
                    'Record Saved'
                );
            }

            header('Location: ' . BASE_URL . '/admin/manual-entry.php');
            exit;
        } catch (\Exception $e) {
            $error = "Failed to create manual record: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>


<div class="max-w-4xl mx-auto">
    <div class="mb-4">
        <h1 class="text-3xl font-extrabold text-prcnavy flex items-center tracking-tight">
            Manual Record Entry 
            <span class="ml-3 text-[10px] font-bold bg-amber-100 text-amber-700 px-3 py-1 rounded-full uppercase tracking-widest border border-amber-200">Data Only Vault</span>
        </h1>
        <p class="text-gray-400 mt-2">Log records manually when the physical PDF is missing or being processed.</p>
    </div>

    <form method="POST" action="" class="bg-white rounded-3xl shadow-soft border border-gray-100 p-8">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Region</label>
                <div class="combobox-wrapper" data-options='<?php echo htmlspecialchars(json_encode($allRegions), ENT_QUOTES, 'UTF-8'); ?>'>
                    <input type="text" name="region" id="regionInput" placeholder="e.g. NCR" autocomplete="off"
                           class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition combobox-input">
                    <div class="combobox-dropdown"></div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">School Name *</label>
                <div class="combobox-wrapper" data-options='<?php echo htmlspecialchars(json_encode($allSchools), ENT_QUOTES, 'UTF-8'); ?>'>
                    <input type="text" name="school_name" id="schoolNameInput" required autocomplete="off"
                           class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition combobox-input">
                    <div class="combobox-dropdown"></div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Program *</label>
                <div class="combobox-wrapper" data-options='<?php echo htmlspecialchars(json_encode($allPrograms), ENT_QUOTES, 'UTF-8'); ?>'>
                    <input type="text" name="program" id="programInput" required autocomplete="off"
                           class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition combobox-input">
                    <div class="combobox-dropdown"></div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Category *</label>
                <select id="categorySelect" name="category" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    <option value="COPC">COPC</option>
                    <option value="GR">Government Recognition (GR)</option>
                    <option value="COPC Exemption">COPC Exemption</option>
                    <option value="HEI List">HEI List</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Date Approved *</label>
                <input type="date" name="date_approved" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
            </div>
            <div class="md:col-span-2" id="copcNoContainer">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">
                    <i class="fa-solid fa-hashtag mr-1 text-prcgold"></i> COPC / Resolution No.
                </label>
                <input type="text" name="copc_no" id="copcNoInput"
                       placeholder="e.g. COPC No. 30 S. 2021"
                       class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                <p class="text-[10px] text-gray-400 mt-1.5 italic">The official COPC certificate or resolution number found on the document.</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Record Status</label>
                <select name="status" class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    <option value="NEW">NEW Record</option>
                    <option value="OLD">OLD Record</option>
                </select>
            </div>
        </div>

        <div id="studentListContainer" class="mb-6 hidden transition-all duration-300">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Student Exemption List</label>
            <textarea name="student_list" rows="5" placeholder="Enter student names or list details..." class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition"></textarea>
            <p class="text-[10px] text-gray-400 mt-2 italic">Essential for COPC Exemptions under CHED. List will be searchable.</p>
        </div>
        
        <div class="mb-8">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Internal Notes</label>
            <textarea name="notes" rows="3" class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition" placeholder="Reason for manual entry..."></textarea>
        </div>

        <button type="submit" class="w-full bg-prcnavy hover:bg-prcaccent text-white font-extrabold py-5 rounded-2xl shadow-md transition-all transform hover:-translate-y-0.5 flex items-center justify-center">
            <i class="fa-solid fa-save mr-2 text-prcgold"></i> Save Record Entry
        </button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ── Existing Visibility Logic ───────────────────────────────────────
        const catSel    = document.getElementById('categorySelect');
        const copcCont  = document.getElementById('copcNoContainer');
        const studentCont = document.getElementById('studentListContainer');

        function syncVisibility() {
            if (!catSel) return;
            const val = catSel.value;
            if (copcCont) copcCont.classList.toggle('hidden', val !== 'COPC' && val !== 'GR');
            if (studentCont) studentCont.classList.toggle('hidden', val !== 'COPC Exemption');
        }

        if (catSel) {
            catSel.addEventListener('change', syncVisibility);
            syncVisibility();
        }
    });
</script>

<?php if ($error !== ''): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.showAppToast({
            title: 'Save Failed',
            message: <?php echo json_encode($error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            type: 'error'
        });
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
