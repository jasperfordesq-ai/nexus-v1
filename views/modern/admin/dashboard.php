<?php
/**
 * Admin Dashboard - Gold Standard Mission Control
 * STANDALONE admin interface - does NOT use main site header/footer
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Safe Stats Initialization
if (!isset($stats) || !is_array($stats)) {
    $stats = [
        'total_users' => 0,
        'total_listings' => 0,
        'total_transactions' => 0,
        'total_volume' => 0,
        'monthly_stats' => []
    ];
}

// Admin header configuration
$adminPageTitle = 'Mission Control';
$adminPageSubtitle = 'Mission Control';
$adminPageIcon = 'fa-satellite-dish';

// Include the standalone admin header (includes <!DOCTYPE html>, <head>, etc.)
require __DIR__ . '/partials/admin-header.php';
?>

<!-- Dashboard Header -->
<div class="admin-page-header" id="tour-dashboard">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-satellite-dish"></i>
            Mission Control
        </h1>
        <p class="admin-page-subtitle">Real-time platform overview and command center</p>
    </div>
    <div class="admin-page-header-actions">
        <button class="admin-btn admin-btn-tour" onclick="AdminTour.start()" title="Take a tour">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>Tour</span>
        </button>
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
        <a href="<?= $basePath ?>/admin/activity-log" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-list-ul"></i>
            View All Activity
        </a>
    </div>
</div>

<!-- Admin Onboarding Tour Elements -->
<div class="admin-tour-backdrop" id="adminTourBackdrop"></div>
<div class="admin-tour-tooltip" id="adminTourTooltip">
    <div class="admin-tour-tooltip-header">
        <span class="admin-tour-step-badge" id="tourStepBadge">1/6</span>
        <button class="admin-tour-close" onclick="AdminTour.end()"><i class="fa-solid fa-times"></i></button>
    </div>
    <h4 class="admin-tour-title" id="tourTitle">Welcome to Mission Control!</h4>
    <p class="admin-tour-description" id="tourDescription">This is your command center for managing your community platform. Let's take a quick tour of the key features.</p>
    <div class="admin-tour-actions">
        <button class="admin-tour-btn admin-tour-btn-secondary" id="tourPrev" onclick="AdminTour.prev()">
            <i class="fa-solid fa-arrow-left"></i> Back
        </button>
        <button class="admin-tour-btn admin-tour-btn-primary" id="tourNext" onclick="AdminTour.next()">
            Next <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="admin-stats-grid" id="tour-stats">
    <!-- Users Online (Live) -->
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-signal"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value" data-stat="users-online">0</div>
            <div class="admin-stat-label">Users Online</div>
        </div>
        <div class="admin-stat-trend">
            <span class="admin-live-indicator">LIVE</span>
        </div>
    </div>

    <!-- Active Sessions (Live) -->
    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-plug"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value" data-stat="active-sessions">0</div>
            <div class="admin-stat-label">Active Sessions</div>
        </div>
        <div class="admin-stat-trend">
            <span class="admin-live-indicator">LIVE</span>
        </div>
    </div>

    <!-- Members -->
    <div class="admin-stat-card admin-stat-pink">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_users']) ?></div>
            <div class="admin-stat-label">Total Members</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-arrow-up"></i>
            <span>Active</span>
        </div>
    </div>

    <!-- Listings -->
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-rectangle-list"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_listings']) ?></div>
            <div class="admin-stat-label">Active Listings</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-arrow-up"></i>
            <span>Live</span>
        </div>
    </div>

    <!-- Transactions -->
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-arrow-right-arrow-left"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_transactions']) ?></div>
            <div class="admin-stat-label">Transactions</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-check"></i>
            <span>Completed</span>
        </div>
    </div>

    <!-- Volume -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_volume']) ?></div>
            <div class="admin-stat-label">Hours Exchanged</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-hourglass-half"></i>
            <span>Hours</span>
        </div>
    </div>
</div>

<!-- Actionable Alerts Section -->
<?php
$hasAlerts = !empty($pending_users) || !empty($pending_listings) || !empty($pending_orgs);
?>
<?php if ($hasAlerts): ?>
<div class="admin-alerts-container">
    <?php if (!empty($pending_users)): ?>
    <div class="admin-alert admin-alert-warning">
        <div class="admin-alert-icon">
            <i class="fa-solid fa-user-clock"></i>
        </div>
        <div class="admin-alert-content">
            <div class="admin-alert-title"><?= count($pending_users) ?> User<?= count($pending_users) > 1 ? 's' : '' ?> Pending</div>
            <div class="admin-alert-text">New member registrations require your review</div>
        </div>
        <a href="<?= $basePath ?>/admin/users?filter=pending" class="admin-btn admin-btn-warning">
            <i class="fa-solid fa-arrow-right"></i> Review
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($pending_listings)): ?>
    <div class="admin-alert admin-alert-info">
        <div class="admin-alert-icon">
            <i class="fa-solid fa-rectangle-list"></i>
        </div>
        <div class="admin-alert-content">
            <div class="admin-alert-title"><?= $pending_listings ?> Listing<?= $pending_listings > 1 ? 's' : '' ?> Pending</div>
            <div class="admin-alert-text">New listings awaiting moderation</div>
        </div>
        <a href="<?= $basePath ?>/admin/listings?status=pending" class="admin-btn admin-btn-info">
            <i class="fa-solid fa-arrow-right"></i> Review
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($pending_orgs)): ?>
    <div class="admin-alert admin-alert-purple">
        <div class="admin-alert-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="admin-alert-content">
            <div class="admin-alert-title"><?= $pending_orgs ?> Organization<?= $pending_orgs > 1 ? 's' : '' ?> Pending</div>
            <div class="admin-alert-text">Volunteering organizations awaiting approval</div>
        </div>
        <a href="<?= $basePath ?>/admin/volunteering/approvals" class="admin-btn admin-btn-purple">
            <i class="fa-solid fa-arrow-right"></i> Review
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Module Grid - Platform Modules (moved above activity) -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-grid-2"></i>
        Platform Modules
    </h2>
    <p class="admin-section-subtitle">Access all administrative functions</p>
</div>

<div class="admin-modules-grid" id="tour-modules">
    <!-- Content -->
    <a href="<?= $basePath ?>/admin/categories" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-violet">
            <i class="fa-solid fa-folder-tree"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Categories</h4>
            <p class="admin-module-desc">Organize listings & volunteering</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/attributes" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-emerald">
            <i class="fa-solid fa-tags"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Attributes</h4>
            <p class="admin-module-desc">Service tags & filters</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/pages" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-amber">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Pages</h4>
            <p class="admin-module-desc">Static content pages</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/blog" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-rose">
            <i class="fa-solid fa-blog"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Blog</h4>
            <p class="admin-module-desc">News & announcements</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <!-- Community -->
    <a href="<?= $basePath ?>/admin/volunteering" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-cyan">
            <i class="fa-solid fa-hands-helping"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Volunteering</h4>
            <p class="admin-module-desc">Opportunities & assignments</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/group-locations" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-orange">
            <i class="fa-solid fa-location-dot"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Group Locations</h4>
            <p class="admin-module-desc">Geographic boundaries</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/group-ranking" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-pink">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Group Ranking</h4>
            <p class="admin-module-desc">Smart featured groups</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <!-- Intelligence -->
    <a href="<?= $basePath ?>/admin/ai-settings" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-indigo">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">AI Assistant</h4>
            <p class="admin-module-desc">Configure AI providers</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/smart-matching" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-pink">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Smart Matching</h4>
            <p class="admin-module-desc">AI-powered recommendations</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/feed-algorithm" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-indigo">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Feed Algorithm</h4>
            <p class="admin-module-desc">EdgeRank configuration</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/algorithm-settings" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-emerald">
            <i class="fa-solid fa-scale-balanced"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Algorithm Settings</h4>
            <p class="admin-module-desc">MatchRank & CommunityRank</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <!-- Engagement -->
    <a href="<?= $basePath ?>/admin/timebanking" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-teal">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Timebanking</h4>
            <p class="admin-module-desc">Analytics & org wallets</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/seo" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-red">
            <i class="fa-solid fa-magnifying-glass-chart"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">SEO Manager</h4>
            <p class="admin-module-desc">Search optimization</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/404-errors" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-warning">
            <i class="fa-solid fa-exclamation-triangle"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">404 Error Tracking</h4>
            <p class="admin-module-desc">Monitor broken links</p>
        </div>
        <span class="admin-module-badge">NEW</span>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin/cron-jobs" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-slate">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Cron Jobs</h4>
            <p class="admin-module-desc">Scheduled tasks</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>
</div>

<!-- Main Content Grid -->
<div class="admin-dashboard-grid">
    <!-- Left Column - Activity & Quick Actions -->
    <div class="admin-dashboard-main">

        <!-- Real-time Activity -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-cyan">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Real-time Activity</h3>
                    <p class="admin-card-subtitle">Latest events in your community</p>
                </div>
                <a href="<?= $basePath ?>/admin/activity-log" class="admin-card-header-action">
                    View All <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="admin-card-body">
                <?php if (!empty($activity_logs)): ?>
                <div class="admin-activity-list">
                    <?php foreach ($activity_logs as $log): ?>
                    <div class="admin-activity-item">
                        <div class="admin-activity-avatar">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="admin-activity-content">
                            <div class="admin-activity-main">
                                <span class="admin-activity-user"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                                <span class="admin-activity-action"><?= str_replace('_', ' ', $log['action']) ?></span>
                            </div>
                            <?php if (!empty($log['details'])): ?>
                            <div class="admin-activity-details"><?= htmlspecialchars($log['details']) ?></div>
                            <?php endif; ?>
                            <div class="admin-activity-time">
                                <i class="fa-regular fa-clock"></i>
                                <?= date('M d, H:i', strtotime($log['created_at'])) ?>
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

        <!-- Analytics Chart -->
        <?php if (!empty($stats['monthly_stats'])): ?>
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Transaction Volume</h3>
                    <p class="admin-card-subtitle">Last 6 months activity</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-chart-container">
                    <canvas id="volumeChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right Column - Quick Access & System -->
    <div class="admin-dashboard-sidebar">

        <!-- Quick Actions -->
        <div class="admin-glass-card" id="tour-quick-actions">
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
                    <a href="<?= $basePath ?>/admin/users" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-blue">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <span>Manage Users</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/listings" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-green">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                        <span>View Listings</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/newsletters" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-purple">
                            <i class="fa-solid fa-paper-plane"></i>
                        </div>
                        <span>Send Newsletter</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/blog" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-pink">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </div>
                        <span>New Blog Post</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/gamification" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-orange">
                            <i class="fa-solid fa-trophy"></i>
                        </div>
                        <span>Gamification</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/settings" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-slate">
                            <i class="fa-solid fa-gear"></i>
                        </div>
                        <span>Settings</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Enterprise Suite -->
        <div class="admin-glass-card admin-glass-card-enterprise">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-indigo">
                    <i class="fa-solid fa-building-shield"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Enterprise Suite</h3>
                    <p class="admin-card-subtitle">Advanced controls</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-enterprise-links">
                    <a href="<?= $basePath ?>/admin/enterprise" class="admin-enterprise-link">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Overview</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin/enterprise/gdpr" class="admin-enterprise-link">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>GDPR Compliance</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin/enterprise/monitoring" class="admin-enterprise-link">
                        <i class="fa-solid fa-heart-pulse"></i>
                        <span>System Health</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin/enterprise/config" class="admin-enterprise-link">
                        <i class="fa-solid fa-gears"></i>
                        <span>Configuration</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="admin-glass-card" id="tour-system-status">
        <?php
        $statusIcons = [
            'database' => 'fa-database',
            'cache' => 'fa-layer-group',
            'queue' => 'fa-clock-rotate-left',
            'api' => 'fa-plug',
        ];
        $statusLabels = [
            'database' => 'Database',
            'cache' => 'Cache',
            'queue' => 'Cron Jobs',
            'api' => 'Email API',
        ];
        $systemStatus = $systemStatus ?? [];
        $allOnline = true;
        $hasWarning = false;
        foreach ($systemStatus as $item) {
            if ($item['status'] !== 'online') $allOnline = false;
            if ($item['status'] === 'warning') $hasWarning = true;
        }
        ?>
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-emerald">
                    <i class="fa-solid fa-server"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">System Status</h3>
                    <p class="admin-card-subtitle">
                        <?php if ($allOnline): ?>
                            All systems operational
                        <?php elseif ($hasWarning): ?>
                            Some services need attention
                        <?php else: ?>
                            Issues detected
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?= $basePath ?>/admin/enterprise/monitoring" class="admin-card-header-action">
                    Details <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="admin-card-body">
                <div class="admin-system-status">
                    <?php foreach ($systemStatus as $key => $item): ?>
                    <div class="admin-status-item">
                        <div class="admin-status-indicator admin-status-<?= $item['status'] ?>"></div>
                        <i class="fa-solid <?= $statusIcons[$key] ?? 'fa-circle' ?> admin-status-icon"></i>
                        <span class="admin-status-label"><?= $statusLabels[$key] ?? ucfirst($key) ?></span>
                        <span class="admin-status-value admin-status-value-<?= $item['status'] ?>"><?= htmlspecialchars($item['label']) ?></span>
                    </div>
                    <?php endforeach; ?>
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

<?php
// Include unified admin footer (closes wrapper, adds toast container)
require __DIR__ . '/../../partials/admin/admin-footer.php';

// Chart Data
if (!empty($stats['monthly_stats'])):
    $chartLabels = array_column($stats['monthly_stats'], 'month');
    $chartData = array_column($stats['monthly_stats'], 'volume');
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('volumeChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Hours Exchanged',
                    data: <?= json_encode($chartData) ?>,
                    borderColor: 'rgba(139, 92, 246, 1)',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(139, 92, 246, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            borderColor: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.6)'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            borderColor: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.6)'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<style>
/**
 * Dashboard-Specific Styles
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
    color: #06b6d4;
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

.admin-stat-pink { --stat-color: #ec4899; }
.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-orange { --stat-color: #f59e0b; }

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

.admin-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-alert-info {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.admin-alert-purple {
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
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

.admin-alert-warning .admin-alert-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.admin-alert-info .admin-alert-icon {
    background: rgba(6, 182, 212, 0.2);
    color: #06b6d4;
}

.admin-alert-purple .admin-alert-icon {
    background: rgba(139, 92, 246, 0.2);
    color: #8b5cf6;
}

.admin-alert-content {
    flex: 1;
    min-width: 0;
}

.admin-alert-title {
    font-weight: 600;
    font-size: 0.95rem;
}

.admin-alert-warning .admin-alert-title { color: #f59e0b; }
.admin-alert-info .admin-alert-title { color: #06b6d4; }
.admin-alert-purple .admin-alert-title { color: #a78bfa; }

.admin-alert-text {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

.admin-btn-info {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: #fff;
    font-weight: 600;
}

.admin-btn-purple {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: #fff;
    font-weight: 600;
}

@media (max-width: 900px) {
    .admin-alerts-container {
        flex-direction: column;
    }
    .admin-alert {
        min-width: unset;
    }
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

.admin-glass-card-enterprise {
    border-color: rgba(99, 102, 241, 0.3);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
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

.admin-card-header-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-card-header-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-card-header-icon-indigo { background: rgba(99, 102, 241, 0.2); color: #6366f1; }
.admin-card-header-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }

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
    max-height: 400px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

/* Custom scrollbar for activity list */
.admin-activity-list::-webkit-scrollbar {
    width: 6px;
}

.admin-activity-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
}

.admin-activity-list::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.3);
    border-radius: 3px;
}

.admin-activity-list::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.5);
}

.admin-activity-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-activity-item:last-child {
    border-bottom: none;
}

.admin-activity-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(6, 182, 212, 0.2);
    color: #06b6d4;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
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
    color: rgba(255, 255, 255, 0.7);
    margin-left: 0.375rem;
}

.admin-activity-details {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin-top: 0.375rem;
    display: inline-block;
}

.admin-activity-time {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
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

/* Chart Container */
.admin-chart-container {
    height: 200px;
    position: relative;
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

.admin-quick-action-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.admin-quick-action-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-quick-action-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-quick-action-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }
.admin-quick-action-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-quick-action-icon-slate { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

/* Enterprise Links */
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

.admin-enterprise-link i:first-child {
    color: #818cf8;
    width: 20px;
    text-align: center;
}

.admin-enterprise-link span {
    flex: 1;
}

.admin-enterprise-link i:last-child {
    font-size: 0.7rem;
    opacity: 0.5;
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

.admin-status-offline {
    background: #ef4444;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
}

.admin-status-icon {
    color: rgba(255, 255, 255, 0.4);
    width: 16px;
    text-align: center;
    font-size: 0.8rem;
}

.admin-status-label {
    flex: 1;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

.admin-status-value {
    font-size: 0.8rem;
    font-weight: 500;
}

.admin-status-value-online { color: #22c55e; }
.admin-status-value-warning { color: #f59e0b; }
.admin-status-value-offline { color: #ef4444; }

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

.admin-module-card-gradient {
    border-color: rgba(139, 92, 246, 0.3);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.05));
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

.admin-module-icon-violet { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-module-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.admin-module-icon-amber { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-module-icon-rose { background: rgba(244, 63, 94, 0.2); color: #f43f5e; }
.admin-module-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-module-icon-orange { background: rgba(249, 115, 22, 0.2); color: #f97316; }
.admin-module-icon-indigo { background: rgba(99, 102, 241, 0.2); color: #6366f1; }
.admin-module-icon-red { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
.admin-module-icon-slate { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

.admin-module-icon-gradient-indigo {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.admin-module-icon-gradient-pink {
    background: linear-gradient(135deg, #ec4899, #a855f7);
    color: white;
    box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
}

.admin-module-icon-gradient-teal {
    background: linear-gradient(135deg, #14b8a6, #06b6d4);
    color: white;
    box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
}

.admin-module-icon-gradient-emerald {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
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
    color: #06b6d4;
    transform: translateX(4px);
}

/* Tour Button */
.admin-btn-tour {
    background: linear-gradient(135deg, #8b5cf6, #a855f7);
    color: #fff;
    border: none;
}

.admin-btn-tour:hover {
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
    transform: translateY(-1px);
}

/* Admin Onboarding Tour Styles */
.admin-tour-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.admin-tour-backdrop.active {
    opacity: 1;
    visibility: visible;
}

/* Highlight class applied to target elements */
.admin-tour-highlight {
    position: relative;
    z-index: 9999 !important;
    outline: 3px solid #8b5cf6 !important;
    outline-offset: 4px !important;
    border-radius: inherit;
    animation: tourHighlightPulse 2s ease-in-out infinite;
    box-shadow: 0 0 30px rgba(139, 92, 246, 0.5) !important;
}

@keyframes tourHighlightPulse {
    0%, 100% { outline-color: #8b5cf6; box-shadow: 0 0 30px rgba(139, 92, 246, 0.5); }
    50% { outline-color: #a855f7; box-shadow: 0 0 40px rgba(168, 85, 247, 0.6); }
}

.admin-tour-tooltip {
    position: fixed;
    z-index: 10000;
    width: 340px;
    max-width: calc(100vw - 2rem);
    background: linear-gradient(135deg, #1e1b4b, #312e81);
    border: 1px solid rgba(139, 92, 246, 0.4);
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 0 0 40px rgba(139, 92, 246, 0.2);
    opacity: 0;
    visibility: hidden;
    transform: scale(0.95) translateY(10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-tour-tooltip.active {
    opacity: 1;
    visibility: visible;
    transform: scale(1) translateY(0);
}

.admin-tour-tooltip-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.admin-tour-step-badge {
    background: linear-gradient(135deg, #8b5cf6, #a855f7);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
}

.admin-tour-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: rgba(255, 255, 255, 0.6);
    width: 28px;
    height: 28px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.admin-tour-close:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.admin-tour-title {
    color: #fff;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.admin-tour-description {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0 0 1.25rem 0;
}

.admin-tour-actions {
    display: flex;
    gap: 0.75rem;
}

.admin-tour-btn {
    flex: 1;
    padding: 0.625rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
    font-family: inherit;
}

.admin-tour-btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #a855f7);
    color: #fff;
}

.admin-tour-btn-primary:hover {
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
    transform: translateY(-1px);
}

.admin-tour-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.admin-tour-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

.admin-tour-btn-secondary:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* Welcome Modal for First-Time Admins */
.admin-welcome-modal {
    position: fixed;
    inset: 0;
    z-index: 10001;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
}

.admin-welcome-modal.open {
    display: flex;
}

.admin-welcome-content {
    background: linear-gradient(135deg, #1e1b4b, #312e81);
    border: 1px solid rgba(139, 92, 246, 0.4);
    border-radius: 20px;
    padding: 2.5rem;
    max-width: 480px;
    width: calc(100% - 2rem);
    text-align: center;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    animation: welcomeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes welcomeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.admin-welcome-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #8b5cf6, #06b6d4);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: white;
    box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
}

.admin-welcome-title {
    color: #fff;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.75rem 0;
}

.admin-welcome-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.6;
    margin: 0 0 2rem 0;
}

.admin-welcome-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.admin-welcome-btn {
    padding: 0.875rem 1.5rem;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
    font-family: inherit;
}

.admin-welcome-btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #a855f7);
    color: #fff;
}

.admin-welcome-btn-primary:hover {
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    transform: translateY(-2px);
}

.admin-welcome-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
}

.admin-welcome-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}
</style>

<!-- Welcome Modal for First-Time Admins -->
<div class="admin-welcome-modal" id="adminWelcomeModal">
    <div class="admin-welcome-content">
        <div class="admin-welcome-icon">
            <i class="fa-solid fa-rocket"></i>
        </div>
        <h2 class="admin-welcome-title">Welcome to Mission Control!</h2>
        <p class="admin-welcome-text">
            You're now an administrator. This powerful dashboard gives you complete control over your community platform. Would you like a quick tour?
        </p>
        <div class="admin-welcome-actions">
            <button class="admin-welcome-btn admin-welcome-btn-secondary" onclick="AdminTour.dismissWelcome()">
                Skip for now
            </button>
            <button class="admin-welcome-btn admin-welcome-btn-primary" onclick="AdminTour.startFromWelcome()">
                <i class="fa-solid fa-graduation-cap"></i>
                Take the Tour
            </button>
        </div>
    </div>
</div>

<script>
/**
 * Admin Onboarding Tour System - Simplified
 * Uses highlight class instead of box-shadow overlay to avoid black screen issues
 */
window.AdminTour = {
    currentStep: 0,
    isActive: false,
    highlightedElement: null,
    STORAGE_KEY: 'nexus_admin_tour_completed',

    steps: [
        {
            target: '#tour-dashboard',
            title: 'Welcome to Mission Control!',
            description: 'This is your command center for managing your community platform. Let\'s explore the key features together.',
            position: 'bottom'
        },
        {
            target: '#tour-stats',
            title: 'Platform Statistics',
            description: 'Monitor your community at a glance. These cards show total members, listings, transactions, and hours exchanged in real-time.',
            position: 'bottom'
        },
        {
            target: '#tour-modules',
            title: 'Platform Modules',
            description: 'Access all administrative functions here. Categories, pages, blog, AI settings, SEO, and much more are organized for easy access.',
            position: 'bottom'
        },
        {
            target: '#tour-quick-actions',
            title: 'Quick Actions',
            description: 'Common tasks are just one click away. Manage users, view listings, send newsletters, and more from these shortcuts.',
            position: 'left'
        },
        {
            target: '#tour-system-status',
            title: 'System Health',
            description: 'Keep an eye on your platform\'s health. Database, cache, cron jobs, and email API status are monitored automatically.',
            position: 'left'
        },
        {
            target: '#adminSearchTrigger',
            title: 'Quick Search (Ctrl+K)',
            description: 'Use the command palette to quickly find users, listings, settings, and more. Press Ctrl+K anywhere to open it instantly.',
            position: 'bottom'
        }
    ],

    init: function() {
        // Check if first-time admin visit
        if (!localStorage.getItem(this.STORAGE_KEY)) {
            // Show welcome modal after a short delay
            setTimeout(function() {
                document.getElementById('adminWelcomeModal').classList.add('open');
            }, 500);
        }
    },

    dismissWelcome: function() {
        document.getElementById('adminWelcomeModal').classList.remove('open');
        localStorage.setItem(this.STORAGE_KEY, 'dismissed');
    },

    startFromWelcome: function() {
        document.getElementById('adminWelcomeModal').classList.remove('open');
        localStorage.setItem(this.STORAGE_KEY, 'completed');
        this.start();
    },

    start: function() {
        this.currentStep = 0;
        this.isActive = true;
        document.getElementById('adminTourBackdrop').classList.add('active');
        document.getElementById('adminTourTooltip').classList.add('active');
        this.showStep(0);
    },

    end: function() {
        this.isActive = false;
        // Remove highlight from current element
        if (this.highlightedElement) {
            this.highlightedElement.classList.remove('admin-tour-highlight');
            this.highlightedElement = null;
        }
        document.getElementById('adminTourBackdrop').classList.remove('active');
        document.getElementById('adminTourTooltip').classList.remove('active');
        localStorage.setItem(this.STORAGE_KEY, 'completed');
    },

    showStep: function(index) {
        var self = this;
        var step = this.steps[index];
        var target = document.querySelector(step.target);

        // Remove previous highlight
        if (this.highlightedElement) {
            this.highlightedElement.classList.remove('admin-tour-highlight');
        }

        if (!target) {
            // Skip to next if target not found
            if (index < this.steps.length - 1) {
                this.currentStep = index + 1;
                this.showStep(this.currentStep);
            } else {
                this.end();
            }
            return;
        }

        // Add highlight to target
        target.classList.add('admin-tour-highlight');
        this.highlightedElement = target;

        // Update content
        document.getElementById('tourStepBadge').textContent = (index + 1) + '/' + this.steps.length;
        document.getElementById('tourTitle').textContent = step.title;
        document.getElementById('tourDescription').textContent = step.description;

        // Update buttons
        var prevBtn = document.getElementById('tourPrev');
        var nextBtn = document.getElementById('tourNext');

        prevBtn.disabled = index === 0;
        prevBtn.style.opacity = index === 0 ? '0.3' : '1';

        if (index === this.steps.length - 1) {
            nextBtn.innerHTML = 'Finish <i class="fa-solid fa-check"></i>';
        } else {
            nextBtn.innerHTML = 'Next <i class="fa-solid fa-arrow-right"></i>';
        }

        // Scroll target into view first
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Position tooltip after scroll with a small delay
        setTimeout(function() {
            self.positionTooltip(target, step.position);
        }, 300);
    },

    positionTooltip: function(target, position) {
        var rect = target.getBoundingClientRect();
        var tooltip = document.getElementById('adminTourTooltip');
        var tooltipRect = { width: 340, height: 200 };

        var top, left;

        switch (position) {
            case 'bottom':
                top = rect.bottom + 16;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'top':
                top = rect.top - tooltipRect.height - 16;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'left':
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.left - tooltipRect.width - 16;
                break;
            case 'right':
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.right + 16;
                break;
            default:
                top = rect.bottom + 16;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        }

        // Keep tooltip in viewport
        left = Math.max(16, Math.min(left, window.innerWidth - tooltipRect.width - 16));
        top = Math.max(16, Math.min(top, window.innerHeight - tooltipRect.height - 16));

        tooltip.style.top = top + 'px';
        tooltip.style.left = left + 'px';
    },

    next: function() {
        if (this.currentStep < this.steps.length - 1) {
            this.currentStep++;
            this.showStep(this.currentStep);
        } else {
            this.end();
            // Show success toast
            if (window.AdminToast) {
                AdminToast.success('Tour Complete!', 'You\'re ready to manage your platform. Press ? anytime for keyboard shortcuts.');
            }
        }
    },

    prev: function() {
        if (this.currentStep > 0) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }
};

// Tour disabled - uncomment to re-enable
// document.addEventListener('DOMContentLoaded', function() {
//     AdminTour.init();
// });

// Close tour on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && AdminTour.isActive) {
        AdminTour.end();
    }
});
</script>
