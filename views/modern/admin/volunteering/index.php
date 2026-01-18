<?php
/**
 * Volunteering Admin Dashboard - Gold Standard Mission Control
 * STANDALONE admin interface - does NOT use main site header/footer
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$tenantId = TenantContext::getId();

// Admin header configuration
$adminPageTitle = 'Volunteering';
$adminPageSubtitle = 'Community';
$adminPageIcon = 'fa-hands-helping';

// Include the standalone admin header (includes <!DOCTYPE html>, <head>, etc.)
require dirname(__DIR__) . '/partials/admin-header.php';

// Get stats
$db = Database::getInstance();

// Total organizations
$totalOrgs = $db->prepare("SELECT COUNT(*) FROM vol_organizations WHERE tenant_id = ?");
$totalOrgs->execute([$tenantId]);
$totalOrgsCount = (int)$totalOrgs->fetchColumn();

// Approved organizations
$approvedOrgs = $db->prepare("SELECT COUNT(*) FROM vol_organizations WHERE tenant_id = ? AND status = 'approved'");
$approvedOrgs->execute([$tenantId]);
$approvedOrgsCount = (int)$approvedOrgs->fetchColumn();

// Pending organizations
$pendingOrgs = $db->prepare("SELECT COUNT(*) FROM vol_organizations WHERE tenant_id = ? AND status = 'pending'");
$pendingOrgs->execute([$tenantId]);
$pendingOrgsCount = (int)$pendingOrgs->fetchColumn();

// Total opportunities (if table exists)
$totalOpportunities = 0;
$activeOpportunities = 0;
try {
    $opps = $db->prepare("SELECT COUNT(*) FROM vol_opportunities WHERE tenant_id = ?");
    $opps->execute([$tenantId]);
    $totalOpportunities = (int)$opps->fetchColumn();

    $activeOpps = $db->prepare("SELECT COUNT(*) FROM vol_opportunities WHERE tenant_id = ? AND status = 'active'");
    $activeOpps->execute([$tenantId]);
    $activeOpportunities = (int)$activeOpps->fetchColumn();
} catch (\Exception $e) {
    // Table might not exist
}

// Total applications (if table exists)
$totalApplications = 0;
$pendingApplications = 0;
try {
    $apps = $db->prepare("SELECT COUNT(*) FROM vol_applications WHERE tenant_id = ?");
    $apps->execute([$tenantId]);
    $totalApplications = (int)$apps->fetchColumn();

    $pendingApps = $db->prepare("SELECT COUNT(*) FROM vol_applications WHERE tenant_id = ? AND status = 'pending'");
    $pendingApps->execute([$tenantId]);
    $pendingApplications = (int)$pendingApps->fetchColumn();
} catch (\Exception $e) {
    // Table might not exist
}

// Recent pending organizations
$recentPending = $db->prepare("SELECT * FROM vol_organizations WHERE tenant_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 5");
$recentPending->execute([$tenantId]);
$recentPendingOrgs = $recentPending->fetchAll();

// Recent approved organizations
$recentApproved = $db->prepare("SELECT * FROM vol_organizations WHERE tenant_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 5");
$recentApproved->execute([$tenantId]);
$recentApprovedOrgs = $recentApproved->fetchAll();

// Recent activity (last 10 org changes)
$recentActivity = $db->prepare("SELECT *,
    CASE
        WHEN status = 'approved' THEN 'Organization approved'
        WHEN status = 'pending' THEN 'New registration'
        WHEN status = 'declined' THEN 'Organization declined'
        ELSE 'Status updated'
    END as action_text
    FROM vol_organizations
    WHERE tenant_id = ?
    ORDER BY created_at DESC
    LIMIT 8");
$recentActivity->execute([$tenantId]);
$activityLogs = $recentActivity->fetchAll();
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-hands-helping"></i>
            Volunteering Hub
        </h1>
        <p class="admin-page-subtitle">Manage organizations, opportunities, and volunteer engagement</p>
    </div>
    <div class="admin-page-header-actions">
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
        <a href="<?= $basePath ?>/admin/volunteering/organizations" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-building"></i>
            All Organizations
        </a>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="admin-stats-grid">
    <!-- Total Organizations -->
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalOrgsCount) ?></div>
            <div class="admin-stat-label">Organizations</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-check"></i>
            <span><?= $approvedOrgsCount ?> Active</span>
        </div>
    </div>

    <!-- Pending Approvals -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($pendingOrgsCount) ?></div>
            <div class="admin-stat-label">Pending Review</div>
        </div>
        <div class="admin-stat-trend <?= $pendingOrgsCount > 0 ? 'admin-stat-trend-warning' : '' ?>">
            <i class="fa-solid fa-clock"></i>
            <span>Awaiting</span>
        </div>
    </div>

    <!-- Opportunities -->
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalOpportunities) ?></div>
            <div class="admin-stat-label">Opportunities</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-bolt"></i>
            <span><?= $activeOpportunities ?> Active</span>
        </div>
    </div>

    <!-- Applications -->
    <div class="admin-stat-card admin-stat-pink">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalApplications) ?></div>
            <div class="admin-stat-label">Applications</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-users"></i>
            <span>Volunteers</span>
        </div>
    </div>
</div>

<!-- Pending Approvals Alert -->
<?php if ($pendingOrgsCount > 0): ?>
<div class="admin-alert admin-alert-warning">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-building-circle-exclamation"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title"><?= $pendingOrgsCount ?> Organization<?= $pendingOrgsCount > 1 ? 's' : '' ?> Pending Approval</div>
        <div class="admin-alert-text">New volunteer organizations require your review before they can post opportunities</div>
    </div>
    <a href="<?= $basePath ?>/admin/volunteering/approvals" class="admin-btn admin-btn-warning">
        Review Now
    </a>
</div>
<?php endif; ?>

<!-- Main Content Grid -->
<div class="admin-dashboard-grid">
    <!-- Left Column - Activity & Pending -->
    <div class="admin-dashboard-main">

        <!-- Pending Approvals -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-orange">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Pending Approvals</h3>
                    <p class="admin-card-subtitle">Organizations awaiting review</p>
                </div>
                <?php if ($pendingOrgsCount > 0): ?>
                <a href="<?= $basePath ?>/admin/volunteering/approvals" class="admin-card-header-action">
                    View All <i class="fa-solid fa-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="admin-card-body">
                <?php if (!empty($recentPendingOrgs)): ?>
                <div class="admin-activity-list">
                    <?php foreach ($recentPendingOrgs as $org): ?>
                    <div class="admin-activity-item">
                        <div class="admin-activity-avatar admin-activity-avatar-pending">
                            <?= strtoupper(substr($org['name'], 0, 1)) ?>
                        </div>
                        <div class="admin-activity-content">
                            <div class="admin-activity-main">
                                <span class="admin-activity-user"><?= htmlspecialchars($org['name']) ?></span>
                            </div>
                            <?php if (!empty($org['contact_email'])): ?>
                            <div class="admin-activity-details"><?= htmlspecialchars($org['contact_email']) ?></div>
                            <?php endif; ?>
                            <div class="admin-activity-time">
                                <i class="fa-regular fa-clock"></i>
                                Submitted <?= date('M j, Y', strtotime($org['created_at'])) ?>
                            </div>
                        </div>
                        <div class="admin-activity-actions">
                            <form action="<?= $basePath ?>/admin/volunteering/approve" method="POST" style="display:inline;">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                <button type="submit" class="admin-btn admin-btn-success admin-btn-sm" title="Approve">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                            <form action="<?= $basePath ?>/admin/volunteering/decline" method="POST" style="display:inline;">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" title="Decline" onclick="return confirm('Decline this organization?');">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-icon">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <h4 class="admin-empty-title">All Caught Up!</h4>
                    <p>No pending organizations to review</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-cyan">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Recent Activity</h3>
                    <p class="admin-card-subtitle">Latest organization updates</p>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (!empty($activityLogs)): ?>
                <div class="admin-activity-list">
                    <?php foreach ($activityLogs as $log): ?>
                    <div class="admin-activity-item">
                        <div class="admin-activity-avatar admin-activity-avatar-<?= $log['status'] ?? 'pending' ?>">
                            <?php
                            $icon = match($log['status'] ?? 'pending') {
                                'approved' => 'fa-check',
                                'declined' => 'fa-times',
                                default => 'fa-clock'
                            };
                            ?>
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        <div class="admin-activity-content">
                            <div class="admin-activity-main">
                                <span class="admin-activity-user"><?= htmlspecialchars($log['name']) ?></span>
                                <span class="admin-activity-action"><?= $log['action_text'] ?></span>
                            </div>
                            <div class="admin-activity-time">
                                <i class="fa-regular fa-clock"></i>
                                <?= date('M j, g:i A', strtotime($log['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-icon">
                        <i class="fa-solid fa-inbox"></i>
                    </div>
                    <p>No recent activity to display</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Column - Quick Actions & Recent Orgs -->
    <div class="admin-dashboard-sidebar">

        <!-- Quick Actions -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-green">
                    <i class="fa-solid fa-rocket"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Quick Actions</h3>
                    <p class="admin-card-subtitle">Common tasks</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-quick-actions">
                    <a href="<?= $basePath ?>/admin/volunteering/approvals" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-orange">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <span>Review Approvals</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/volunteering/organizations" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-green">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <span>All Organizations</span>
                    </a>
                    <a href="<?= $basePath ?>/volunteering" class="admin-quick-action" target="_blank">
                        <div class="admin-quick-action-icon admin-quick-action-icon-blue">
                            <i class="fa-solid fa-external-link"></i>
                        </div>
                        <span>View Public Page</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/categories" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-purple">
                            <i class="fa-solid fa-folder-tree"></i>
                        </div>
                        <span>Manage Categories</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Active Organizations -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-emerald">
                    <i class="fa-solid fa-building-circle-check"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Active Organizations</h3>
                    <p class="admin-card-subtitle">Recently approved</p>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (!empty($recentApprovedOrgs)): ?>
                <div class="admin-enterprise-links">
                    <?php foreach (array_slice($recentApprovedOrgs, 0, 5) as $org): ?>
                    <a href="<?= $basePath ?>/volunteering/org/edit/<?= $org['id'] ?>" class="admin-enterprise-link">
                        <div class="org-avatar-mini"><?= strtoupper(substr($org['name'], 0, 1)) ?></div>
                        <span><?= htmlspecialchars($org['name']) ?></span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="admin-empty-state" style="padding: 2rem 1rem;">
                    <div class="admin-empty-icon" style="font-size: 2rem;">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <p style="margin: 0;">No approved organizations yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Status -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Module Status</h3>
                    <p class="admin-card-subtitle">Volunteering health</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-system-status">
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-online"></div>
                        <span class="admin-status-label">Organizations</span>
                        <span class="admin-status-value"><?= $approvedOrgsCount ?> Active</span>
                    </div>
                    <div class="admin-status-item">
                        <div class="admin-status-indicator <?= $pendingOrgsCount > 0 ? 'admin-status-warning' : 'admin-status-online' ?>"></div>
                        <span class="admin-status-label">Approvals</span>
                        <span class="admin-status-value" style="<?= $pendingOrgsCount > 0 ? 'color: #f59e0b;' : '' ?>"><?= $pendingOrgsCount ?> Pending</span>
                    </div>
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-online"></div>
                        <span class="admin-status-label">Opportunities</span>
                        <span class="admin-status-value"><?= $activeOpportunities ?> Live</span>
                    </div>
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-online"></div>
                        <span class="admin-status-label">Applications</span>
                        <span class="admin-status-value"><?= $totalApplications ?> Total</span>
                    </div>
                </div>
                <div class="admin-status-footer">
                    <span class="admin-status-timestamp">
                        <i class="fa-regular fa-clock"></i>
                        Last checked: <?= date('H:i:s') ?>
                    </span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Module Navigation -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-grid-2"></i>
        Volunteering Modules
    </h2>
    <p class="admin-section-subtitle">Access all volunteering administrative functions</p>
</div>

<div class="admin-modules-grid">
    <a href="<?= $basePath ?>/admin/volunteering/approvals" class="admin-module-card <?= $pendingOrgsCount > 0 ? 'admin-module-card-gradient' : '' ?>">
        <div class="admin-module-icon <?= $pendingOrgsCount > 0 ? 'admin-module-icon-gradient-orange' : 'admin-module-icon-orange' ?>">
            <i class="fa-solid fa-clipboard-check"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Org Approvals</h4>
            <p class="admin-module-desc"><?= $pendingOrgsCount ?> pending review</p>
        </div>
        <?php if ($pendingOrgsCount > 0): ?>
        <span class="admin-module-badge"><?= $pendingOrgsCount ?></span>
        <?php endif; ?>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/volunteering/organizations" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-emerald">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Organizations</h4>
            <p class="admin-module-desc"><?= $approvedOrgsCount ?> active organizations</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/volunteering" class="admin-module-card" target="_blank">
        <div class="admin-module-icon admin-module-icon-cyan">
            <i class="fa-solid fa-globe"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Public Portal</h4>
            <p class="admin-module-desc">View volunteer listings</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/categories" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-violet">
            <i class="fa-solid fa-tags"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Categories</h4>
            <p class="admin-module-desc">Manage opportunity types</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>
</div>

<style>
/**
 * Volunteering Dashboard Specific Styles
 * These supplement the shared admin styles from admin-header.php
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
    color: #10b981;
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

.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-orange { --stat-color: #f59e0b; }
.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-pink { --stat-color: #ec4899; }

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

.admin-stat-trend-up {
    color: #22c55e;
    background: rgba(34, 197, 94, 0.1);
}

.admin-stat-trend-warning {
    color: #f59e0b;
    background: rgba(245, 158, 11, 0.1);
}

/* Alert Banner */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.admin-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-alert-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.admin-alert-content {
    flex: 1;
}

.admin-alert-title {
    font-weight: 600;
    color: #f59e0b;
}

.admin-alert-text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
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

.admin-btn-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
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

.admin-btn-success {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-btn-success:hover {
    background: rgba(34, 197, 94, 0.3);
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.3);
}

/* Dashboard Grid Layout */
.admin-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    margin-bottom: 3rem;
}

@media (max-width: 1200px) {
    .admin-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

.admin-dashboard-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.admin-dashboard-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    overflow: hidden;
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

.admin-card-header-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-card-header-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-card-header-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-card-header-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }
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

.admin-card-header-action {
    font-size: 0.8rem;
    color: #06b6d4;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s;
}

.admin-card-header-action:hover {
    color: #22d3ee;
}

.admin-card-body {
    padding: 1.25rem 1.5rem;
}

/* Activity List */
.admin-activity-list {
    display: flex;
    flex-direction: column;
}

.admin-activity-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    align-items: center;
}

.admin-activity-item:last-child {
    border-bottom: none;
}

.admin-activity-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-weight: 700;
    font-size: 1rem;
}

.admin-activity-avatar-pending {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.admin-activity-avatar-approved {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.admin-activity-avatar-declined {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.admin-activity-content {
    flex: 1;
    min-width: 0;
}

.admin-activity-main {
    font-size: 0.9rem;
}

.admin-activity-user {
    font-weight: 600;
    color: #fff;
}

.admin-activity-action {
    color: rgba(255, 255, 255, 0.6);
    margin-left: 0.375rem;
}

.admin-activity-details {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.25rem;
}

.admin-activity-time {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.admin-activity-actions {
    display: flex;
    gap: 0.5rem;
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
    color: #22c55e;
}

.admin-empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

/* Quick Actions */
.admin-quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.admin-quick-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
    transition: all 0.2s;
}

.admin-quick-action:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.admin-quick-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.admin-quick-action-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-quick-action-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-quick-action-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.admin-quick-action-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }

/* Enterprise Links (used for org list) */
.admin-enterprise-links {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.admin-enterprise-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.admin-enterprise-link:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-enterprise-link span {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-enterprise-link i:last-child {
    font-size: 0.7rem;
    opacity: 0.5;
}

.org-avatar-mini {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.75rem;
    flex-shrink: 0;
}

/* System Status */
.admin-system-status {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.admin-status-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
}

.admin-status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.admin-status-online {
    background: #22c55e;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
}

.admin-status-warning {
    background: #f59e0b;
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
}

.admin-status-label {
    flex: 1;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

.admin-status-value {
    color: #22c55e;
    font-size: 0.8rem;
    font-weight: 500;
}

.admin-status-footer {
    padding-top: 0.75rem;
    margin-top: 0.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-status-timestamp {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    display: flex;
    align-items: center;
    gap: 0.375rem;
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
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
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
    position: relative;
}

.admin-module-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

.admin-module-card-gradient {
    border-color: rgba(245, 158, 11, 0.3);
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(234, 88, 12, 0.05));
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

.admin-module-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-module-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.admin-module-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-module-icon-violet { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }

.admin-module-icon-gradient-orange {
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    color: white;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
}

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

.admin-module-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
}

.admin-module-arrow {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.admin-module-card:hover .admin-module-arrow {
    color: #10b981;
    transform: translateX(4px);
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
