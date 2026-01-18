<?php
/**
 * GDPR Audit Log - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'GDPR Audit Log';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-clock-rotate-left';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'gdpr';
$currentPage = 'audit';

// Extract data with defaults
$stats = $stats ?? [];
$auditLogs = $auditLogs ?? [];
$filters = $filters ?? [];
$currentPage_num = $currentPage_num ?? 1;
$totalPages = $totalPages ?? 1;
$totalCount = $totalCount ?? 0;
$offset = $offset ?? 0;
$retentionPeriod = $retentionPeriod ?? '7 years';

// Helper functions
function getActionBadgeClass($action) {
    $classes = [
        'data_exported' => 'badge-info',
        'data_deleted' => 'badge-danger',
        'consent_granted' => 'badge-success',
        'consent_withdrawn' => 'badge-warning',
        'request_created' => 'badge-primary',
        'request_processed' => 'badge-success',
        'breach_reported' => 'badge-danger',
        'user_data_accessed' => 'badge-secondary',
    ];
    return $classes[$action] ?? 'badge-default';
}

function getActionIcon($action) {
    $icons = [
        'data_exported' => 'fa-download',
        'data_deleted' => 'fa-trash',
        'consent_granted' => 'fa-check',
        'consent_withdrawn' => 'fa-undo',
        'request_created' => 'fa-plus',
        'request_processed' => 'fa-cog',
        'breach_reported' => 'fa-exclamation-triangle',
        'user_data_accessed' => 'fa-eye',
    ];
    return $icons[$action] ?? 'fa-circle';
}

function formatAction($action) {
    return ucwords(str_replace('_', ' ', $action));
}

function getEntityLink($basePath, $type, $id) {
    $links = [
        'user' => '/admin/users/',
        'request' => '/admin/enterprise/gdpr/requests/',
        'consent' => '/admin/enterprise/gdpr/consents/',
        'breach' => '/admin/enterprise/gdpr/breaches/',
    ];
    return $basePath . ($links[$type] ?? '#') . $id;
}
?>

<style>
/* GDPR Audit - Gold Standard v2.0 */

/* Page Header */
.admin-page-header {
    margin-bottom: 2rem;
}

/* Stats Grid */
.audit-stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1200px) {
    .audit-stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .audit-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

.audit-stat {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
}

.audit-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.audit-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #6366f1;
    margin-bottom: 4px;
}

.audit-stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
}

/* Filter Card */
.filter-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 20px 24px;
    margin-bottom: 24px;
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--audit-text-muted);
    margin-bottom: 6px;
}

.filter-input,
.filter-select {
    width: 100%;
    padding: 10px 14px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid var(--audit-border);
    border-radius: 10px;
    color: var(--audit-text);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.filter-input:focus,
.filter-select:focus {
    outline: none;
    border-color: var(--audit-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.filter-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.filter-actions {
    display: flex;
    gap: 8px;
}

/* Audit Card */
.audit-card {
    background: var(--audit-surface);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--audit-border);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 24px;
}

.audit-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--audit-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), transparent);
}

.audit-card-header h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--audit-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.audit-card-header h3 i {
    color: var(--audit-primary);
}

.audit-card-actions {
    display: flex;
    gap: 8px;
}

/* Table Styles */
.audit-table-wrapper {
    overflow-x: auto;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
}

.audit-table th {
    padding: 14px 20px;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--audit-text-muted);
    border-bottom: 1px solid var(--audit-border);
    white-space: nowrap;
    background: rgba(99, 102, 241, 0.03);
}

.audit-table td {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    color: var(--audit-text);
    font-size: 0.9rem;
}

.audit-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.audit-table tbody tr:last-child td {
    border-bottom: none;
}

/* Badges */
.audit-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-primary {
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.badge-success {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fcd34d;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.badge-info {
    background: rgba(6, 182, 212, 0.15);
    color: #67e8f9;
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.badge-secondary {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.badge-default {
    background: rgba(99, 102, 241, 0.1);
    color: var(--audit-text-muted);
    border: 1px solid var(--audit-border);
}

/* Entity Link */
.entity-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 8px;
    color: var(--audit-primary);
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.entity-link:hover {
    background: rgba(99, 102, 241, 0.2);
}

/* IP Code */
.ip-code {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.8rem;
    color: var(--audit-text-muted);
    background: rgba(99, 102, 241, 0.05);
    padding: 4px 8px;
    border-radius: 6px;
}

/* Empty State */
.audit-empty {
    text-align: center;
    padding: 60px 24px;
}

.audit-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--audit-primary);
}

.audit-empty h4 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--audit-text);
    margin-bottom: 8px;
}

.audit-empty p {
    color: var(--audit-text-muted);
    margin: 0;
}

/* Pagination */
.audit-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-top: 1px solid var(--audit-border);
}

.pagination-info {
    color: var(--audit-text-muted);
    font-size: 0.875rem;
}

.pagination-links {
    display: flex;
    gap: 4px;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid var(--audit-border);
    border-radius: 8px;
    color: var(--audit-text);
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.page-link:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: var(--audit-primary);
}

.page-link.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: white;
}

.page-link.disabled {
    opacity: 0.4;
    pointer-events: none;
}

/* Info Alert */
.retention-alert {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.3);
    border-left: 4px solid var(--audit-info);
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--audit-text);
    font-size: 0.9rem;
}

.retention-alert i {
    font-size: 1.25rem;
    color: var(--audit-info);
}

/* Buttons */
.cyber-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.cyber-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
}

.cyber-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.cyber-btn-outline {
    background: transparent;
    color: var(--audit-text);
    border: 1px solid var(--audit-border);
}

.cyber-btn-outline:hover {
    background: rgba(99, 102, 241, 0.1);
}

.cyber-btn-sm {
    padding: 8px 14px;
    font-size: 0.8rem;
}

/* Timestamp */
.timestamp {
    white-space: nowrap;
}

.timestamp-date {
    color: var(--audit-text);
    font-weight: 500;
}

.timestamp-time {
    color: var(--audit-text-muted);
    font-size: 0.8rem;
}

/* Responsive */
@media (max-width: 768px) {
    .audit-container {
        padding: 100px 16px 60px 16px;
    }

    .filter-form {
        flex-direction: column;
    }

    .filter-group {
        width: 100%;
    }

    .audit-card-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-clock-rotate-left"></i>
            GDPR Audit Log
        </h1>
        <p class="admin-page-subtitle">Complete compliance activity trail</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/enterprise/gdpr" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> GDPR
        </a>
        <button type="button" class="admin-btn admin-btn-primary" onclick="exportAuditLog()">
            <i class="fa-solid fa-download"></i> Export Log
        </button>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Stats Grid -->
    <div class="audit-stats-grid">
        <div class="audit-stat">
            <div class="audit-stat-value"><?= number_format($stats['total_entries'] ?? 0) ?></div>
            <div class="audit-stat-label">Total Entries</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-value"><?= $stats['today'] ?? 0 ?></div>
            <div class="audit-stat-label">Today</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-value"><?= $stats['this_week'] ?? 0 ?></div>
            <div class="audit-stat-label">This Week</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-value"><?= $stats['data_exports'] ?? 0 ?></div>
            <div class="audit-stat-label">Data Exports</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-value"><?= $stats['deletions'] ?? 0 ?></div>
            <div class="audit-stat-label">Deletions</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-value"><?= $stats['consent_changes'] ?? 0 ?></div>
            <div class="audit-stat-label">Consent Changes</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label class="filter-label">Action Type</label>
                <select name="action" class="filter-select">
                    <option value="">All Actions</option>
                    <option value="data_exported" <?= ($filters['action'] ?? '') === 'data_exported' ? 'selected' : '' ?>>Data Export</option>
                    <option value="data_deleted" <?= ($filters['action'] ?? '') === 'data_deleted' ? 'selected' : '' ?>>Data Deletion</option>
                    <option value="consent_granted" <?= ($filters['action'] ?? '') === 'consent_granted' ? 'selected' : '' ?>>Consent Granted</option>
                    <option value="consent_withdrawn" <?= ($filters['action'] ?? '') === 'consent_withdrawn' ? 'selected' : '' ?>>Consent Withdrawn</option>
                    <option value="request_created" <?= ($filters['action'] ?? '') === 'request_created' ? 'selected' : '' ?>>Request Created</option>
                    <option value="request_processed" <?= ($filters['action'] ?? '') === 'request_processed' ? 'selected' : '' ?>>Request Processed</option>
                    <option value="breach_reported" <?= ($filters['action'] ?? '') === 'breach_reported' ? 'selected' : '' ?>>Breach Reported</option>
                    <option value="user_data_accessed" <?= ($filters['action'] ?? '') === 'user_data_accessed' ? 'selected' : '' ?>>Data Accessed</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Entity Type</label>
                <select name="entity_type" class="filter-select">
                    <option value="">All Entities</option>
                    <option value="user" <?= ($filters['entity_type'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="request" <?= ($filters['entity_type'] ?? '') === 'request' ? 'selected' : '' ?>>GDPR Request</option>
                    <option value="consent" <?= ($filters['entity_type'] ?? '') === 'consent' ? 'selected' : '' ?>>Consent</option>
                    <option value="breach" <?= ($filters['entity_type'] ?? '') === 'breach' ? 'selected' : '' ?>>Breach</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">User</label>
                <input type="text" name="user" class="filter-input" placeholder="Email or ID" value="<?= htmlspecialchars($filters['user'] ?? '') ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">From Date</label>
                <input type="date" name="from_date" class="filter-input" value="<?= $filters['from_date'] ?? '' ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">To Date</label>
                <input type="date" name="to_date" class="filter-input" value="<?= $filters['to_date'] ?? '' ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="cyber-btn cyber-btn-primary cyber-btn-sm">
                    <i class="fa-solid fa-search"></i> Filter
                </button>
                <a href="<?= $basePath ?>/admin/enterprise/gdpr/audit" class="cyber-btn cyber-btn-outline cyber-btn-sm">
                    <i class="fa-solid fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Audit Log Table -->
    <div class="audit-card">
        <div class="audit-card-header">
            <h3>
                <i class="fa-solid fa-clock-rotate-left"></i>
                Audit Trail
            </h3>
            <div class="audit-card-actions">
                <button type="button" class="cyber-btn cyber-btn-outline cyber-btn-sm" onclick="exportAuditLog()">
                    <i class="fa-solid fa-download"></i> Export
                </button>
            </div>
        </div>

        <div class="audit-table-wrapper">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Entity</th>
                        <th>User</th>
                        <th>Performed By</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="audit-empty">
                                    <div class="audit-empty-icon">
                                        <i class="fa-solid fa-history"></i>
                                    </div>
                                    <h4>No audit entries found</h4>
                                    <p>Adjust your filters to see more results</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td>
                                    <div class="timestamp">
                                        <div class="timestamp-date"><?= date('M j, Y', strtotime($log['created_at'])) ?></div>
                                        <div class="timestamp-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="audit-badge <?= getActionBadgeClass($log['action']) ?>">
                                        <i class="fa-solid <?= getActionIcon($log['action']) ?>"></i>
                                        <?= formatAction($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['details'] ?? '-') ?>
                                    <?php if (!empty($log['metadata'])): ?>
                                        <button type="button" class="cyber-btn cyber-btn-outline cyber-btn-sm" style="padding: 4px 8px; margin-left: 8px;" onclick="showMetadata(<?= htmlspecialchars(json_encode($log['metadata'])) ?>)">
                                            <i class="fa-solid fa-info-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['entity_type'] && $log['entity_id']): ?>
                                        <a href="<?= getEntityLink($basePath, $log['entity_type'], $log['entity_id']) ?>" class="entity-link">
                                            <?= ucfirst($log['entity_type']) ?> #<?= $log['entity_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--audit-text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                        <a href="<?= $basePath ?>/admin/users/<?= $log['user_id'] ?>" style="color: var(--audit-primary); text-decoration: none;">
                                            <?= htmlspecialchars($log['user_email'] ?? 'User #' . $log['user_id']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--audit-text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['performed_by']): ?>
                                        <?= htmlspecialchars($log['admin_email'] ?? 'Admin') ?>
                                    <?php else: ?>
                                        <span class="audit-badge badge-secondary">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code class="ip-code"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($auditLogs) && $totalPages > 1): ?>
            <div class="audit-pagination">
                <div class="pagination-info">
                    Showing <?= number_format($offset + 1) ?>-<?= number_format(min($offset + count($auditLogs), $totalCount)) ?>
                    of <?= number_format($totalCount) ?> entries
                </div>
                <div class="pagination-links">
                    <a href="?page=<?= $currentPage_num - 1 ?>&<?= http_build_query(array_filter($filters)) ?>"
                       class="page-link <?= $currentPage_num <= 1 ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>

                    <?php
                    $startPage = max(1, $currentPage_num - 2);
                    $endPage = min($totalPages, $currentPage_num + 2);

                    if ($startPage > 1): ?>
                        <a href="?page=1&<?= http_build_query(array_filter($filters)) ?>" class="page-link">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="page-link disabled">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"
                           class="page-link <?= $i === $currentPage_num ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="page-link disabled">...</span>
                        <?php endif; ?>
                        <a href="?page=<?= $totalPages ?>&<?= http_build_query(array_filter($filters)) ?>" class="page-link"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <a href="?page=<?= $currentPage_num + 1 ?>&<?= http_build_query(array_filter($filters)) ?>"
                       class="page-link <?= $currentPage_num >= $totalPages ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Retention Notice -->
    <div class="retention-alert">
        <i class="fa-solid fa-info-circle"></i>
        <div>
            <strong>Data Retention:</strong> Audit logs are retained for <?= $retentionPeriod ?> in compliance with GDPR Article 30.
            Logs older than this period are automatically archived and eventually deleted.
        </div>
    </div>

<!-- Metadata Modal -->
<div id="metadataModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 20px; max-width: 600px; width: 90%; max-height: 80vh; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid rgba(99, 102, 241, 0.2); display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; color: #f1f5f9;">Additional Details</h4>
            <button onclick="closeMetadata()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <div style="padding: 24px; overflow: auto; max-height: 60vh;">
            <pre id="metadataContent" style="background: rgba(99, 102, 241, 0.05); padding: 16px; border-radius: 12px; color: #f1f5f9; font-size: 0.85rem; overflow: auto; margin: 0;"></pre>
        </div>
    </div>
</div>

<script>
function exportAuditLog() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '<?= $basePath ?>/admin/enterprise/gdpr/audit/export?' + params.toString();
}

function showMetadata(metadata) {
    document.getElementById('metadataContent').textContent = JSON.stringify(metadata, null, 2);
    document.getElementById('metadataModal').style.display = 'flex';
}

function closeMetadata() {
    document.getElementById('metadataModal').style.display = 'none';
}

// Close modal on backdrop click
document.getElementById('metadataModal').addEventListener('click', function(e) {
    if (e.target === this) closeMetadata();
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMetadata();
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
