<?php
/**
 * Federation Analytics Dashboard
 * View federation activity metrics
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

$totalActivity = $auditStats['total_actions'] ?? 0;
$activePartners = $partnershipStats['active'] ?? 0;
$totalMessages = ($tenantAnalytics['messages_sent'] ?? 0) + ($tenantAnalytics['messages_received'] ?? 0);
$totalHours = ($tenantAnalytics['hours_given'] ?? 0) + ($tenantAnalytics['hours_received'] ?? 0);
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Federation Analytics
        </h1>
        <p class="admin-page-subtitle">Track your federation activity and engagement</p>
    </div>
    <div class="admin-page-header-actions">
        <div class="analytics-date-filter">
            <a href="?days=7" class="analytics-date-btn <?= $days == 7 ? 'active' : '' ?>">7 Days</a>
            <a href="?days=30" class="analytics-date-btn <?= $days == 30 ? 'active' : '' ?>">30 Days</a>
            <a href="?days=90" class="analytics-date-btn <?= $days == 90 ? 'active' : '' ?>">90 Days</a>
        </div>
    </div>
</div>

<!-- Stats Overview -->
<div class="fed-stats-grid">
    <div class="fed-stat-card">
        <div class="fed-stat-icon purple">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <div class="fed-stat-content">
            <div class="fed-stat-value"><?= number_format($totalActivity) ?></div>
            <div class="fed-stat-label">Total Actions</div>
        </div>
    </div>

    <div class="fed-stat-card">
        <div class="fed-stat-icon blue">
            <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="fed-stat-content">
            <div class="fed-stat-value"><?= number_format($totalMessages) ?></div>
            <div class="fed-stat-label">Messages Exchanged</div>
        </div>
    </div>

    <div class="fed-stat-card">
        <div class="fed-stat-icon green">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="fed-stat-content">
            <div class="fed-stat-value"><?= number_format($totalHours, 1) ?></div>
            <div class="fed-stat-label">Hours Exchanged</div>
        </div>
    </div>

    <div class="fed-stat-card">
        <div class="fed-stat-icon amber">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="fed-stat-content">
            <div class="fed-stat-value"><?= number_format($activePartners) ?></div>
            <div class="fed-stat-label">Active Partners</div>
        </div>
    </div>
</div>

<!-- Activity Chart -->
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-chart-area"></i>
            Activity Over Time
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <?php if (empty($activityTimeline)): ?>
        <div class="chart-placeholder">
            <i class="fa-solid fa-chart-line"></i>
            <p>No activity data available for this period</p>
        </div>
        <?php else: ?>
        <div class="chart-container">
            <canvas id="activityChart"></canvas>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="fed-grid-2">
    <!-- Partner Activity -->
    <div class="fed-admin-card">
        <div class="fed-admin-card-header">
            <h3 class="fed-admin-card-title">
                <i class="fa-solid fa-handshake"></i>
                Partner Activity
            </h3>
        </div>
        <div class="fed-admin-card-body">
            <?php if (empty($partnerActivity)): ?>
            <div class="fed-empty-state">
                <i class="fa-solid fa-handshake-slash"></i>
                <p>No partner activity data</p>
            </div>
            <?php else: ?>
            <div class="partner-activity-list">
                <?php foreach (array_slice($partnerActivity, 0, 5) as $partner): ?>
                <div class="partner-activity-item">
                    <div class="fed-partnership-logo">
                        <?= strtoupper(substr($partner['name'] ?? 'P', 0, 2)) ?>
                    </div>
                    <div class="fed-top-user-info">
                        <div class="fed-top-user-name"><?= htmlspecialchars($partner['name'] ?? 'Unknown') ?></div>
                        <div class="fed-top-user-stats">
                            <?= $partner['messages'] ?? 0 ?> messages, <?= $partner['transactions'] ?? 0 ?> transactions
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feature Usage -->
    <div class="fed-admin-card">
        <div class="fed-admin-card-header">
            <h3 class="fed-admin-card-title">
                <i class="fa-solid fa-puzzle-piece"></i>
                Feature Usage
            </h3>
        </div>
        <div class="fed-admin-card-body">
            <?php if (empty($featureUsage)): ?>
            <div class="fed-empty-state">
                <i class="fa-solid fa-chart-pie"></i>
                <p>No feature usage data</p>
            </div>
            <?php else: ?>
            <div class="analytics-metric-grid">
                <?php foreach ($featureUsage as $feature => $count): ?>
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= number_format($count) ?></div>
                    <div class="analytics-metric-label"><?= ucfirst(str_replace('_', ' ', $feature)) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Recent Activity
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <?php if (empty($recentActivity)): ?>
        <div class="fed-empty-state">
            <i class="fa-solid fa-list"></i>
            <p>No recent activity</p>
        </div>
        <?php else: ?>
        <div class="fed-activity-list">
            <?php foreach (array_slice($recentActivity, 0, 10) as $activity): ?>
            <div class="fed-activity-item">
                <div class="fed-activity-icon">
                    <i class="fa-solid fa-circle"></i>
                </div>
                <div class="fed-activity-content">
                    <div class="fed-activity-action"><?= htmlspecialchars($activity['description'] ?? $activity['action_type'] ?? '') ?></div>
                    <div class="fed-activity-meta">
                        <?php if (!empty($activity['user_name'])): ?>
                        by <?= htmlspecialchars($activity['user_name']) ?>
                        <?php endif; ?>
                        <?php if (!empty($activity['partner_name'])): ?>
                        with <?= htmlspecialchars($activity['partner_name']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="fed-activity-time">
                    <?php
                    if (!empty($activity['created_at'])) {
                        $diff = time() - strtotime($activity['created_at']);
                        if ($diff < 60) echo 'just now';
                        elseif ($diff < 3600) echo floor($diff / 60) . 'm ago';
                        elseif ($diff < 86400) echo floor($diff / 3600) . 'h ago';
                        elseif ($diff < 604800) echo floor($diff / 86400) . 'd ago';
                        else echo date('M j', strtotime($activity['created_at']));
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($activityTimeline)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('M j', strtotime($d['date'])), $activityTimeline)) ?>,
            datasets: [{
                label: 'Messages',
                data: <?= json_encode(array_map(fn($d) => $d['messaging'] ?? 0, $activityTimeline)) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Transactions',
                data: <?= json_encode(array_map(fn($d) => $d['transaction'] ?? 0, $activityTimeline)) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: 'rgba(255,255,255,0.7)' }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.1)' },
                    ticks: { color: 'rgba(255,255,255,0.5)' }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.1)' },
                    ticks: { color: 'rgba(255,255,255,0.5)' }
                }
            }
        }
    });
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
