document.addEventListener('DOMContentLoaded', () => {
    const bulkPdfForm = document.getElementById('bulkPdfForm');
    const bulkPdfInput = document.getElementById('bulkPdfInput');
    const bulkPdfDropZone = document.getElementById('bulkPdfDropZone');
    const bulkPdfStatus = document.getElementById('bulkPdfStatus');
    const extractionResultsContainer = document.getElementById('extractionResultsContainer');
    const extractionLogBody = document.getElementById('extractionLogBody');
    const bulkProgressLabel = document.getElementById('bulkProgressLabel');
    const startBulkProcessingBtn = document.getElementById('startBulkProcessingBtn');
    const refreshOcrDiagnosticsBtn = document.getElementById('refreshOcrDiagnosticsBtn');
    const ocrDiagnosticsStatus = document.getElementById('ocrDiagnosticsStatus');
    const ocrToolTesseract = document.getElementById('ocrToolTesseract');
    const ocrToolPdftoppm = document.getElementById('ocrToolPdftoppm');
    
    let filesToProcess = [];

    function renderToolStatus(element, tool) {
        if (!element) {
            return;
        }

        if (!tool) {
            element.innerHTML = '<span class="text-red-600 font-semibold">Unavailable</span>';
            return;
        }

        element.innerHTML = tool.available
            ? `<span class="font-semibold text-green-700">Available</span><div class="mt-1 break-all text-xs text-slate-500">${tool.path}</div>`
            : '<span class="font-semibold text-red-600">Missing</span>';
    }

    async function refreshOcrDiagnostics() {
        if (!ocrDiagnosticsStatus) {
            return;
        }

        ocrDiagnosticsStatus.className = 'mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600';
        ocrDiagnosticsStatus.textContent = 'Checking OCR tools...';

        try {
            const response = await fetch('../api/ocr_status.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Failed to read OCR diagnostics.');
            }

            renderToolStatus(ocrToolTesseract, result.tools?.tesseract);
            renderToolStatus(ocrToolPdftoppm, result.tools?.pdftoppm);

            if (result.ocr_ready) {
                ocrDiagnosticsStatus.className = 'mt-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700';
                ocrDiagnosticsStatus.textContent = 'OCR is ready for scanned PDFs.';
            } else {
                ocrDiagnosticsStatus.className = 'mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700';
                ocrDiagnosticsStatus.textContent = result.diagnostic || 'OCR is not fully available.';
            }
        } catch (error) {
            renderToolStatus(ocrToolTesseract, null);
            renderToolStatus(ocrToolPdftoppm, null);
            ocrDiagnosticsStatus.className = 'mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700';
            ocrDiagnosticsStatus.textContent = error.message;
        }
    }

    // Helper to log extraction status
    function logExtraction(fileName, data, isError = false) {
        const row = document.createElement('tr');
        row.className = isError ? 'bg-red-50 text-red-700' : 'bg-white';
        
        if (isError) {
            row.innerHTML = `
                <td class="px-4 py-3 font-medium">${fileName}</td>
                <td colspan="3" class="px-4 py-3 italic text-xs">Error: ${data.error || 'Processing Failed'}</td>
                <td class="px-4 py-3"><i class="fa-solid fa-circle-xmark"></i></td>
            `;
        } else {
            const extractedCount = Number(data.count || 1);
            const countLabel = extractedCount > 1
                ? `<div class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-prcgold">${extractedCount} records extracted</div>`
                : '';

            row.innerHTML = `
                <td class="px-4 py-3 font-medium">${fileName}</td>
                <td class="px-4 py-3">${data.extracted.school}${countLabel}</td>
                <td class="px-4 py-3">${data.extracted.program}</td>
                <td class="px-4 py-3 font-bold">${data.extracted.region}</td>
                <td class="px-4 py-3 text-green-600"><i class="fa-solid fa-circle-check"></i></td>
            `;
        }
        
        extractionLogBody.appendChild(row);
        extractionResultsContainer.classList.remove('hidden');
    }

    const updateFileCount = () => {
        const count = bulkPdfInput.files.length;
        bulkPdfStatus.innerHTML = count > 0 
            ? `<span class="text-prcnavy font-bold">${count} PDFs</span> selected for batch parsing`
            : `Drag many PDFs here or click to browse`;
    };

    bulkPdfInput.addEventListener('change', updateFileCount);

    if (refreshOcrDiagnosticsBtn) {
        refreshOcrDiagnosticsBtn.addEventListener('click', refreshOcrDiagnostics);
    }

    refreshOcrDiagnostics();

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        bulkPdfDropZone.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    bulkPdfDropZone.addEventListener('drop', e => {
        bulkPdfInput.files = e.dataTransfer.files;
        updateFileCount();
    });

    bulkPdfForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const files = Array.from(bulkPdfInput.files);
        if (files.length === 0) return;

        startBulkProcessingBtn.disabled = true;
        extractionLogBody.innerHTML = '';
        extractionResultsContainer.classList.remove('hidden');
        
        let completed = 0;
        const total = files.length;
        const category = bulkPdfForm.querySelector('[name="category"]').value;
        const globalRegion = bulkPdfForm.querySelector('[name="global_region"]').value;
        const csrfToken = bulkPdfForm.querySelector('[name="csrf_token"]').value;

        bulkProgressLabel.innerText = `${completed} / ${total} Processing...`;

        for (const file of files) {
            const formData = new FormData();
            formData.append('document_file', file);
            formData.append('category', category);
            formData.append('global_region', globalRegion);
            formData.append('csrf_token', csrfToken);
            formData.append('status', 'NEW');

            try {
                const response = await fetch('../api/bulk_pdf_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    logExtraction(file.name, result);
                } else {
                    logExtraction(file.name, result, true);
                }
            } catch (err) {
                logExtraction(file.name, { error: err.message }, true);
            }

            completed++;
            bulkProgressLabel.innerText = `${completed} / ${total} Completed`;
        }

        startBulkProcessingBtn.disabled = false;
        bulkPdfForm.reset();
        bulkPdfStatus.innerHTML = 'All files processed. Select more?';
    });
});
