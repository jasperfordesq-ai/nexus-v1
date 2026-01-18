<?php
/**
 * Organization UI Components
 * Shared components for modals, loaders, toasts, and form validation
 * Include this file once in your layout or page
 */
?>

<style>
/* ============================================
   MODAL SYSTEM
   ============================================ */
.org-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.org-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.org-modal {
    background: white;
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    max-width: 480px;
    width: 100%;
    transform: scale(0.9) translateY(20px);
    transition: all 0.3s ease;
    overflow: hidden;
}

.org-modal-overlay.active .org-modal {
    transform: scale(1) translateY(0);
}

[data-theme="dark"] .org-modal {
    background: #1e293b;
}

.org-modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(229, 231, 235, 0.5);
}

[data-theme="dark"] .org-modal-header {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.org-modal-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.org-modal-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.org-modal-icon.danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.org-modal-icon.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.org-modal-icon.info {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.org-modal-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

[data-theme="dark"] .org-modal-title {
    color: #f1f5f9;
}

.org-modal-body {
    padding: 24px;
}

.org-modal-text {
    color: #6b7280;
    font-size: 0.95rem;
    line-height: 1.6;
    margin: 0 0 16px 0;
}

[data-theme="dark"] .org-modal-text {
    color: #94a3b8;
}

.org-modal-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(107, 114, 128, 0.2);
    border-radius: 10px;
    font-size: 0.95rem;
    background: white;
    color: #1f2937;
    transition: all 0.2s;
}

.org-modal-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .org-modal-input {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

.org-modal-footer {
    display: flex;
    gap: 12px;
    padding: 16px 24px;
    background: rgba(249, 250, 251, 0.5);
    border-top: 1px solid rgba(229, 231, 235, 0.5);
}

[data-theme="dark"] .org-modal-footer {
    background: rgba(15, 23, 42, 0.5);
    border-top-color: rgba(255, 255, 255, 0.1);
}

.org-modal-btn {
    flex: 1;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.org-modal-btn.cancel {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.org-modal-btn.cancel:hover {
    background: rgba(107, 114, 128, 0.2);
}

.org-modal-btn.confirm {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.org-modal-btn.confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.org-modal-btn.danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.org-modal-btn.danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
}

/* ============================================
   LOADING STATES
   ============================================ */
.org-loader {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.org-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: orgSpin 0.8s linear infinite;
}

.org-spinner.dark {
    border-color: rgba(16, 185, 129, 0.2);
    border-top-color: #10b981;
}

@keyframes orgSpin {
    to { transform: rotate(360deg); }
}

/* Button loading state */
.org-btn-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.8;
}

.org-btn-loading .org-btn-text {
    visibility: hidden;
}

.org-btn-loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: orgSpin 0.8s linear infinite;
}

/* Skeleton loading */
.org-skeleton {
    background: linear-gradient(90deg,
        rgba(229, 231, 235, 0.5) 0%,
        rgba(229, 231, 235, 0.8) 50%,
        rgba(229, 231, 235, 0.5) 100%);
    background-size: 200% 100%;
    animation: orgSkeletonShimmer 1.5s ease-in-out infinite;
    border-radius: 8px;
}

[data-theme="dark"] .org-skeleton {
    background: linear-gradient(90deg,
        rgba(51, 65, 85, 0.5) 0%,
        rgba(51, 65, 85, 0.8) 50%,
        rgba(51, 65, 85, 0.5) 100%);
    background-size: 200% 100%;
}

@keyframes orgSkeletonShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.org-skeleton-text {
    height: 16px;
    margin-bottom: 8px;
}

.org-skeleton-text.short {
    width: 60%;
}

.org-skeleton-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
}

.org-skeleton-card {
    height: 120px;
}

/* ============================================
   TOAST NOTIFICATIONS
   ============================================ */
.org-toast-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
}

.org-toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    transform: translateX(120%);
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    pointer-events: auto;
    max-width: 380px;
}

.org-toast.show {
    transform: translateX(0);
}

[data-theme="dark"] .org-toast {
    background: #1e293b;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
}

.org-toast-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.org-toast.success .org-toast-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.org-toast.error .org-toast-icon {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.org-toast.warning .org-toast-icon {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.org-toast.info .org-toast-icon {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.org-toast-content {
    flex: 1;
    min-width: 0;
}

.org-toast-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: #1f2937;
    margin: 0 0 2px 0;
}

[data-theme="dark"] .org-toast-title {
    color: #f1f5f9;
}

.org-toast-message {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 0;
}

.org-toast-close {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: transparent;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    flex-shrink: 0;
}

.org-toast-close:hover {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

/* ============================================
   FORM VALIDATION
   ============================================ */
.org-form-group {
    position: relative;
}

.org-form-group .org-validation-icon {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    opacity: 0;
    transition: all 0.2s;
}

.org-form-group.valid .org-validation-icon.valid-icon {
    opacity: 1;
    color: #10b981;
}

.org-form-group.invalid .org-validation-icon.invalid-icon {
    opacity: 1;
    color: #ef4444;
}

.org-form-group.valid .org-form-input {
    border-color: #10b981;
}

.org-form-group.invalid .org-form-input {
    border-color: #ef4444;
}

.org-validation-message {
    font-size: 0.8rem;
    margin-top: 6px;
    display: none;
}

.org-form-group.invalid .org-validation-message {
    display: block;
    color: #ef4444;
}

/* ============================================
   EMPTY STATES (ENHANCED)
   ============================================ */
.org-empty-state {
    text-align: center;
    padding: 60px 30px;
}

.org-empty-illustration {
    width: 120px;
    height: 120px;
    margin: 0 auto 24px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.org-empty-illustration::before {
    content: '';
    position: absolute;
    inset: -8px;
    border: 2px dashed rgba(16, 185, 129, 0.2);
    border-radius: 50%;
    animation: orgEmptyPulse 3s ease-in-out infinite;
}

@keyframes orgEmptyPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.5; }
}

.org-empty-illustration i {
    font-size: 3rem;
    color: #10b981;
    opacity: 0.6;
}

.org-empty-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 8px 0;
}

[data-theme="dark"] .org-empty-title {
    color: #f1f5f9;
}

.org-empty-description {
    font-size: 0.95rem;
    color: #6b7280;
    margin: 0 0 24px 0;
    max-width: 320px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

.org-empty-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.org-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}

.org-empty-btn.primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.org-empty-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.org-empty-btn.secondary {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.org-empty-btn.secondary:hover {
    background: rgba(107, 114, 128, 0.2);
}

/* ============================================
   CHECKBOX SELECTION
   ============================================ */
.org-checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}

.org-checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(107, 114, 128, 0.3);
    border-radius: 6px;
    appearance: none;
    -webkit-appearance: none;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    flex-shrink: 0;
}

.org-checkbox:checked {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: #10b981;
}

.org-checkbox:checked::after {
    content: '';
    position: absolute;
    left: 5px;
    top: 2px;
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.org-checkbox:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.org-select-all-bar {
    display: none;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
    border-bottom: 1px solid rgba(16, 185, 129, 0.2);
    gap: 16px;
}

.org-select-all-bar.show {
    display: flex;
}

.org-select-count {
    font-weight: 600;
    color: #059669;
    font-size: 0.9rem;
}

.org-bulk-actions {
    display: flex;
    gap: 8px;
}

.org-bulk-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.org-bulk-btn.approve {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.org-bulk-btn.reject {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.org-bulk-btn.reject:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* ============================================
   BALANCE GAUGE
   ============================================ */
.org-balance-gauge {
    position: relative;
    width: 100%;
    height: 8px;
    background: rgba(107, 114, 128, 0.2);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 12px;
}

.org-balance-gauge-fill {
    height: 100%;
    border-radius: 4px;
    transition: all 0.5s ease;
}

.org-balance-gauge-fill.healthy {
    background: linear-gradient(90deg, #10b981, #059669);
}

.org-balance-gauge-fill.low {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.org-balance-gauge-fill.critical {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

.org-balance-thresholds {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    font-size: 0.7rem;
    color: #9ca3af;
}

/* ============================================
   ACCESSIBILITY
   ============================================ */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Focus visible for keyboard navigation */
*:focus-visible {
    outline: 2px solid #10b981;
    outline-offset: 2px;
}

/* Skip link for accessibility */
.org-skip-link {
    position: absolute;
    top: -40px;
    left: 0;
    background: #10b981;
    color: white;
    padding: 8px 16px;
    z-index: 10001;
    transition: top 0.3s;
}

.org-skip-link:focus {
    top: 0;
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* ============================================
   LIVE UPDATE INDICATOR
   ============================================ */
.org-live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: #10b981;
    padding: 4px 10px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 12px;
}

.org-live-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: orgLivePulse 2s ease-in-out infinite;
}

@keyframes orgLivePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .org-toast-container {
        left: 20px;
        right: 20px;
    }

    .org-toast {
        max-width: none;
    }

    .org-modal {
        margin: 20px;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }
}
</style>

<!-- Toast Container -->
<div class="org-toast-container" id="orgToastContainer" role="alert" aria-live="polite"></div>

<!-- Modal Container -->
<div class="org-modal-overlay" id="orgModalOverlay" role="dialog" aria-modal="true" aria-labelledby="orgModalTitle">
    <div class="org-modal">
        <div class="org-modal-header">
            <div class="org-modal-icon" id="orgModalIcon">
                <i class="fa-solid fa-question"></i>
            </div>
            <h3 class="org-modal-title" id="orgModalTitle">Confirm Action</h3>
        </div>
        <div class="org-modal-body">
            <p class="org-modal-text" id="orgModalText">Are you sure you want to proceed?</p>
            <input type="text" class="org-modal-input" id="orgModalInput" style="display: none;" placeholder="">
        </div>
        <div class="org-modal-footer">
            <button type="button" class="org-modal-btn cancel" id="orgModalCancel">Cancel</button>
            <button type="button" class="org-modal-btn confirm" id="orgModalConfirm">Confirm</button>
        </div>
    </div>
</div>

<script>
/**
 * Organization UI Components - JavaScript
 */
const OrgUI = {
    // Modal System
    modal: {
        overlay: null,
        resolvePromise: null,

        init() {
            this.overlay = document.getElementById('orgModalOverlay');
            document.getElementById('orgModalCancel').addEventListener('click', () => this.close(false));
            document.getElementById('orgModalConfirm').addEventListener('click', () => this.close(true));
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) this.close(false);
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.overlay.classList.contains('active')) {
                    this.close(false);
                }
            });
        },

        show(options = {}) {
            const {
                title = 'Confirm Action',
                message = 'Are you sure you want to proceed?',
                type = 'warning', // warning, danger, success, info
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                confirmClass = 'confirm',
                showInput = false,
                inputPlaceholder = '',
                inputValue = ''
            } = options;

            document.getElementById('orgModalTitle').textContent = title;
            document.getElementById('orgModalText').textContent = message;
            document.getElementById('orgModalConfirm').textContent = confirmText;
            document.getElementById('orgModalCancel').textContent = cancelText;

            const confirmBtn = document.getElementById('orgModalConfirm');
            confirmBtn.className = 'org-modal-btn ' + confirmClass;

            const icon = document.getElementById('orgModalIcon');
            icon.className = 'org-modal-icon ' + type;
            const icons = { warning: 'fa-exclamation-triangle', danger: 'fa-trash', success: 'fa-check', info: 'fa-info-circle' };
            icon.innerHTML = `<i class="fa-solid ${icons[type] || icons.warning}"></i>`;

            const input = document.getElementById('orgModalInput');
            input.style.display = showInput ? 'block' : 'none';
            input.placeholder = inputPlaceholder;
            input.value = inputValue;

            this.overlay.classList.add('active');
            if (showInput) input.focus();

            return new Promise(resolve => {
                this.resolvePromise = resolve;
            });
        },

        close(confirmed) {
            this.overlay.classList.remove('active');
            const inputValue = document.getElementById('orgModalInput').value;
            if (this.resolvePromise) {
                this.resolvePromise({ confirmed, inputValue });
                this.resolvePromise = null;
            }
        },

        // Convenience methods
        async confirm(message, title = 'Confirm Action') {
            const result = await this.show({ title, message, type: 'warning' });
            return result.confirmed;
        },

        async confirmDanger(message, title = 'Warning') {
            const result = await this.show({
                title,
                message,
                type: 'danger',
                confirmText: 'Delete',
                confirmClass: 'danger'
            });
            return result.confirmed;
        },

        async prompt(message, title = 'Enter Value', placeholder = '', defaultValue = '') {
            const result = await this.show({
                title,
                message,
                type: 'info',
                showInput: true,
                inputPlaceholder: placeholder,
                inputValue: defaultValue
            });
            return result.confirmed ? result.inputValue : null;
        }
    },

    // Toast Notifications
    toast: {
        container: null,

        init() {
            this.container = document.getElementById('orgToastContainer');
        },

        show(options = {}) {
            const {
                title = 'Notification',
                message = '',
                type = 'info', // success, error, warning, info
                duration = 5000
            } = options;

            const icons = {
                success: 'fa-check',
                error: 'fa-times',
                warning: 'fa-exclamation',
                info: 'fa-info'
            };

            const toast = document.createElement('div');
            toast.className = `org-toast ${type}`;
            toast.innerHTML = `
                <div class="org-toast-icon">
                    <i class="fa-solid ${icons[type]}"></i>
                </div>
                <div class="org-toast-content">
                    <p class="org-toast-title">${this.escapeHtml(title)}</p>
                    ${message ? `<p class="org-toast-message">${this.escapeHtml(message)}</p>` : ''}
                </div>
                <button class="org-toast-close" aria-label="Close notification">
                    <i class="fa-solid fa-times"></i>
                </button>
            `;

            toast.querySelector('.org-toast-close').addEventListener('click', () => this.dismiss(toast));

            this.container.appendChild(toast);

            // Trigger animation
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => this.dismiss(toast), duration);
            }

            return toast;
        },

        dismiss(toast) {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        },

        success(title, message = '') {
            return this.show({ title, message, type: 'success' });
        },

        error(title, message = '') {
            return this.show({ title, message, type: 'error', duration: 8000 });
        },

        warning(title, message = '') {
            return this.show({ title, message, type: 'warning' });
        },

        info(title, message = '') {
            return this.show({ title, message, type: 'info' });
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    },

    // Form Validation
    validation: {
        rules: {
            required: (value) => value.trim() !== '' ? null : 'This field is required',
            email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? null : 'Please enter a valid email',
            min: (value, min) => parseFloat(value) >= min ? null : `Minimum value is ${min}`,
            max: (value, max) => parseFloat(value) <= max ? null : `Maximum value is ${max}`,
            minLength: (value, len) => value.length >= len ? null : `Minimum ${len} characters required`,
            positive: (value) => parseFloat(value) > 0 ? null : 'Value must be positive'
        },

        validate(input, rules = []) {
            const value = input.value;
            const group = input.closest('.org-form-group');

            for (const rule of rules) {
                let error = null;
                if (typeof rule === 'string') {
                    error = this.rules[rule]?.(value);
                } else if (typeof rule === 'object') {
                    error = this.rules[rule.type]?.(value, rule.value);
                }

                if (error) {
                    this.setInvalid(group, error);
                    return false;
                }
            }

            this.setValid(group);
            return true;
        },

        setValid(group) {
            if (!group) return;
            group.classList.remove('invalid');
            group.classList.add('valid');
            const msg = group.querySelector('.org-validation-message');
            if (msg) msg.textContent = '';
        },

        setInvalid(group, message) {
            if (!group) return;
            group.classList.remove('valid');
            group.classList.add('invalid');
            const msg = group.querySelector('.org-validation-message');
            if (msg) msg.textContent = message;
        },

        reset(group) {
            if (!group) return;
            group.classList.remove('valid', 'invalid');
            const msg = group.querySelector('.org-validation-message');
            if (msg) msg.textContent = '';
        }
    },

    // Loading States
    loading: {
        setButton(button, loading = true) {
            if (loading) {
                button.classList.add('org-btn-loading');
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                const text = button.querySelector('.org-btn-text') || button;
                text.dataset.text = text.textContent;
            } else {
                button.classList.remove('org-btn-loading');
                button.disabled = false;
                if (button.dataset.originalText) {
                    button.innerHTML = button.dataset.originalText;
                }
            }
        },

        showSkeleton(container, count = 3) {
            container.innerHTML = Array(count).fill(`
                <div style="display: flex; gap: 12px; padding: 16px; align-items: center;">
                    <div class="org-skeleton org-skeleton-avatar"></div>
                    <div style="flex: 1;">
                        <div class="org-skeleton org-skeleton-text" style="width: 70%;"></div>
                        <div class="org-skeleton org-skeleton-text short"></div>
                    </div>
                </div>
            `).join('');
        }
    },

    // Bulk Selection
    bulkSelect: {
        selectedIds: new Set(),

        init(containerSelector, options = {}) {
            const container = document.querySelector(containerSelector);
            if (!container) return;

            const {
                onSelectionChange = () => {},
                itemSelector = '.org-request-item',
                checkboxSelector = '.org-bulk-checkbox'
            } = options;

            this.selectedIds.clear();

            // Select all checkbox
            const selectAll = container.querySelector('.org-select-all');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    const checkboxes = container.querySelectorAll(checkboxSelector);
                    checkboxes.forEach(cb => {
                        cb.checked = e.target.checked;
                        const id = cb.dataset.id;
                        if (e.target.checked) {
                            this.selectedIds.add(id);
                        } else {
                            this.selectedIds.delete(id);
                        }
                    });
                    this.updateUI(container);
                    onSelectionChange([...this.selectedIds]);
                });
            }

            // Individual checkboxes
            container.querySelectorAll(checkboxSelector).forEach(cb => {
                cb.addEventListener('change', (e) => {
                    const id = e.target.dataset.id;
                    if (e.target.checked) {
                        this.selectedIds.add(id);
                    } else {
                        this.selectedIds.delete(id);
                    }
                    this.updateUI(container);
                    onSelectionChange([...this.selectedIds]);
                });
            });
        },

        updateUI(container) {
            const bar = container.querySelector('.org-select-all-bar');
            const count = container.querySelector('.org-select-count');

            if (this.selectedIds.size > 0) {
                bar?.classList.add('show');
                if (count) count.textContent = `${this.selectedIds.size} selected`;
            } else {
                bar?.classList.remove('show');
            }
        },

        getSelected() {
            return [...this.selectedIds];
        },

        clear() {
            this.selectedIds.clear();
        }
    },

    // Live Updates (Polling)
    liveUpdate: {
        intervalId: null,

        start(options = {}) {
            const {
                url,
                interval = 30000,
                onUpdate = () => {},
                indicator = null
            } = options;

            if (indicator) {
                indicator.style.display = 'inline-flex';
            }

            this.intervalId = setInterval(async () => {
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    onUpdate(data);
                } catch (e) {
                    console.error('Live update failed:', e);
                }
            }, interval);
        },

        stop() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        }
    },

    // Initialize all components
    init() {
        this.modal.init();
        this.toast.init();
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => OrgUI.init());
</script>
