<?php
/**
 * GDPR Requests Management - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'GDPR Requests';
$adminPageSubtitle = 'Enterprise GDPR';
$adminPageIcon = 'fa-inbox';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'gdpr';
$currentPage = 'requests';

// Get data with defaults
$requests = $requests ?? [];
$filters = $filters ?? [];
$summary = $summary ?? [];
$totalCount = $totalCount ?? count($requests);
$totalPages = $totalPages ?? 1;
$currentPage = $currentPageNum ?? 1;
$offset = $offset ?? 0;

// Helper functions
function getRequestBadgeClass(string $type): string {
    return match($type) {
        'access' => 'info',
        'erasure' => 'danger',
        'portability' => 'primary',
        'rectification' => 'warning',
        default => 'secondary'
    };
}

function getRequestIcon(string $type): string {
    return match($type) {
        'access' => 'eye',
        'erasure' => 'trash-can',
        'portability' => 'right-left',
        'rectification' => 'pen-to-square',
        default => 'file'
    };
}

function formatRequestType(string $type): string {
    return ucwords(str_replace('_', ' ', $type));
}

function getStatusBadgeClass(string $status): string {
    return match($status) {
        'pending' => 'warning',
        'in_progress', 'processing' => 'info',
        'completed' => 'success',
        'rejected' => 'danger',
        default => 'secondary'
    };
}

function isRequestOverdue(string $createdAt, string $status): bool {
    if (in_array($status, ['completed', 'rejected'])) return false;
    return (time() - strtotime($createdAt)) > (30 * 86400);
}

function getSlaIndicatorHtml(string $createdAt, string $status): string {
    if (in_array($status, ['completed', 'rejected'])) {
        return '<span class="sla-badge sla-done"><i class="fa-solid fa-check"></i> Done</span>';
    }
    $daysPassed = (time() - strtotime($createdAt)) / 86400;
    $daysRemaining = 30 - $daysPassed;
    if ($daysRemaining < 0) {
        return '<span class="sla-badge sla-overdue"><i class="fa-solid fa-circle-exclamation"></i> ' . abs(round($daysRemaining)) . 'd overdue</span>';
    } elseif ($daysRemaining <= 5) {
        return '<span class="sla-badge sla-urgent"><i class="fa-solid fa-clock"></i> ' . round($daysRemaining) . 'd left</span>';
    }
    return '<span class="sla-badge sla-normal"><i class="fa-solid fa-circle-check"></i> ' . round($daysRemaining) . 'd left</span>';
}

function formatTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            GDPR Requests
        </h1>
        <p class="admin-page-subtitle">Manage data subject access and deletion requests</p>
    </div>
    <div class="admin-page-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Request
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<style>
/* Page Header */
.admin-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.admin-page-header-content {
    flex: 1;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-page-subtitle {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

[data-theme="light"] .admin-page-title {
    color: #1e293b;
}

[data-theme="light"] .admin-page-subtitle {
    color: #64748b;
}

.admin-page-actions {
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
}

.back-link {
    color: inherit;
    text-decoration: none;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 1;
}

/* Admin Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.admin-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.admin-btn-secondary {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.admin-btn-secondary:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
}

[data-theme="light"] .admin-btn-secondary {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.admin-btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

[data-theme="light"] .stat-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--stat-color), transparent);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0 25px var(--stat-glow);
    border-color: var(--stat-color);
}

.stat-card.warning {
    --stat-color: #f59e0b;
    --stat-glow: rgba(245, 158, 11, 0.3);
}

.stat-card.info {
    --stat-color: #06b6d4;
    --stat-glow: rgba(6, 182, 212, 0.3);
}

.stat-card.danger {
    --stat-color: #ef4444;
    --stat-glow: rgba(239, 68, 68, 0.3);
}

.stat-card.success {
    --stat-color: #10b981;
    --stat-glow: rgba(16, 185, 129, 0.3);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--stat-color), var(--stat-color-light, var(--stat-color)));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.stat-card.warning .stat-icon { --stat-color-light: #fbbf24; }
.stat-card.info .stat-icon { --stat-color-light: #22d3ee; }
.stat-card.danger .stat-icon { --stat-color-light: #f87171; }
.stat-card.success .stat-icon { --stat-color-light: #34d399; }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.25rem;
}

[data-theme="light"] .stat-value {
    color: #1e293b;
}

.stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

[data-theme="light"] .stat-label {
    color: #64748b;
}

/* Filter Card */
.filter-card {
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 1rem;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

[data-theme="light"] .filter-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.15);
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
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

[data-theme="light"] .form-group label {
    color: #64748b;
}

.form-control {
    padding: 0.625rem 0.875rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: rgba(99, 102, 241, 0.05);
    color: #fff;
    transition: all 0.2s;
}

[data-theme="light"] .form-control {
    background: rgba(99, 102, 241, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    color: #1e293b;
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

[data-theme="light"] .form-control::placeholder {
    color: #94a3b8;
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 1.25rem;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

[data-theme="light"] .admin-glass-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-card-header-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.25rem 0 0 0;
}

[data-theme="light"] .admin-card-title {
    color: #1e293b;
}

[data-theme="light"] .admin-card-subtitle {
    color: #64748b;
}

/* Table */
.admin-table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table thead {
    background: rgba(99, 102, 241, 0.05);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

[data-theme="light"] .admin-table th {
    color: #64748b;
}

.admin-table tbody tr {
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    transition: all 0.2s;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.06);
}

.admin-table tbody tr.overdue-row {
    background: rgba(239, 68, 68, 0.08);
    border-left: 3px solid #ef4444;
}

.admin-table tbody tr.overdue-row:hover {
    background: rgba(239, 68, 68, 0.12);
}

.admin-table td {
    padding: 1rem 1.25rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.875rem;
    vertical-align: middle;
}

[data-theme="light"] .admin-table td {
    color: #1e293b;
}

/* Request ID Link */
.request-id {
    font-family: 'Monaco', 'Menlo', monospace;
    font-weight: 700;
    color: #818cf8;
    text-decoration: none;
    transition: all 0.2s;
}

.request-id:hover {
    color: #a5b4fc;
    text-decoration: underline;
}

/* Badges */
.type-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.type-badge.info,
.status-badge.info {
    background: rgba(6, 182, 212, 0.15);
    color: #22d3ee;
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.type-badge.danger,
.status-badge.danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.type-badge.primary {
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.type-badge.warning,
.status-badge.warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge.success {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.secondary {
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.3);
}

/* SLA Badge */
.sla-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.sla-badge.sla-overdue { color: #f87171; }
.sla-badge.sla-urgent { color: #fbbf24; }
.sla-badge.sla-normal { color: #34d399; }
.sla-badge.sla-done { color: #94a3b8; }

/* User Info */
.user-cell {
    line-height: 1.4;
}

.user-cell .user-email {
    font-weight: 600;
    color: #fff;
}

[data-theme="light"] .user-cell .user-email {
    color: #1e293b;
}

.user-cell .user-id {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

[data-theme="light"] .user-cell .user-id {
    color: #64748b;
}

/* Assigned Badge */
.assigned-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    background: rgba(99, 102, 241, 0.1);
    color: #a5b4fc;
}

.unassigned-text {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
}

[data-theme="light"] .unassigned-text {
    color: #94a3b8;
}

/* Actions */
.table-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: rgba(255, 255, 255, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.action-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.4);
    color: #fff;
    transform: translateY(-2px);
}

.action-btn.success {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.2);
}

.action-btn.success:hover {
    background: rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.4);
    color: #34d399;
}

.action-btn.danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
}

.action-btn.danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
    color: #f87171;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

[data-theme="light"] .empty-state h3 {
    color: #1e293b;
}

.empty-state p {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

[data-theme="light"] .empty-state p {
    color: #64748b;
}

/* Pagination */
.pagination-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
}

.pagination-info {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

[data-theme="light"] .pagination-info {
    color: #64748b;
}

.pagination {
    display: flex;
    gap: 0.25rem;
}

.pagination .page-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s;
    background: transparent;
}

.pagination .page-link:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}

.pagination .active .page-link {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: transparent;
}

.pagination .disabled .page-link {
    color: rgba(255, 255, 255, 0.3);
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
    box-shadow: 0 15px 50px rgba(99, 102, 241, 0.5);
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
    accent-color: #6366f1;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 1200px) {
    .filter-form {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-page-header {
        flex-direction: column;
        gap: 1rem;
    }

    .admin-page-actions {
        width: 100%;
    }

    .admin-page-actions .admin-btn {
        flex: 1;
        justify-content: center;
    }

    .filter-form {
        grid-template-columns: 1fr 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .admin-table {
        min-width: 900px;
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

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card warning">
        <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $summary['pending'] ?? 0 ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>

    <div class="stat-card info">
        <div class="stat-icon">
            <i class="fa-solid fa-spinner"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $summary['in_progress'] ?? 0 ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>

    <div class="stat-card danger">
        <div class="stat-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $summary['overdue'] ?? 0 ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <div class="stat-card success">
        <div class="stat-icon">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div class="stat-content">
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
            <input type="text" name="search" class="form-control" placeholder="Email or ID" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
        </div>
        <div class="form-group" style="flex-direction: row; gap: 0.5rem; align-items: flex-end;">
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                <i class="fa-solid fa-search"></i> Filter
            </button>
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Requests Table -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <i class="fa-solid fa-inbox"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Data Subject Requests</h3>
            <p class="admin-card-subtitle">All GDPR requests requiring action</p>
        </div>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-table">
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
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fa-solid fa-inbox"></i>
                                </div>
                                <h3>No Requests Found</h3>
                                <p>Adjust your filters or wait for new data subject requests</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <tr class="<?= isRequestOverdue($request['created_at'], $request['status']) ? 'overdue-row' : '' ?>">
                            <td><input type="checkbox" class="request-checkbox" value="<?= $request['id'] ?>"></td>
                            <td>
                                <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/<?= $request['id'] ?>" class="request-id">
                                    #<?= $request['id'] ?>
                                </a>
                            </td>
                            <td>
                                <span class="type-badge <?= getRequestBadgeClass($request['request_type']) ?>">
                                    <i class="fa-solid fa-<?= getRequestIcon($request['request_type']) ?>"></i>
                                    <?= formatRequestType($request['request_type']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-email"><?= htmlspecialchars($request['email'] ?? '') ?></div>
                                    <?php if (!empty($request['user_id'])): ?>
                                        <div class="user-id">User #<?= $request['user_id'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?= getStatusBadgeClass($request['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                </span>
                            </td>
                            <td><?= getSlaIndicatorHtml($request['created_at'], $request['status']) ?></td>
                            <td>
                                <?php if (!empty($request['assigned_to'])): ?>
                                    <span class="assigned-badge">
                                        <i class="fa-solid fa-user"></i>
                                        <?= htmlspecialchars($request['assigned_name'] ?? 'Admin #' . $request['assigned_to']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="unassigned-text">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span title="<?= date('Y-m-d H:i:s', strtotime($request['created_at'])) ?>">
                                    <?= formatTimeAgo($request['created_at']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/<?= $request['id'] ?>" class="action-btn" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <button type="button" class="action-btn success" onclick="processRequest(<?= $request['id'] ?>)" title="Start Processing">
                                            <i class="fa-solid fa-play"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!in_array($request['status'], ['completed', 'rejected'])): ?>
                                        <button type="button" class="action-btn danger" onclick="rejectRequest(<?= $request['id'] ?>)" title="Reject Request">
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
    </div>

    <?php if (!empty($requests) && ($totalPages ?? 1) > 1): ?>
        <div class="pagination-footer">
            <div class="pagination-info">
                Showing <?= $offset + 1 ?>-<?= min($offset + count($requests), $totalCount) ?> of <?= $totalCount ?> requests
            </div>
            <nav class="pagination">
                <div class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $currentPage - 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                </div>
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <div class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $i ?></a>
                    </div>
                <?php endfor; ?>
                <div class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $currentPage + 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
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
        fetch(basePath + '/admin-legacy/enterprise/gdpr/requests/' + id + '/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= Csrf::generate() ?>'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Request processing started', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(() => {
            showToast('Network error. Please try again.', 'error');
        });
    }
}

function rejectRequest(id) {
    const reason = prompt('Enter rejection reason:');
    if (reason) {
        fetch(basePath + '/admin-legacy/enterprise/gdpr/requests/' + id + '/reject', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= Csrf::generate() ?>'
            },
            body: JSON.stringify({ reason: reason })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Request rejected', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(() => {
            showToast('Network error. Please try again.', 'error');
        });
    }
}

function bulkProcess() {
    const ids = Array.from(document.querySelectorAll('.request-checkbox:checked')).map(cb => cb.value);
    if (confirm('Process ' + ids.length + ' requests?')) {
        fetch(basePath + '/admin-legacy/enterprise/gdpr/requests/bulk-process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= Csrf::generate() ?>'
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Bulk processing started', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(() => {
            showToast('Network error. Please try again.', 'error');
        });
    }
}

function bulkAssign() {
    const ids = Array.from(document.querySelectorAll('.request-checkbox:checked')).map(cb => cb.value);
    window.location.href = basePath + '/admin-legacy/enterprise/gdpr/requests/bulk-assign?ids=' + ids.join(',');
}

// Toast notification
function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
