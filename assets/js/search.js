document.addEventListener('DOMContentLoaded', () => {

    // UI Elements
    const searchInput = document.getElementById('searchInput');
    const filterCategory = document.getElementById('filterCategory');
    const filterProgram = document.getElementById('filterProgram');
    const filterYear = document.getElementById('filterYear');

    const resultsGrid = document.getElementById('resultsGrid');
    const resultsTableWrapper = document.getElementById('resultsTableWrapper');
    const resultCount = document.getElementById('resultCount');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const emptyState = document.getElementById('emptyState');
    const resultModal = document.getElementById('resultModal');
    const resultModalContent = document.getElementById('resultModalContent');
    const closeResultModal = document.getElementById('closeResultModal');

    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const loadMoreBtn = document.getElementById('loadMoreBtn');

    // State
    let currentPage = 1;
    let isLoading = false;
    let debounceTimer;
    const recordStore = new Map();

    // FontAwesome Icons mapping
    const iconDownload = `<i class="fa-solid fa-cloud-arrow-down mr-2 text-lg group-hover:animate-bounce"></i>`;
    const iconNoFile = `<i class="fa-solid fa-file-circle-exclamation mr-1.5 opacity-70"></i>`;
    const iconFile = `<i class="fa-solid fa-file-lines mr-1.5"></i>`;
    const iconDate = `<i class="fa-regular fa-clock text-gray-400 mr-1.5"></i>`;

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
        if (!tokens.length) {
            return false;
        }

        const normalizedEntry = normalizeSearchText(entry);
        if (!normalizedEntry) {
            return false;
        }

        const entryWords = normalizedEntry.split(' ').filter(Boolean);
        return tokens.every((token) => {
            if (normalizedEntry.includes(token)) {
                return true;
            }

            return entryWords.some((word) => word.includes(token));
        });
    }

    function splitStudentEntries(studentList) {
        const raw = String(studentList || '').trim();
        if (!raw) {
            return [];
        }

        const numberedEntries = [...raw.matchAll(/(?:^|\n)\s*\d+[\.\)]\s*(.+)$/gm)]
            .map((match) => match[1].trim())
            .filter((entry) => entry.length > 0);

        if (numberedEntries.length > 0) {
            return [...new Set(numberedEntries)];
        }

        return raw
            .split(/\r?\n|;/)
            .map((entry) => entry.trim())
            .filter((entry) => entry.length > 0);
    }

    function extractStudentEntriesFromText(text) {
        const normalized = String(text || '').replace(/\s+/g, ' ').trim();
        if (!normalized) {
            return [];
        }

        const blockMatch = normalized.match(/namely\s*:\s*(.+?)(relative to this|further,|nonetheless|thank you|very truly|very truly yours)/i);
        const candidate = blockMatch ? blockMatch[1] : normalized;
        const matches = [...candidate.matchAll(/\b(\d+)\.\s*([A-Z][A-Za-z'\-.]+,\s*[A-Z][A-Za-z .'\-]+?)(?=\s+\d+\.|$)/g)];
        const ordered = matches
            .map((match) => ({
                index: Number(match[1]),
                name: match[2].replace(/\s+/g, ' ').trim(),
            }))
            .filter((entry) => entry.name);

        ordered.sort((a, b) => a.index - b.index);
        return [...new Set(ordered.map((entry) => entry.name))];
    }

    function getStudentPreview(rc) {
        if (rc.category !== 'COPC Exemption') {
            return {
                summary: 'N/A',
                detailHtml: '',
            };
        }

        let entries = splitStudentEntries(rc.student_list);
        if (entries.length === 0) {
            entries = extractStudentEntriesFromText(rc.extracted_text);
        }

        if (entries.length === 0) {
            return {
                summary: 'N/A',
                detailHtml: '',
            };
        }

        const tokens = getSearchTokens();
        const matchedEntries = tokens.length
            ? entries.filter((entry) => entryMatchesTokens(entry, tokens))
            : [];

        const activeEntries = matchedEntries.length ? matchedEntries : entries;
        const displayEntries = activeEntries.slice(0, 3);
        const summary = escapeHtml(displayEntries.join(', '));
        const moreCount = activeEntries.length - displayEntries.length;
        const suffix = moreCount > 0 ? ` <span class="text-xs text-gray-400">+${moreCount} more</span>` : '';
        const detailHtml = activeEntries.map((entry) => `<div>${escapeHtml(entry)}</div>`).join('');

        return {
            summary: `${summary}${suffix}`,
            detailHtml,
        };
    }

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

        const params = new URLSearchParams({
            q: searchInput.value,
            cat: filterCategory.value,
            prog: filterProgram.value,
            yr: filterYear.value,
            page: currentPage
        });

        try {
            const res = await fetch(`${API_BASE_URL}/api/search.php?${params.toString()}`);
            if (!res.ok) {
                throw new Error(`Search request failed with status ${res.status}`);
            }

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

    function renderResults(records, total, append, hasMore) {
        resultCount.textContent = Number(total).toLocaleString();
        
        // Update headers based on current filter
        updateTableHeaders(filterCategory.value);

        if (records.length === 0 && !append) {
            emptyState.classList.remove('hidden');
            resultsGrid.innerHTML = '';
            loadMoreContainer.classList.add('hidden');
            resultsTableWrapper.classList.add('hidden');
            return;
        }

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
                // Specialized rendering for HEI List
                const programParts = rc.program ? rc.program.split(' - ') : [];
                const profileType = programParts[1] || 'Institution';
                const location = programParts[2] || 'N/A';
                
                const contactInfo = rc.student_list ? rc.student_list.replace(/\|/g, '<br>') : 'N/A';

                html += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 align-top text-sm text-gray-500 font-semibold">
                        ${rc.region || 'N/A'}
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[28rem] font-bold text-prcnavy leading-snug">${rc.school_name}</div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[24rem]">
                            <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider rounded-md bg-purple-50 text-purple-700 border border-purple-100 mb-2 inline-block">
                                <i class="fa-solid fa-building-columns mr-1"></i> ${profileType}
                            </span>
                            <div class="text-sm font-medium text-gray-700">${rc.program}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 align-top text-sm text-gray-600">
                        <div class="min-w-[14rem] flex items-start gap-2">
                            <i class="fa-solid fa-map-location-dot mt-1 text-prcgold/60"></i>
                            <span>${location}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 align-top text-xs text-gray-500 italic">
                        <div class="min-w-[14rem] leading-relaxed">
                            ${contactInfo}
                        </div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        ${statusBadge}
                    </td>
                    <td class="px-6 py-4 align-top text-right whitespace-nowrap">
                        <button type="button" data-view-id="${rc.id}" class="inline-flex items-center rounded-xl bg-prcnavy px-4 py-2 text-xs font-bold uppercase tracking-widest text-white hover:bg-prcaccent transition">
                            <i class="fa-solid fa-eye mr-2"></i> View
                        </button>
                    </td>
                </tr>
                `;
            } else {
                // Default rendering for COPC/GR/Exemption
                const studentPreview = getStudentPreview(rc);
                const catBadge = rc.category === 'COPC'
                    ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-prcnavy text-white shadow-sm flex items-center"><i class="fa-solid fa-certificate mr-1.5 text-prcgold"></i> COPC</span>'
                    : rc.category === 'GR'
                    ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-600 text-white shadow-sm flex items-center"><i class="fa-solid fa-landmark mr-1.5 text-emerald-100"></i> GR</span>'
                    : '<span class="px-3 py-1 text-xs font-bold rounded-full bg-prcgold text-white shadow-sm flex items-center"><i class="fa-solid fa-circle-check mr-1.5 text-prcnavy"></i> Exemption</span>';

                html += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 align-top">
                        <div class="flex flex-wrap gap-2">
                            ${catBadge}
                            ${statusBadge}
                        </div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[28rem] font-bold text-prcnavy leading-snug">${rc.school_name}</div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[24rem] text-sm font-medium text-gray-700 leading-relaxed">${rc.program}</div>
                    </td>
                    <td class="px-6 py-4 align-top">
                        <div class="min-w-[14rem] text-sm text-gray-600 leading-relaxed">
                            ${studentPreview.summary}
                        </div>
                    </td>
                    <td class="px-6 py-4 align-top text-sm text-gray-500">
                        ${rc.region || 'Unknown Region'}
                    </td>
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
                </tr>
                `;
            }
        });

        if (append) {
            resultsGrid.insertAdjacentHTML('beforeend', html);
        } else {
            resultsGrid.innerHTML = html;
        }

        if (hasMore) {
            loadMoreContainer.classList.remove('hidden');
        } else {
            loadMoreContainer.classList.add('hidden');
        }
    }

    function buildFileUI(rc) {
        if (rc.entry_type === 'manual') {
            return `<span class="inline-flex items-center text-xs font-semibold text-gray-500 bg-gray-50 px-4 py-2 rounded-xl border border-dashed border-gray-300 w-full justify-center">${iconNoFile} Manual Log - No Physical File</span>`;
        }

        if (rc.file_type) {
            const sz = rc.file_size_kb ? formatBytes(rc.file_size_kb * 1024, 0) : 'File';
            const ext = rc.file_type.toUpperCase();
            return `<a href="${API_BASE_URL}/download.php?id=${rc.id}" class="inline-flex items-center text-sm font-bold text-prcnavy bg-prclight hover:bg-prcnavy hover:text-prclight transition-all px-5 py-3 rounded-xl shadow-sm border border-gray-100 group w-full justify-center group-hover:border-prcnavy">${iconDownload} Download ${ext} <span class="font-normal opacity-70 ml-2">(${sz})</span></a>`;
        }

        return `<span class="inline-flex items-center text-xs font-semibold text-gray-500 bg-gray-50 px-4 py-2 rounded-xl border border-dashed border-gray-300 w-full justify-center">${iconFile} File metadata unavailable</span>`;
    }

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
        if (rc.status === 'NEW') {
            statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700 shadow-sm">NEW</span>';
        } else if (rc.status === 'OLD') {
            statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-600 shadow-sm">OLD</span>';
        }

        let mainContentUI = '';
        if (rc.category === 'HEI List') {
            const contactInfo = rc.student_list ? rc.student_list.replace(/\|/g, '<br>') : 'N/A';
            mainContentUI = `
            <div class="mt-4 pt-4 border-t border-gray-50 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Institution Type</div>
                    <div class="text-sm font-bold text-prcnavy">${rc.program}</div>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Contact / Website</div>
                    <div class="text-sm text-gray-600">${contactInfo}</div>
                </div>
            </div>
            `;
        } else if (studentPreview.detailHtml !== '') {
            mainContentUI = `
            <div class="mt-4 pt-3 border-t border-gray-50">
                <div class="flex items-center text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1">
                    <i class="fa-solid fa-users-rectangle mr-2 text-prcgold/60"></i> Exempted Student List
                </div>
                <div class="text-xs text-gray-500 bg-prclight p-3 rounded-xl border border-gray-100 leading-relaxed">
                    ${studentPreview.detailHtml}
                </div>
            </div>
            `;
        }

        resultModalContent.innerHTML = `
        <div class="bg-white rounded-2xl border border-gray-50 flex flex-col p-6 lg:p-8">
            <div class="flex flex-wrap gap-2 justify-between items-start mb-6 pb-4 border-b border-gray-50 border-dashed">
                <div class="flex items-center gap-2">
                    ${catBadge}
                    ${statusBadge}
                </div>
                <span class="text-xs text-gray-400 font-semibold tracking-wide flex items-center bg-gray-50 px-2 py-1 rounded-md">
                    ${iconDate} ${rc.category === 'HEI List' ? 'Added' : 'Apprv'}: ${formatDate(rc.date_approved)}
                </span>
            </div>

            <h3 class="text-2xl font-extrabold text-gray-900 leading-tight mb-1">${rc.school_name}</h3>
            <div class="flex items-center mb-4 text-xs font-bold text-gray-400 uppercase tracking-widest">
                <i class="fa-solid fa-location-dot mr-1.5 text-prcgold/50"></i> ${rc.region || 'Unknown Region'}
            </div>

            ${mainContentUI}

            <div class="mt-8">
                ${buildFileUI(rc)}
            </div>
        </div>
        `;

        resultModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        resultModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function triggerSearch() {
        currentPage = 1;
        fetchResults(false);
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerSearch, 300);
    });

    [filterCategory, filterProgram, filterYear].forEach((el) => {
        el.addEventListener('change', triggerSearch);
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
        if (event.target === resultModal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !resultModal.classList.contains('hidden')) {
            closeModal();
        }
    });

    fetchResults(false);
});
