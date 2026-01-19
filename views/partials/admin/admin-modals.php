<?php
/**
 * Admin Modal System - Gold Standard v2.0
 * Reusable modal components for quick actions
 */
?>

<style>
/* Modal System Styles */
.admin-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.admin-modal.open {
    display: flex;
    opacity: 1;
}

.admin-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.admin-modal-content {
    position: relative;
    z-index: 1;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 20px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

.admin-modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.admin-modal-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.admin-modal-title i {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.admin-modal-close {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.admin-modal-close:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.admin-modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.admin-modal-body::-webkit-scrollbar {
    width: 8px;
}

.admin-modal-body::-webkit-scrollbar-track {
    background: rgba(30, 41, 59, 0.5);
    border-radius: 4px;
}

.admin-modal-body::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.3);
    border-radius: 4px;
}

.admin-modal-body::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.5);
}

.admin-modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-shrink: 0;
}

/* Modal Form Styles */
.admin-modal-form-group {
    margin-bottom: 1.5rem;
}

.admin-modal-form-group:last-child {
    margin-bottom: 0;
}

.admin-modal-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    margin-bottom: 0.5rem;
}

.admin-modal-label.required::after {
    content: ' *';
    color: #ef4444;
}

.admin-modal-input,
.admin-modal-textarea,
.admin-modal-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: #f1f5f9;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.admin-modal-input:focus,
.admin-modal-textarea:focus,
.admin-modal-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.admin-modal-textarea {
    resize: vertical;
    min-height: 100px;
}

/* Confirmation Modal */
.admin-modal-content.confirm {
    max-width: 450px;
}

.confirm-body {
    text-align: center;
    padding: 2rem 2rem 1.5rem;
}

.confirm-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.confirm-icon.warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.confirm-icon.danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.confirm-icon.info {
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.confirm-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 0.75rem 0;
}

.confirm-message {
    font-size: 0.95rem;
    color: #94a3b8;
    line-height: 1.6;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-modal-content {
        border-radius: 16px;
        max-height: 95vh;
    }

    .admin-modal-header,
    .admin-modal-body,
    .admin-modal-footer {
        padding: 1.25rem 1.5rem;
    }

    .admin-modal-footer {
        flex-direction: column;
    }

    .admin-modal-footer .admin-btn {
        width: 100%;
    }
}
</style>

<script>
// Admin Modal System JavaScript API
window.AdminModal = {
    open: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) setTimeout(() => firstInput.focus(), 100);
        }
    },

    close: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
    },

    confirm: function(options) {
        return new Promise((resolve) => {
            const defaults = {
                title: 'Confirm Action',
                message: 'Are you sure you want to proceed?',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                type: 'warning',
                icon: 'fa-triangle-exclamation'
            };

            const config = Object.assign({}, defaults, options);
            const modalId = 'adminConfirmModal' + Date.now();
            const modalHtml = `
                <div class="admin-modal" id="${modalId}">
                    <div class="admin-modal-backdrop"></div>
                    <div class="admin-modal-content confirm">
                        <div class="admin-modal-body confirm-body">
                            <div class="confirm-icon ${config.type}">
                                <i class="fa-solid ${config.icon}"></i>
                            </div>
                            <h3 class="confirm-title">${this.escapeHtml(config.title)}</h3>
                            <p class="confirm-message">${this.escapeHtml(config.message)}</p>
                        </div>
                        <div class="admin-modal-footer">
                            <button type="button" class="admin-btn admin-btn-secondary" data-action="cancel">
                                ${this.escapeHtml(config.cancelText)}
                            </button>
                            <button type="button" class="admin-btn ${config.type === 'danger' ? 'admin-btn-danger' : 'admin-btn-primary'}" data-action="confirm">
                                ${this.escapeHtml(config.confirmText)}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = modalHtml;
            const modal = tempDiv.firstElementChild;
            document.body.appendChild(modal);

            const cleanup = () => {
                this.close(modalId);
                setTimeout(() => modal.remove(), 300);
            };

            modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
                resolve(true);
                cleanup();
            });

            modal.querySelector('[data-action="cancel"]').addEventListener('click', () => {
                resolve(false);
                cleanup();
            });

            modal.querySelector('.admin-modal-backdrop').addEventListener('click', () => {
                resolve(false);
                cleanup();
            });

            setTimeout(() => this.open(modalId), 10);
        });
    },

    alert: function(options) {
        return new Promise((resolve) => {
            const defaults = {
                title: 'Notice',
                message: '',
                type: 'info',
                buttonText: 'OK'
            };

            const config = Object.assign({}, defaults, options);
            const iconMap = {
                success: 'fa-circle-check',
                error: 'fa-circle-xmark',
                warning: 'fa-triangle-exclamation',
                info: 'fa-circle-info'
            };

            const modalId = 'adminAlertModal' + Date.now();
            const modalHtml = `
                <div class="admin-modal" id="${modalId}">
                    <div class="admin-modal-backdrop"></div>
                    <div class="admin-modal-content confirm">
                        <div class="admin-modal-body confirm-body">
                            <div class="confirm-icon ${config.type}">
                                <i class="fa-solid ${iconMap[config.type]}"></i>
                            </div>
                            <h3 class="confirm-title">${this.escapeHtml(config.title)}</h3>
                            <p class="confirm-message">${this.escapeHtml(config.message)}</p>
                        </div>
                        <div class="admin-modal-footer">
                            <button type="button" class="admin-btn admin-btn-primary" data-action="ok">
                                ${this.escapeHtml(config.buttonText)}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = modalHtml;
            const modal = tempDiv.firstElementChild;
            document.body.appendChild(modal);

            const cleanup = () => {
                this.close(modalId);
                setTimeout(() => modal.remove(), 300);
                resolve();
            };

            modal.querySelector('[data-action="ok"]').addEventListener('click', cleanup);
            modal.querySelector('.admin-modal-backdrop').addEventListener('click', cleanup);

            setTimeout(() => this.open(modalId), 10);
        });
    },

    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Auto-setup
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        if (e.target.matches('.admin-modal-close') || e.target.closest('.admin-modal-close')) {
            const modal = e.target.closest('.admin-modal');
            if (modal) AdminModal.close(modal.id);
        }
        if (e.target.matches('.admin-modal-backdrop')) {
            const modal = e.target.closest('.admin-modal');
            if (modal) AdminModal.close(modal.id);
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.admin-modal.open');
            if (openModal) AdminModal.close(openModal.id);
        }
    });
});
</script>
