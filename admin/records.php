<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

function splitStudentEntriesForAdmin(?string $studentList): array {
    $raw = trim((string) $studentList);
    if ($raw === '') {
        return [];
    }

    $numberedEntries = [];
    if (preg_match_all('/(?:^|\n)\s*\d+[\.\)]\s*(.+)$/m', $raw, $matches)) {
        $numberedEntries = array_map('trim', $matches[1]);
        $numberedEntries = array_values(array_filter($numberedEntries, static fn($entry) => $entry !== ''));
    }

    if (!empty($numberedEntries)) {
        return array_values(array_unique($numberedEntries));
    }

    $entries = preg_split('/\r?\n|;/', $raw) ?: [];
    $entries = array_map('trim', $entries);
    $entries = array_values(array_filter($entries, static fn($entry) => $entry !== ''));

    return array_values(array_unique($entries));
}

$pageTitle = "All Records";
require_once __DIR__ . '/../includes/admin_header.php';

try {
    $pdo = getDBConnection();
    
    // Pagination
    $page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Filtering
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $category = isset($_GET['cat']) ? trim($_GET['cat']) : '';
    $regionFilter = isset($_GET['region']) ? trim($_GET['region']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    $where = ["1=1"];
    $params = [];
    
    if ($search !== '') {
        $where[] = "(school_name LIKE ? OR program LIKE ? OR region LIKE ? OR student_list LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($category !== '') {
        $where[] = "category = ?";
        $params[] = $category;
    }
    if ($regionFilter !== '') {
        $where[] = "region = ?";
        $params[] = $regionFilter;
    }
    if ($statusFilter !== '') {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    $whereSql = implode(' AND ', $where);
    
    // Count total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM copc_documents WHERE $whereSql");
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    
    // Fetch data
    $sql = "SELECT id, school_name, program, region, category, date_approved, status, student_list, entry_type, file_type, created_at 
            FROM copc_documents WHERE $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
} catch (\Exception $e) {
    die("Error loading records: " . $e->getMessage());
}

$csrfToken = generateCsrfToken();

// Helper for pagination links
function getPageUrl($p, $search, $category, $regionFilter, $statusFilter) {
    $params = [
        'p' => $p,
        'q' => $search,
        'cat' => $category,
        'region' => $regionFilter,
        'status' => $statusFilter
    ];
    return '?' . http_build_query($params);
}
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-prcnavy">Record Vault</h1>
        <p class="text-sm text-gray-500 mt-1">Manage all administrative documents and batch-imported lists. Total: <?php echo number_format($totalRecords); ?></p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100 mb-8">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Search Keywords</label>
            <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="School, Program, or Region..." class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Category</label>
            <select name="cat" class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                <option value="">All Categories</option>
                <option value="COPC" <?php echo $category==='COPC'?'selected':'';?>>COPC</option>
                <option value="GR" <?php echo $category==='GR'?'selected':'';?>>Government Recognition</option>
                <option value="COPC Exemption" <?php echo $category==='COPC Exemption'?'selected':'';?>>Exemption</option>
                <option value="HEI List" <?php echo $category==='HEI List'?'selected':'';?>>HEI List</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Status</label>
            <select name="status" class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                <option value="">All Status</option>
                <option value="NEW" <?php echo ($statusFilter==='NEW'?'selected':'');?>>NEW</option>
                <option value="OLD" <?php echo ($statusFilter==='OLD'?'selected':'');?>>OLD</option>
            </select>
        </div>
        <div class="md:col-span-4 flex justify-end items-center gap-6 pt-2 border-t border-gray-50 mt-2">
             <a href="records.php" class="text-xs text-gray-400 font-bold hover:text-prcnavy transition uppercase tracking-widest whitespace-nowrap">
                <i class="fa-solid fa-rotate-left mr-1"></i> Reset Filters
             </a>
            <button type="submit" class="bg-prcnavy hover:bg-prcaccent text-white py-3 px-10 rounded-xl font-bold text-sm shadow-md transition transform hover:-translate-y-0.5 flex items-center">
                <i class="fa-solid fa-filter mr-2 text-prcgold text-xs"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-soft border border-gray-100 overflow-hidden mb-12">
    <div class="overflow-x-auto">
        <table class="min-w-[1700px] w-full text-left border-collapse">
            <thead>
                <tr class="bg-prclight text-prcnavy text-xs font-bold uppercase tracking-widest">
                    <th class="px-6 py-4 border-b border-gray-100 w-[34rem]">School & Region</th>
                    <th class="px-6 py-4 border-b border-gray-100 w-[32rem]">Program Details</th>
                    <th class="px-6 py-4 border-b border-gray-100 w-[24rem]">Student Names</th>
                    <th class="px-6 py-4 border-b border-gray-100 w-[12rem]">Category</th>
                    <th class="px-6 py-4 border-b border-gray-100 w-[10rem]">Status</th>
                    <th class="px-6 py-4 border-b border-gray-100 w-[12rem]">Type</th>
                    <th class="px-6 py-4 border-b border-gray-100 text-right w-[10rem]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 text-sm">
                <?php if (empty($records)): ?>
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400 italic">No records found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                    <?php
                        $studentEntries = splitStudentEntriesForAdmin($r['student_list'] ?? '');
                        $studentPreview = 'N/A';
                        $studentExtraCount = 0;

                        if ($r['category'] === 'COPC Exemption' && !empty($studentEntries)) {
                            $previewEntries = array_slice($studentEntries, 0, 3);
                            $studentPreview = implode(', ', $previewEntries);
                            $studentExtraCount = count($studentEntries) - count($previewEntries);
                        }
                    ?>
                    <tr class="hover:bg-gray-50 transition group">
                        <td class="px-6 py-4 align-top">
                            <div class="font-bold text-prcnavy leading-snug min-w-[28rem]" title="<?php echo h($r['school_name']); ?>"><?php echo h($r['school_name']); ?></div>
                            <div class="text-[10px] font-bold text-gray-400 uppercase mt-0.5 flex items-center">
                                <i class="fa-solid fa-location-dot mr-1 text-prcgold/70"></i> <?php echo h($r['region'] ?: 'N/A'); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="font-medium text-gray-700 leading-relaxed min-w-[24rem]"><?php echo h($r['program']); ?></div>
                            <div class="text-[10px] text-gray-400 mt-0.5 uppercase tracking-wide">Approved: <?php echo date('M d, Y', strtotime($r['date_approved'])); ?></div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="min-w-[20rem] text-sm text-gray-600 leading-relaxed">
                                <?php echo h($studentPreview); ?>
                                <?php if ($studentExtraCount > 0): ?>
                                    <span class="text-xs text-gray-400">+<?php echo number_format($studentExtraCount); ?> more</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <?php if ($r['category'] === 'COPC'): ?>
                                <span class="px-3 py-1 text-[10px] font-bold rounded-full bg-prcnavy text-white">COPC</span>
                            <?php elseif ($r['category'] === 'GR'): ?>
                                <span class="px-3 py-1 text-[10px] font-bold rounded-full bg-emerald-600 text-white">GR</span>
                            <?php elseif ($r['category'] === 'HEI List'): ?>
                                <span class="px-3 py-1 text-[10px] font-bold rounded-full bg-purple-600 text-white">HEI</span>
                            <?php else: ?>
                                <span class="px-3 py-1 text-[10px] font-bold rounded-full bg-prcgold text-white">EXEMPT</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <?php if ($r['status'] === 'NEW'): ?>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-green-100 text-green-700 border border-green-200 uppercase">New</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-gray-100 text-gray-500 border border-gray-200 uppercase">Old</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <?php if ($r['entry_type'] === 'manual'): ?>
                                <span class="text-amber-600 bg-amber-50 px-2 py-1 rounded-lg text-[10px] font-bold uppercase border border-amber-100 inline-flex items-center">
                                    <i class="fa-solid fa-keyboard mr-1"></i> Data Only
                                </span>
                            <?php else: ?>
                                <span class="text-blue-600 bg-blue-50 px-2 py-1 rounded-lg text-[10px] font-bold uppercase border border-blue-100 inline-flex items-center">
                                    <i class="fa-solid fa-file-pdf mr-1"></i> Digital
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right space-x-1 align-top whitespace-nowrap">
                            <?php if ($r['entry_type'] === 'upload' && $r['file_type']): ?>
                                <a href="../download.php?id=<?php echo $r['id']; ?>" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-prclight text-prcnavy hover:bg-prcnavy hover:text-white transition shadow-sm" title="Download File">
                                    <i class="fa-solid fa-download text-xs"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="edit.php?id=<?php echo $r['id']; ?>" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition shadow-sm" title="Edit Metadata">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </a>
                            
                            <button type="button" onclick="confirmDelete(<?php echo $r['id']; ?>)" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition shadow-sm" title="Delete Record">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Scalable Pagination UI -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-6 border-t border-gray-100 bg-gray-50 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="text-sm font-medium text-gray-500">
            Showing <span class="text-prcnavy font-bold"><?php echo ($offset + 1); ?></span> to <span class="text-prcnavy font-bold"><?php echo min($totalRecords, $offset + $limit); ?></span> of <span class="text-prcnavy font-bold"><?php echo $totalRecords; ?></span> records
        </div>
        
        <nav class="flex items-center -space-x-px" aria-label="Pagination">
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="<?php echo getPageUrl($page - 1, $search, $category, $regionFilter, $statusFilter); ?>" class="relative inline-flex items-center px-4 py-2 rounded-l-xl border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">
                    <i class="fa-solid fa-chevron-left mr-2 text-[10px]"></i> Prev
                </a>
            <?php else: ?>
                <span class="relative inline-flex items-center px-4 py-2 rounded-l-xl border border-gray-200 bg-gray-50 text-sm font-bold text-gray-300 cursor-not-allowed">
                    <i class="fa-solid fa-chevron-left mr-2 text-[10px]"></i> Prev
                </span>
            <?php endif; ?>

            <?php
            // Logic for scalable page numbers (e.g., 1 ... 5 6 7 ... 20)
            $sidePages = 2;
            $startPage = max(1, $page - $sidePages);
            $endPage = min($totalPages, $page + $sidePages);

            if ($startPage > 1) {
                echo '<a href="'.getPageUrl(1, $search, $category, $regionFilter, $statusFilter).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">1</a>';
                if ($startPage > 2) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-400">...</span>';
            }

            for ($i = $startPage; $i <= $endPage; $i++) {
                $isCurrent = ($i === $page);
                $activeClass = $isCurrent ? 'bg-prcnavy text-white hover:bg-prcnavy' : 'bg-white text-gray-500 hover:bg-prclight hover:text-prcnavy';
                echo '<a href="'.getPageUrl($i, $search, $category, $regionFilter, $statusFilter).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 text-sm font-bold transition '.$activeClass.' shadow-sm">'.$i.'</a>';
            }

            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-400">...</span>';
                echo '<a href="'.getPageUrl($totalPages, $search, $category, $regionFilter, $statusFilter).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">'.$totalPages.'</a>';
            }
            ?>

            <!-- Next Button -->
            <?php if ($page < $totalPages): ?>
                <a href="<?php echo getPageUrl($page + 1, $search, $category, $regionFilter, $statusFilter); ?>" class="relative inline-flex items-center px-4 py-2 rounded-r-xl border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">
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
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" action="delete.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
    <input type="hidden" name="id" id="deleteId" value="">
</form>

<?php require_once __DIR__ . '/../includes/delete_modal.php'; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
