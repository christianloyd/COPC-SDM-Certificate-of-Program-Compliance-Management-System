document.addEventListener('DOMContentLoaded', () => {
    const singleForm = document.getElementById('singleUploadForm');
    const batchForm = document.getElementById('batchImportForm');
    const modeInputs = document.querySelectorAll('input[name="upload_mode"]');
    const singlePanel = document.getElementById('singleUploadPanel');
    const batchPanel = document.getElementById('batchImportPanel');
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileStatus = document.getElementById('fileStatus');

    const importDropZone = document.getElementById('importDropZone');
    const importInput = document.getElementById('importInput');
    const importStatus = document.getElementById('importStatus');

    const progressOverlay = document.getElementById('progressOverlay');

    const bulkPdfPanel = document.getElementById('bulkPdfPanel');

    const setUploadMode = (mode) => {
        if (!singlePanel || !batchPanel || !bulkPdfPanel) {
            return;
        }

        singlePanel.classList.toggle('hidden', mode !== 'single');
        singlePanel.classList.toggle('flex', mode === 'single');
        
        batchPanel.classList.toggle('hidden', mode !== 'batch');
        batchPanel.classList.toggle('flex', mode === 'batch');
        
        bulkPdfPanel.classList.toggle('hidden', mode !== 'bulkpdf');
        bulkPdfPanel.classList.toggle('flex', mode === 'bulkpdf');
    };

    const showModalAlert = (title, message, type = 'info') => {
        if (typeof window.showAppAlert === 'function') {
            window.showAppAlert({ title, message, type });
            return;
        }

        console[type === 'error' ? 'error' : 'log'](`${title}: ${message}`);
    };

    const showToast = (title, message, type = 'info') => {
        if (typeof window.showAppToast === 'function') {
            window.showAppToast({ title, message, type });
            return;
        }

        console[type === 'error' ? 'error' : 'log'](`${title}: ${message}`);
    };

    const showConfirmationModal = async ({
        title = 'Confirm Action',
        message = 'Are you sure you want to continue?',
        subtitle = 'Review before continuing',
        confirmText = 'Continue'
    } = {}) => {
        if (typeof window.showAppConfirm === 'function') {
            return window.showAppConfirm({
                title,
                message,
                subtitle,
                confirmText,
                cancelText: 'Cancel',
                type: 'warning'
            });
        }

        return window.confirm(message);
    };

    if (modeInputs.length) {
        modeInputs.forEach((input) => {
            input.addEventListener('change', () => setUploadMode(input.value));
        });

        const selectedMode = document.querySelector('input[name="upload_mode"]:checked');
        setUploadMode(selectedMode ? selectedMode.value : 'single');
    }

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('bg-prclight/50', 'border-prcgold'));
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('bg-prclight/50', 'border-prcgold'));
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
            updateFileName(files[0].name);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) updateFileName(fileInput.files[0].name);
    });

    function updateFileName(name) {
        fileStatus.innerHTML = `<span class="text-prcnavy font-bold">${name}</span> selected`;
    }

    const categorySelect = singleForm.querySelector('select[name="category"]');
    const studentListContainer = document.getElementById('studentListContainer');

    if (categorySelect && studentListContainer) {
        categorySelect.addEventListener('change', () => {
            if (categorySelect.value === 'COPC Exemption') {
                studentListContainer.classList.remove('hidden');
            } else {
                studentListContainer.classList.add('hidden');
            }
        });
    }

    async function parseJsonResponse(response) {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            const cleaned = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            throw new Error(cleaned || `Unexpected server response (HTTP ${response.status})`);
        }
    }

    singleForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const confirmed = await showConfirmationModal({
            title: 'Confirm Upload',
            subtitle: 'Single record submission',
            message: 'This will save the uploaded file and create record entries in the Record Vault. Continue?',
            confirmText: 'Upload Now'
        });

        if (!confirmed) {
            return;
        }

        progressOverlay.classList.remove('hidden');

        try {
            const formData = new FormData(singleForm);
            const response = await fetch('../api/upload_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await parseJsonResponse(response);
            if (result.success) {
                showToast('Upload Complete', result.message || 'Record uploaded successfully.', 'success');
                singleForm.reset();
                fileStatus.innerText = 'Drag and drop file here or click to browse';
            } else {
                const detail = result.details ? `\n\nDetails: ${result.details}` : '';
                showToast('Upload Failed', result.error || 'Unknown server error.', 'error');
                showModalAlert('Upload Failed', `${result.error || 'Unknown server error.'}${detail}`, 'error');
            }
        } catch (error) {
            showToast('Upload Error', error.message, 'error');
            showModalAlert('Upload Error', error.message, 'error');
        } finally {
            progressOverlay.classList.add('hidden');
        }
    });

    if (importDropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
            importDropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            importDropZone.addEventListener(eventName, () => importDropZone.classList.add('bg-prclight/50', 'border-prcgold'));
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            importDropZone.addEventListener(eventName, () => importDropZone.classList.remove('bg-prclight/50', 'border-prcgold'));
        });

        importDropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length) {
                importInput.files = files;
                updateImportName(files[0].name);
            }
        });
    }

    if (importInput) {
        importInput.addEventListener('change', () => {
            if (importInput.files.length) updateImportName(importInput.files[0].name);
        });
    }

    function updateImportName(name) {
        if (importStatus) {
            importStatus.innerHTML = `<span class="text-prcnavy font-bold">${name}</span> ready for batch ingestion`;
        }
    }

    if (batchForm) {
        batchForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!importInput.files.length) {
                showModalAlert('Import Error', 'Please select a master list file first.', 'error');
                return;
            }

            const confirmed = await showConfirmationModal({
                title: 'Confirm Batch Import',
                subtitle: 'Bulk record creation',
                message: 'This will import the selected master list and save all parsed rows into the Record Vault. Continue?',
                confirmText: 'Start Import'
            });

            if (!confirmed) {
                return;
            }

            progressOverlay.classList.remove('hidden');

            try {
                const formData = new FormData(batchForm);
                const response = await fetch('../api/import_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await parseJsonResponse(response);
                if (result.success) {
                    showToast('Import Complete', result.message, 'success');
                    batchForm.reset();
                    importStatus.innerText = 'Drop Masterlist file here';
                } else {
                    const detail = result.details ? `\n\nDetails: ${result.details}` : '';
                    showToast('Import Failed', result.error || 'Unknown server error.', 'error');
                    showModalAlert('Import Failed', `${result.error || 'Unknown server error.'}${detail}`, 'error');
                }
            } catch (error) {
                showToast('Import Error', error.message, 'error');
                showModalAlert('Import Error', error.message, 'error');
            } finally {
                progressOverlay.classList.add('hidden');
            }
        });
    }
});
