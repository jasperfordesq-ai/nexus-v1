<?php
/**
 * Admin Groups Management - Gold Standard
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Groups';
$adminPageSubtitle = 'Manage community groups and hubs';
$adminPageIcon = 'fa-layer-group';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-layer-group"></i>
            Group Management
        </h1>
        <p class="admin-page-subtitle">Manage all community groups and hubs</p>
    </div>
    <div class="admin-page-header-actions">
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
        <a href="<?= $basePath ?>/admin/groups/export" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-download"></i>
            Export
        </a>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="admin-stats-grid">
    <!-- Total Groups -->
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users-rectangle"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalGroups ?? 0) ?></div>
            <div class="admin-stat-label">Total Groups</div>
        </div>
        <div class="admin-stat-trend">
            <span>Active</span>
        </div>
    </div>

    <!-- Featured Groups -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-star"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($featuredCount ?? 0) ?></div>
            <div class="admin-stat-label">Featured</div>
        </div>
        <div class="admin-stat-trend">
            <span>Promoted</span>
        </div>
    </div>

    <!-- Hub Groups -->
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-map-pin"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($hubCount ?? 0) ?></div>
            <div class="admin-stat-label">Hubs</div>
        </div>
        <div class="admin-stat-trend">
            <span>Locations</span>
        </div>
    </div>

    <!-- Total Members -->
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalMembers ?? 0) ?></div>
            <div class="admin-stat-label">Total Members</div>
        </div>
        <div class="admin-stat-trend">
            <span>Community</span>
        </div>
    </div>
</div>

<!-- Actionable Alerts Section -->
<?php
$pendingApprovals = $pendingApprovals ?? 0;
$pendingFlags = $pendingFlags ?? 0;
$hasAlerts = $pendingApprovals > 0 || $pendingFlags > 0;
?>
<?php if ($hasAlerts): ?>
<div class="admin-alerts-container">
    <?php if ($pendingApprovals > 0): ?>
    <div class="admin-alert admin-alert-warning">
        <div class="admin-alert-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-alert-content">
            <div class="admin-alert-title"><?= $pendingApprovals ?> Group<?= $pendingApprovals > 1 ? 's' : '' ?> Pending Approval</div>
            <div class="admin-alert-text">New group creation requests require your review</div>
        </div>
        <a href="<?= $basePath ?>/admin/groups/approvals" class="admin-btn admin-btn-warning">
            <i class="fa-solid fa-arrow-right"></i> Review
        </a>
    </div>
    <?php endif; ?>

    <?php if ($pendingFlags > 0): ?>
    <div class="admin-alert admin-alert-danger">
        <div class="admin-alert-icon">
            <i class="fa-solid fa-flag"></i>
        </div>
        <div class="admin-alert-content">
            <div class="admin-alert-title"><?= $pendingFlags ?> Flagged Item<?= $pendingFlags > 1 ? 's' : '' ?></div>
            <div class="admin-alert-text">Content flagged by users needs moderation</div>
        </div>
        <a href="<?= $basePath ?>/admin/groups/moderation" class="admin-btn admin-btn-danger">
            <i class="fa-solid fa-arrow-right"></i> Moderate
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Module Grid - Quick Access -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-grid-2"></i>
        Groups Management Modules
    </h2>
    <p class="admin-section-subtitle">Quick access to all group administration functions</p>
</div>

<div class="admin-modules-grid">
    <!-- Analytics -->
    <a href="<?= $basePath ?>/admin/groups/analytics" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-cyan">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Analytics</h4>
            <p class="admin-module-desc">Growth metrics & insights</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <!-- Settings -->
    <a href="<?= $basePath ?>/admin/groups/settings" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-indigo">
            <i class="fa-solid fa-gear"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Settings</h4>
            <p class="admin-module-desc">Configure module behavior</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <!-- Policies -->
    <a href="<?= $basePath ?>/admin/groups/policies" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-violet">
            <i class="fa-solid fa-file-contract"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Policies</h4>
            <p class="admin-module-desc">Rules & regulations</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <!-- Moderation -->
    <a href="<?= $basePath ?>/admin/groups/moderation" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-red">
            <i class="fa-solid fa-flag"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Moderation</h4>
            <p class="admin-module-desc">Review flagged content</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <!-- Approvals -->
    <a href="<?= $basePath ?>/admin/groups/approvals" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-orange">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Approvals</h4>
            <p class="admin-module-desc">Pending group requests</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>
</div>

<!-- Success Messages -->
<?php if (isset($_GET['featured'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Success!</div>
        <div class="admin-alert-text">Group featured status updated.</div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Success!</div>
        <div class="admin-alert-text">Group deleted successfully.</div>
    </div>
</div>
<?php endif; ?>

<!-- All Groups Section -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-list"></i>
        All Groups
    </h2>
    <p class="admin-section-subtitle">
        <?php if (($totalPages ?? 1) > 1): ?>
            Showing <?= (($currentPage ?? 1) - 1) * 20 + 1 ?>-<?= min(($currentPage ?? 1) * 20, $totalGroups ?? 0) ?> of <?= number_format($totalGroups ?? 0) ?> groups
        <?php else: ?>
            <?= number_format($totalGroups ?? 0) ?> group<?= ($totalGroups ?? 0) !== 1 ? 's' : '' ?> total
        <?php endif; ?>
    </p>
</div>

<!-- Groups Table Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Groups Directory</h3>
            <p class="admin-card-subtitle">Browse and manage all community groups</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (!empty($groups)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Group Details</th>
                        <th class="hide-mobile">Type</th>
                        <th class="hide-tablet" style="text-align: center;">Members</th>
                        <th class="hide-tablet" style="text-align: center;">Sub-Groups</th>
                        <th class="hide-tablet" style="text-align: center;">Featured</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                    <tr>
                        <td>
                            <div class="admin-group-cell">
                                <div class="admin-group-info">
                                    <div class="admin-group-name">
                                        <?= htmlspecialchars($group['name']) ?>
                                        <?php if ($group['is_featured']): ?>
                                            <span style="color: #fbbf24; margin-left: 8px;"><i class="fa-solid fa-star"></i></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-group-meta">
                                        <?php if ($group['location']): ?>
                                            <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($group['location']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($group['parent_id']): ?>
                                            <span><i class="fa-solid fa-arrow-right"></i> Sub-group</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <?php if ($group['is_hub']): ?>
                                <span class="admin-badge admin-badge-danger">
                                    <i class="fa-solid fa-map-pin"></i> Hub
                                </span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-primary">
                                    <?= htmlspecialchars($group['type_name'] ?? 'Community') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-tablet" style="text-align: center;">
                            <span class="admin-count-badge">
                                <i class="fa-solid fa-users"></i> <?= $group['member_count'] ?>
                            </span>
                        </td>
                        <td class="hide-tablet" style="text-align: center;">
                            <?php if ($group['child_count'] > 0): ?>
                                <span class="admin-count-badge">
                                    <i class="fa-solid fa-sitemap"></i> <?= $group['child_count'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.3);">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-tablet" style="text-align: center;">
                            <form action="<?= $basePath ?>/admin/groups/toggle-featured" method="POST" style="display:inline;">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                <button type="submit" class="admin-toggle-btn" title="<?= $group['is_featured'] ? 'Remove from featured' : 'Mark as featured' ?>">
                                    <?php if ($group['is_featured']): ?>
                                        <i class="fa-solid fa-star" style="color: #fbbf24;"></i>
                                    <?php else: ?>
                                        <i class="fa-regular fa-star" style="color: rgba(255,255,255,0.4);"></i>
                                    <?php endif; ?>
                                </button>
                            </form>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" target="_blank">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                                <form action="<?= $basePath ?>/admin/groups/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this group? This action cannot be undone.');" style="display:inline;">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <h3 class="admin-empty-title">No groups found</h3>
            <p class="admin-empty-text">No groups have been created yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if (($totalPages ?? 1) > 1): ?>
<div style="display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 24px; padding: 20px;">
    <?php
    $currentPage = $currentPage ?? 1;
    $totalPages = $totalPages ?? 1;
    $prevPage = max(1, $currentPage - 1);
    $nextPage = min($totalPages, $currentPage + 1);

    // Build query string preserving filters
    $queryParams = [];
    if (!empty($search)) $queryParams['search'] = $search;
    if (!empty($typeFilter)) $queryParams['type'] = $typeFilter;
    if (!empty($statusFilter)) $queryParams['status'] = $statusFilter;
    $baseQuery = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
    ?>

    <?php if ($currentPage > 1): ?>
        <a href="?page=<?= $prevPage ?><?= $baseQuery ?>"
           class="admin-btn admin-btn-secondary admin-btn-sm">
            <i class="fa-solid fa-chevron-left"></i> Previous
        </a>
    <?php endif; ?>

    <span style="color: rgba(255,255,255,0.7); font-weight: 500;">
        Page <?= $currentPage ?> of <?= $totalPages ?>
        <span style="color: rgba(255,255,255,0.5);">(<?= number_format($totalGroups ?? 0) ?> total)</span>
    </span>

    <?php if ($currentPage < $totalPages): ?>
        <a href="?page=<?= $nextPage ?><?= $baseQuery ?>"
           class="admin-btn admin-btn-secondary admin-btn-sm">
            Next <i class="fa-solid fa-chevron-right"></i>
        </a>
    <?php endif; ?>

    <!-- Jump to page -->
    <?php if ($totalPages > 2): ?>
        <div style="display: flex; align-items: center; gap: 8px; margin-left: 20px;">
            <span style="color: rgba(255,255,255,0.5); font-size: 0.875rem;">Go to:</span>
            <select onchange="window.location.href='?page=' + this.value + '<?= $baseQuery ?>'"
                    class="admin-form-control"
                    style="width: auto; min-width: 80px; padding: 6px 12px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === $currentPage ? 'selected' : '' ?>>
                        <?= $i ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
/**
 * Groups Admin - Gold Standard Styles
 * Supplements shared admin styles from admin-header.php
 */

/* Page Header */
.admin-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.admin-page-title i {
    color: #a855f7;
}

.admin-page-subtitle {
    color: rgba(255, 255, 255, 0.6);
    margin: 0.25rem 0 0 0;
    font-size: 0.9rem;
}

.admin-page-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #06b6d4, #3b82f6);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 4px 20px rgba(6, 182, 212, 0.3);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.2);
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #000;
    font-weight: 600;
}

.admin-btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    font-weight: 600;
}

/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .admin-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .admin-stats-grid {
        grid-template-columns: 1fr;
    }
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-orange { --stat-color: #f59e0b; }
.admin-stat-purple { --stat-color: #a855f7; }

.admin-stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.admin-stat-content {
    flex: 1;
}

.admin-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.admin-stat-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.admin-stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
}

/* Alerts Container */
.admin-alerts-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}

/* Alert Banner */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    flex: 1;
    min-width: 280px;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-alert-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.admin-alert-success .admin-alert-icon {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.admin-alert-warning .admin-alert-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.admin-alert-danger .admin-alert-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.admin-alert-content {
    flex: 1;
    min-width: 0;
}

.admin-alert-title {
    font-weight: 600;
    font-size: 0.95rem;
}

.admin-alert-success .admin-alert-title { color: #22c55e; }
.admin-alert-warning .admin-alert-title { color: #f59e0b; }
.admin-alert-danger .admin-alert-title { color: #ef4444; }

.admin-alert-text {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Section Header */
.admin-section-header {
    margin-bottom: 1.5rem;
}

.admin-section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-section-title i {
    color: #8b5cf6;
}

.admin-section-subtitle {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    margin: 0.25rem 0 0 0;
}

/* Modules Grid */
.admin-modules-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 1400px) {
    .admin-modules-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1000px) {
    .admin-modules-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .admin-modules-grid {
        grid-template-columns: 1fr;
    }
}

.admin-module-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.admin-module-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

.admin-module-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-module-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-module-icon-indigo { background: rgba(99, 102, 241, 0.2); color: #6366f1; }
.admin-module-icon-violet { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-module-icon-red { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
.admin-module-icon-orange { background: rgba(249, 115, 22, 0.2); color: #f97316; }

.admin-module-content {
    flex: 1;
    min-width: 0;
}

.admin-module-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.admin-module-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.125rem 0 0 0;
}

.admin-module-arrow {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.admin-module-card:hover .admin-module-arrow {
    color: #06b6d4;
    transform: translateX(4px);
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 2rem;
}

.admin-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-card-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.125rem 0 0 0;
}

.admin-card-body {
    padding: 1.25rem 1.5rem;
}

/* Group Management Specific Styles */
.admin-group-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-group-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.admin-group-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
}

.admin-group-meta {
    display: flex;
    gap: 12px;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-group-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.admin-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    font-size: 0.8rem;
    font-weight: 600;
}

.admin-toggle-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 1.1rem;
    transition: transform 0.2s;
}

.admin-toggle-btn:hover {
    transform: scale(1.2);
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
}

.admin-empty-text {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
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

    .admin-action-buttons {
        flex-direction: column;
    }

    .admin-alerts-container {
        flex-direction: column;
    }

    .admin-alert {
        min-width: unset;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
