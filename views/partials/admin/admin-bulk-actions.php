<?php
/**
 * Admin Bulk Actions System - Gold Standard v2.0
 * Reusable bulk operations for list pages with checkboxes
 */
?>

<style>
/* Bulk Actions Bar */
.admin-bulk-bar {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    z-index: 1000;
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.4);
    border-radius: 16px;
    padding: 1rem 1.5rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
}

.admin-bulk-bar.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
    pointer-events: all;
}

.admin-bulk-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-bulk-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}

.admin-bulk-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.admin-bulk-count {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f1f5f9;
}

.admin-bulk-label {
    font-size: 0.75rem;
    color: #94a3b8;
}

.admin-bulk-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-left: 1.5rem;
    border-left: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-bulk-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
}

.admin-bulk-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.admin-bulk-btn-primary:hover {
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    transform: translateY(-1px);
}

.admin-bulk-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-bulk-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
}

.admin-bulk-btn-secondary {
    background: rgba(30, 41, 59, 0.8);
    color: #f1f5f9;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.admin-bulk-btn-secondary:hover {
    background: rgba(99, 102, 241, 0.15);
}

/* Table Checkbox Styles */
.admin-table-checkbox {
    width: 40px;
    text-align: center;
}

.admin-checkbox-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.admin-checkbox {
    appearance: none;
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 6px;
    background: rgba(30, 41, 59, 0.6);
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
}

.admin-checkbox:hover {
    border-color: rgba(99, 102, 241, 0.5);
    background: rgba(30, 41, 59, 0.8);
}

.admin-checkbox:checked {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: #6366f1;
}

.admin-checkbox:checked::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: translate(-50%, -60%) rotate(45deg);
}

.admin-checkbox:indeterminate {
    background: rgba(99, 102, 241, 0.3);
    border-color: #6366f1;
}

.admin-checkbox:indeterminate::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 10px;
    height: 2px;
    background: #6366f1;
}

/* Table Row Selection */
.admin-table tbody tr.selected {
    background: rgba(99, 102, 241, 0.08);
}

.admin-table tbody tr.selected:hover {
    background: rgba(99, 102, 241, 0.12);
}

/* Responsive */
@media (max-width: 768px) {
    .admin-bulk-bar {
        left: 1rem;
        right: 1rem;
        transform: translateX(0) translateY(100px);
        flex-direction: column;
        gap: 1rem;
        padding: 1.25rem;
    }

    .admin-bulk-bar.show {
        transform: translateX(0) translateY(0);
    }

    .admin-bulk-actions {
        width: 100%;
        padding-left: 0;
        padding-top: 1rem;
        border-left: none;
        border-top: 1px solid rgba(99, 102, 241, 0.2);
        flex-wrap: wrap;
        justify-content: center;
    }

    .admin-bulk-btn {
        flex: 1;
        min-width: 120px;
        justify-content: center;
    }
}
</style>

<script>
/**
 * Admin Bulk Actions System
 */
window.AdminBulkActions = {
    selectedItems: new Set(),
    selectAllCheckbox: null,
    itemCheckboxes: [],
    bulkBar: null,

    /**
     * Initialize bulk actions for a table
     * @param {Object} config - Configuration object
     */
    init: function(config) {
        const defaults = {
            tableSelector: '.admin-table',
            checkboxSelector: '.admin-checkbox-item',
            selectAllSelector: '.admin-checkbox-all',
            bulkBarId: 'adminBulkBar',
            onSelectionChange: null
        };

        this.config = Object.assign({}, defaults, config);
        this.bulkBar = document.getElementById(this.config.bulkBarId);
        this.selectAllCheckbox = document.querySelector(this.config.selectAllSelector);
        this.itemCheckboxes = Array.from(document.querySelectorAll(this.config.checkboxSelector));

        if (!this.bulkBar || !this.selectAllCheckbox || this.itemCheckboxes.length === 0) {
            console.warn('AdminBulkActions: Required elements not found');
            return;
        }

        this.setupEventListeners();
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        const self = this;

        // Select all checkbox
        this.selectAllCheckbox.addEventListener('change', function() {
            self.toggleSelectAll(this.checked);
        });

        // Individual checkboxes
        this.itemCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                self.handleItemSelection(this);
            });
        });

        // Row click to select (optional)
        const table = document.querySelector(this.config.tableSelector);
        if (table) {
            table.querySelectorAll('tbody tr').forEach(function(row) {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on links, buttons, or checkbox itself
                    if (e.target.closest('a, button, .admin-checkbox')) return;

                    const checkbox = row.querySelector('.admin-checkbox-item');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        self.handleItemSelection(checkbox);
                    }
                });
            });
        }
    },

    /**
     * Toggle select all
     */
    toggleSelectAll: function(checked) {
        const self = this;
        this.itemCheckboxes.forEach(function(checkbox) {
            checkbox.checked = checked;
            const value = checkbox.value;
            if (checked) {
                self.selectedItems.add(value);
                checkbox.closest('tr').classList.add('selected');
            } else {
                self.selectedItems.delete(value);
                checkbox.closest('tr').classList.remove('selected');
            }
        });
        this.updateBulkBar();
    },

    /**
     * Handle individual item selection
     */
    handleItemSelection: function(checkbox) {
        const value = checkbox.value;
        const row = checkbox.closest('tr');

        if (checkbox.checked) {
            this.selectedItems.add(value);
            if (row) row.classList.add('selected');
        } else {
            this.selectedItems.delete(value);
            if (row) row.classList.remove('selected');
        }

        // Update select all checkbox state
        this.updateSelectAllState();
        this.updateBulkBar();
    },

    /**
     * Update select all checkbox state (checked, unchecked, or indeterminate)
     */
    updateSelectAllState: function() {
        const checkedCount = this.itemCheckboxes.filter(function(cb) { return cb.checked; }).length;
        const totalCount = this.itemCheckboxes.length;

        if (checkedCount === 0) {
            this.selectAllCheckbox.checked = false;
            this.selectAllCheckbox.indeterminate = false;
        } else if (checkedCount === totalCount) {
            this.selectAllCheckbox.checked = true;
            this.selectAllCheckbox.indeterminate = false;
        } else {
            this.selectAllCheckbox.checked = false;
            this.selectAllCheckbox.indeterminate = true;
        }
    },

    /**
     * Update bulk actions bar visibility and count
     */
    updateBulkBar: function() {
        const count = this.selectedItems.size;

        if (count > 0) {
            this.bulkBar.classList.add('show');
            const countEl = this.bulkBar.querySelector('.admin-bulk-count');
            if (countEl) {
                countEl.textContent = count + ' item' + (count === 1 ? '' : 's') + ' selected';
            }
        } else {
            this.bulkBar.classList.remove('show');
        }

        // Call custom callback if provided
        if (this.config.onSelectionChange && typeof this.config.onSelectionChange === 'function') {
            this.config.onSelectionChange(Array.from(this.selectedItems));
        }
    },

    /**
     * Get selected item IDs
     * @returns {Array<string>}
     */
    getSelectedIds: function() {
        return Array.from(this.selectedItems);
    },

    /**
     * Clear all selections
     */
    clearSelection: function() {
        this.toggleSelectAll(false);
    },

    /**
     * Perform bulk action with confirmation
     * @param {string} action - Action name
     * @param {string} url - Target URL
     * @param {Object} options - Additional options
     */
    performAction: async function(action, url, options) {
        const defaults = {
            method: 'POST',
            confirmTitle: 'Confirm Action',
            confirmMessage: 'Are you sure you want to perform this action on ' + this.selectedItems.size + ' item(s)?',
            confirmType: 'warning',
            onSuccess: null,
            onError: null
        };

        const config = Object.assign({}, defaults, options);

        // Confirm action
        const confirmed = await AdminModal.confirm({
            title: config.confirmTitle,
            message: config.confirmMessage,
            type: config.confirmType,
            confirmText: 'Proceed',
            cancelText: 'Cancel'
        });

        if (!confirmed) return;

        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                          document.querySelector('input[name="csrf_token"]')?.value;

        // Prepare form data
        const formData = new FormData();
        formData.append('action', action);
        formData.append('ids', JSON.stringify(this.getSelectedIds()));
        if (csrfToken) formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch(url, {
                method: config.method,
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                await AdminModal.alert({
                    title: 'Success',
                    message: data.message || 'Action completed successfully',
                    type: 'success'
                });

                if (config.onSuccess) {
                    config.onSuccess(data);
                } else {
                    // Default: reload page
                    window.location.reload();
                }
            } else {
                throw new Error(data.message || 'Action failed');
            }
        } catch (error) {
            await AdminModal.alert({
                title: 'Error',
                message: error.message || 'An error occurred',
                type: 'error'
            });

            if (config.onError) {
                config.onError(error);
            }
        }
    }
};
</script>
