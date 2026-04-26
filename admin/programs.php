<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = "Program Manager";
require_once __DIR__ . '/../includes/admin_header.php';

// ── classify helper (mirrors index.php) ──────────────────────────────────────
function classifyProgram(string $prog): string {
    if (preg_match('/^(doctor|ph\.?d\.?\b)/i', $prog))                          return 'Doctoral';
    if (preg_match('/^(master|masters\b|maed|magdev|ma\s|ma$|ms\s|ms$|m\.s\.|m\.a\.)/i', $prog)) return 'Masters';
    if (preg_match('/^(certificate|diploma)/i', $prog))                          return 'Certificate & Diploma';
    if (preg_match('/^associate/i', $prog))
        return preg_match('/ladderized/i', $prog) ? 'Ladderized Programs' : 'Associate Programs';
    if (preg_match('/ladderized/i', $prog))                                      return 'Ladderized Programs';
    if (preg_match('/^(bachelor|bachetor|bacholor|bahcelor|batsilyer|ba\s|ba$|bs\s|bs$|bsed|bstte|btvted|bsied)/i', $prog)) return 'Bachelor Programs';
    return 'Other Programs';
}

$levelIcon = [
    'Doctoral'             => 'fa-user-graduate',
    'Masters'              => 'fa-scroll',
    'Bachelor Programs'    => 'fa-graduation-cap',
    'Associate Programs'   => 'fa-award',
    'Ladderized Programs'  => 'fa-stairs',
    'Certificate & Diploma'=> 'fa-certificate',
    'Other Programs'       => 'fa-list',
];

$levelColor = [
    'Doctoral'              => 'bg-purple-100 text-purple-700',
    'Masters'               => 'bg-blue-100 text-blue-700',
    'Bachelor Programs'     => 'bg-prcnavy/10 text-prcnavy',
    'Associate Programs'    => 'bg-teal-100 text-teal-700',
    'Ladderized Programs'   => 'bg-orange-100 text-orange-700',
    'Certificate & Diploma' => 'bg-amber-100 text-amber-700',
    'Other Programs'        => 'bg-gray-100 text-gray-600',
];

try {
    $pdo = getDBConnection();

    // Pagination & Search
    $page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    $where = ["program NOT LIKE 'Institution Profile%'"];
    $params = [];

    if ($search !== '') {
        $where[] = "program LIKE ?";
        $params[] = "%$search%";
    }
    $whereSql = implode(' AND ', $where);

    // Summary Statistics (Entire Database)
    $summary = $pdo->query("SELECT COUNT(DISTINCT program) as dist, COUNT(*) as rec FROM copc_documents WHERE program NOT LIKE 'Institution Profile%'")->fetch();
    $totalDistinct = (int) $summary['dist'];
    $totalRecords  = (int) $summary['rec'];

    // Matching Count (for pagination)
    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT program) FROM copc_documents WHERE $whereSql");
    $countStmt->execute($params);
    $totalMatch = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalMatch / $limit);

    // Fetch Paginated Rows
    $sql = "SELECT program, COUNT(*) as record_count
            FROM copc_documents
            WHERE $whereSql
            GROUP BY program
            ORDER BY program ASC
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

} catch (\Exception $e) {
    $rows = [];
    $totalDistinct = $totalRecords = $totalMatch = $totalPages = 0;
}

function getProgramPageUrl($p, $search) {
    $params = ['p' => $p];
    if ($search !== '') $params['q'] = $search;
    return '?' . http_build_query($params);
}

$csrfToken = generateCsrfToken();
?>

<!-- ── Page Header ──────────────────────────────────────────────────────────── -->
<div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-prcnavy flex items-center gap-3">
            <i class="fa-solid fa-tags text-prcgold"></i> Program Manager
        </h1>
        <p class="text-sm text-gray-400 mt-1">Rename or merge duplicate/variant program names across all records.</p>
    </div>
    <div class="flex gap-3 items-center">
        <div class="bg-white border border-gray-100 rounded-2xl px-5 py-3 shadow-sm text-center min-w-[110px]">
            <div class="text-2xl font-black text-prcnavy"><?php echo number_format($totalDistinct); ?></div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Distinct Programs</div>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl px-5 py-3 shadow-sm text-center min-w-[110px]">
            <div class="text-2xl font-black text-prcgold"><?php echo number_format($totalRecords); ?></div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Total Records</div>
        </div>
    </div>
</div>

<!-- ── Info Banner ────────────────────────────────────────────────────────────── -->
<div class="mb-6 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 text-sm text-amber-800">
    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500 flex-shrink-0"></i>
    <p>
        <strong>Rename</strong> updates a single program's name across all its records.
        <strong class="ml-2">Merge</strong> (select 2 or more) consolidates all selected programs into one canonical name.
        Both actions are <strong>irreversible</strong> — double-check before confirming.
    </p>
</div>

<!-- ── Toolbar ───────────────────────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-soft border border-gray-100 p-4 mb-6 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
    <form method="GET" action="" class="relative flex-1 max-w-md">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm pointer-events-none"></i>
        <input type="text" name="q" id="programSearch" placeholder="Search programs…" value="<?php echo h($search); ?>"
               class="w-full bg-gray-50 pl-9 pr-4 py-2.5 rounded-xl text-sm text-prcnavy placeholder-gray-300 border-none focus:ring-2 focus:ring-prcgold focus:outline-none transition">
    </form>
    <div class="flex items-center gap-3">
        <span id="selectionCount" class="hidden text-xs font-bold text-prcnavy bg-prclight px-3 py-1.5 rounded-full border border-gray-200"></span>
        <button id="mergeBtn" type="button"
                class="hidden items-center gap-2 bg-prcnavy text-white text-xs font-bold uppercase tracking-widest px-4 py-2.5 rounded-xl shadow hover:bg-prcaccent transition"
                onclick="openMergeModal()">
            <i class="fa-solid fa-code-merge"></i> Merge Selected
        </button>
    </div>
</div>

<!-- ── Programs Table ─────────────────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-soft border border-gray-100 overflow-hidden mb-12">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-left border-collapse" id="programsTable">
            <thead class="bg-prclight text-prcnavy text-xs font-bold uppercase tracking-widest">
                <tr>
                    <th class="px-5 py-4 border-b border-gray-100 w-10">
                        <input type="checkbox" id="selectAll" class="h-4 w-4 rounded border-gray-300 text-prcnavy focus:ring-prcgold cursor-pointer">
                    </th>
                    <th class="px-5 py-4 border-b border-gray-100">Program Name</th>
                    <th class="px-5 py-4 border-b border-gray-100 w-44">Level</th>
                    <th class="px-5 py-4 border-b border-gray-100 w-32 text-center">Records</th>
                    <th class="px-5 py-4 border-b border-gray-100 w-28 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 text-sm">
                <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">No programs found.</td></tr>
                <?php else: ?>
                <?php foreach ($rows as $row):
                    $prog  = $row['program'];
                    $count = $row['record_count'];
                    $level = classifyProgram($prog);
                    $icon  = $levelIcon[$level]  ?? 'fa-list';
                    $color = $levelColor[$level] ?? 'bg-gray-100 text-gray-600';
                ?>
                <tr class="program-row hover:bg-gray-50 transition" data-program="<?php echo h(strtolower($prog)); ?>">
                    <td class="px-5 py-4 align-middle">
                        <input type="checkbox" class="prog-checkbox h-4 w-4 rounded border-gray-300 text-prcnavy focus:ring-prcgold cursor-pointer"
                               data-name="<?php echo h($prog); ?>">
                    </td>
                    <td class="px-5 py-4 align-middle font-medium text-gray-800 leading-snug">
                        <?php echo h($prog); ?>
                    </td>
                    <td class="px-5 py-4 align-middle">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full <?php echo $color; ?>">
                            <i class="fa-solid <?php echo $icon; ?> text-[10px]"></i>
                            <?php echo h($level); ?>
                        </span>
                    </td>
                    <td class="px-5 py-4 align-middle text-center">
                        <span class="inline-block bg-gray-100 text-gray-600 text-xs font-bold px-3 py-1 rounded-full">
                            <?php echo number_format($count); ?>
                        </span>
                    </td>
                    <td class="px-5 py-4 align-middle text-right">
                        <button type="button"
                                class="rename-btn inline-flex items-center gap-1.5 text-xs font-bold text-prcnavy bg-prclight border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-prcnavy hover:text-white hover:border-prcnavy transition shadow-sm"
                                data-program="<?php echo h($prog); ?>"
                                data-count="<?php echo $count; ?>">
                            <i class="fa-solid fa-pen-to-square text-[10px]"></i> Rename
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div id="noResults" class="<?php echo empty($rows) ? '' : 'hidden'; ?> py-12 text-center text-gray-400 italic text-sm">No programs match your search.</div>

    <!-- Scalable Pagination UI -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-6 border-t border-gray-100 bg-gray-50 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="text-sm font-medium text-gray-500">
            Showing <span class="text-prcnavy font-bold"><?php echo ($offset + 1); ?></span> to <span class="text-prcnavy font-bold"><?php echo min($totalMatch, $offset + $limit); ?></span> of <span class="text-prcnavy font-bold"><?php echo $totalMatch; ?></span> matching programs
        </div>
        
        <nav class="flex items-center -space-x-px" aria-label="Pagination">
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="<?php echo getProgramPageUrl($page - 1, $search); ?>" class="relative inline-flex items-center px-4 py-2 rounded-l-xl border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">
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
            $endPage = min($totalPages, $page + $sidePages);

            if ($startPage > 1) {
                echo '<a href="'.getProgramPageUrl(1, $search).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">1</a>';
                if ($startPage > 2) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-400">...</span>';
            }

            for ($i = $startPage; $i <= $endPage; $i++) {
                $isCurrent = ($i === $page);
                $activeClass = $isCurrent ? 'bg-prcnavy text-white hover:bg-prcnavy' : 'bg-white text-gray-500 hover:bg-prclight hover:text-prcnavy';
                echo '<a href="'.getProgramPageUrl($i, $search).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 text-sm font-bold transition '.$activeClass.' shadow-sm">'.$i.'</a>';
            }

            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-400">...</span>';
                echo '<a href="'.getProgramPageUrl($totalPages, $search).'" class="relative inline-flex items-center px-4 py-2 border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">'.$totalPages.'</a>';
            }
            ?>

            <!-- Next Button -->
            <?php if ($page < $totalPages): ?>
                <a href="<?php echo getProgramPageUrl($page + 1, $search); ?>" class="relative inline-flex items-center px-4 py-2 rounded-r-xl border border-gray-200 bg-white text-sm font-bold text-gray-500 hover:bg-prclight hover:text-prcnavy transition">
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

<!-- ══════════════════════════════════════════════════════════════════════════════
     RENAME MODAL
══════════════════════════════════════════════════════════════════════════════ -->
<div id="renameModal" class="hidden fixed inset-0 z-50 bg-prcnavy/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-prclight border-b border-gray-100 px-7 py-5 flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-prcnavy flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-prcgold"></i> Rename Program
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">Updates <strong id="renameCount">0</strong> record(s) in the database.</p>
            </div>
            <button type="button" onclick="closeRenameModal()"
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-400 hover:text-gray-700 transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <!-- Body -->
        <div class="px-7 py-6 space-y-5">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Current Name</label>
                <div id="renameOldDisplay" class="bg-red-50 border border-red-100 text-red-700 font-semibold text-sm px-4 py-3 rounded-xl leading-snug break-words"></div>
            </div>
            <div class="flex items-center justify-center text-gray-300">
                <i class="fa-solid fa-arrow-down text-lg"></i>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">New Name <span class="text-red-400">*</span></label>
                <input type="text" id="renameNewInput"
                       class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy text-sm focus:ring-2 focus:ring-prcgold focus:outline-none transition"
                       placeholder="Enter the correct program name…">
                <p id="renameError" class="hidden mt-2 text-xs text-red-500 font-semibold"></p>
            </div>
        </div>
        <!-- Footer -->
        <div class="px-7 pb-7 flex gap-3">
            <button type="button" onclick="closeRenameModal()"
                    class="flex-1 border border-gray-200 text-gray-500 font-bold text-sm py-3 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" id="renameConfirmBtn" onclick="submitRename()"
                    class="flex-1 bg-prcnavy text-white font-bold text-sm py-3 rounded-xl hover:bg-prcaccent transition shadow-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-check"></i> Confirm Rename
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MERGE MODAL
══════════════════════════════════════════════════════════════════════════════ -->
<div id="mergeModal" class="hidden fixed inset-0 z-50 bg-prcnavy/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-prclight border-b border-gray-100 px-7 py-5 flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-prcnavy flex items-center gap-2">
                    <i class="fa-solid fa-code-merge text-prcgold"></i> Merge Programs
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">All selected programs will be renamed to the target name.</p>
            </div>
            <button type="button" onclick="closeMergeModal()"
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-400 hover:text-gray-700 transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <!-- Body -->
        <div class="px-7 py-6 space-y-5">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Programs Being Merged</label>
                <div id="mergeSourceList" class="space-y-1.5 max-h-40 overflow-y-auto"></div>
            </div>
            <div class="flex items-center justify-center text-gray-300">
                <i class="fa-solid fa-arrows-to-dot text-lg"></i>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Target Name <span class="text-red-400">*</span></label>
                <input type="text" id="mergeTargetInput"
                       class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy text-sm focus:ring-2 focus:ring-prcgold focus:outline-none transition"
                       placeholder="Enter the canonical program name…">
                <p class="text-[10px] text-gray-400 mt-1.5">Tip: pick one of the names above or type a corrected version.</p>
                <p id="mergeError" class="hidden mt-2 text-xs text-red-500 font-semibold"></p>
            </div>
        </div>
        <!-- Footer -->
        <div class="px-7 pb-7 flex gap-3">
            <button type="button" onclick="closeMergeModal()"
                    class="flex-1 border border-gray-200 text-gray-500 font-bold text-sm py-3 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" id="mergeConfirmBtn" onclick="submitMerge()"
                    class="flex-1 bg-prcnavy text-white font-bold text-sm py-3 rounded-xl hover:bg-prcaccent transition shadow-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-check"></i> Confirm Merge
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     SUCCESS TOAST
══════════════════════════════════════════════════════════════════════════════ -->
<div id="successToast"
     class="hidden fixed bottom-6 right-6 z-[100] max-w-sm bg-white border border-green-200 shadow-xl rounded-2xl px-5 py-4 flex items-start gap-3 transition-all">
    <div class="w-8 h-8 flex-shrink-0 bg-green-100 rounded-full flex items-center justify-center">
        <i class="fa-solid fa-check text-green-600 text-sm"></i>
    </div>
    <div>
        <p class="text-xs font-bold uppercase tracking-widest text-green-700 mb-0.5">Success</p>
        <p id="toastMessage" class="text-sm text-gray-700 font-medium"></p>
    </div>
</div>

<script>
(function () {
    const API_BASE  = '<?php echo BASE_URL; ?>';
    const CSRF      = '<?php echo h($csrfToken); ?>';

    // ── Search filter (removed client-side logic, using server-side search) ─────
    // searchInput handler is no longer needed as we use a form GET for pagination.
    // We can however add a small delay if we wanted to auto-submit, but standard GET is fine.

    // ── Checkbox logic ─────────────────────────────────────────────────────────
    const selectAll      = document.getElementById('selectAll');
    const selectionCount = document.getElementById('selectionCount');
    const mergeBtn       = document.getElementById('mergeBtn');

    function getChecked() {
        return [...document.querySelectorAll('.prog-checkbox:checked')];
    }

    function syncSelection() {
        const checked = getChecked();
        const all     = [...document.querySelectorAll('.prog-checkbox')];
        selectAll.checked       = checked.length === all.length && all.length > 0;
        selectAll.indeterminate = checked.length > 0 && checked.length < all.length;

        if (checked.length === 0) {
            selectionCount.classList.add('hidden');
            mergeBtn.classList.add('hidden');
            mergeBtn.classList.remove('inline-flex');
        } else {
            selectionCount.textContent = `${checked.length} selected`;
            selectionCount.classList.remove('hidden');
            if (checked.length >= 2) {
                mergeBtn.classList.remove('hidden');
                mergeBtn.classList.add('inline-flex');
            } else {
                mergeBtn.classList.add('hidden');
                mergeBtn.classList.remove('inline-flex');
            }
        }
    }

    selectAll.addEventListener('change', () => {
        document.querySelectorAll('.prog-checkbox').forEach(cb => {
            const row = cb.closest('.program-row');
            if (!row.classList.contains('hidden')) cb.checked = selectAll.checked;
        });
        syncSelection();
    });
    document.querySelectorAll('.prog-checkbox').forEach(cb =>
        cb.addEventListener('change', syncSelection)
    );

    // ── Rename modal ───────────────────────────────────────────────────────────
    let currentOldName = '';

    window.openRenameModal = function (programName, count) {
        currentOldName = programName;
        document.getElementById('renameOldDisplay').textContent  = programName;
        document.getElementById('renameCount').textContent       = count;
        document.getElementById('renameNewInput').value          = programName;
        document.getElementById('renameError').classList.add('hidden');
        document.getElementById('renameModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('renameNewInput').focus(), 80);
    };

    window.closeRenameModal = function () {
        document.getElementById('renameModal').classList.add('hidden');
    };

    window.submitRename = async function () {
        const newName = document.getElementById('renameNewInput').value.trim();
        const errEl   = document.getElementById('renameError');
        errEl.classList.add('hidden');

        if (!newName) { errEl.textContent = 'New name cannot be empty.'; errEl.classList.remove('hidden'); return; }
        if (newName === currentOldName) { errEl.textContent = 'New name is the same as the current name.'; errEl.classList.remove('hidden'); return; }

        setLoadingBtn('renameConfirmBtn', true);

        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('old_name',   currentOldName);
        fd.append('new_name',   newName);

        const ok = await callRenameApi(fd);
        setLoadingBtn('renameConfirmBtn', false);
        if (ok) { closeRenameModal(); reloadAfterDelay(); }
    };

    // Wire rename buttons
    document.querySelectorAll('.rename-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            openRenameModal(btn.dataset.program, btn.dataset.count);
        });
    });

    // Enter key in rename input
    document.getElementById('renameNewInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') window.submitRename();
    });

    // ── Merge modal ────────────────────────────────────────────────────────────
    window.openMergeModal = function () {
        const checked = getChecked();
        const sourceList = document.getElementById('mergeSourceList');
        sourceList.innerHTML = '';
        let longestName = '';
        checked.forEach(cb => {
            const name = cb.dataset.name;
            if (name.length > longestName.length) longestName = name;
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2 bg-red-50 border border-red-100 text-red-700 text-xs font-semibold px-3 py-2 rounded-xl leading-snug break-words';
            div.innerHTML = `<i class="fa-solid fa-xmark-circle flex-shrink-0 text-red-400"></i><span>${escHtml(name)}</span>`;
            sourceList.appendChild(div);
        });
        document.getElementById('mergeTargetInput').value = longestName;
        document.getElementById('mergeError').classList.add('hidden');
        document.getElementById('mergeModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('mergeTargetInput').focus(), 80);
    };

    window.closeMergeModal = function () {
        document.getElementById('mergeModal').classList.add('hidden');
    };

    window.submitMerge = async function () {
        const newName = document.getElementById('mergeTargetInput').value.trim();
        const errEl   = document.getElementById('mergeError');
        errEl.classList.add('hidden');
        if (!newName) { errEl.textContent = 'Target name cannot be empty.'; errEl.classList.remove('hidden'); return; }

        setLoadingBtn('mergeConfirmBtn', true);

        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('new_name',   newName);
        getChecked().forEach(cb => fd.append('old_names[]', cb.dataset.name));

        const ok = await callRenameApi(fd);
        setLoadingBtn('mergeConfirmBtn', false);
        if (ok) { closeMergeModal(); reloadAfterDelay(); }
    };

    // Enter key in merge input
    document.getElementById('mergeTargetInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') window.submitMerge();
    });

    // ── Shared helpers ─────────────────────────────────────────────────────────
    async function callRenameApi(formData) {
        try {
            const res  = await fetch(`${API_BASE}/api/rename_program.php`, { method: 'POST', body: formData });
            const json = await res.json();
            if (json.success) {
                showToast(json.message);
                return true;
            } else {
                const errId = document.activeElement?.closest('#renameModal') ? 'renameError' : 'mergeError';
                const errEl = document.getElementById(errId) ?? document.getElementById('renameError');
                errEl.textContent = json.error;
                errEl.classList.remove('hidden');
                return false;
            }
        } catch (err) {
            console.error(err);
            return false;
        }
    }

    function setLoadingBtn(id, loading) {
        const btn = document.getElementById(id);
        btn.disabled = loading;
        btn.innerHTML = loading
            ? '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Working…'
            : (id === 'renameConfirmBtn'
                ? '<i class="fa-solid fa-check mr-2"></i> Confirm Rename'
                : '<i class="fa-solid fa-check mr-2"></i> Confirm Merge');
    }

    function showToast(msg) {
        const toast = document.getElementById('successToast');
        document.getElementById('toastMessage').textContent = msg;
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 4500);
    }

    function reloadAfterDelay() {
        setTimeout(() => location.reload(), 1800);
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Close modals on backdrop click
    document.getElementById('renameModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeRenameModal(); });
    document.getElementById('mergeModal').addEventListener('click',  e => { if (e.target === e.currentTarget) closeMergeModal();  });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeRenameModal(); closeMergeModal(); }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
