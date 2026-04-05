// General admin scripts
document.addEventListener('DOMContentLoaded', () => {
    const appAlertModal = document.getElementById('appAlertModal');
    const appAlertIcon = document.getElementById('appAlertIcon');
    const appAlertTitle = document.getElementById('appAlertTitle');
    const appAlertSubtitle = document.getElementById('appAlertSubtitle');
    const appAlertMessage = document.getElementById('appAlertMessage');
    const appAlertClose = document.getElementById('appAlertClose');
    const appAlertOk = document.getElementById('appAlertOk');

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

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && appAlertModal && !appAlertModal.classList.contains('hidden')) {
            closeAppAlert();
        }
    });

    // Toast Notification System
    const createToast = (msg, type = 'success') => {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 z-50 flex translate-y-0 transform items-center rounded px-6 py-3 opacity-100 shadow-lg transition-opacity duration-300';

        if (type === 'success') {
            toast.classList.add('bg-green-600', 'text-white');
            toast.innerHTML = `<svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> ${msg}`;
        } else {
            toast.classList.add('bg-red-600', 'text-white');
            toast.innerHTML = `<svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> ${msg}`;
        }

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('opacity-100', 'translate-y-0');
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
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
                    createToast(json.message, 'success');
                } else {
                    createToast(json.error, 'error');
                }
            } catch (err) {
                createToast('Network error during extraction.', 'error');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    });
});
