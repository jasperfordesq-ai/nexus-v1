<?php
/**
 * Modern Admin 404 Error Tracking Dashboard
 * Gold theme with polished interface
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = '404 Error Tracking';
require_once __DIR__ . '/../partials/admin-header.php';
?>

<style>
/* 404 Error Tracking Specific Styles */
.error-404-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.error-404-table thead {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
}

.error-404-table thead th {
    padding: 16px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid rgba(255,255,255,0.1);
}

.error-404-table tbody tr {
    border-bottom: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.error-404-table tbody tr:hover {
    background: #fefce8;
    transform: translateX(4px);
}

.error-404-table tbody tr.error-resolved {
    background: #f0fdf4;
    opacity: 0.8;
}

.error-404-table tbody tr.error-resolved:hover {
    background: #dcfce7;
}

.error-404-table tbody td {
    padding: 16px;
    font-size: 14px;
    color: #1f2937;
}

.error-url {
    font-family: 'Monaco', 'Courier New', monospace;
    background: #f1f5f9;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 13px;
    color: #0f172a;
    word-break: break-all;
    display: inline-block;
    max-width: 500px;
}

.error-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.error-badge-hits {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    color: white;
}

.error-badge-hits.medium {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    color: #78350f;
}

.error-badge-hits.low {
    background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
    color: white;
}

.error-badge-resolved {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    color: white;
}

.error-badge-unresolved {
    background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
    color: white;
}

.error-actions {
    display: flex;
    gap: 6px;
}

.error-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.error-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.error-btn-redirect {
    background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
    color: white;
}

.error-btn-resolve {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    color: white;
}

.error-btn-unresolve {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    color: #78350f;
}

.error-btn-delete {
    background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
    color: white;
}

.error-referer {
    color: #6366f1;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.error-referer:hover {
    text-decoration: underline;
}

.error-notes {
    color: #6b7280;
    font-size: 12px;
    font-style: italic;
    margin-top: 4px;
}

.filters-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.filter-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    color: #1f2937;
    background: white;
    transition: all 0.2s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #eab308;
    box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.1);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 32px;
    padding: 24px;
}

.pagination-btn {
    padding: 10px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    color: #374151;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pagination-btn:hover:not(.active) {
    border-color: #eab308;
    background: #fef3c7;
}

.pagination-btn.active {
    background: linear-gradient(135deg, #eab308 0%, #facc15 100%);
    color: white;
    border-color: #eab308;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-dialog {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 24px;
    border-bottom: 2px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #111827;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 24px;
    border-top: 2px solid #f3f4f6;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #eab308;
    box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.1);
}

.form-text {
    display: block;
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
}
</style>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-exclamation-triangle"></i>
            404 Error Tracking
        </h1>
        <p class="admin-page-subtitle">Monitor and fix broken links across your site</p>
    </div>
    <div class="admin-page-header-actions">
        <button class="admin-btn admin-btn-primary" id="bulkRedirectBtn" onclick="showBulkRedirectModal()" style="display: none;">
            <i class="fa-solid fa-arrow-right"></i>
            <span id="selectedCount">0</span> Selected - Create Redirect
        </button>
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
        <button class="admin-btn admin-btn-danger" onclick="cleanOldResolved()">
            <i class="fa-solid fa-trash"></i>
            Clean Old Resolved
        </button>
        <a href="<?= $basePath ?>/admin-legacy/seo/redirects" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-arrow-right-arrow-left"></i>
            Manage Redirects
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-label">Total 404s</div>
            <div class="admin-stat-value"><?= number_format($stats['total']) ?></div>
            <div class="admin-stat-subtitle">Unique URLs</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-red">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-xmark"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-label">Unresolved</div>
            <div class="admin-stat-value"><?= number_format($stats['unresolved']) ?></div>
            <div class="admin-stat-subtitle">Need attention</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-label">Resolved</div>
            <div class="admin-stat-value"><?= number_format($stats['resolved']) ?></div>
            <div class="admin-stat-subtitle">Fixed issues</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-fire"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-label">Total Hits</div>
            <div class="admin-stat-value"><?= number_format($stats['total_hits']) ?></div>
            <div class="admin-stat-subtitle"><?= $stats['recent_24h'] ?> in last 24h</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filters-card">
    <form method="GET" action="<?= $basePath ?>/admin-legacy/404-errors">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Status</label>
                <select name="resolved" class="filter-select">
                    <option value="">All Errors</option>
                    <option value="0" <?= ($filters['resolved'] === false) ? 'selected' : '' ?>>Unresolved Only</option>
                    <option value="1" <?= ($filters['resolved'] === true) ? 'selected' : '' ?>>Resolved Only</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Sort By</label>
                <select name="order_by" class="filter-select">
                    <option value="hit_count" <?= $filters['order_by'] === 'hit_count' ? 'selected' : '' ?>>Hit Count</option>
                    <option value="last_seen_at" <?= $filters['order_by'] === 'last_seen_at' ? 'selected' : '' ?>>Last Seen</option>
                    <option value="first_seen_at" <?= $filters['order_by'] === 'first_seen_at' ? 'selected' : '' ?>>First Seen</option>
                    <option value="url" <?= $filters['order_by'] === 'url' ? 'selected' : '' ?>>URL</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Order</label>
                <select name="order_dir" class="filter-select">
                    <option value="DESC" <?= $filters['order_dir'] === 'DESC' ? 'selected' : '' ?>>Descending</option>
                    <option value="ASC" <?= $filters['order_dir'] === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Per Page</label>
                <select name="per_page" class="filter-select">
                    <option value="25" <?= $pagination['per_page'] === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $pagination['per_page'] === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $pagination['per_page'] === 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>

            <div class="filter-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;">
                    <i class="fa-solid fa-filter"></i>
                    Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Errors Table -->
<div class="admin-card">
    <?php if (empty($errors)): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-check-circle" style="font-size: 64px; color: #10b981; margin-bottom: 16px;"></i>
            <h3 style="color: #1f2937; margin-bottom: 8px;">No 404 Errors Found</h3>
            <p style="color: #6b7280;">Great job! No errors match your current filters.</p>
        </div>
    <?php else: ?>
        <table class="error-404-table">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor: pointer; width: 18px; height: 18px;">
                    </th>
                    <th>URL</th>
                    <th>Hits</th>
                    <th>First Seen</th>
                    <th>Last Seen</th>
                    <th>Referer</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errors as $error): ?>
                    <tr id="error-<?= $error['id'] ?>" class="<?= $error['resolved'] ? 'error-resolved' : '' ?>">
                        <td>
                            <?php if (!$error['resolved']): ?>
                                <input type="checkbox" class="error-checkbox" data-error-id="<?= $error['id'] ?>" data-error-url="<?= htmlspecialchars($error['url'], ENT_QUOTES) ?>" onchange="updateSelectedCount()" style="cursor: pointer; width: 18px; height: 18px;">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="error-url"><?= htmlspecialchars($error['url']) ?></div>
                            <?php if ($error['notes']): ?>
                                <div class="error-notes"><?= htmlspecialchars($error['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="error-badge error-badge-hits <?= $error['hit_count'] > 10 ? '' : ($error['hit_count'] > 5 ? 'medium' : 'low') ?>">
                                <?= number_format($error['hit_count']) ?> hits
                            </span>
                        </td>
                        <td style="color: #6b7280; font-size: 13px;">
                            <?= date('M d, Y', strtotime($error['first_seen_at'])) ?><br>
                            <span style="font-size: 12px;"><?= date('H:i', strtotime($error['first_seen_at'])) ?></span>
                        </td>
                        <td style="color: #6b7280; font-size: 13px;">
                            <?= date('M d, Y', strtotime($error['last_seen_at'])) ?><br>
                            <span style="font-size: 12px;"><?= date('H:i', strtotime($error['last_seen_at'])) ?></span>
                        </td>
                        <td>
                            <?php if ($error['referer']): ?>
                                <a href="<?= htmlspecialchars($error['referer']) ?>" target="_blank" rel="noopener" class="error-referer">
                                    <i class="fa-solid fa-external-link"></i>
                                    <?= htmlspecialchars(substr(parse_url($error['referer'], PHP_URL_HOST) ?? $error['referer'], 0, 25)) ?>...
                                </a>
                            <?php else: ?>
                                <span style="color: #d1d5db; font-size: 13px;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($error['resolved']): ?>
                                <span class="error-badge error-badge-resolved">
                                    <i class="fa-solid fa-check"></i> Resolved
                                </span>
                            <?php else: ?>
                                <span class="error-badge error-badge-unresolved">
                                    <i class="fa-solid fa-exclamation"></i> Unresolved
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="error-actions">
                                <?php if (!$error['resolved']): ?>
                                    <button class="error-btn error-btn-redirect" onclick="createRedirectModal(<?= $error['id'] ?>, '<?= htmlspecialchars($error['url'], ENT_QUOTES) ?>')" title="Create redirect">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                    <button class="error-btn error-btn-resolve" onclick="markResolved(<?= $error['id'] ?>)" title="Mark as resolved">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="error-btn error-btn-unresolve" onclick="markUnresolved(<?= $error['id'] ?>)" title="Mark as unresolved">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="error-btn error-btn-delete" onclick="deleteError(<?= $error['id'] ?>)" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination">
                <?php
                $currentPage = $pagination['current_page'];
                $totalPages = $pagination['total_pages'];
                $range = 2;

                // Build query string
                $queryParams = $_GET;

                for ($i = 1; $i <= $totalPages; $i++):
                    if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $range && $i <= $currentPage + $range)):
                        $queryParams['page'] = $i;
                        $queryString = http_build_query($queryParams);
                ?>
                    <a href="?<?= $queryString ?>" class="pagination-btn <?= $i === $currentPage ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php
                    elseif ($i == $currentPage - $range - 1 || $i == $currentPage + $range + 1):
                        echo '<span style="padding: 10px; color: #9ca3af;">...</span>';
                    endif;
                endfor;
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Create Redirect Modal -->
<div class="modal" id="redirectModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Create 301 Redirect</h5>
            <button type="button" class="modal-close" onclick="closeModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="redirectForm">
                <input type="hidden" id="errorId" name="error_id">
                <div class="form-group">
                    <label class="form-label">Source URL (404 Error)</label>
                    <input type="text" class="form-input" id="sourceUrl" name="source_url" readonly>
                    <span class="form-text">The broken URL that's returning 404</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Destination URL</label>
                    <input type="text" class="form-input" id="destinationUrl" name="destination_url" placeholder="/new-page-url" required>
                    <span class="form-text">Enter the URL to redirect users to</span>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-primary" onclick="submitRedirect()">
                <i class="fa-solid fa-arrow-right"></i>
                Create Redirect
            </button>
        </div>
    </div>
</div>

<!-- Bulk Redirect Modal -->
<div class="modal" id="bulkRedirectModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Bulk Create Redirects</h5>
            <button type="button" class="modal-close" onclick="closeBulkModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Redirect all <strong><span id="bulkCount">0</span> selected URLs</strong> to:</label>
                <input type="text" class="form-input" id="bulkDestinationUrl" placeholder="/new-page-url" required>
                <span class="form-text">All selected 404 errors will be redirected to this URL</span>
            </div>
            <div id="bulkUrlList" style="max-height: 300px; overflow-y: auto; background: #f9fafb; padding: 16px; border-radius: 8px; margin-top: 16px;">
                <!-- URLs will be listed here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="closeBulkModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-primary" onclick="submitBulkRedirect()">
                <i class="fa-solid fa-arrow-right"></i>
                Create <span id="bulkCount2">0</span> Redirects
            </button>
        </div>
    </div>
</div>

<script>
// Select All Functionality
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.error-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.error-checkbox:checked');
    const count = checked.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('bulkRedirectBtn').style.display = count > 0 ? 'inline-flex' : 'none';

    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.error-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        selectAllCheckbox.checked = count === allCheckboxes.length;
    }
}

function showBulkRedirectModal() {
    const checked = document.querySelectorAll('.error-checkbox:checked');
    const count = checked.length;

    if (count === 0) {
        alert('Please select at least one 404 error');
        return;
    }

    // Update counts
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkCount2').textContent = count;

    // Build URL list
    let urlListHtml = '<div style="font-size: 13px; color: #6b7280;">';
    checked.forEach((checkbox, index) => {
        const url = checkbox.getAttribute('data-error-url');
        urlListHtml += `<div style="padding: 8px; border-bottom: 1px solid #e5e7eb;">${index + 1}. ${url}</div>`;
    });
    urlListHtml += '</div>';
    document.getElementById('bulkUrlList').innerHTML = urlListHtml;

    // Clear destination
    document.getElementById('bulkDestinationUrl').value = '';

    // Show modal
    document.getElementById('bulkRedirectModal').classList.add('show');
}

function closeBulkModal() {
    document.getElementById('bulkRedirectModal').classList.remove('show');
}

function submitBulkRedirect() {
    const destinationUrl = document.getElementById('bulkDestinationUrl').value.trim();

    if (!destinationUrl) {
        alert('Please enter a destination URL');
        return;
    }

    const checked = document.querySelectorAll('.error-checkbox:checked');
    const errorIds = Array.from(checked).map(cb => cb.getAttribute('data-error-id'));

    if (errorIds.length === 0) {
        alert('No errors selected');
        return;
    }

    // Show progress
    const submitBtn = event.target;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
    submitBtn.disabled = true;

    fetch('<?= $basePath ?>/admin-legacy/404-errors/bulk-redirect', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            error_ids: errorIds,
            destination_url: destinationUrl
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✓ Successfully created ${data.count} redirects!`);
            location.reload();
        } else {
            alert('✗ Failed to create redirects: ' + data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        alert('✗ Error: ' + error);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function createRedirectModal(errorId, sourceUrl) {
    document.getElementById('errorId').value = errorId;
    document.getElementById('sourceUrl').value = sourceUrl;
    document.getElementById('destinationUrl').value = '';
    document.getElementById('redirectModal').classList.add('show');
}

function closeModal() {
    document.getElementById('redirectModal').classList.remove('show');
}

function submitRedirect() {
    const formData = new FormData(document.getElementById('redirectForm'));

    fetch('<?= $basePath ?>/admin-legacy/404-errors/create-redirect', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Redirect created successfully!');
            location.reload();
        } else {
            alert('✗ Failed to create redirect: ' + data.message);
        }
    })
    .catch(error => {
        alert('✗ Error: ' + error);
    });
}

function markResolved(id) {
    if (!confirm('Mark this 404 error as resolved?')) return;

    fetch('<?= $basePath ?>/admin-legacy/404-errors/mark-resolved', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('✗ Failed: ' + data.message);
        }
    });
}

function markUnresolved(id) {
    if (!confirm('Mark this error as unresolved?')) return;

    fetch('<?= $basePath ?>/admin-legacy/404-errors/mark-unresolved', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('✗ Failed: ' + data.message);
        }
    });
}

function deleteError(id) {
    if (!confirm('Delete this 404 error log entry? This cannot be undone.')) return;

    fetch('<?= $basePath ?>/admin-legacy/404-errors/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('error-' + id).remove();
        } else {
            alert('✗ Failed: ' + data.message);
        }
    });
}

function cleanOldResolved() {
    if (!confirm('This will delete all resolved 404 errors older than 90 days. Continue?')) return;

    fetch('<?= $basePath ?>/admin-legacy/404-errors/clean-old', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'days=90'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ' + data.message);
            location.reload();
        } else {
            alert('✗ Failed: ' + data.message);
        }
    });
}

// Close modals on outside click
document.getElementById('redirectModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('bulkRedirectModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeBulkModal();
    }
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeBulkModal();
    }
});
</script>

<?php require_once __DIR__ . '/../partials/admin-footer.php'; ?>
