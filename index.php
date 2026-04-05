<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
$pageTitle = "Search Documents | PRC COPC";
require_once __DIR__ . '/includes/header.php';

// Prepare unique programs for filter dropdown
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT DISTINCT program FROM copc_documents ORDER BY program");
    $programs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT YEAR(date_approved) as yr FROM copc_documents ORDER BY yr DESC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Initial stats
    $statsStmt = $pdo->query("SELECT COUNT(*) FROM copc_documents");
    $totalRecords = $statsStmt->fetchColumn();
} catch (\Exception $e) {
    $programs = [];
    $years = [];
    $totalRecords = 0;
}
?>

<div class="relative w-full max-w-7xl mx-auto rounded-3xl" id="searchApp">
    
    <!-- Hero / Title Section -->
    <div class="mb-10 text-center px-4 flex flex-col items-center">
        <h1 class="text-4xl md:text-5xl font-extrabold text-prcnavy tracking-tight mb-4">
            Document Intelligence
        </h1>
        <p class="text-gray-500 text-lg md:text-xl font-light max-w-2xl mx-auto leading-relaxed">
            Search intuitively across <strong class="text-prcgold font-semibold"><?php echo number_format($totalRecords); ?></strong> official COPC certificates and exemptions using program names or full-text OCR capabilities.
        </p>
    </div>

    <!-- Minimalist Search Input Container -->
    <div class="relative max-w-3xl mx-auto mb-12 shadow-soft-lg group rounded-2xl bg-white transition hover:shadow-xl focus-within:shadow-xl focus-within:ring-4 ring-prcgold/20">
        <div class="absolute inset-y-0 left-6 flex items-center pointer-events-none">
            <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-prcgold transition-colors text-xl"></i>
        </div>
        <input type="text" id="searchInput" class="block w-full pl-16 pr-8 py-5 md:py-6 bg-transparent rounded-2xl leading-5 placeholder-gray-400 focus:outline-none text-xl lg:text-2xl text-prcnavy font-medium tracking-wide transition-all" placeholder="Search school, program, or file contents...">
    </div>

    <!-- Soft Filter Panel -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16 bg-white shadow-soft rounded-2xl p-6 lg:p-8 border border-gray-50 max-w-4xl mx-auto">
        
        <div class="relative">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2"><i class="fa-solid fa-folder-open mr-1"></i> Document Type</label>
            <div class="relative">
                <select id="filterCategory" class="block w-full appearance-none bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer">
                    <option value="">All Categories</option>
                    <option value="COPC">COPC Only</option>
                    <option value="GR">Government Recognition Only</option>
                    <option value="COPC Exemption">Exemptions Only</option>
                    <option value="HEI List">HEI List Only</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </div>
            </div>
        </div>
        
        <div class="relative">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2"><i class="fa-solid fa-graduation-cap mr-1"></i> Program</label>
            <div class="relative">
                <select id="filterProgram" class="block w-full appearance-none bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?php echo h($prog); ?>"><?php echo h($prog); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </div>
            </div>
        </div>
        
        <div class="relative">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2"><i class="fa-regular fa-calendar-check mr-1"></i> Approval Year</label>
            <div class="relative">
                <select id="filterYear" class="block w-full appearance-none bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer">
                    <option value="">Any Year</option>
                    <?php foreach ($years as $yr): ?>
                        <option value="<?php echo h($yr); ?>"><?php echo h($yr); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Results State Display -->
    <div class="flex justify-between items-center mb-8 border-b border-gray-100 pb-4 px-2">
        <h2 class="text-xl font-bold text-prcnavy flex items-center">
            Search Results <span class="bg-gray-100 text-gray-600 rounded-full px-3 py-1 font-bold text-sm ml-3 tracking-wider shadow-inner" id="resultCount">0</span>
        </h2>
        <div id="loadingIndicator" class="hidden text-prcgold">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl"></i>
        </div>
    </div>

    <!-- Results Table -->
    <div id="resultsTableWrapper" class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1500px] text-left border-collapse">
                <thead class="bg-prclight">
                    <tr id="tableHeader" class="text-xs font-bold uppercase tracking-widest text-prcnavy">
                        <th class="px-6 py-4 border-b border-gray-100 w-[14rem]">Category</th>
                        <th class="px-6 py-4 border-b border-gray-100 w-[34rem]">School</th>
                        <th class="px-6 py-4 border-b border-gray-100 w-[32rem]">Program</th>
                        <th class="px-6 py-4 border-b border-gray-100 w-[18rem]">Student Names</th>
                        <th class="px-6 py-4 border-b border-gray-100 w-[14rem]">Region</th>
                        <th class="px-6 py-4 border-b border-gray-100 w-[12rem]">Approved</th>
                        <th class="px-6 py-4 border-b border-gray-100 w-[12rem]">Type</th>
                        <th class="px-6 py-4 border-b border-gray-100 text-right w-[10rem]">Action</th>
                    </tr>
                </thead>
                <tbody id="resultsGrid" class="divide-y divide-gray-100 bg-white">
                    <!-- Results injected via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <div id="resultModal" class="hidden fixed inset-0 z-50 bg-prcnavy/70 p-4">
        <div class="flex min-h-screen items-center justify-center">
            <div class="relative w-full max-w-3xl rounded-3xl bg-white shadow-soft-lg overflow-hidden">
                <button id="closeResultModal" type="button" class="absolute right-4 top-4 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-900 transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
                <div id="resultModalContent" class="p-6 lg:p-8">
                    <!-- Modal card injected via JS -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Empty State / No Results -->
    <div id="emptyState" class="text-center py-20 hidden bg-white rounded-3xl shadow-sm border border-gray-50">
        <i class="fa-solid fa-file-circle-xmark text-6xl text-gray-200 mb-6 mx-auto block"></i>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">No Records Found</h3>
        <p class="text-gray-500 font-light max-w-sm mx-auto">Adjust your search terms or filters to find what you're looking for.</p>
    </div>

    <!-- Load More Button -->
    <div id="loadMoreContainer" class="mt-16 text-center hidden mb-10">
        <button id="loadMoreBtn" class="bg-white text-prcnavy border border-gray-200 shadow-sm px-10 py-4 rounded-full hover:bg-prclight hover:text-prcaccent font-bold tracking-widest text-sm uppercase transition cursor-pointer hover:shadow-soft flex items-center justify-center mx-auto">
            <i class="fa-solid fa-arrow-down mr-3"></i> Load More Records
        </button>
    </div>
</div>

<script>
    const API_BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/search.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
