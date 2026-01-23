/**
 * Organization UI Components JavaScript
 * Interactive behaviors for org components
 * CivicOne Theme
 */

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
            if (showInput) {
                input.classList.remove('hidden');
            } else {
                input.classList.add('hidden');
            }
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
                indicator.classList.remove('hidden');
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
