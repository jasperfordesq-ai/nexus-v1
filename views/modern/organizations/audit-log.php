<?php
// Phoenix View: Organization Audit Log
// Path: views/modern/organizations/audit-log.php

$hTitle = $org['name'] . ' - Audit Log';
$hSubtitle = 'Activity History & Security Audit';
$hideHero = true;

$activeTab = 'audit';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>


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
