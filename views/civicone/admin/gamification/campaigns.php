<?php
/**
 * Achievement Campaigns - Gold Standard Mission Control
 * STANDALONE admin interface - does NOT use main site header/footer
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Campaigns';
$adminPageSubtitle = 'Gamification';
$adminPageIcon = 'fa-bullhorn';

// Include the standalone admin header (includes <!DOCTYPE html>, <head>, etc.)
require dirname(__DIR__) . '/partials/admin-header.php';

$campaigns = $campaigns ?? [];
$badges = $badges ?? [];

// Calculate stats
$activeCampaigns = count(array_filter($campaigns, fn($c) => ($c['status'] ?? '') === 'active'));
$pausedCampaigns = count(array_filter($campaigns, fn($c) => ($c['status'] ?? '') === 'paused'));
$totalAwards = array_sum(array_column($campaigns, 'total_awards'));
$draftCampaigns = count(array_filter($campaigns, fn($c) => ($c['status'] ?? '') === 'draft'));
$recurringCampaigns = count(array_filter($campaigns, fn($c) => ($c['type'] ?? '') === 'recurring'));
$totalCampaigns = count($campaigns);

// Get recent campaigns for sidebar
$recentCampaigns = array_slice($campaigns, 0, 5);
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-bullhorn"></i>
            Achievement Campaigns
        </h1>
        <p class="admin-page-subtitle">Create and manage bulk badge and XP award campaigns</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/gamification" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
        <a href="<?= $basePath ?>/admin/gamification/campaigns/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i>
            New Campaign
        </a>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="admin-stats-grid">
    <!-- Active Campaigns -->
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-play"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $activeCampaigns ?></div>
            <div class="admin-stat-label">Active Campaigns</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-bolt"></i>
            <span>Running</span>
        </div>
    </div>

    <!-- Total Awards -->
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalAwards) ?></div>
            <div class="admin-stat-label">Total Awards Given</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-medal"></i>
            <span>Earned</span>
        </div>
    </div>

    <!-- Draft Campaigns -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-file-pen"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $draftCampaigns ?></div>
            <div class="admin-stat-label">Draft Campaigns</div>
        </div>
        <div class="admin-stat-trend <?= $draftCampaigns > 0 ? 'admin-stat-trend-warning' : '' ?>">
            <i class="fa-solid fa-clock"></i>
            <span>Pending</span>
        </div>
    </div>

    <!-- Recurring Campaigns -->
    <div class="admin-stat-card admin-stat-pink">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-repeat"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $recurringCampaigns ?></div>
            <div class="admin-stat-label">Recurring</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-calendar"></i>
            <span>Scheduled</span>
        </div>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_GET['saved'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Campaign Saved</div>
        <div class="admin-alert-text">Your campaign has been saved successfully</div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['activated'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-play"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Campaign Activated</div>
        <div class="admin-alert-text">The campaign is now live and ready to run</div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['paused'])): ?>
<div class="admin-alert admin-alert-warning">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-pause"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Campaign Paused</div>
        <div class="admin-alert-text">The campaign has been paused</div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-trash"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Campaign Deleted</div>
        <div class="admin-alert-text">The campaign has been permanently removed</div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['run'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-bolt"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Campaign Executed!</div>
        <div class="admin-alert-text"><?= (int)($_GET['awarded'] ?? 0) ?> award(s) given to users</div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Grid -->
<div class="admin-dashboard-grid">
    <!-- Left Column - Campaign List -->
    <div class="admin-dashboard-main">

        <!-- All Campaigns -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">All Campaigns</h3>
                    <p class="admin-card-subtitle"><?= $totalCampaigns ?> total campaigns configured</p>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($campaigns)): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-icon">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <h4 class="admin-empty-title">No Campaigns Yet</h4>
                    <p>Create your first campaign to award badges or XP to multiple users at once.</p>
                    <a href="<?= $basePath ?>/admin/gamification/campaigns/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                        <i class="fa-solid fa-plus"></i> Create First Campaign
                    </a>
                </div>
                <?php else: ?>
                <div class="campaigns-list">
                    <?php foreach ($campaigns as $campaign): ?>
                    <div class="campaign-card">
                        <div class="campaign-header">
                            <div class="campaign-info">
                                <div class="campaign-badges">
                                    <span class="campaign-status status-<?= htmlspecialchars($campaign['status'] ?? 'draft') ?>">
                                        <?php
                                        $statusIcon = match($campaign['status'] ?? 'draft') {
                                            'active' => 'fa-play',
                                            'paused' => 'fa-pause',
                                            'completed' => 'fa-check',
                                            default => 'fa-file-pen'
                                        };
                                        ?>
                                        <i class="fa-solid <?= $statusIcon ?>"></i>
                                        <?= ucfirst($campaign['status'] ?? 'draft') ?>
                                    </span>
                                    <span class="campaign-type type-<?= htmlspecialchars($campaign['type'] ?? 'one_time') ?>">
                                        <?php
                                        $typeIcon = match($campaign['type'] ?? 'one_time') {
                                            'recurring' => 'fa-repeat',
                                            'triggered' => 'fa-bolt',
                                            default => 'fa-circle-dot'
                                        };
                                        ?>
                                        <i class="fa-solid <?= $typeIcon ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $campaign['type'] ?? 'one time')) ?>
                                    </span>
                                </div>

                                <h4 class="campaign-title"><?= htmlspecialchars($campaign['name'] ?? 'Untitled Campaign') ?></h4>

                                <?php if (!empty($campaign['description'])): ?>
                                <p class="campaign-desc"><?= htmlspecialchars($campaign['description']) ?></p>
                                <?php endif; ?>

                                <div class="campaign-meta">
                                    <?php if (!empty($campaign['badge_key'])): ?>
                                    <div class="meta-item">
                                        <i class="fa-solid fa-medal"></i>
                                        <span>Badge:</span>
                                        <span class="meta-value"><?= htmlspecialchars($campaign['badge_key']) ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (($campaign['xp_amount'] ?? 0) > 0): ?>
                                    <div class="meta-item">
                                        <i class="fa-solid fa-star"></i>
                                        <span class="meta-value">+<?= number_format($campaign['xp_amount']) ?> XP</span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="meta-item">
                                        <i class="fa-solid fa-users"></i>
                                        <span>Audience:</span>
                                        <span class="meta-value"><?= ucfirst(str_replace('_', ' ', $campaign['target_audience'] ?? 'all users')) ?></span>
                                    </div>

                                    <div class="meta-item">
                                        <i class="fa-solid fa-trophy"></i>
                                        <span class="meta-value"><?= number_format($campaign['total_awards'] ?? 0) ?> awarded</span>
                                    </div>

                                    <?php if (!empty($campaign['last_run_at'])): ?>
                                    <div class="meta-item">
                                        <i class="fa-solid fa-clock"></i>
                                        <span>Last run:</span>
                                        <span class="meta-value"><?= date('M j, g:i A', strtotime($campaign['last_run_at'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="campaign-actions">
                                <?php if (($campaign['status'] ?? '') === 'draft'): ?>
                                <form action="<?= $basePath ?>/admin/gamification/campaigns/activate" method="POST">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-success admin-btn-sm">
                                        <i class="fa-solid fa-play"></i> Activate
                                    </button>
                                </form>
                                <?php elseif (($campaign['status'] ?? '') === 'active'): ?>
                                <form action="<?= $basePath ?>/admin/gamification/campaigns/run" method="POST">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                                        <i class="fa-solid fa-bolt"></i> Run Now
                                    </button>
                                </form>
                                <form action="<?= $basePath ?>/admin/gamification/campaigns/pause" method="POST">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-warning admin-btn-sm">
                                        <i class="fa-solid fa-pause"></i> Pause
                                    </button>
                                </form>
                                <?php elseif (($campaign['status'] ?? '') === 'paused'): ?>
                                <form action="<?= $basePath ?>/admin/gamification/campaigns/activate" method="POST">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-success admin-btn-sm">
                                        <i class="fa-solid fa-play"></i> Resume
                                    </button>
                                </form>
                                <?php endif; ?>

                                <a href="<?= $basePath ?>/admin/gamification/campaigns/edit/<?= $campaign['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit Campaign">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>

                                <form action="<?= $basePath ?>/admin/gamification/campaigns/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this campaign? This action cannot be undone.');">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" title="Delete Campaign">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Column - Quick Actions & Info -->
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
                    <a href="<?= $basePath ?>/admin/gamification/campaigns/create" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-purple">
                            <i class="fa-solid fa-plus"></i>
                        </div>
                        <span>New Campaign</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/gamification/analytics" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-blue">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <span>Analytics</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/cron-jobs" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-orange">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <span>Cron Jobs</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/gamification" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-pink">
                            <i class="fa-solid fa-trophy"></i>
                        </div>
                        <span>Gamification Hub</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Campaign Status Overview -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-emerald">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Status Overview</h3>
                    <p class="admin-card-subtitle">Campaign breakdown</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-system-status">
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-online"></div>
                        <span class="admin-status-label">Active</span>
                        <span class="admin-status-value"><?= $activeCampaigns ?></span>
                    </div>
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-warning"></div>
                        <span class="admin-status-label">Paused</span>
                        <span class="admin-status-value" style="color: #f59e0b;"><?= $pausedCampaigns ?></span>
                    </div>
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-draft"></div>
                        <span class="admin-status-label">Draft</span>
                        <span class="admin-status-value" style="color: #94a3b8;"><?= $draftCampaigns ?></span>
                    </div>
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-recurring"></div>
                        <span class="admin-status-label">Recurring</span>
                        <span class="admin-status-value" style="color: #ec4899;"><?= $recurringCampaigns ?></span>
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

        <!-- Campaign Tips -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-amber">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Campaign Tips</h3>
                    <p class="admin-card-subtitle">Best practices</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="tips-list">
                    <div class="tip-item">
                        <div class="tip-icon tip-icon-purple">
                            <i class="fa-solid fa-circle-dot"></i>
                        </div>
                        <div class="tip-content">
                            <div class="tip-title">One-Time Campaigns</div>
                            <div class="tip-text">Perfect for special events, milestones, or retroactive awards.</div>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon tip-icon-pink">
                            <i class="fa-solid fa-repeat"></i>
                        </div>
                        <div class="tip-content">
                            <div class="tip-title">Recurring Campaigns</div>
                            <div class="tip-text">Automatically run on a schedule via cron jobs.</div>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon tip-icon-cyan">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="tip-content">
                            <div class="tip-title">Target Audiences</div>
                            <div class="tip-text">Filter by activity level, join date, or custom criteria.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Module Navigation -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-grid-2"></i>
        Gamification Modules
    </h2>
    <p class="admin-section-subtitle">Access all gamification administrative functions</p>
</div>

<div class="admin-modules-grid">
    <a href="<?= $basePath ?>/admin/gamification" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-purple">
            <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Gamification Hub</h4>
            <p class="admin-module-desc">Main dashboard</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/gamification/analytics" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-cyan">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Analytics</h4>
            <p class="admin-module-desc">Achievement insights</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/gamification/campaigns" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-pink">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Campaigns</h4>
            <p class="admin-module-desc"><?= $totalCampaigns ?> configured</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/cron-jobs" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-orange">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Cron Jobs</h4>
            <p class="admin-module-desc">Scheduled tasks</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>
</div>

<style>
/**
 * Campaigns Dashboard Specific Styles
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
    color: #8b5cf6;
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
.admin-stat-purple { --stat-color: #8b5cf6; }
.admin-stat-orange { --stat-color: #f59e0b; }
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
    margin-bottom: 1.5rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-alert-success .admin-alert-icon {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.admin-alert-success .admin-alert-title {
    color: #22c55e;
}

.admin-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-alert-warning .admin-alert-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.admin-alert-warning .admin-alert-title {
    color: #f59e0b;
}

.admin-alert-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.admin-alert-content {
    flex: 1;
}

.admin-alert-title {
    font-weight: 600;
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
    padding: 0.5rem 0.875rem;
    font-size: 0.8rem;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
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

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
}

.admin-btn-success:hover {
    box-shadow: 0 4px 20px rgba(34, 197, 94, 0.3);
    transform: translateY(-1px);
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #000;
    font-weight: 600;
}

.admin-btn-warning:hover {
    box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
    transform: translateY(-1px);
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
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

.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-card-header-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-card-header-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.admin-card-header-icon-amber { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-card-header-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }

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

/* Campaign List */
.campaigns-list {
    display: flex;
    flex-direction: column;
}

.campaign-card {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s ease;
}

.campaign-card:last-child {
    border-bottom: none;
}

.campaign-card:hover {
    background: rgba(139, 92, 246, 0.05);
}

.campaign-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.campaign-info {
    flex: 1;
    min-width: 300px;
}

.campaign-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
}

.campaign-status {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-draft {
    background: rgba(148, 163, 184, 0.2);
    color: #94a3b8;
}

.status-active {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.status-paused {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-completed {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.campaign-type {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-one_time {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

.type-recurring {
    background: rgba(236, 72, 153, 0.2);
    color: #f472b6;
}

.type-triggered {
    background: rgba(20, 184, 166, 0.2);
    color: #2dd4bf;
}

.campaign-title {
    font-size: 1.15rem;
    font-weight: 600;
    color: #f1f5f9;
    margin: 0 0 0.5rem 0;
}

.campaign-desc {
    color: #94a3b8;
    font-size: 0.875rem;
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

.campaign-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.8rem;
}

.meta-item i {
    width: 16px;
    text-align: center;
    color: #8b5cf6;
}

.meta-item .meta-value {
    color: #cbd5e1;
    font-weight: 500;
}

.campaign-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: flex-start;
}

.campaign-actions form {
    display: inline;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.admin-empty-title {
    font-size: 1.25rem;
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

.admin-quick-action-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-quick-action-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.admin-quick-action-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-quick-action-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }

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

.admin-status-draft {
    background: #94a3b8;
    box-shadow: 0 0 10px rgba(148, 163, 184, 0.3);
}

.admin-status-recurring {
    background: #ec4899;
    box-shadow: 0 0 10px rgba(236, 72, 153, 0.5);
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

/* Tips List */
.tips-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tip-item {
    display: flex;
    gap: 0.75rem;
}

.tip-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.tip-icon-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
.tip-icon-pink { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
.tip-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #22d3ee; }

.tip-content {
    flex: 1;
}

.tip-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 0.125rem;
}

.tip-text {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.4;
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
}

.admin-module-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

.admin-module-card-gradient {
    border-color: rgba(139, 92, 246, 0.3);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(236, 72, 153, 0.05));
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

.admin-module-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-module-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-module-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }

.admin-module-icon-gradient-pink {
    background: linear-gradient(135deg, #ec4899, #a855f7);
    color: white;
    box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
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

.admin-module-arrow {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.admin-module-card:hover .admin-module-arrow {
    color: #8b5cf6;
    transform: translateX(4px);
}

/* Responsive */
@media (max-width: 768px) {
    .campaign-header {
        flex-direction: column;
    }

    .campaign-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .campaign-meta {
        gap: 0.75rem;
    }

    .campaign-info {
        min-width: 100%;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
