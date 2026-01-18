<?php
// Phoenix View: Organization Audit Log
// Path: views/modern/organizations/audit-log.php

$hTitle = $org['name'] . ' - Audit Log';
$hSubtitle = 'Activity History & Security Audit';
$hideHero = true;

$activeTab = 'audit';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<style>
/* Audit Log Page Styles */
.audit-log-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #f0f9ff 50%, #f8fafc 100%);
}

[data-theme="dark"] .audit-log-bg {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}

.audit-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 120px 24px 40px 24px;
    position: relative;
    z-index: 10;
}

/* Glass Card */
.audit-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.75) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
    overflow: hidden;
    margin-bottom: 24px;
}

[data-theme="dark"] .audit-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(30, 41, 59, 0.75) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

/* Header */
.audit-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px;
    border-bottom: 1px solid rgba(229, 231, 235, 0.5);
    flex-wrap: wrap;
    gap: 16px;
}

[data-theme="dark"] .audit-header {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.audit-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
}

[data-theme="dark"] .audit-title {
    color: #f1f5f9;
}

.audit-title i {
    color: #6366f1;
}

.audit-count {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.audit-actions {
    display: flex;
    gap: 8px;
}

.audit-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.audit-export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
}

/* Filters */
.audit-filters {
    display: flex;
    gap: 12px;
    padding: 16px 24px;
    background: rgba(99, 102, 241, 0.05);
    border-bottom: 1px solid rgba(229, 231, 235, 0.5);
    flex-wrap: wrap;
}

[data-theme="dark"] .audit-filters {
    background: rgba(99, 102, 241, 0.1);
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.audit-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.audit-filter-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
}

.audit-filter-select, .audit-filter-input {
    padding: 8px 12px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
    color: #1f2937;
    min-width: 150px;
}

[data-theme="dark"] .audit-filter-select,
[data-theme="dark"] .audit-filter-input {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

.audit-filter-btn {
    padding: 8px 16px;
    background: #6366f1;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    align-self: flex-end;
}

.audit-filter-btn:hover {
    background: #4f46e5;
}

.audit-filter-clear {
    padding: 8px 16px;
    background: transparent;
    border: 1px solid rgba(107, 114, 128, 0.3);
    color: #6b7280;
    border-radius: 8px;
    cursor: pointer;
    align-self: flex-end;
    text-decoration: none;
    font-size: 0.9rem;
}

/* Log Entries */
.audit-log-list {
    padding: 0;
}

.audit-log-entry {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 24px;
    border-bottom: 1px solid rgba(229, 231, 235, 0.3);
    transition: background 0.15s;
}

.audit-log-entry:hover {
    background: rgba(99, 102, 241, 0.03);
}

[data-theme="dark"] .audit-log-entry {
    border-bottom-color: rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .audit-log-entry:hover {
    background: rgba(99, 102, 241, 0.08);
}

.audit-log-entry:last-child {
    border-bottom: none;
}

.audit-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.audit-icon.deposit { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.audit-icon.withdrawal { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.audit-icon.transfer { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.audit-icon.member { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.audit-icon.settings { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
.audit-icon.ownership { background: rgba(168, 85, 247, 0.1); color: #a855f7; }
.audit-icon.bulk { background: rgba(236, 72, 153, 0.1); color: #ec4899; }

.audit-content {
    flex: 1;
    min-width: 0;
}

.audit-action-label {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
    margin-bottom: 4px;
}

[data-theme="dark"] .audit-action-label {
    color: #f1f5f9;
}

.audit-user {
    color: #6366f1;
    font-weight: 600;
}

.audit-target {
    color: #059669;
    font-weight: 600;
}

.audit-details {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 4px;
}

.audit-details span {
    display: inline-block;
    background: rgba(107, 114, 128, 0.1);
    padding: 2px 8px;
    border-radius: 4px;
    margin-right: 8px;
    margin-top: 4px;
}

.audit-meta {
    text-align: right;
    flex-shrink: 0;
    min-width: 160px;
}

.audit-time {
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.audit-ip {
    font-size: 0.75rem;
    color: #9ca3af;
    font-family: monospace;
}

/* Pagination */
.audit-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px;
    border-top: 1px solid rgba(229, 231, 235, 0.5);
}

[data-theme="dark"] .audit-pagination {
    border-top-color: rgba(255, 255, 255, 0.1);
}

.audit-page-link {
    padding: 8px 14px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #6366f1;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.15s;
}

.audit-page-link:hover {
    background: rgba(99, 102, 241, 0.2);
}

.audit-page-link.active {
    background: #6366f1;
    color: white;
    border-color: #6366f1;
}

.audit-page-link.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Empty State */
.audit-empty {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.audit-empty-icon {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .audit-container {
        padding: 100px 16px 40px 16px;
    }

    .audit-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .audit-filters {
        flex-direction: column;
    }

    .audit-filter-select, .audit-filter-input {
        width: 100%;
    }

    .audit-log-entry {
        flex-direction: column;
        gap: 12px;
    }

    .audit-meta {
        text-align: left;
        min-width: auto;
    }
}
</style>

<div class="audit-log-bg"></div>

<div class="audit-container">
    <!-- Shared Organization Utility Bar -->
    <?php include __DIR__ . '/_org-utility-bar.php'; ?>

    <div class="audit-card">
        <!-- Header -->
        <div class="audit-header">
            <h2 class="audit-title">
                <i class="fa-solid fa-shield-halved"></i>
                Audit Log
                <span class="audit-count"><?= number_format($totalCount) ?> entries</span>
            </h2>
            <div class="audit-actions">
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/audit-log/export<?= http_build_query($filters) ? '?' . http_build_query(array_filter($filters)) : '' ?>"
                   class="audit-export-btn">
                    <i class="fa-solid fa-download"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="audit-filters">
            <div class="audit-filter-group">
                <label class="audit-filter-label">Action Type</label>
                <select name="action" class="audit-filter-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actionSummary as $action): ?>
                    <option value="<?= htmlspecialchars($action['action']) ?>"
                            <?= ($filters['action'] ?? '') === $action['action'] ? 'selected' : '' ?>>
                        <?= \Nexus\Services\AuditLogService::getActionLabel($action['action']) ?> (<?= $action['count'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="audit-filter-group">
                <label class="audit-filter-label">User</label>
                <select name="user_id" class="audit-filter-select">
                    <option value="">All Users</option>
                    <?php foreach ($members as $member): ?>
                    <option value="<?= $member['user_id'] ?>"
                            <?= ($filters['userId'] ?? '') == $member['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($member['display_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="audit-filter-group">
                <label class="audit-filter-label">From Date</label>
                <input type="date" name="start_date" class="audit-filter-input"
                       value="<?= htmlspecialchars($filters['startDate'] ?? '') ?>">
            </div>

            <div class="audit-filter-group">
                <label class="audit-filter-label">To Date</label>
                <input type="date" name="end_date" class="audit-filter-input"
                       value="<?= htmlspecialchars($filters['endDate'] ?? '') ?>">
            </div>

            <button type="submit" class="audit-filter-btn">
                <i class="fa-solid fa-filter"></i> Filter
            </button>

            <?php if (array_filter($filters)): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/audit-log" class="audit-filter-clear">
                Clear
            </a>
            <?php endif; ?>
        </form>

        <!-- Log Entries -->
        <div class="audit-log-list">
            <?php if (empty($logs)): ?>
            <div class="audit-empty">
                <div class="audit-empty-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <p>No audit log entries found.</p>
            </div>
            <?php else: ?>
                <?php foreach ($logs as $log):
                    // Determine icon class
                    $iconClass = 'settings';
                    if (str_contains($log['action'], 'deposit')) $iconClass = 'deposit';
                    elseif (str_contains($log['action'], 'withdrawal')) $iconClass = 'withdrawal';
                    elseif (str_contains($log['action'], 'transfer')) $iconClass = 'transfer';
                    elseif (str_contains($log['action'], 'member')) $iconClass = 'member';
                    elseif (str_contains($log['action'], 'ownership')) $iconClass = 'ownership';
                    elseif (str_contains($log['action'], 'bulk')) $iconClass = 'bulk';

                    // Icon
                    $icons = [
                        'deposit' => 'fa-arrow-down',
                        'withdrawal' => 'fa-arrow-up',
                        'transfer' => 'fa-exchange-alt',
                        'member' => 'fa-user',
                        'settings' => 'fa-cog',
                        'ownership' => 'fa-crown',
                        'bulk' => 'fa-layer-group',
                    ];
                    $icon = $icons[$iconClass] ?? 'fa-circle';
                ?>
                <div class="audit-log-entry">
                    <div class="audit-icon <?= $iconClass ?>">
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="audit-content">
                        <div class="audit-action-label">
                            <?= \Nexus\Services\AuditLogService::getActionLabel($log['action']) ?>
                        </div>
                        <div class="audit-details">
                            <?php if ($log['user_name']): ?>
                            <span class="audit-user"><?= htmlspecialchars($log['user_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($log['target_user_name']): ?>
                            <i class="fa-solid fa-arrow-right" style="font-size: 0.7rem; color: #9ca3af; margin: 0 4px;"></i>
                            <span class="audit-target"><?= htmlspecialchars($log['target_user_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($log['details'])): ?>
                            <div style="margin-top: 6px;">
                                <?php foreach ($log['details'] as $key => $value):
                                    if (is_array($value)) $value = json_encode($value);
                                    if ($value === null || $value === '') continue;
                                ?>
                                <span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?>: <?= htmlspecialchars($value) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="audit-meta">
                        <div class="audit-time">
                            <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                            <small><?= date('g:i A', strtotime($log['created_at'])) ?></small>
                        </div>
                        <?php if ($log['ip_address']): ?>
                        <div class="audit-ip" title="IP Address">
                            <i class="fa-solid fa-globe"></i> <?= htmlspecialchars($log['ip_address']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="audit-pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>"
               class="audit-page-link">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
            <a href="?page=<?= $i ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>"
               class="audit-page-link <?= $i === $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>"
               class="audit-page-link">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
