// General admin scripts
document.addEventListener('DOMContentLoaded', () => {
    const appAlertModal = document.getElementById('appAlertModal');
    const appAlertIcon = document.getElementById('appAlertIcon');
    const appAlertTitle = document.getElementById('appAlertTitle');
    const appAlertSubtitle = document.getElementById('appAlertSubtitle');
    const appAlertMessage = document.getElementById('appAlertMessage');
    const appAlertClose = document.getElementById('appAlertClose');
    const appAlertOk = document.getElementById('appAlertOk');
    const appConfirmModal = document.getElementById('appConfirmModal');
    const appConfirmIcon = document.getElementById('appConfirmIcon');
    const appConfirmTitle = document.getElementById('appConfirmTitle');
    const appConfirmSubtitle = document.getElementById('appConfirmSubtitle');
    const appConfirmMessage = document.getElementById('appConfirmMessage');
    const appConfirmClose = document.getElementById('appConfirmClose');
    const appConfirmCancel = document.getElementById('appConfirmCancel');
    const appConfirmOk = document.getElementById('appConfirmOk');
    const appToastStack = document.getElementById('appToastStack');
    let confirmResolver = null;

    const closeAppAlert = () => {
        if (!appAlertModal) {
            return;
        }

        appAlertModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    window.showAppAlert = ({ title = 'Notice', message = '', type = 'info', subtitle = '' } = {}) => {
        if (!appAlertModal) {
            return;
        }

        const typeConfig = {
            info: {
                iconWrap: 'bg-blue-50 text-blue-600',
                icon: 'fa-circle-info',
                subtitle: 'System message',
            },
            success: {
                iconWrap: 'bg-green-50 text-green-600',
                icon: 'fa-circle-check',
                subtitle: 'Success message',
            },
            error: {
                iconWrap: 'bg-red-50 text-red-500',
                icon: 'fa-circle-exclamation',
                subtitle: 'Error message',
            },
        };

        const config = typeConfig[type] || typeConfig.info;
        appAlertIcon.className = `mr-4 flex h-12 w-12 items-center justify-center rounded-2xl ${config.iconWrap}`;
        appAlertIcon.innerHTML = `<i class="fa-solid ${config.icon} text-xl"></i>`;
        appAlertTitle.textContent = title;
        appAlertSubtitle.textContent = subtitle || config.subtitle;
        appAlertMessage.textContent = message;

        appAlertModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeAppConfirm = (result = false) => {
        if (!appConfirmModal) {
            return;
        }

        appConfirmModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');

        if (confirmResolver) {
            confirmResolver(result);
            confirmResolver = null;
        }
    };

    window.showAppConfirm = ({ title = 'Confirm Action', message = '', subtitle = 'Review before continuing', confirmText = 'Continue', cancelText = 'Cancel', type = 'warning' } = {}) => {
        if (!appConfirmModal) {
            return Promise.resolve(window.confirm(message || title));
        }

        const typeConfig = {
            info: {
                iconWrap: 'bg-blue-50 text-blue-600',
                icon: 'fa-circle-info',
            },
            warning: {
                iconWrap: 'bg-amber-50 text-amber-600',
                icon: 'fa-triangle-exclamation',
            },
            danger: {
                iconWrap: 'bg-red-50 text-red-500',
                icon: 'fa-trash-can',
            },
        };

        const config = typeConfig[type] || typeConfig.warning;
        appConfirmIcon.className = `mr-4 flex h-12 w-12 items-center justify-center rounded-2xl ${config.iconWrap}`;
        appConfirmIcon.innerHTML = `<i class="fa-solid ${config.icon} text-xl"></i>`;
        appConfirmTitle.textContent = title;
        appConfirmSubtitle.textContent = subtitle;
        appConfirmMessage.textContent = message;
        appConfirmOk.textContent = confirmText;
        appConfirmCancel.textContent = cancelText;

        appConfirmModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        return new Promise((resolve) => {
            confirmResolver = resolve;
        });
    };

    [appAlertClose, appAlertOk].forEach((button) => {
        if (button) {
            button.addEventListener('click', closeAppAlert);
        }
    });

    if (appAlertModal) {
        appAlertModal.addEventListener('click', (event) => {
            if (event.target === appAlertModal) {
                closeAppAlert();
            }
        });
    }

    [appConfirmClose, appConfirmCancel].forEach((button) => {
        if (button) {
            button.addEventListener('click', () => closeAppConfirm(false));
        }
    });

    if (appConfirmOk) {
        appConfirmOk.addEventListener('click', () => closeAppConfirm(true));
    }

    if (appConfirmModal) {
        appConfirmModal.addEventListener('click', (event) => {
            if (event.target === appConfirmModal) {
                closeAppConfirm(false);
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && appAlertModal && !appAlertModal.classList.contains('hidden')) {
            closeAppAlert();
        }

        if (event.key === 'Escape' && appConfirmModal && !appConfirmModal.classList.contains('hidden')) {
            closeAppConfirm(false);
        }
    });

    const toastTypeConfig = {
        success: {
            card: 'border-green-200 bg-white',
            iconWrap: 'bg-green-50 text-green-600',
            icon: 'fa-circle-check',
            title: 'Success',
        },
        error: {
            card: 'border-red-200 bg-white',
            iconWrap: 'bg-red-50 text-red-500',
            icon: 'fa-circle-xmark',
            title: 'Error',
        },
        warning: {
            card: 'border-amber-200 bg-white',
            iconWrap: 'bg-amber-50 text-amber-600',
            icon: 'fa-triangle-exclamation',
            title: 'Warning',
        },
        info: {
            card: 'border-blue-200 bg-white',
            iconWrap: 'bg-blue-50 text-blue-600',
            icon: 'fa-circle-info',
            title: 'Notice',
        },
    };

    window.showAppToast = ({ title = '', message = '', type = 'info', duration = 4200 } = {}) => {
        if (!appToastStack) {
            return;
        }

        const config = toastTypeConfig[type] || toastTypeConfig.info;
        const toast = document.createElement('div');
        toast.className = `pointer-events-auto translate-y-0 opacity-100 transition-all duration-300 ${config.card} overflow-hidden rounded-2xl border shadow-soft`;
        toast.innerHTML = `
            <div class="flex items-start gap-3 p-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ${config.iconWrap}">
                    <i class="fa-solid ${config.icon} text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold text-prcnavy">${title || config.title}</div>
                    <div class="mt-1 text-sm leading-relaxed text-gray-600">${message}</div>
                </div>
                <button type="button" class="toast-close inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition hover:bg-gray-200 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        `;

        const dismiss = () => {
            toast.classList.add('translate-y-2', 'opacity-0');
            setTimeout(() => toast.remove(), 260);
        };

        const closeButton = toast.querySelector('.toast-close');
        if (closeButton) {
            closeButton.addEventListener('click', dismiss);
        }

        appToastStack.appendChild(toast);
        window.setTimeout(dismiss, duration);
    };

    // Auto-dismiss PHP-generated alerts after 5 seconds if they don't have errors
    const successAlerts = document.querySelectorAll('.bg-green-50');
    successAlerts.forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });

    // Extract Text Manually
    const extractBtns = document.querySelectorAll('.trigger-extract-btn');
    extractBtns.forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-id');
            const token = document.querySelector('input[name="csrf_token"]').value;

            if (!id || !token) return;

            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Extracting...';

            try {
                const fd = new FormData();
                fd.append('id', id);
                fd.append('csrf_token', token);

                const url = (typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : '..') + '/api/extract.php';
                const res = await fetch(url, {
                    method: 'POST',
                    body: fd
                });

                const json = await res.json();
                if (json.success) {
                    window.showAppToast({ title: 'Extraction Complete', message: json.message, type: 'success' });
                } else {
                    window.showAppToast({ title: 'Extraction Failed', message: json.error, type: 'error' });
                }
            } catch (err) {
                window.showAppToast({ title: 'Network Error', message: 'Network error during extraction.', type: 'error' });
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    });

    // ── Searchable Combobox Global Initializer ───────────────────────────
    function initCombobox(wrapper) {
        const input    = wrapper.querySelector('.combobox-input');
        const dropdown = wrapper.querySelector('.combobox-dropdown');
        const options  = JSON.parse(wrapper.dataset.options || '[]');
        let activeIdx  = -1;

        function renderDropdown(filterText = '') {
            const query = filterText.toLowerCase().trim();
            const filtered = options.filter(opt => opt.toLowerCase().includes(query));
            
            if (filtered.length === 0 && query === '') {
                dropdown.classList.remove('active');
                return;
            }

            let html = '';
            const displayLimit = 50;
            const matches = filtered.slice(0, displayLimit);

            matches.forEach((opt, idx) => {
                const highlighted = opt.replace(new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'), '<span class="combobox-match">$1</span>');
                html += `<div class="combobox-item" data-value="${escapeHtml(opt)}">${highlighted}</div>`;
            });

            if (matches.length === 0 && query !== '') {
                html = `<div class="combobox-no-results">No existing entries match "${escapeHtml(filterText)}"</div>`;
                html += `<div class="combobox-item new-entry" data-value="${escapeHtml(filterText)}">Add new: "${escapeHtml(filterText)}"</div>`;
            } else if (filtered.length > displayLimit) {
                html += `<div class="combobox-no-results text-[10px] opacity-60">Showing first ${displayLimit} matches...</div>`;
            }

            dropdown.innerHTML = html;
            dropdown.classList.add('active');
            activeIdx = -1;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        if (input) {
            input.addEventListener('input', (e) => renderDropdown(e.target.value));
            input.addEventListener('focus', () => {
                if (input.value.trim() !== '') renderDropdown(input.value);
                else if (options.length > 0) renderDropdown('');
            });
            input.addEventListener('blur', () => {
                setTimeout(() => dropdown.classList.remove('active'), 200);
            });
        }

        dropdown.addEventListener('mousedown', (e) => {
            const item = e.target.closest('.combobox-item');
            if (item) {
                e.preventDefault();
                input.value = item.dataset.value;
                dropdown.classList.remove('active');
                input.dispatchEvent(new Event('change'));
            }
        });

        input.addEventListener('keydown', (e) => {
            const items = dropdown.querySelectorAll('.combobox-item');
            if (!dropdown.classList.contains('active')) {
                if (e.key === 'ArrowDown') renderDropdown(input.value);
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, items.length - 1);
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                updateSelection(items);
            } else if (e.key === 'Enter' && activeIdx >= 0) {
                e.preventDefault();
                input.value = items[activeIdx].dataset.value;
                dropdown.classList.remove('active');
                input.dispatchEvent(new Event('change'));
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        function updateSelection(items) {
            items.forEach((item, idx) => {
                item.classList.toggle('selected', idx === activeIdx);
                if (idx === activeIdx) item.scrollIntoView({ block: 'nearest' });
            });
        }
    }

    document.querySelectorAll('.combobox-wrapper').forEach(initCombobox);
});
