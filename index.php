<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
$pageTitle = "Search Documents | PRC COPC";
require_once __DIR__ . '/includes/header.php';
?>
<style>
  mark {
    background-color: #fef08a;
    color: #713f12;
    border-radius: 3px;
    padding: 0 2px;
    font-weight: 600;
  }
  #chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }
  .filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 9999px;
    padding: 4px 12px;
    transition: background 0.15s;
  }
  .filter-chip button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 9999px;
    background: #bfdbfe;
    color: #1e3a8a;
    font-size: 10px;
    font-weight: 900;
    cursor: pointer;
    border: none;
    line-height: 1;
    transition: background 0.15s;
  }
  .filter-chip button:hover { background: #93c5fd; }
  #search-hint { transition: opacity 0.2s; }

  /* ── Grouped Program Dropdown ─────────────────────── */
  /* ── Shared Custom Dropdown Panel ───────────────────────────────────── */
  .custom-dd-panel {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    min-width: max(100%, 260px);
    max-width: 400px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,.12);
    z-index: 200;
    overflow: hidden;
  }
  .custom-dd-panel.hidden { display: none; }
  /* Program panel needs to be wider to show full names */
  #programDropdownPanel {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    min-width: max(100%, 420px);
    max-width: 540px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,.12);
    z-index: 200;
    overflow: hidden;
  }
  #programSearchInput {
    width: 100%;
    border: none;
    border-bottom: 1px solid #f3f4f6;
    padding: 10px 14px;
    font-size: 0.82rem;
    color: #1e3a5f;
    outline: none;
    background: #fafafa;
    border-radius: 0;
  }
  #programDropdownList {
    max-height: 260px;
    overflow-y: auto;
    padding: 4px 0;
  }
  .prog-group-header {
    padding: 6px 14px 3px;
    font-size: 0.65rem;
    font-weight: 900;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #9ca3af;
    background: #f9fafb;
    border-top: 1px solid #f3f4f6;
    pointer-events: none;
    user-select: none;
  }
  .prog-group-header:first-child { border-top: none; }
  .prog-item {
    padding: 7px 14px 7px 22px;
    font-size: 0.82rem;
    color: #1e3a5f;
    cursor: pointer;
    white-space: normal;
    line-height: 1.4;
    transition: background 0.1s;
  }
  .prog-item:hover { background: #eff6ff; color: #1d4ed8; }
  .prog-item.selected { background: #dbeafe; font-weight: 700; color: #1d4ed8; }
  .prog-item-all {
    padding: 8px 14px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #6b7280;
    cursor: pointer;
    transition: background 0.1s;
  }
  .prog-item-all:hover { background: #f3f4f6; }
  .prog-item-all.selected { background: #dbeafe; color: #1d4ed8; }
  .prog-no-results {
    padding: 14px;
    font-size: 0.8rem;
    color: #9ca3af;
    text-align: center;
  }
</style>

<?php
// ── Program grouping helper ───────────────────────────────────────────────
function classifyProgram(string $prog): string {
    // Doctoral
    if (preg_match('/^(doctor|ph\.?d\.?\b)/i', $prog)) return 'Doctoral';
    // Masters
    if (preg_match('/^(master|masters\b|maed|magdev|ma\s|ma$|ms\s|ms$|m\.s\.|m\.a\.)/i', $prog)) return 'Masters';
    // Certificate / Diploma
    if (preg_match('/^(certificate|diploma)/i', $prog)) return 'Certificate & Diploma';
    // Associate
    if (preg_match('/^associate/i', $prog)) {
        // Ladderized Associate falls under Ladderized
        if (preg_match('/ladderized/i', $prog)) return 'Ladderized Programs';
        return 'Associate Programs';
    }
    // Ladderized (any level)
    if (preg_match('/ladderized/i', $prog)) return 'Ladderized Programs';
    // Bachelor variants (including typos: Bachetor, Bacholor, Bahcelor, Batsilyer)
    if (preg_match('/^(bachelor|bachetor|bacholor|bahcelor|batsilyer|ba\s|ba$|bs\s|bs$|bsed|bstte|btvted|bsied)/i', $prog)) return 'Bachelor Programs';
    // Other
    return 'Other Programs';
}

// Group order
$groupOrder = [
    'Doctoral',
    'Masters',
    'Bachelor Programs',
    'Associate Programs',
    'Ladderized Programs',
    'Certificate & Diploma',
    'Other Programs',
];

// Prepare unique programs for filter dropdown
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT DISTINCT program FROM copc_documents WHERE program NOT LIKE 'Institution Profile%' ORDER BY program");
    $programs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Build grouped structure
    $groupedPrograms = array_fill_keys($groupOrder, []);
    foreach ($programs as $prog) {
        if (!$prog) continue;
        $group = classifyProgram($prog);
        if (!isset($groupedPrograms[$group])) $groupedPrograms[$group] = [];
        $groupedPrograms[$group][] = $prog;
    }
    // Remove empty groups
    $groupedPrograms = array_filter($groupedPrograms, fn($g) => count($g) > 0);

    $stmt = $pdo->query("SELECT DISTINCT YEAR(date_approved) as yr FROM copc_documents ORDER BY yr DESC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Initial stats
    $statsStmt = $pdo->query("SELECT COUNT(*) FROM copc_documents");
    $totalRecords = $statsStmt->fetchColumn();
} catch (\Exception $e) {
    $programs = [];
    $groupedPrograms = [];
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
    <div class="relative max-w-3xl mx-auto shadow-soft-lg group rounded-2xl bg-white transition hover:shadow-xl focus-within:shadow-xl focus-within:ring-4 ring-prcgold/20">
        <div class="absolute inset-y-0 left-6 flex items-center pointer-events-none">
            <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-prcgold transition-colors text-xl"></i>
        </div>
        <input type="text" id="searchInput" class="block w-full pl-16 pr-14 py-5 md:py-6 bg-transparent rounded-2xl leading-5 placeholder-gray-400 focus:outline-none text-xl lg:text-2xl text-prcnavy font-medium tracking-wide transition-all" placeholder="Search school, program, or file contents...">
        <button id="clearSearchBtn" type="button" class="hidden absolute inset-y-0 right-0 flex items-center justify-center w-14 text-gray-400 hover:text-prcnavy transition-colors" title="Clear search" aria-label="Clear search">
            <i class="fa-solid fa-circle-xmark text-xl"></i>
        </button>
    </div>

    <!-- Search Hint -->
    <div class="max-w-3xl mx-auto mb-12 mt-3 px-1">
        <p id="search-hint" class="text-xs text-gray-400 text-center">
            Try: <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 font-mono">program: nursing</code> &nbsp;·&nbsp;
            <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 font-mono">school: ust</code> &nbsp;·&nbsp;
            <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 font-mono">year: 2023</code>
        </p>
    </div>

    <!-- Soft Filter Panel -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-16 bg-white shadow-soft rounded-2xl p-6 border border-gray-50 max-w-6xl mx-auto">

        <!-- ── Document Type ─────────────────────────────────────── -->
        <div class="relative" id="categoryDropdownWrapper">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                <i class="fa-solid fa-folder-open mr-1"></i> Document Type
            </label>
            <select id="filterCategory" class="sr-only" aria-hidden="true" tabindex="-1">
                <option value="">All Categories</option>
                <option value="COPC">COPC Only</option>
                <option value="GR">Government Recognition Only</option>
                <option value="COPC Exemption">Exemptions Only</option>
                <option value="HEI List">HEI List Only</option>
            </select>
            <button type="button" id="categoryDropdownTrigger"
                class="flex w-full items-center justify-between bg-gray-50 rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer text-sm"
                aria-haspopup="listbox" aria-expanded="false">
                <span id="categoryDropdownLabel" class="truncate">All Categories</span>
                <i class="fa-solid fa-chevron-down text-xs text-gray-400 ml-2 flex-shrink-0" id="categoryDropdownChevron"></i>
            </button>
            <div id="categoryDropdownPanel" class="hidden custom-dd-panel">
                <div id="categoryDropdownList" role="listbox">
                    <div class="prog-item-all selected" role="option" data-value="" aria-selected="true">All Categories</div>
                    <div class="prog-item" role="option" data-value="COPC"><i class="fa-solid fa-certificate mr-1.5 text-prcgold/80"></i> COPC Only</div>
                    <div class="prog-item" role="option" data-value="GR"><i class="fa-solid fa-landmark mr-1.5 text-emerald-600/80"></i> Government Recognition Only</div>
                    <div class="prog-item" role="option" data-value="COPC Exemption"><i class="fa-solid fa-circle-check mr-1.5 text-amber-500/80"></i> Exemptions Only</div>
                    <div class="prog-item" role="option" data-value="HEI List"><i class="fa-solid fa-building-columns mr-1.5 text-purple-600/80"></i> HEI List Only</div>
                </div>
            </div>
        </div>

        <!-- ── Program ───────────────────────────────────────────── -->
        <div class="relative" id="programDropdownWrapper">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                <i class="fa-solid fa-graduation-cap mr-1"></i> Program
            </label>
            <select id="filterProgram" class="sr-only" aria-hidden="true" tabindex="-1">
                <option value="">All Programs</option>
                <?php foreach ($programs as $prog): ?>
                    <option value="<?php echo h($prog); ?>"><?php echo h($prog); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="programDropdownTrigger"
                class="flex w-full items-center justify-between bg-gray-50 rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer text-sm"
                aria-haspopup="listbox" aria-expanded="false">
                <span id="programDropdownLabel" class="truncate">All Programs</span>
                <i class="fa-solid fa-chevron-down text-xs text-gray-400 ml-2 flex-shrink-0" id="programDropdownChevron"></i>
            </button>
            <div id="programDropdownPanel" class="hidden custom-dd-panel" style="min-width:max(100%,420px);max-width:540px">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs pointer-events-none"></i>
                    <input type="text" id="programSearchInput" placeholder="Search programs..." autocomplete="off" style="padding-left:30px">
                </div>
                <div id="programDropdownList" role="listbox">
                    <div class="prog-item-all selected" role="option" data-value="" aria-selected="true">All Programs</div>
                    <?php foreach ($groupedPrograms as $groupName => $groupProgs): ?>
                    <div class="prog-group-header" data-group>
                        <?php
                        $icon = match($groupName) {
                            'Doctoral'              => 'fa-user-graduate',
                            'Masters'               => 'fa-scroll',
                            'Bachelor Programs'     => 'fa-graduation-cap',
                            'Associate Programs'    => 'fa-award',
                            'Ladderized Programs'   => 'fa-stairs',
                            'Certificate & Diploma' => 'fa-certificate',
                            default                 => 'fa-list',
                        };
                        ?>
                        <i class="fa-solid <?php echo $icon; ?> mr-1 opacity-60"></i><?php echo h($groupName); ?>
                    </div>
                    <?php foreach ($groupProgs as $prog): ?>
                    <div class="prog-item" role="option" data-value="<?php echo h($prog); ?>"><?php echo h($prog); ?></div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <div class="prog-no-results hidden">No programs match your search</div>
                </div>
            </div>
        </div>

        <!-- ── Approval Year ─────────────────────────────────────── -->
        <div class="relative" id="yearDropdownWrapper">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                <i class="fa-regular fa-calendar-check mr-1"></i> Approval Year
            </label>
            <select id="filterYear" class="sr-only" aria-hidden="true" tabindex="-1">
                <option value="">Any Year</option>
                <?php foreach ($years as $yr): ?>
                    <option value="<?php echo h($yr); ?>"><?php echo h($yr); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="yearDropdownTrigger"
                class="flex w-full items-center justify-between bg-gray-50 rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer text-sm"
                aria-haspopup="listbox" aria-expanded="false">
                <span id="yearDropdownLabel" class="truncate">Any Year</span>
                <i class="fa-solid fa-chevron-down text-xs text-gray-400 ml-2 flex-shrink-0" id="yearDropdownChevron"></i>
            </button>
            <div id="yearDropdownPanel" class="hidden custom-dd-panel">
                <div id="yearDropdownList" role="listbox">
                    <div class="prog-item-all selected" role="option" data-value="" aria-selected="true">Any Year</div>
                    <?php foreach ($years as $yr): ?>
                    <div class="prog-item" role="option" data-value="<?php echo h($yr); ?>">
                        <i class="fa-regular fa-calendar mr-1.5 text-gray-400"></i><?php echo h($yr); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Status ────────────────────────────────────────────── -->
        <div class="relative" id="statusDropdownWrapper">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                <i class="fa-solid fa-circle-half-stroke mr-1"></i> Status
            </label>
            <select id="filterStatus" class="sr-only" aria-hidden="true" tabindex="-1">
                <option value="">All Status</option>
                <option value="NEW">New Records</option>
                <option value="OLD">Old Records</option>
            </select>
            <button type="button" id="statusDropdownTrigger"
                class="flex w-full items-center justify-between bg-gray-50 rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer text-sm"
                aria-haspopup="listbox" aria-expanded="false">
                <span id="statusDropdownLabel" class="truncate">All Status</span>
                <i class="fa-solid fa-chevron-down text-xs text-gray-400 ml-2 flex-shrink-0" id="statusDropdownChevron"></i>
            </button>
            <div id="statusDropdownPanel" class="hidden custom-dd-panel">
                <div id="statusDropdownList" role="listbox">
                    <div class="prog-item-all selected" role="option" data-value="" aria-selected="true">All Status</div>
                    <div class="prog-item" role="option" data-value="NEW"><span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-2"></span>New Records</div>
                    <div class="prog-item" role="option" data-value="OLD"><span class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-2"></span>Old Records</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Active Filter Chips -->
    <div id="chip-row" class="mb-6 hidden"></div>

    <!-- Results State Display -->
    <div class="flex justify-between items-center mb-8 border-b border-gray-100 pb-4 px-2">
        <h2 class="text-xl font-bold text-prcnavy flex items-center">
            Search Results
            <span class="bg-gray-100 text-gray-600 rounded-full px-3 py-1 font-bold text-sm ml-3 tracking-wider shadow-inner" id="resultCount">—</span>
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
        <p class="text-gray-300 text-6xl mb-4">🔍</p>
        <h3 class="text-xl font-bold text-gray-700 mb-2">No results found</h3>
        <p class="text-gray-400 text-sm leading-relaxed max-w-xs mx-auto">
            Check your spelling &nbsp;·&nbsp; Remove a filter &nbsp;·&nbsp; Try broader keywords
        </p>
        <button id="emptyClearBtn" type="button" class="mt-6 text-sm border border-gray-300 rounded-full px-5 py-2 text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition font-semibold">
            <i class="fa-solid fa-rotate-left mr-1.5"></i> Clear All Filters
        </button>
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

<script>
/* ── Shared Custom Dropdown Factory ────────────────────────────────────────
   Drives all four filter dropdowns (Category, Program, Year, Status).
   Program is the only one with an internal search box.
─────────────────────────────────────────────────────────────────────────── */
function initCustomDropdown({ wrapperId, triggerId, panelId, chevronId, listId, labelId, nativeSelectId, searchInputId, defaultLabel }) {
    const wrapper      = document.getElementById(wrapperId);
    const trigger      = document.getElementById(triggerId);
    const panel        = document.getElementById(panelId);
    const chevron      = document.getElementById(chevronId);
    const list         = document.getElementById(listId);
    const label        = document.getElementById(labelId);
    const nativeSel    = document.getElementById(nativeSelectId);
    const searchBox    = searchInputId ? document.getElementById(searchInputId) : null;
    const noResults    = list.querySelector('.prog-no-results');

    if (!wrapper || !trigger || !panel || !list || !nativeSel) return;

    function openPanel() {
        // Close any other open panels first
        document.querySelectorAll('.custom-dd-panel').forEach(p => {
            if (p !== panel) p.classList.add('hidden');
        });
        document.querySelectorAll('[aria-expanded="true"]').forEach(t => {
            if (t !== trigger) t.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('[id$="DropdownChevron"]').forEach(c => {
            if (c !== chevron) c.style.transform = '';
        });

        panel.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
        trigger.setAttribute('aria-expanded', 'true');
        if (searchBox) {
            searchBox.value = '';
            filterItems('');
            setTimeout(() => searchBox.focus(), 50);
        }
    }

    function closePanel() {
        panel.classList.add('hidden');
        chevron.style.transform = '';
        trigger.setAttribute('aria-expanded', 'false');
    }

    function filterItems(q) {
        if (!searchBox) return;
        const term = q.toLowerCase().trim();
        let visibleCount = 0;
        const allItem = list.querySelector('.prog-item-all');
        allItem.style.display = '';

        const groupVisibility = new Map();
        list.querySelectorAll('[data-group]').forEach(g => groupVisibility.set(g, false));

        let currentGroup = null;
        list.querySelectorAll('.prog-group-header, .prog-item').forEach(el => {
            if (el.classList.contains('prog-group-header')) {
                currentGroup = el;
            } else {
                const visible = !term || el.textContent.toLowerCase().includes(term);
                el.style.display = visible ? '' : 'none';
                if (visible) { visibleCount++; if (currentGroup) groupVisibility.set(currentGroup, true); }
            }
        });
        groupVisibility.forEach((v, g) => { g.style.display = v ? '' : 'none'; });
        if (noResults) noResults.classList.toggle('hidden', visibleCount > 0 || !term);
    }

    function selectItem(value, text) {
        nativeSel.value = value;
        label.textContent = text || defaultLabel;
        list.querySelectorAll('.prog-item, .prog-item-all').forEach(el => {
            const match = el.dataset.value === value;
            el.classList.toggle('selected', match);
            el.setAttribute('aria-selected', match ? 'true' : 'false');
        });
        closePanel();
        nativeSel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Trigger
    trigger.addEventListener('click', e => { e.stopPropagation(); panel.classList.contains('hidden') ? openPanel() : closePanel(); });
    // Search
    if (searchBox) {
        searchBox.addEventListener('input', () => filterItems(searchBox.value));
        searchBox.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });
    }
    // Item selection
    list.addEventListener('click', e => {
        const item = e.target.closest('.prog-item, .prog-item-all');
        if (item) selectItem(item.dataset.value, item.textContent.trim());
    });
    // Outside click
    document.addEventListener('click', e => { if (!wrapper.contains(e.target)) closePanel(); });
    // Poll for external resets (e.g., resetAllFilters in search.js)
    let lastVal = nativeSel.value;
    setInterval(() => {
        if (nativeSel.value !== lastVal) {
            lastVal = nativeSel.value;
            const item = list.querySelector(`.prog-item[data-value="${CSS.escape(nativeSel.value)}"]`) || list.querySelector('.prog-item-all');
            if (item) {
                label.textContent = item.textContent.trim();
                list.querySelectorAll('.prog-item, .prog-item-all').forEach(el => el.classList.toggle('selected', el.dataset.value === nativeSel.value));
            }
        }
    }, 150);
}

// ── Initialise all four dropdowns ──────────────────────────────────────────
initCustomDropdown({ wrapperId:'categoryDropdownWrapper', triggerId:'categoryDropdownTrigger', panelId:'categoryDropdownPanel', chevronId:'categoryDropdownChevron', listId:'categoryDropdownList', labelId:'categoryDropdownLabel', nativeSelectId:'filterCategory', searchInputId:null, defaultLabel:'All Categories' });
initCustomDropdown({ wrapperId:'programDropdownWrapper',  triggerId:'programDropdownTrigger',  panelId:'programDropdownPanel',  chevronId:'programDropdownChevron',  listId:'programDropdownList',  labelId:'programDropdownLabel',  nativeSelectId:'filterProgram',  searchInputId:'programSearchInput', defaultLabel:'All Programs' });
initCustomDropdown({ wrapperId:'yearDropdownWrapper',     triggerId:'yearDropdownTrigger',     panelId:'yearDropdownPanel',     chevronId:'yearDropdownChevron',     listId:'yearDropdownList',     labelId:'yearDropdownLabel',     nativeSelectId:'filterYear',     searchInputId:null, defaultLabel:'Any Year' });
initCustomDropdown({ wrapperId:'statusDropdownWrapper',   triggerId:'statusDropdownTrigger',   panelId:'statusDropdownPanel',   chevronId:'statusDropdownChevron',   listId:'statusDropdownList',   labelId:'statusDropdownLabel',   nativeSelectId:'filterStatus',   searchInputId:null, defaultLabel:'All Status' });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
