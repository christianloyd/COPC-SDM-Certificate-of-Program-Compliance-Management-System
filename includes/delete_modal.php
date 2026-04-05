<!-- 
    Delete Confirmation Modal Partial
    Included in: admin/records.php, admin/edit.php
-->
<style>
    :root {
        --dm-navy: #1e2d4a;
        --dm-red-main: #D03030;
        --dm-red-hover: #B52020;
        --dm-red-light: #FFF1F1;
        --dm-red-border: #FFCACA;
        --dm-red-text: #C53030;
        --dm-gray-50: #F9FAFB;
        --dm-gray-100: #F3F4F6;
        --dm-gray-200: #E5E7EB;
        --dm-gray-400: #9CA3AF;
        --dm-gray-600: #4B5563;
        --dm-gray-800: #1F2937;
        --dm-white: #ffffff;
        --dm-radius-modal: 28px;
        --dm-radius-card: 14px;
        --dm-font-display: 'Syne', sans-serif;
        --dm-font-body: 'DM Sans', sans-serif;
    }

    #dmOverlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }

    #dmOverlay.visible {
        opacity: 1;
        pointer-events: all;
    }

    .dm-card {
        width: 100%;
        max-width: 400px;
        background: var(--dm-white);
        border-radius: var(--dm-radius-modal);
        border: 0.5px solid var(--dm-gray-200);
        overflow: hidden;
        transform: scale(0.92) translateY(12px);
        transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 24px 64px rgba(15, 23, 42, 0.18);
    }

    #dmOverlay.visible .dm-card {
        transform: scale(1) translateY(0);
    }

    .dm-top {
        padding: 2.5rem 2.5rem 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .dm-icon-wrap {
        position: relative;
        width: 72px;
        height: 72px;
    }

    .dm-icon-ring {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: var(--dm-red-light);
        border: 1.5px solid var(--dm-red-border);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 1;
    }

    .dm-icon-ring svg {
        width: 28px;
        height: 28px;
        color: var(--dm-red-main);
    }

    .dm-pulse {
        position: absolute;
        inset: -6px;
        border-radius: 50%;
        border: 1.5px solid var(--dm-red-border);
        opacity: 0;
        animation: dm-pulse-out 2.4s ease-out infinite;
    }

    @keyframes dm-pulse-out {
        0%   { transform: scale(0.9); opacity: 0.6; }
        100% { transform: scale(1.5); opacity: 0; }
    }

    .dm-title {
        font-family: var(--dm-font-display);
        font-size: 22px;
        font-weight: 800;
        color: var(--dm-gray-800);
        letter-spacing: -0.5px;
        text-align: center;
    }

    .dm-sub {
        font-size: 10px;
        font-weight: 500;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--dm-gray-400);
        text-align: center;
        margin-top: -0.5rem;
    }

    .dm-body {
        padding: 0 2rem 1.5rem;
    }

    .dm-warning {
        background: var(--dm-gray-50);
        border: 0.5px solid var(--dm-gray-200);
        border-radius: var(--dm-radius-card);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .dm-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: var(--dm-red-light);
        border: 0.5px solid var(--dm-red-border);
        border-radius: 6px;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 500;
        color: var(--dm-red-text);
        margin-bottom: 0.6rem;
    }

    .dm-warning p {
        font-size: 13px;
        line-height: 1.7;
        color: var(--dm-gray-600);
    }

    .dm-danger {
        color: var(--dm-red-text);
        font-weight: 600;
    }

    .dm-footer {
        padding: 0 2rem 2.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .dm-btn-confirm {
        width: 100%;
        padding: 14px;
        border-radius: var(--dm-radius-card);
        border: none;
        background: var(--dm-red-main);
        color: white;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background 0.15s, transform 0.1s;
    }

    .dm-btn-confirm:hover { background: var(--dm-red-hover); }
    .dm-btn-confirm:active { transform: scale(0.97); }

    .dm-btn-cancel {
        width: 100%;
        padding: 13px;
        border-radius: var(--dm-radius-card);
        border: 0.5px solid var(--dm-gray-200);
        background: transparent;
        color: var(--dm-gray-600);
        font-size: 12px;
        font-weight: 500;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        cursor: pointer;
        transition: background 0.15s;
    }

    .dm-btn-cancel:hover { background: var(--dm-gray-100); }
</style>

<!-- Modal Structure -->
<div id="dmOverlay" role="dialog" aria-modal="true">
    <div class="dm-card text-center">
        <div class="dm-top">
            <div class="dm-icon-wrap">
                <div class="dm-pulse"></div>
                <div class="dm-icon-ring">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"></path>
                        <path d="M10 11v6M14 11v6"></path>
                        <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"></path>
                    </svg>
                </div>
            </div>
            <div>
                <p class="dm-title">Confirm Deletion?</p>
                <p class="dm-sub">Permanent data removal</p>
            </div>
        </div>

        <div class="dm-body">
            <div class="dm-warning">
                <span class="dm-tag">Irreversible Action</span>
                <p>You are about to permanently delete this record. This action <span class="dm-danger">cannot be undone</span> and will remove all attachments.</p>
            </div>
            <p style="font-size: 11px; font-style: italic; color: var(--dm-gray-400);">Are you sure you want to proceed?</p>
        </div>

        <div class="dm-footer">
            <button type="button" class="dm-btn-confirm" id="dmConfirmBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:14px; height:14px">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                Confirm &amp; Delete
            </button>
            <button type="button" class="dm-btn-cancel" id="dmCancelBtn">
                Stay Safe, Cancel
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    let pendingDeleteId = null;
    const overlay    = document.getElementById('dmOverlay');
    const confirmBtn = document.getElementById('dmConfirmBtn');
    const cancelBtn  = document.getElementById('dmCancelBtn');
    
    // Parent components must have forms with these specific IDs
    const deleteForm = document.getElementById('deleteForm');
    const deleteIdInput = document.getElementById('deleteId');

    /* ── Global trigger function ── */
    window.confirmDelete = function (id) {
        pendingDeleteId = id;
        overlay.classList.add('visible');
    };

    function closeModal() {
        overlay.classList.remove('visible');
        pendingDeleteId = null;
    }

    cancelBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

    confirmBtn.addEventListener('click', function () {
        if (pendingDeleteId !== null && deleteForm && deleteIdInput) {
            deleteIdInput.value = pendingDeleteId;
            deleteForm.submit();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('visible')) closeModal();
    });
})();
</script>