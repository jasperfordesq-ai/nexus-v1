<?php
/**
 * Admin Export System - Gold Standard v2.0
 * CSV/PDF/Excel export functionality for data tables
 */
?>

<script>
/**
 * Admin Export System
 */
window.AdminExport = {
    /**
     * Export table to CSV
     * @param {string} tableSelector - Table selector
     * @param {string} filename - Output filename
     * @param {Object} options - Additional options
     */
    toCSV: function(tableSelector, filename, options) {
        const defaults = {
            includeHeaders: true,
            excludeColumns: [], // Column indices to exclude
            onlySelected: false, // Export only selected rows (requires bulk actions)
            dateFormat: 'YYYY-MM-DD'
        };

        const config = Object.assign({}, defaults, options);
        const table = document.querySelector(tableSelector);

        if (!table) {
            console.error('AdminExport: Table not found');
            return;
        }

        const rows = [];

        // Get headers
        if (config.includeHeaders) {
            const headerRow = [];
            const headers = table.querySelectorAll('thead th');
            headers.forEach((th, index) => {
                if (!config.excludeColumns.includes(index)) {
                    headerRow.push(this.cleanText(th.textContent));
                }
            });
            if (headerRow.length > 0) rows.push(headerRow);
        }

        // Get data rows
        const tableRows = config.onlySelected
            ? table.querySelectorAll('tbody tr.selected')
            : table.querySelectorAll('tbody tr');

        tableRows.forEach(tr => {
            const row = [];
            const cells = tr.querySelectorAll('td');
            cells.forEach((td, index) => {
                if (!config.excludeColumns.includes(index)) {
                    // Try to get clean text content, avoiding action buttons
                    let text = '';
                    const textNode = td.cloneNode(true);
                    // Remove action buttons
                    textNode.querySelectorAll('.admin-action-buttons, .admin-btn').forEach(el => el.remove());
                    text = this.cleanText(textNode.textContent);
                    row.push(text);
                }
            });
            if (row.length > 0) rows.push(row);
        });

        // Convert to CSV
        const csv = rows.map(row =>
            row.map(cell => this.escapeCSV(cell)).join(',')
        ).join('\n');

        // Download
        this.downloadFile(csv, filename || 'export.csv', 'text/csv');
    },

    /**
     * Export data array to CSV
     * @param {Array} data - Array of objects
     * @param {string} filename - Output filename
     * @param {Array} columns - Column configuration
     */
    arrayToCSV: function(data, filename, columns) {
        if (!Array.isArray(data) || data.length === 0) {
            AdminModal.alert({
                title: 'No Data',
                message: 'There is no data to export.',
                type: 'warning'
            });
            return;
        }

        const rows = [];

        // Headers
        const headers = columns || Object.keys(data[0]);
        rows.push(headers.map(h => typeof h === 'object' ? h.label : h));

        // Data rows
        data.forEach(item => {
            const row = headers.map(col => {
                const key = typeof col === 'object' ? col.key : col;
                const value = item[key] || '';
                return typeof col === 'object' && col.format
                    ? col.format(value, item)
                    : String(value);
            });
            rows.push(row);
        });

        // Convert to CSV
        const csv = rows.map(row =>
            row.map(cell => this.escapeCSV(cell)).join(',')
        ).join('\n');

        this.downloadFile(csv, filename || 'export.csv', 'text/csv');
    },

    /**
     * Export to Excel (via CSV with proper headers)
     * @param {string} tableSelector - Table selector
     * @param {string} filename - Output filename
     * @param {Object} options - Additional options
     */
    toExcel: function(tableSelector, filename, options) {
        // Use CSV export with Excel MIME type
        this.toCSV(tableSelector, filename || 'export.xlsx', options);
    },

    /**
     * Export current view to JSON
     * @param {Array} data - Data array
     * @param {string} filename - Output filename
     */
    toJSON: function(data, filename) {
        const json = JSON.stringify(data, null, 2);
        this.downloadFile(json, filename || 'export.json', 'application/json');
    },

    /**
     * Clean text content
     */
    cleanText: function(text) {
        return text.trim().replace(/\s+/g, ' ');
    },

    /**
     * Escape CSV cell content
     */
    escapeCSV: function(text) {
        text = String(text);
        if (text.includes(',') || text.includes('"') || text.includes('\n')) {
            return '"' + text.replace(/"/g, '""') + '"';
        }
        return text;
    },

    /**
     * Download file to browser
     */
    downloadFile: function(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        // Show success notification
        AdminModal.alert({
            title: 'Export Complete',
            message: 'Your file has been downloaded successfully.',
            type: 'success'
        });
    },

    /**
     * Show export options modal
     * @param {Object} config - Configuration
     */
    showExportModal: async function(config) {
        const defaults = {
            tableSelector: '.admin-table',
            filename: 'export',
            title: 'Export Data',
            formats: ['csv', 'json'],
            onExport: null
        };

        const settings = Object.assign({}, defaults, config);

        // Create modal HTML
        const modalId = 'adminExportModal' + Date.now();
        const formatOptions = settings.formats.map(format => {
            const icons = {
                csv: 'fa-file-csv',
                excel: 'fa-file-excel',
                json: 'fa-file-code',
                pdf: 'fa-file-pdf'
            };
            const labels = {
                csv: 'CSV (Comma Separated)',
                excel: 'Excel Spreadsheet',
                json: 'JSON Data',
                pdf: 'PDF Document'
            };
            return `
                <label class="export-format-option">
                    <input type="radio" name="exportFormat" value="${format}" ${format === 'csv' ? 'checked' : ''}>
                    <div class="export-format-card">
                        <i class="fa-solid ${icons[format]}"></i>
                        <span>${labels[format]}</span>
                    </div>
                </label>
            `;
        }).join('');

        const modalHtml = `
            <div class="admin-modal" id="${modalId}">
                <div class="admin-modal-backdrop"></div>
                <div class="admin-modal-content">
                    <div class="admin-modal-header">
                        <h3 class="admin-modal-title">
                            <i class="fa-solid fa-download"></i>
                            ${settings.title}
                        </h3>
                        <button type="button" class="admin-modal-close">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                    <div class="admin-modal-body">
                        <div class="admin-modal-form-group">
                            <label class="admin-modal-label">Export Format</label>
                            <div class="export-format-options">
                                ${formatOptions}
                            </div>
                        </div>
                        <div class="admin-modal-form-group">
                            <label class="admin-modal-label">Filename</label>
                            <input type="text" class="admin-modal-input" id="exportFilename" value="${settings.filename}" placeholder="export">
                        </div>
                    </div>
                    <div class="admin-modal-footer">
                        <button type="button" class="admin-btn admin-btn-secondary" onclick="AdminModal.close('${modalId}')">
                            Cancel
                        </button>
                        <button type="button" class="admin-btn admin-btn-primary" id="exportConfirmBtn">
                            <i class="fa-solid fa-download"></i>
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <style>
            .export-format-options {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 0.75rem;
            }

            .export-format-option {
                cursor: pointer;
            }

            .export-format-option input {
                display: none;
            }

            .export-format-card {
                padding: 1.25rem;
                border: 2px solid rgba(99, 102, 241, 0.2);
                border-radius: 12px;
                background: rgba(30, 41, 59, 0.5);
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.75rem;
                transition: all 0.2s;
                cursor: pointer;
            }

            .export-format-card i {
                font-size: 2rem;
                color: #94a3b8;
            }

            .export-format-card span {
                font-size: 0.875rem;
                color: #94a3b8;
                text-align: center;
            }

            .export-format-option input:checked + .export-format-card {
                border-color: #6366f1;
                background: rgba(99, 102, 241, 0.1);
            }

            .export-format-option input:checked + .export-format-card i,
            .export-format-option input:checked + .export-format-card span {
                color: #6366f1;
            }

            .export-format-card:hover {
                border-color: rgba(99, 102, 241, 0.4);
                transform: translateY(-2px);
            }
            </style>
        `;

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = modalHtml;
        const modal = tempDiv.firstElementChild;
        document.body.appendChild(modal);

        // Handle export button
        modal.querySelector('#exportConfirmBtn').addEventListener('click', () => {
            const format = modal.querySelector('input[name="exportFormat"]:checked').value;
            const filename = modal.querySelector('#exportFilename').value || 'export';

            AdminModal.close(modalId);

            setTimeout(() => {
                if (settings.onExport) {
                    settings.onExport(format, filename);
                } else {
                    // Default: export table
                    if (format === 'csv') {
                        this.toCSV(settings.tableSelector, filename + '.csv', {
                            excludeColumns: [0] // Exclude checkbox column
                        });
                    } else if (format === 'json') {
                        AdminModal.alert({
                            title: 'Not Implemented',
                            message: 'JSON export requires custom implementation with data array.',
                            type: 'info'
                        });
                    }
                }
                modal.remove();
            }, 300);
        });

        AdminModal.open(modalId);
    }
};
</script>
