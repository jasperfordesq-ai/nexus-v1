<?php
/**
 * Federation Analytics Dashboard
 * Gold Standard admin page for viewing federation activity metrics
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Analytics';
$adminPageSubtitle = 'Activity Dashboard';
$adminPageIcon = 'fa-chart-line';

require __DIR__ . '/../partials/admin-header.php';

$auditStats = $auditStats ?? [];
$partnershipStats = $partnershipStats ?? [];
$tenantAnalytics = $tenantAnalytics ?? [];
$activityTimeline = $activityTimeline ?? [];
$partnerActivity = $partnerActivity ?? [];
$featureUsage = $featureUsage ?? [];
$recentActivity = $recentActivity ?? [];
$days = $days ?? 30;

// Calculate totals
$totalActivity = $auditStats['total_actions'] ?? 0;
$activePartners = $partnershipStats['active'] ?? 0;
$totalMessages = ($tenantAnalytics['messages_sent'] ?? 0) + ($tenantAnalytics['messages_received'] ?? 0);
$totalHours = ($tenantAnalytics['hours_given'] ?? 0) + ($tenantAnalytics['hours_received'] ?? 0);

// Prepare chart data
$timelineLabels = array_map(fn($d) => date('M j', strtotime($d['date'])), $activityTimeline);
$timelineMessaging = array_map(fn($d) => $d['messaging'] ?? 0, $activityTimeline);
$timelineTransactions = array_map(fn($d) => $d['transaction'] ?? 0, $activityTimeline);
$timelineOther = array_map(fn($d) => ($d['profile'] ?? 0) + ($d['listing'] ?? 0) + ($d['partnership'] ?? 0) + ($d['other'] ?? 0), $activityTimeline);
?>

<style>
/* Modern Dark Glass UI - Analytics */
.analytics-date-filter {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.analytics-date-btn {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
}

.analytics-date-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(139, 92, 246, 0.4);
    color: #fff;
}

.analytics-date-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: transparent;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.chart-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 1px dashed rgba(99, 102, 241, 0.2);
}

.chart-placeholder i {
    font-size: 3rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.analytics-metric-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.analytics-metric {
    display: flex;
    flex-direction: column;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 10px;
    transition: all 0.2s;
}

.analytics-metric:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(99, 102, 241, 0.2);
}

.analytics-metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

.analytics-metric-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.analytics-metric.positive .analytics-metric-value {
    color: #22c55e;
}

.analytics-metric.negative .analytics-metric-value {
    color: #ef4444;
}

.partner-activity-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.partner-activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 10px;
    transition: all 0.2s;
}

.partner-activity-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(99, 102, 241, 0.2);
    transform: translateX(4px);
}

.partner-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.partner-info {
    flex: 1;
    min-width: 0;
}

.partner-name {
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.partner-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.partner-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.partner-stats span i {
    color: #8b5cf6;
}

.partner-activity-count {
    font-weight: 700;
    font-size: 1.25rem;
    color: #8b5cf6;
}

.feature-usage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.feature-usage-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    text-align: center;
    transition: all 0.2s;
}

.feature-usage-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(99, 102, 241, 0.2);
    transform: translateY(-2px);
}

.feature-usage-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
}

.feature-usage-icon.messaging {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.feature-usage-icon.transaction {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.feature-usage-icon.profile {
    background: rgba(168, 85, 247, 0.15);
    color: #a855f7;
}

.feature-usage-icon.listing {
    background: rgba(249, 115, 22, 0.15);
    color: #f97316;
}

.feature-usage-icon.partnership {
    background: rgba(14, 165, 233, 0.15);
    color: #0ea5e9;
}

.feature-usage-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

.feature-usage-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: capitalize;
}

.activity-log-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.activity-log-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 10px;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.activity-log-item:hover {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(99, 102, 241, 0.1);
}

.activity-log-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-log-icon.info {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.activity-log-icon.success {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.activity-log-icon.warning {
    background: rgba(249, 115, 22, 0.15);
    color: #f97316;
}

.activity-log-icon.critical {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.activity-log-content {
    flex: 1;
    min-width: 0;
}

.activity-log-action {
    font-weight: 500;
    color: #fff;
}

.activity-log-meta {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    color: rgba(255, 255, 255, 0.5);
    text-align: center;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.empty-state p {
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
}

.empty-state small {
    color: rgba(255, 255, 255, 0.4);
}

.progress-bar-container {
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.progress-bar {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s;
}

.progress-bar.primary { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.progress-bar.success { background: linear-gradient(90deg, #22c55e, #4ade80); }
.progress-bar.warning { background: linear-gradient(90deg, #f59e0b, #f97316); }
.progress-bar.purple { background: linear-gradient(90deg, #a855f7, #c084fc); }
.progress-bar.cyan { background: linear-gradient(90deg, #0ea5e9, #22d3ee); }

/* Dropdown for export */
.dropdown-menu {
    background: rgba(15, 23, 42, 0.95) !important;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2) !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4) !important;
    padding: 0.5rem 0 !important;
}

.dropdown-item {
    color: rgba(255, 255, 255, 0.8) !important;
    padding: 0.625rem 1rem !important;
    transition: all 0.2s !important;
}

.dropdown-item:hover {
    background: rgba(139, 92, 246, 0.15) !important;
    color: #fff !important;
}

.dropdown-item i {
    width: 20px;
    margin-right: 0.5rem;
    color: #8b5cf6;
}

@media (max-width: 768px) {
    .analytics-date-filter {
        flex-wrap: wrap;
    }

    .analytics-metric-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Federation Analytics
        </h1>
        <p class="admin-page-subtitle">Track your cross-timebank activity and partnership health</p>
    </div>
    <div class="admin-page-header-actions">
        <div class="analytics-date-filter">
            <a href="?days=7" class="analytics-date-btn <?= $days == 7 ? 'active' : '' ?>">7 Days</a>
            <a href="?days=30" class="analytics-date-btn <?= $days == 30 ? 'active' : '' ?>">30 Days</a>
            <a href="?days=90" class="analytics-date-btn <?= $days == 90 ? 'active' : '' ?>">90 Days</a>
            <a href="?days=365" class="analytics-date-btn <?= $days == 365 ? 'active' : '' ?>">1 Year</a>
        </div>
        <div class="dropdown" style="position: relative;">
            <button class="admin-btn admin-btn-secondary dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                <i class="fa-solid fa-download"></i>
                Export
            </button>
            <div class="dropdown-menu" style="position: absolute; right: 0; top: 100%; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; min-width: 160px; z-index: 100;">
                <a href="<?= $basePath ?>/admin/federation/analytics/export?type=activity&days=<?= $days ?>" class="dropdown-item" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: inherit;">
                    <i class="fa-solid fa-calendar"></i> Activity Timeline
                </a>
                <a href="<?= $basePath ?>/admin/federation/analytics/export?type=partners&days=<?= $days ?>" class="dropdown-item" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: inherit;">
                    <i class="fa-solid fa-users"></i> Partner Activity
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalActivity) ?></div>
            <div class="admin-stat-label">Total Activity</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-chart-simple"></i>
            <span><?= $days ?> days</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $activePartners ?></div>
            <div class="admin-stat-label">Active Partners</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-check-circle"></i>
            <span>Connected</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalMessages) ?></div>
            <div class="admin-stat-label">Messages Exchanged</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-arrow-right-arrow-left"></i>
            <span>Cross-TB</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalHours, 1) ?></div>
            <div class="admin-stat-label">Hours Exchanged</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-coins"></i>
            <span>Federated</span>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="admin-grid admin-grid-2">
    <!-- Activity Timeline Chart -->
    <div class="admin-card admin-card-span-2">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-chart-area"></i>
                Activity Timeline
            </h3>
        </div>
        <div class="admin-card-body">
            <?php if (!empty($activityTimeline)): ?>
            <div class="chart-container">
                <canvas id="activityChart"></canvas>
            </div>
            <?php else: ?>
            <div class="chart-placeholder">
                <i class="fa-solid fa-chart-area"></i>
                <p>No activity data for the selected period</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Your Activity Summary -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-user-chart"></i>
                Your Federation Activity
            </h3>
        </div>
        <div class="admin-card-body">
            <div class="analytics-metric-grid">
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= $tenantAnalytics['messages_sent'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Messages Sent</div>
                </div>
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= $tenantAnalytics['messages_received'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Messages Received</div>
                </div>
                <div class="analytics-metric positive">
                    <div class="analytics-metric-value"><?= number_format($tenantAnalytics['hours_received'] ?? 0, 1) ?></div>
                    <div class="analytics-metric-label">Hours Received</div>
                </div>
                <div class="analytics-metric negative">
                    <div class="analytics-metric-value"><?= number_format($tenantAnalytics['hours_given'] ?? 0, 1) ?></div>
                    <div class="analytics-metric-label">Hours Given</div>
                </div>
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= $tenantAnalytics['profile_views'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Profile Views</div>
                </div>
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= $tenantAnalytics['listing_interactions'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Listing Views</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Partner Activity -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-users"></i>
                Top Partners by Activity
            </h3>
        </div>
        <div class="admin-card-body">
            <?php if (!empty($partnerActivity)): ?>
            <div class="partner-activity-list">
                <?php foreach (array_slice($partnerActivity, 0, 5) as $partner): ?>
                <div class="partner-activity-item">
                    <div class="partner-avatar">
                        <?= strtoupper(substr($partner['partner_name'] ?? '?', 0, 1)) ?>
                    </div>
                    <div class="partner-info">
                        <div class="partner-name"><?= htmlspecialchars($partner['partner_name'] ?? 'Unknown') ?></div>
                        <div class="partner-stats">
                            <span><i class="fa-solid fa-envelope"></i> <?= $partner['messages'] ?? 0 ?></span>
                            <span><i class="fa-solid fa-exchange-alt"></i> <?= $partner['transactions'] ?? 0 ?></span>
                            <span><i class="fa-solid fa-eye"></i> <?= $partner['profile_views'] ?? 0 ?></span>
                        </div>
                    </div>
                    <div class="partner-activity-count"><?= $partner['activity_count'] ?? 0 ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-users-slash"></i>
                <p>No partner activity yet</p>
                <small>Start connecting with partners to see activity here</small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feature Usage -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-puzzle-piece"></i>
                Feature Usage
            </h3>
        </div>
        <div class="admin-card-body">
            <?php if (!empty($featureUsage)): ?>
            <div class="feature-usage-grid">
                <?php
                $featureIcons = [
                    'messaging' => 'fa-envelope',
                    'transaction' => 'fa-exchange-alt',
                    'profile' => 'fa-user',
                    'listing' => 'fa-list',
                    'partnership' => 'fa-handshake',
                    'event' => 'fa-calendar',
                    'group' => 'fa-users-rectangle',
                    'search' => 'fa-search',
                ];
                $maxUsage = max(array_column($featureUsage, 'usage_count'));
                foreach ($featureUsage as $feature):
                    $icon = $featureIcons[$feature['category']] ?? 'fa-cog';
                    $percent = $maxUsage > 0 ? round(($feature['usage_count'] / $maxUsage) * 100) : 0;
                ?>
                <div class="feature-usage-item">
                    <div class="feature-usage-icon <?= $feature['category'] ?>">
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="feature-usage-value"><?= number_format($feature['usage_count']) ?></div>
                    <div class="feature-usage-label"><?= ucfirst($feature['category']) ?></div>
                    <div class="progress-bar-container" style="width: 100%;">
                        <div class="progress-bar primary" style="width: <?= $percent ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-puzzle-piece"></i>
                <p>No feature usage data</p>
                <small>Usage statistics will appear as you use federation features</small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Partnership Overview -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-handshake-angle"></i>
                Partnership Overview
            </h3>
        </div>
        <div class="admin-card-body">
            <div class="analytics-metric-grid">
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= $partnershipStats['total'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Total Partnerships</div>
                </div>
                <div class="analytics-metric positive">
                    <div class="analytics-metric-value"><?= $partnershipStats['active'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Active</div>
                </div>
                <div class="analytics-metric" style="background: rgba(249, 115, 22, 0.1);">
                    <div class="analytics-metric-value" style="color: #f97316;"><?= $partnershipStats['pending'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Pending</div>
                </div>
                <div class="analytics-metric negative">
                    <div class="analytics-metric-value"><?= $partnershipStats['suspended'] ?? 0 ?></div>
                    <div class="analytics-metric-label">Suspended</div>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fa-solid fa-arrow-right"></i>
                    Manage Partnerships
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity Log -->
    <div class="admin-card admin-card-span-2">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Recent Activity
            </h3>
            <a href="<?= $basePath ?>/admin/federation/audit" class="admin-btn admin-btn-sm admin-btn-secondary">
                View All
            </a>
        </div>
        <div class="admin-card-body">
            <?php if (!empty($recentActivity)): ?>
            <div class="activity-log-list">
                <?php foreach ($recentActivity as $log):
                    $levelClass = match($log['level'] ?? 'info') {
                        'critical' => 'critical',
                        'warning' => 'warning',
                        'success' => 'success',
                        default => 'info'
                    };
                    $levelIcon = match($log['level'] ?? 'info') {
                        'critical' => 'fa-exclamation-circle',
                        'warning' => 'fa-triangle-exclamation',
                        'success' => 'fa-check-circle',
                        default => 'fa-info-circle'
                    };
                ?>
                <div class="activity-log-item">
                    <div class="activity-log-icon <?= $levelClass ?>">
                        <i class="fa-solid <?= $levelIcon ?>"></i>
                    </div>
                    <div class="activity-log-content">
                        <div class="activity-log-action">
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action_type'] ?? 'Unknown'))) ?>
                        </div>
                        <div class="activity-log-meta">
                            <?= ucfirst($log['category'] ?? 'system') ?> &bull;
                            <?= date('M j, g:i a', strtotime($log['created_at'] ?? 'now')) ?>
                            <?php if (!empty($log['related_tenant_name'])): ?>
                            &bull; with <?= htmlspecialchars($log['related_tenant_name']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <p>No recent activity</p>
                <small>Activity will appear here as you use federation features</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dropdown toggle
    document.querySelectorAll('.dropdown-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = this.parentElement.querySelector('.dropdown-menu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        });
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    });

    // Activity Chart
    const chartCanvas = document.getElementById('activityChart');
    if (chartCanvas) {
        const labels = <?= json_encode($timelineLabels) ?>;
        const messagingData = <?= json_encode($timelineMessaging) ?>;
        const transactionData = <?= json_encode($timelineTransactions) ?>;
        const otherData = <?= json_encode($timelineOther) ?>;

        new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Messaging',
                        data: messagingData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Transactions',
                        data: transactionData,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Other',
                        data: otherData,
                        borderColor: '#a855f7',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#fff',
                        bodyColor: 'rgba(255, 255, 255, 0.8)',
                        borderColor: 'rgba(99, 102, 241, 0.3)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.5)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.5)'
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
