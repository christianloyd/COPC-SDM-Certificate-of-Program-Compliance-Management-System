<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = "Manual Entry";
require_once __DIR__ . '/../includes/admin_header.php';

$csrfToken = generateCsrfToken();
$error = '';
$success = '';

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
            $uploadedBy = $_SESSION['username'] ?? 'admin';

            if (!$school || !$program || !$category || !$dateApproved) {
                throw new Exception("All required fields must be filled.");
            }

            $sql = "INSERT INTO copc_documents 
                    (school_name, program, region, category, date_approved, status, student_list, file_path, file_type, file_name, file_size_kb, extracted_text, notes, entry_type, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, ?, 'manual', ?)";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $school,
                $program,
                $region,
                $category,
                $dateApproved,
                $status,
                $studentList,
                $notes,
                $uploadedBy
            ]);

            $success = "Manual record for '{$school}' was successfully created.";
        } catch (\Exception $e) {
            $error = "Failed to create manual record: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-4">
        <h1 class="text-3xl font-extrabold text-prcnavy flex items-center tracking-tight">
            Manual Record Entry 
            <span class="ml-3 text-[10px] font-bold bg-amber-100 text-amber-700 px-3 py-1 rounded-full uppercase tracking-widest border border-amber-200">Data Only Vault</span>
        </h1>
        <p class="text-gray-400 mt-2">Log records manually when the physical PDF is missing or being processed.</p>
    </div>

    <!-- ... (show errors/success) ... -->

    <form method="POST" action="" class="bg-white rounded-3xl shadow-soft border border-gray-100 p-8">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Region</label>
                <input type="text" name="region" placeholder="e.g. NCR" class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">School Name *</label>
                <input type="text" name="school_name" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Program *</label>
                <input type="text" name="program" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
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
            <i class="fa-solid fa-save mr-2 text-prcgold"></i> PERSIST MANUAL RECORD
        </button>
    </form>
</div>

<script>
    document.getElementById('categorySelect').addEventListener('change', function() {
        const container = document.getElementById('studentListContainer');
        if (this.value === 'COPC Exemption') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
