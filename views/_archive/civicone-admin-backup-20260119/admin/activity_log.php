<?php
/**
 * Activity Log - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Pagination
$baseUrl = $basePath . '/admin-legacy/activity-log';
$prevPage = $currentPage > 1 ? $currentPage - 1 : null;
$nextPage = $currentPage < $totalPages ? $currentPage + 1 : null;

// Admin header configuration
$adminPageTitle = 'Activity Log';
$adminPageSubtitle = 'System Audit';
$adminPageIcon = 'fa-list-ul';

// Include standalone admin header
require __DIR__ . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-list-ul"></i>
            Activity Log
        </h1>
        <p class="admin-page-subtitle">Full record of user actions and system events</p>
    </div>
    <div class="admin-page-header-actions">
        <span class="admin-badge admin-badge-primary">
            Page <?= $currentPage ?> of <?= $totalPages ?>
        </span>
    </div>
</div>

<!-- Activity Log Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-scroll"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Access History</h3>
            <p class="admin-card-subtitle"><?= number_format($totalLogs ?? count($logs ?? [])) ?> total entries</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (!empty($logs)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th class="hide-mobile">Details</th>
                        <th class="hide-tablet">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <div class="admin-user-cell">
                                <div class="admin-user-avatar-placeholder">
                                    <?php
                                    $parts = explode(' ', $log['user_name'] ?? 'Unknown');
                                    echo strtoupper(substr($parts[0], 0, 1));
                                    ?>
                                </div>
                                <div class="admin-user-info">
                                    <div class="admin-user-name"><?= htmlspecialchars($log['user_name'] ?? 'Unknown') ?></div>
                                    <div class="admin-user-email"><?= htmlspecialchars($log['user_email'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="admin-action-badge">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))) ?>
                            </span>
                        </td>
                        <td class="hide-mobile">
                            <div class="admin-log-details">
                                <?= htmlspecialchars($log['details'] ?? '-') ?>
                                <?php if (!empty($log['ip_address'])): ?>
                                    <div class="admin-log-ip">IP: <?= htmlspecialchars($log['ip_address']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="hide-tablet">
                            <div class="admin-log-time">
                                <i class="fa-regular fa-clock"></i>
                                <?= date('M d, Y', strtotime($log['created_at'])) ?>
                                <span class="admin-log-hour"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="admin-pagination">
            <div class="admin-pagination-btn">
                <?php if ($prevPage): ?>
                    <a href="<?= $baseUrl ?>?page=<?= $prevPage ?>" class="admin-btn admin-btn-secondary">
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <button class="admin-btn admin-btn-secondary" disabled>
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                <?php endif; ?>
            </div>

            <div class="admin-pagination-info">
                Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
            </div>

            <div class="admin-pagination-btn">
                <?php if ($nextPage): ?>
                    <a href="<?= $baseUrl ?>?page=<?= $nextPage ?>" class="admin-btn admin-btn-secondary">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button class="admin-btn admin-btn-secondary" disabled>
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <h3 class="admin-empty-title">No activity found</h3>
            <p class="admin-empty-text">Activity will appear here as users interact with the platform.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Activity Log Specific Styles */
.admin-user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-user-avatar-placeholder {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.admin-user-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.admin-user-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.admin-user-email {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-action-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #a5b4fc;
    text-transform: capitalize;
}

.admin-log-details {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-log-ip {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 2px;
    font-family: monospace;
}

.admin-log-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
}

.admin-log-time i {
    color: rgba(255, 255, 255, 0.4);
}

.admin-log-hour {
    color: rgba(255, 255, 255, 0.4);
    font-family: monospace;
}

/* Badge */
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.admin-badge-primary {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

/* Table */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Pagination */
.admin-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    background: rgba(0, 0, 0, 0.15);
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-pagination-info {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

.admin-pagination-info strong {
    color: #fff;
}

.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-pagination {
        flex-direction: column;
        gap: 1rem;
    }

    .admin-page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}
</style>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
