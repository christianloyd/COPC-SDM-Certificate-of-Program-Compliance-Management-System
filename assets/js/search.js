document.addEventListener('DOMContentLoaded', () => {

    // ── UI Elements ─────────────────────────────────────────────────────────
    const searchInput       = document.getElementById('searchInput');
    const clearSearchBtn    = document.getElementById('clearSearchBtn');
    const searchHint        = document.getElementById('search-hint');
    const chipRow           = document.getElementById('chip-row');
    const filterCategory    = document.getElementById('filterCategory');
    const filterProgram     = document.getElementById('filterProgram');
    const filterYear        = document.getElementById('filterYear');
    const filterStatus      = document.getElementById('filterStatus');

    const resultsGrid          = document.getElementById('resultsGrid');
    const resultsTableWrapper  = document.getElementById('resultsTableWrapper');
    const resultCount          = document.getElementById('resultCount');
    const loadingIndicator     = document.getElementById('loadingIndicator');
    const emptyState           = document.getElementById('emptyState');
    const emptyClearBtn        = document.getElementById('emptyClearBtn');
    const resultModal          = document.getElementById('resultModal');
    const resultModalContent   = document.getElementById('resultModalContent');
    const closeResultModal     = document.getElementById('closeResultModal');
    const loadMoreContainer    = document.getElementById('loadMoreContainer');
    const loadMoreBtn          = document.getElementById('loadMoreBtn');

    // ── State ────────────────────────────────────────────────────────────────
    let currentPage   = 1;
    let isLoading     = false;
    let debounceTimer;
    const recordStore = new Map();

    // ── Icons ─────────────────────────────────────────────────────────────────
    const iconDownload = `<i class="fa-solid fa-cloud-arrow-down mr-2 text-lg group-hover:animate-bounce"></i>`;
    const iconNoFile   = `<i class="fa-solid fa-file-circle-exclamation mr-1.5 opacity-70"></i>`;
    const iconFile     = `<i class="fa-solid fa-file-lines mr-1.5"></i>`;
    const iconDate     = `<i class="fa-regular fa-clock text-gray-400 mr-1.5"></i>`;

    // ── Helpers ───────────────────────────────────────────────────────────────

    function formatBytes(bytes, decimals = 2) {
        if (!+bytes) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    }

    function formatDate(dateStr) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateStr).toLocaleDateString(undefined, options);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── Keyword Highlighting ──────────────────────────────────────────────────
    /**
     * Wraps every occurrence of `query` words inside already-escaped HTML text
     * with a <mark> tag.  Safe to call on pre-escaped strings.
     */
    function highlight(text, query) {
        if (!query || query.trim().length < 2) return text;
        // Pull plain part from a structured query if present
        const { plain } = parseQuery(query);
        const q = plain.trim() || query.trim();
        if (q.length < 2) return text;

        // Build OR-regex from each whitespace-separated token (≥2 chars)
        const tokens = q.split(/\s+/).filter(t => t.length >= 2);
        if (!tokens.length) return text;

        const escaped = tokens
            .map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
            .join('|');
        return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark>$1</mark>');
    }

    // ── Structured Query Syntax ───────────────────────────────────────────────
    /**
     * Parses `program: nursing school: ust year: 2023` from the search box.
     * Returns { structured: {program, school, year}, plain: "remaining text" }
     */
    function parseQuery(q) {
        const structured = {};
        let plain = q;
        const re = /(program|school|year):\s*([^\s]+)/gi;
        let m;
        while ((m = re.exec(q)) !== null) {
            structured[m[1].toLowerCase()] = m[2];
            plain = plain.replace(m[0], '').trim();
        }
        return { structured, plain };
    }

    // ── URL State Preservation ────────────────────────────────────────────────
    function pushUrlState() {
        const params = new URLSearchParams();
        if (searchInput.value) params.set('q', searchInput.value);
        if (filterCategory.value) params.set('cat', filterCategory.value);
        if (filterProgram.value) params.set('prog', filterProgram.value);
        if (filterYear.value) params.set('yr', filterYear.value);
        const qs = params.toString();
        history.replaceState(null, '', qs ? `?${qs}` : location.pathname);
    }

    function restoreUrlState() {
        const params = new URLSearchParams(location.search);
        if (params.get('q')) searchInput.value = params.get('q');
        if (params.get('cat')) filterCategory.value = params.get('cat');
        if (params.get('prog')) filterProgram.value = params.get('prog');
        if (params.get('yr')) filterYear.value = params.get('yr');
    }

    // ── Clear Button ──────────────────────────────────────────────────────────
    function updateClearBtn() {
        if (searchInput.value.length > 0) {
            clearSearchBtn.classList.remove('hidden');
        } else {
            clearSearchBtn.classList.add('hidden');
        }
    }

    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        updateClearBtn();
        updateHint('default');
        triggerSearch();
        searchInput.focus();
    });

    // ── Search Hint ───────────────────────────────────────────────────────────
    const HINT_DEFAULT = `Try: <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 font-mono">program: nursing</code> &nbsp;&middot;&nbsp;
            <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 font-mono">school: ust</code> &nbsp;&middot;&nbsp;
            <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 font-mono">year: 2023</code>`;
    const HINT_TYPING  = '';
    const HINT_EMPTY   = 'Try broader keywords or remove some filters';

    function updateHint(state) {
        if (!searchHint) return;
        if (state === 'default') {
            searchHint.innerHTML = HINT_DEFAULT;
            searchHint.style.opacity = '1';
        } else if (state === 'typing') {
            searchHint.innerHTML = HINT_TYPING;
            searchHint.style.opacity = '0';
        } else if (state === 'empty') {
            searchHint.innerHTML = HINT_EMPTY;
            searchHint.style.opacity = '1';
        }
    }

    // ── Filter Chips ──────────────────────────────────────────────────────────
    const FILTER_LABELS = {
        filterCategory: 'Doc Type',
        filterProgram:  'Program',
        filterYear:     'Year',
        filterStatus:   'Status',
    };

    function renderChips() {
        if (!chipRow) return;
        const filters = [
            { id: 'filterCategory', el: filterCategory },
            { id: 'filterProgram',  el: filterProgram  },
            { id: 'filterYear',     el: filterYear      },
            { id: 'filterStatus',   el: filterStatus    },
        ];
        const activeFilters = filters.filter(f => f.el && f.el.value !== '');

        if (activeFilters.length === 0) {
            chipRow.classList.add('hidden');
            chipRow.innerHTML = '';
            return;
        }

        let html = '';
        activeFilters.forEach(({ id, el }) => {
            const label = FILTER_LABELS[id];
            const val = el.options[el.selectedIndex].text;
            html += `
              <span class="filter-chip">
                <i class="fa-solid fa-filter text-blue-400 text-[10px]"></i>
                ${label}: <strong>${escapeHtml(val)}</strong>
                <button type="button" data-chip-filter="${id}" aria-label="Remove ${label} filter">&#x2715;</button>
              </span>`;
        });

        if (activeFilters.length >= 2) {
            html += `<button type="button" id="clearAllChips" class="text-xs text-red-400 hover:text-red-600 font-bold ml-2 transition">Clear All</button>`;
        }

        chipRow.innerHTML = html;
        chipRow.classList.remove('hidden');

        // Wire up chip remove buttons
        chipRow.querySelectorAll('[data-chip-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                const filterId = btn.getAttribute('data-chip-filter');
                const selectEl = document.getElementById(filterId);
                if (selectEl) {
                    selectEl.value = '';
                    triggerSearch();
                }
            });
        });

        const clearAllBtn = document.getElementById('clearAllChips');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', resetAllFilters);
        }
    }

    // ── Reset All Filters ─────────────────────────────────────────────────────
    function resetAllFilters() {
        searchInput.value    = '';
        filterCategory.value = '';
        filterProgram.value  = '';
        filterYear.value     = '';
        if (filterStatus) filterStatus.value = '';

        // Sync the custom program dropdown label
        const progLabel = document.getElementById('programDropdownLabel');
        if (progLabel) progLabel.textContent = 'All Programs';
        const progList  = document.getElementById('programDropdownList');
        if (progList) {
            progList.querySelectorAll('.prog-item, .prog-item-all').forEach(el => {
                el.classList.toggle('selected', el.dataset.value === '');
            });
        }

        updateClearBtn();
        updateHint('default');
        triggerSearch();
    }

    if (emptyClearBtn) {
        emptyClearBtn.addEventListener('click', resetAllFilters);
    }

    // ── Search Token Helpers (unchanged) ──────────────────────────────────────
    function getSearchTokens() {
        return searchInput.value
            .trim()
            .split(/\s+/)
            .map((token) => normalizeSearchText(token))
            .filter((token) => token.length > 0);
    }

    function normalizeSearchText(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function entryMatchesTokens(entry, tokens) {
        if (!tokens.length) return false;
        const normalizedEntry = normalizeSearchText(entry);
        if (!normalizedEntry) return false;
        const entryWords = normalizedEntry.split(' ').filter(Boolean);
        return tokens.every((token) => {
            if (normalizedEntry.includes(token)) return true;
            return entryWords.some((word) => word.includes(token));
        });
    }

    function splitStudentEntries(studentList) {
        const raw = String(studentList || '').trim();
        if (!raw) return [];
        const numberedEntries = [...raw.matchAll(/(?:^|\n)\s*\d+[.)]\s*(.+)$/gm)]
            .map((match) => match[1].trim())
            .filter((entry) => entry.length > 0);
        if (numberedEntries.length > 0) return [...new Set(numberedEntries)];
        return raw
            .split(/\r?\n|;/)
            .map((entry) => entry.trim())
            .filter((entry) => entry.length > 0);
    }

    function extractStudentEntriesFromText(text) {
        const normalized = String(text || '').replace(/\s+/g, ' ').trim();
        if (!normalized) return [];
        const blockMatch = normalized.match(/namely\s*:\s*(.+?)(relative to this|further,|nonetheless|thank you|very truly|very truly yours)/i);
        const candidate = blockMatch ? blockMatch[1] : normalized;
        const matches = [...candidate.matchAll(/\b(\d+)\.\s*([A-Z][A-Za-z'\-.]+,\s*[A-Z][A-Za-z .'\-]+?)(?=\s+\d+\.|$)/g)];
        const ordered = matches
            .map((match) => ({ index: Number(match[1]), name: match[2].replace(/\s+/g, ' ').trim() }))
            .filter((entry) => entry.name);
        ordered.sort((a, b) => a.index - b.index);
        return [...new Set(ordered.map((entry) => entry.name))];
    }

    function getStudentPreview(rc) {
        if (rc.category !== 'COPC Exemption') return { summary: 'N/A', detailHtml: '' };
        let entries = splitStudentEntries(rc.student_list);
        if (entries.length === 0) entries = extractStudentEntriesFromText(rc.extracted_text);
        if (entries.length === 0) return { summary: 'N/A', detailHtml: '' };

        const tokens = getSearchTokens();
        const matchedEntries = tokens.length ? entries.filter((entry) => entryMatchesTokens(entry, tokens)) : [];
        const activeEntries = matchedEntries.length ? matchedEntries : entries;
        const displayEntries = activeEntries.slice(0, 3);
        const summary = escapeHtml(displayEntries.join(', '));
        const moreCount = activeEntries.length - displayEntries.length;
        const suffix = moreCount > 0 ? ` <span class="text-xs text-gray-400">+${moreCount} more</span>` : '';
        const detailHtml = activeEntries.map((entry) => `<div>${escapeHtml(entry)}</div>`).join('');
        return { summary: `${summary}${suffix}`, detailHtml };
    }

    // ── Fetch Results ─────────────────────────────────────────────────────────
    async function fetchResults(append = false) {
        if (isLoading) return;
        isLoading = true;

        loadingIndicator.classList.remove('hidden');
        if (!append) {
            resultsGrid.innerHTML = '';
            emptyState.classList.add('hidden');
            loadMoreContainer.classList.add('hidden');
            resultsTableWrapper.classList.remove('hidden');
            recordStore.clear();
        }

        // Parse structured query keywords
        const { structured, plain } = parseQuery(searchInput.value);

        const params = new URLSearchParams({
            q:      plain || searchInput.value,
            cat:    filterCategory.value,
            prog:   filterProgram.value,
            yr:     filterYear.value,
            status: filterStatus ? filterStatus.value : '',
            page:   currentPage,
        });

        // Structured keyword overrides (sent as separate params for precise SQL)
        if (structured.program) params.set('sp', structured.program);
        if (structured.school)  params.set('ss', structured.school);
        if (structured.year)    params.set('sy', structured.year);

        // Persist URL state
        pushUrlState();

        try {
            const res = await fetch(`${API_BASE_URL}/api/search.php?${params.toString()}`);
            if (!res.ok) throw new Error(`Search request failed with status ${res.status}`);
            const json = await res.json();
            if (json.success) {
                renderResults(json.data, json.total, append, json.has_more);
            } else {
                console.error(json.error);
                alert(`Search failed. ${json.error}`);
            }
        } catch (e) {
            console.error('Network error fetching search results:', e);
        } finally {
            isLoading = false;
            loadingIndicator.classList.add('hidden');
        }
    }

    // ── Table Headers ─────────────────────────────────────────────────────────
    function updateTableHeaders(category) {
        const tableHeader = document.getElementById('tableHeader');
        if (!tableHeader) return;
        if (category === 'HEI List') {
            tableHeader.innerHTML = `
                <th class="px-6 py-4 border-b border-gray-100 w-[14rem]">Region</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[34rem]">Institution Name</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[32rem]">Program / Profile</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[18rem]">Location</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[18rem]">Contact / Website</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[10rem]">Status</th>
                <th class="px-6 py-4 border-b border-gray-100 text-right w-[10rem]">Action</th>
            `;
        } else {
            tableHeader.innerHTML = `
                <th class="px-6 py-4 border-b border-gray-100 w-[14rem]">Category</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[34rem]">School</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[32rem]">Program</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[18rem]">Student Names</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[14rem]">Region</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[12rem]">Approved</th>
                <th class="px-6 py-4 border-b border-gray-100 w-[12rem]">Type</th>
                <th class="px-6 py-4 border-b border-gray-100 text-right w-[10rem]">Action</th>
            `;
        }
    }

    // ── Render Results ────────────────────────────────────────────────────────
    function renderResults(records, total, append, hasMore) {
        const q = searchInput.value;

        // Update count badge
        if (total === null || total === undefined) {
            resultCount.textContent = '—';
        } else {
            resultCount.textContent = Number(total).toLocaleString();
        }

        updateTableHeaders(filterCategory.value);
        renderChips();

        if (records.length === 0 && !append) {
            emptyState.classList.remove('hidden');
            resultsGrid.innerHTML = '';
            loadMoreContainer.classList.add('hidden');
            resultsTableWrapper.classList.add('hidden');
            updateHint('empty');
            return;
        }

        // Reset hint if we have results
        if (!q) updateHint('default');

        let html = '';
        records.forEach((rc) => {
            recordStore.set(String(rc.id), rc);

            let statusBadge = '';
            if (rc.status === 'NEW') {
                statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700 shadow-sm">NEW</span>';
            } else if (rc.status === 'OLD') {
                statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-600 shadow-sm">OLD</span>';
            }

            if (rc.category === 'HEI List') {
                const programParts = rc.program ? rc.program.split(' - ') : [];
                const profileType  = programParts[1] || 'Institution';
                const location     = programParts[2] || 'N/A';
                const contactInfo  = rc.student_list ? rc.student_list.replace(/\|/g, '<br>') : 'N/A';

                // Highlighted fields
                const hSchool   = highlight(escapeHtml(rc.school_name || ''), q);
                const hProgram  = highlight(escapeHtml(rc.program || ''), q);
                const hLocation = highlight(escapeHtml(location), q);

                html += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 align-top text-sm text-gray-500 font-semibold">${escapeHtml(rc.region || 'N/A')}</td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[28rem] font-bold text-prcnavy leading-snug">${hSchool}</div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[24rem]">
                            <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider rounded-md bg-purple-50 text-purple-700 border border-purple-100 mb-2 inline-block">
                                <i class="fa-solid fa-building-columns mr-1"></i> ${escapeHtml(profileType)}
                            </span>
                            <div class="text-sm font-medium text-gray-700">${hProgram}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 align-top text-sm text-gray-600">
                        <div class="min-w-[14rem] flex items-start gap-2">
                            <i class="fa-solid fa-map-location-dot mt-1 text-prcgold/60"></i>
                            <span>${hLocation}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 align-top text-xs text-gray-500 italic">
                        <div class="min-w-[14rem] leading-relaxed">${contactInfo}</div>
                    </td>
                    <td class="px-6 py-4 align-top">${statusBadge}</td>
                    <td class="px-6 py-4 align-top text-right whitespace-nowrap">
                        <button type="button" data-view-id="${rc.id}" class="inline-flex items-center rounded-xl bg-prcnavy px-4 py-2 text-xs font-bold uppercase tracking-widest text-white hover:bg-prcaccent transition">
                            <i class="fa-solid fa-eye mr-2"></i> View
                        </button>
                    </td>
                </tr>`;
            } else {
                const studentPreview = getStudentPreview(rc);
                const catBadge = rc.category === 'COPC'
                    ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-prcnavy text-white shadow-sm flex items-center"><i class="fa-solid fa-certificate mr-1.5 text-prcgold"></i> COPC</span>'
                    : rc.category === 'GR'
                    ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-600 text-white shadow-sm flex items-center"><i class="fa-solid fa-landmark mr-1.5 text-emerald-100"></i> GR</span>'
                    : '<span class="px-3 py-1 text-xs font-bold rounded-full bg-prcgold text-white shadow-sm flex items-center"><i class="fa-solid fa-circle-check mr-1.5 text-prcnavy"></i> Exemption</span>';

                // Highlighted fields
                const hSchool  = highlight(escapeHtml(rc.school_name || ''), q);
                const hProgram = highlight(escapeHtml(rc.program || ''), q);

                // Student preview already contains escaped HTML — highlight plain text only
                const rawStudentSummary = studentPreview.summary;

                html += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 align-top">
                        <div class="flex flex-wrap gap-2">${catBadge}${statusBadge}</div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[28rem] font-bold text-prcnavy leading-snug">${hSchool}</div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[24rem] text-sm font-medium text-gray-700 leading-relaxed">${hProgram}</div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[14rem] text-sm text-gray-600 leading-relaxed">${rawStudentSummary}</div>
                    </td>
                    <td class="px-6 py-4 align-top text-sm text-gray-500">${escapeHtml(rc.region || 'Unknown Region')}</td>
                    <td class="px-6 py-4 align-top">
                        <span class="text-xs text-gray-500 font-medium inline-flex items-center">${iconDate} ${formatDate(rc.date_approved)}</span>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <span class="inline-flex items-center rounded-full bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-600 border border-gray-200">
                            ${rc.entry_type === 'manual' ? `${iconNoFile} Manual` : `${iconFile} ${rc.file_type ? rc.file_type.toUpperCase() : 'File'}`}
                        </span>
                    </td>
                    <td class="px-6 py-4 align-top text-right whitespace-nowrap">
                        <button type="button" data-view-id="${rc.id}" class="inline-flex items-center rounded-xl bg-prcnavy px-4 py-2 text-xs font-bold uppercase tracking-widest text-white hover:bg-prcaccent transition">
                            <i class="fa-solid fa-eye mr-2"></i> View
                        </button>
                    </td>
                </tr>`;
            }
        });

        if (append) {
            resultsGrid.insertAdjacentHTML('beforeend', html);
        } else {
            resultsGrid.innerHTML = html;
        }

        loadMoreContainer.classList.toggle('hidden', !hasMore);
    }

    // ── File UI ───────────────────────────────────────────────────────────────
    function buildFileUI(rc) {
        if (rc.entry_type === 'manual') {
            return `<span class="inline-flex items-center text-xs font-semibold text-gray-500 bg-gray-50 px-4 py-2 rounded-xl border border-dashed border-gray-300 w-full justify-center">${iconNoFile} Manual Log - No Physical File</span>`;
        }
        if (rc.file_type) {
            const sz  = rc.file_size_kb ? formatBytes(rc.file_size_kb * 1024, 0) : 'File';
            const ext = rc.file_type.toUpperCase();
            return `<a href="${API_BASE_URL}/download.php?id=${rc.id}" class="inline-flex items-center text-sm font-bold text-prcnavy bg-prclight hover:bg-prcnavy hover:text-prclight transition-all px-5 py-3 rounded-xl shadow-sm border border-gray-100 group w-full justify-center group-hover:border-prcnavy">${iconDownload} Download ${ext} <span class="font-normal opacity-70 ml-2">(${sz})</span></a>`;
        }
        return `<span class="inline-flex items-center text-xs font-semibold text-gray-500 bg-gray-50 px-4 py-2 rounded-xl border border-dashed border-gray-300 w-full justify-center">${iconFile} File metadata unavailable</span>`;
    }

    // ── Modal ─────────────────────────────────────────────────────────────────
    function openRecordModal(id) {
        const rc = recordStore.get(String(id));
        if (!rc) return;
        const studentPreview = getStudentPreview(rc);

        const catBadge = rc.category === 'COPC'
            ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-prcnavy text-white shadow-sm flex items-center"><i class="fa-solid fa-certificate mr-1.5 text-prcgold"></i> COPC</span>'
            : rc.category === 'GR'
            ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-600 text-white shadow-sm flex items-center"><i class="fa-solid fa-landmark mr-1.5 text-emerald-100"></i> GR</span>'
            : rc.category === 'HEI List'
            ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-purple-600 text-white shadow-sm flex items-center"><i class="fa-solid fa-building-columns mr-1.5 text-purple-200"></i> HEI List</span>'
            : '<span class="px-3 py-1 text-xs font-bold rounded-full bg-prcgold text-white shadow-sm flex items-center"><i class="fa-solid fa-circle-check mr-1.5 text-prcnavy"></i> Exemption</span>';

        let statusBadge = '';
        if (rc.status === 'NEW') statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700 shadow-sm">NEW</span>';
        else if (rc.status === 'OLD') statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-600 shadow-sm">OLD</span>';

        let mainContentUI = '';
        if (rc.category === 'HEI List') {
            const contactInfo = rc.student_list ? rc.student_list.replace(/\|/g, '<br>') : 'N/A';
            mainContentUI = `
            <div class="mt-4 pt-4 border-t border-gray-50 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Institution Type</div>
                    <div class="text-sm font-bold text-prcnavy">${escapeHtml(rc.program || '')}</div>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Contact / Website</div>
                    <div class="text-sm text-gray-600">${contactInfo}</div>
                </div>
            </div>`;
        } else if (studentPreview.detailHtml !== '') {
            mainContentUI = `
            <div class="mt-4 pt-3 border-t border-gray-50">
                <div class="flex items-center text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1">
                    <i class="fa-solid fa-users-rectangle mr-2 text-prcgold/60"></i> Exempted Student List
                </div>
                <div class="text-xs text-gray-500 bg-prclight p-3 rounded-xl border border-gray-100 leading-relaxed">
                    ${studentPreview.detailHtml}
                </div>
            </div>`;
        }

        resultModalContent.innerHTML = `
        <div class="bg-white rounded-2xl border border-gray-50 flex flex-col p-6 lg:p-8">
            <div class="flex flex-wrap gap-2 justify-between items-start mb-6 pb-4 border-b border-gray-50 border-dashed">
                <div class="flex items-center gap-2">${catBadge}${statusBadge}</div>
                <span class="text-xs text-gray-400 font-semibold tracking-wide flex items-center bg-gray-50 px-2 py-1 rounded-md">
                    ${iconDate} ${rc.category === 'HEI List' ? 'Added' : 'Apprv'}: ${formatDate(rc.date_approved)}
                </span>
            </div>
            <h3 class="text-2xl font-extrabold text-gray-900 leading-tight mb-1">${escapeHtml(rc.school_name || '')}</h3>
            <div class="flex items-center mb-4 text-xs font-bold text-gray-400 uppercase tracking-widest">
                <i class="fa-solid fa-location-dot mr-1.5 text-prcgold/50"></i> ${escapeHtml(rc.region || 'Unknown Region')}
            </div>
            ${mainContentUI}
            <div class="mt-8">${buildFileUI(rc)}</div>
        </div>`;

        resultModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        resultModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // ── Trigger Search ────────────────────────────────────────────────────────
    function triggerSearch() {
        currentPage = 1;
        fetchResults(false);
    }

    // ── Event Listeners ───────────────────────────────────────────────────────
    searchInput.addEventListener('input', () => {
        updateClearBtn();
        updateHint(searchInput.value.length > 0 ? 'typing' : 'default');
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerSearch, 300);
    });

    [filterCategory, filterProgram, filterYear, filterStatus].filter(Boolean).forEach((el) => {
        el.addEventListener('change', () => {
            triggerSearch();
        });
    });

    loadMoreBtn.addEventListener('click', () => {
        currentPage++;
        fetchResults(true);
    });

    resultsGrid.addEventListener('click', (event) => {
        const button = event.target.closest('[data-view-id]');
        if (!button) return;
        openRecordModal(button.getAttribute('data-view-id'));
    });

    closeResultModal.addEventListener('click', closeModal);

    resultModal.addEventListener('click', (event) => {
        if (event.target === resultModal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !resultModal.classList.contains('hidden')) closeModal();
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    restoreUrlState();         // Restore state from URL if present
    updateClearBtn();          // Sync clear button to initial input value
    updateHint('default');     // Show default hint
    fetchResults(false);       // Initial load
});
