<?php
/**
 * GDPR Requests - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Page config for header
$enterprisePageTitle = 'GDPR Requests';
$enterprisePageSubtitle = 'Data Subject Requests';
$enterprisePageIcon = 'fa-inbox';
$enterpriseSection = 'gdpr';
$enterpriseSubpage = 'requests';

$filters = $filters ?? [];
$summary = $summary ?? [];

require dirname(__DIR__) . '/partials/enterprise-header.php';

// Helper functions
function getRequestBadge(string $type): string {
    return ['access' => 'info', 'erasure' => 'danger', 'portability' => 'success', 'rectification' => 'warning'][$type] ?? 'secondary';
}

function getRequestIcon(string $type): string {
    return ['access' => 'eye', 'erasure' => 'trash', 'portability' => 'right-left', 'rectification' => 'pen'][$type] ?? 'file';
}

function formatRequestType(string $type): string {
    return ucwords(str_replace('_', ' ', $type));
}

function getStatusBadge(string $status): string {
    return ['pending' => 'warning', 'in_progress' => 'info', 'processing' => 'info', 'completed' => 'success', 'rejected' => 'danger'][$status] ?? 'secondary';
}

function isRequestOverdue(string $createdAt, string $status): bool {
    if (in_array($status, ['completed', 'rejected'])) return false;
    return (time() - strtotime($createdAt)) > (30 * 86400);
}

function getSlaIndicatorHtml(string $createdAt, string $status): string {
    if (in_array($status, ['completed', 'rejected'])) {
        return '<span class="sla-indicator sla-done"><i class="fa-solid fa-check"></i> Done</span>';
    }
    $daysPassed = (time() - strtotime($createdAt)) / 86400;
    $daysRemaining = 30 - $daysPassed;
    if ($daysRemaining < 0) {
        return '<span class="sla-indicator sla-overdue"><i class="fa-solid fa-circle-exclamation"></i> ' . abs(round($daysRemaining)) . 'd overdue</span>';
    } elseif ($daysRemaining <= 5) {
        return '<span class="sla-indicator sla-urgent"><i class="fa-solid fa-clock"></i> ' . round($daysRemaining) . 'd left</span>';
    }
    return '<span class="sla-indicator sla-normal"><i class="fa-solid fa-circle-check"></i> ' . round($daysRemaining) . 'd left</span>';
}

function formatRequestTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>

<style>
/* Page Header */
.page-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.page-header-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-header-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: white;
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
}

.page-header-text h1 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    margin: 0;
}

.page-header-text p {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.5);
    margin: 0;
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-mini {
    display: flex;
    align-items: center;
    padding: 1.25rem;
    border-radius: 14px;
    color: white;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}

.stat-mini::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -25%;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.stat-mini.warning { background: linear-gradient(135deg, #D97706, #F59E0B); box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3); }
.stat-mini.info { background: linear-gradient(135deg, #0891B2, #06B6D4); box-shadow: 0 4px 20px rgba(6, 182, 212, 0.3); }
.stat-mini.danger { background: linear-gradient(135deg, #DC2626, #EF4444); box-shadow: 0 4px 20px rgba(239, 68, 68, 0.3); }
.stat-mini.success { background: linear-gradient(135deg, #059669, #10B981); box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3); }

.stat-mini i { font-size: 1.5rem; opacity: 0.9; }
.stat-mini .stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; }
.stat-mini .stat-label { font-size: 0.8rem; opacity: 0.9; font-weight: 500; }

/* Filter Card */
.filter-card {
    background: rgba(10, 22, 40, 0.8);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 16px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(5, 1fr) auto;
    gap: 1rem;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.form-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control {
    padding: 0.6rem 0.85rem;
    border: 1px solid rgba(6, 182, 212, 0.2);
    border-radius: 8px;
    font-size: 0.85rem;
    background: rgba(10, 22, 40, 0.6);
    color: #fff;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: rgba(6, 182, 212, 0.5);
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
}

.form-control::placeholder {
    color: rgba(255,255,255,0.3);
}

/* Table Card */
.table-card {
    background: rgba(10, 22, 40, 0.8);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 16px;
    overflow: hidden;
}

.requests-table {
    width: 100%;
    border-collapse: collapse;
}

.requests-table th {
    background: rgba(6, 182, 212, 0.05);
    padding: 0.85rem 1rem;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(6, 182, 212, 0.1);
}

.requests-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(6, 182, 212, 0.08);
    font-size: 0.9rem;
    color: #fff;
    vertical-align: middle;
}

.requests-table tbody tr {
    transition: background 0.15s;
}

.requests-table tbody tr:hover {
    background: rgba(6, 182, 212, 0.05);
}

.requests-table tr.overdue {
    background: rgba(239, 68, 68, 0.08);
}

.requests-table tr.overdue:hover {
    background: rgba(239, 68, 68, 0.12);
}

/* Request ID Link */
.request-id {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: #22d3ee;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s;
}

.request-id:hover {
    color: #06b6d4;
    text-decoration: underline;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-primary { background: rgba(99, 102, 241, 0.15); color: #a5b4fc; }
.badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.badge-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.badge-info { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
.badge-secondary { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }

/* SLA Indicator */
.sla-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.sla-indicator.sla-overdue { color: #f87171; }
.sla-indicator.sla-urgent { color: #fbbf24; }
.sla-indicator.sla-normal { color: #34d399; }
.sla-indicator.sla-done { color: #94a3b8; }

/* User Info */
.user-info {
    line-height: 1.4;
}

.user-info strong {
    color: #fff;
    font-weight: 600;
}

.user-info small {
    color: rgba(255,255,255,0.4);
    font-size: 0.75rem;
}

/* Actions */
.actions-cell {
    display: flex;
    gap: 0.4rem;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid rgba(6, 182, 212, 0.2);
    background: transparent;
    color: rgba(255,255,255,0.5);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

.action-btn:hover {
    background: rgba(6, 182, 212, 0.1);
    color: #22d3ee;
    border-color: rgba(6, 182, 212, 0.4);
}

.action-btn.success:hover {
    color: #34d399;
    border-color: rgba(16, 185, 129, 0.4);
    background: rgba(16, 185, 129, 0.1);
}

.action-btn.danger:hover {
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.4);
    background: rgba(239, 68, 68, 0.1);
}

/* Empty State */
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
}

.empty-state i {
    font-size: 3.5rem;
    color: rgba(255,255,255,0.2);
    margin-bottom: 1rem;
}

.empty-state h5 {
    color: rgba(255,255,255,0.6);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: rgba(255,255,255,0.4);
    margin: 0;
}

/* Pagination */
.pagination-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(6, 182, 212, 0.1);
}

.pagination-info {
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
}

.pagination {
    display: flex;
    gap: 0.25rem;
}

.pagination .page-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid rgba(6, 182, 212, 0.2);
    border-radius: 6px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s;
    background: transparent;
}

.pagination .page-link:hover {
    background: rgba(6, 182, 212, 0.1);
    border-color: rgba(6, 182, 212, 0.3);
}

.pagination .active .page-link {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: white;
    border-color: transparent;
}

.pagination .disabled .page-link {
    color: rgba(255,255,255,0.3);
    pointer-events: none;
}

/* Bulk Actions Bar */
.bulk-actions {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1000;
    min-width: 400px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 14px;
    padding: 1rem 1.5rem;
    display: none;
    box-shadow: 0 15px 50px rgba(99, 102, 241, 0.4);
}

.bulk-actions.show {
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateX(-50%) translateY(20px); opacity: 0; }
    to { transform: translateX(-50%) translateY(0); opacity: 1; }
}

.bulk-actions span {
    color: white;
    font-weight: 600;
}

.bulk-actions .bulk-btns {
    display: flex;
    gap: 0.5rem;
}

.bulk-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.bulk-btn.light {
    background: rgba(255,255,255,0.2);
    color: white;
}

.bulk-btn.light:hover {
    background: rgba(255,255,255,0.3);
}

.bulk-btn.success {
    background: #10B981;
    color: white;
}

.bulk-btn.success:hover {
    background: #059669;
}

/* Checkbox Styling */
input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #06b6d4;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 1200px) {
    .filter-form {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1024px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .filter-form {
        grid-template-columns: 1fr 1fr;
    }

    .stats-row {
        grid-template-columns: 1fr;
    }

    .page-header-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .requests-table {
        font-size: 0.8rem;
    }

    .requests-table th,
    .requests-table td {
        padding: 0.75rem 0.5rem;
    }
}

@media (max-width: 600px) {
    .filter-form {
        grid-template-columns: 1fr;
    }

    .bulk-actions {
        min-width: auto;
        width: calc(100% - 2rem);
        flex-direction: column;
        gap: 0.75rem;
    }
}
</style>

<!-- Page Header -->
<div class="page-header-bar">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fa-solid fa-inbox"></i>
        </div>
        <div class="page-header-text">
            <h1>GDPR Requests</h1>
            <p>Manage data subject access and deletion requests</p>
        </div>
    </div>
    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/create" class="enterprise-btn enterprise-btn-primary">
        <i class="fa-solid fa-plus"></i>
        New Request
    </a>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <div class="stat-mini warning">
        <i class="fa-solid fa-clock"></i>
        <div>
            <div class="stat-value"><?= $summary['pending'] ?? 0 ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="stat-mini info">
        <i class="fa-solid fa-spinner"></i>
        <div>
            <div class="stat-value"><?= $summary['in_progress'] ?? 0 ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="stat-mini danger">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
            <div class="stat-value"><?= $summary['overdue'] ?? 0 ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>
    <div class="stat-mini success">
        <i class="fa-solid fa-check"></i>
        <div>
            <div class="stat-value"><?= $summary['completed_month'] ?? 0 ?></div>
            <div class="stat-label">Completed (30d)</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-card">
    <form method="GET" class="filter-form">
        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="form-group">
            <label>Type</label>
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <option value="access" <?= ($filters['type'] ?? '') === 'access' ? 'selected' : '' ?>>Access</option>
                <option value="erasure" <?= ($filters['type'] ?? '') === 'erasure' ? 'selected' : '' ?>>Erasure</option>
                <option value="portability" <?= ($filters['type'] ?? '') === 'portability' ? 'selected' : '' ?>>Portability</option>
                <option value="rectification" <?= ($filters['type'] ?? '') === 'rectification' ? 'selected' : '' ?>>Rectification</option>
            </select>
        </div>
        <div class="form-group">
            <label>SLA Status</label>
            <select name="sla" class="form-control">
                <option value="">All</option>
                <option value="overdue" <?= ($filters['sla'] ?? '') === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="urgent" <?= ($filters['sla'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent (&lt;5 days)</option>
                <option value="normal" <?= ($filters['sla'] ?? '') === 'normal' ? 'selected' : '' ?>>On Track</option>
            </select>
        </div>
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Email or Request ID" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
        </div>
        <div class="form-group" style="flex-direction: row; gap: 0.5rem; align-items: flex-end;">
            <button type="submit" class="enterprise-btn enterprise-btn-primary enterprise-btn-sm">
                <i class="fa-solid fa-search"></i> Filter
            </button>
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests" class="enterprise-btn enterprise-btn-secondary enterprise-btn-sm">
                <i class="fa-solid fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Requests Table -->
<div class="table-card">
    <table class="requests-table">
        <thead>
            <tr>
                <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                <th>ID</th>
                <th>Type</th>
                <th>User</th>
                <th>Status</th>
                <th>SLA</th>
                <th>Assigned</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests ?? [])): ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <h5>No requests found</h5>
                            <p>Adjust your filters or wait for new requests</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <tr class="<?= isRequestOverdue($request['created_at'], $request['status']) ? 'overdue' : '' ?>">
                        <td><input type="checkbox" class="request-checkbox" value="<?= $request['id'] ?>"></td>
                        <td>
                            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/<?= $request['id'] ?>" class="request-id">
                                #<?= $request['id'] ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge badge-<?= getRequestBadge($request['request_type']) ?>">
                                <i class="fa-solid fa-<?= getRequestIcon($request['request_type']) ?>"></i>
                                <?= formatRequestType($request['request_type']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="user-info">
                                <strong><?= htmlspecialchars($request['email'] ?? '') ?></strong>
                                <?php if (!empty($request['user_id'])): ?>
                                    <br><small>User #<?= $request['user_id'] ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= getStatusBadge($request['status']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                            </span>
                        </td>
                        <td><?= getSlaIndicatorHtml($request['created_at'], $request['status']) ?></td>
                        <td>
                            <?php if (!empty($request['assigned_to'])): ?>
                                <span class="badge badge-secondary">
                                    <i class="fa-solid fa-user"></i>
                                    <?= htmlspecialchars($request['assigned_name'] ?? 'Admin #' . $request['assigned_to']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.4);">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span title="<?= date('Y-m-d H:i:s', strtotime($request['created_at'])) ?>">
                                <?= formatRequestTimeAgo($request['created_at']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/<?= $request['id'] ?>" class="action-btn" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <button type="button" class="action-btn success" onclick="processRequest(<?= $request['id'] ?>)" title="Process">
                                        <i class="fa-solid fa-play"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($request['status'] !== 'completed' && $request['status'] !== 'rejected'): ?>
                                    <button type="button" class="action-btn danger" onclick="rejectRequest(<?= $request['id'] ?>)" title="Reject">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($requests) && ($totalPages ?? 1) > 1): ?>
        <div class="pagination-footer">
            <div class="pagination-info">
                Showing <?= ($offset ?? 0) + 1 ?>-<?= min(($offset ?? 0) + count($requests), $totalCount ?? 0) ?> of <?= $totalCount ?? 0 ?> requests
            </div>
            <nav role="navigation" aria-label="Main navigation" class="pagination">
                <div class="page-item <?= ($currentPage ?? 1) <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= ($currentPage ?? 1) - 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                </div>
                <?php for ($i = max(1, ($currentPage ?? 1) - 2); $i <= min($totalPages ?? 1, ($currentPage ?? 1) + 2); $i++): ?>
                    <div class="page-item <?= $i === ($currentPage ?? 1) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $i ?></a>
                    </div>
                <?php endfor; ?>
                <div class="page-item <?= ($currentPage ?? 1) >= ($totalPages ?? 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= ($currentPage ?? 1) + 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Actions -->
<div class="bulk-actions" id="bulkActions">
    <span><strong id="selectedCount">0</strong> requests selected</span>
    <div class="bulk-btns">
        <button type="button" class="bulk-btn light" onclick="bulkAssign()">
            <i class="fa-solid fa-user-plus"></i> Assign
        </button>
        <button type="button" class="bulk-btn success" onclick="bulkProcess()">
            <i class="fa-solid fa-play"></i> Process All
        </button>
        <button type="button" class="bulk-btn light" onclick="clearSelection()">
            <i class="fa-solid fa-times"></i> Clear
        </button>
    </div>
</div>

<script>
const basePath = '<?= $basePath ?>';

document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.request-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkActions);
    });

    function updateBulkActions() {
        const selected = document.querySelectorAll('.request-checkbox:checked');
        selectedCount.textContent = selected.length;
        bulkActions.classList.toggle('show', selected.length > 0);
    }
});

function clearSelection() {
    document.querySelectorAll('.request-checkbox, #selectAll').forEach(cb => cb.checked = false);
    document.getElementById('bulkActions').classList.remove('show');
}

function processRequest(id) {
    if (confirm('Start processing this request?')) {
        fetch(basePath + '/admin-legacy/enterprise/gdpr/requests/' + id + '/process', { method: 'POST' })
            .then(() => location.reload());
    }
}

function rejectRequest(id) {
    const reason = prompt('Enter rejection reason:');
    if (reason) {
        fetch(basePath + '/admin-legacy/enterprise/gdpr/requests/' + id + '/reject', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({reason: reason})
        }).then(() => location.reload());
    }
}

function bulkProcess() {
    const ids = Array.from(document.querySelectorAll('.request-checkbox:checked')).map(cb => cb.value);
    if (confirm('Process ' + ids.length + ' requests?')) {
        fetch(basePath + '/admin-legacy/enterprise/gdpr/requests/bulk-process', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ids: ids})
        }).then(() => location.reload());
    }
}

function bulkAssign() {
    const ids = Array.from(document.querySelectorAll('.request-checkbox:checked')).map(cb => cb.value);
    window.location.href = basePath + '/admin-legacy/enterprise/gdpr/requests/bulk-assign?ids=' + ids.join(',');
}
</script>

<?php require dirname(__DIR__) . '/partials/enterprise-footer.php'; ?>
