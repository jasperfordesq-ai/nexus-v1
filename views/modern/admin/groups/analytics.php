<?php
/**
 * Groups Analytics Dashboard
 * Path: views/modern/admin-legacy/groups/analytics.php
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Groups Analytics';
$adminPageSubtitle = 'Growth metrics and performance insights';
$adminPageIcon = 'fa-chart-line';

// Include the standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Groups Analytics
        </h1>
        <p class="admin-page-subtitle">Growth metrics and performance insights</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/groups" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Groups
        </a>
        <a href="<?= $basePath ?>/admin-legacy/groups/export" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-download"></i>
            Export Data
        </a>
    </div>
</div>

<!-- Overview Stats -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users-rectangle"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['total_groups'] ?? 0) ?></div>
            <div class="admin-stat-label">Total Groups</div>
        </div>
        <div class="admin-stat-trend">
            <?php if (($analytics['growth_rate'] ?? 0) > 0): ?>
                <span style="color: #22c55e;">
                    <i class="fa-solid fa-arrow-up"></i> <?= number_format($analytics['growth_rate'], 1) ?>%
                </span>
            <?php else: ?>
                <span>Growth</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['total_members'] ?? 0) ?></div>
            <div class="admin-stat-label">Total Members</div>
        </div>
        <div class="admin-stat-trend">
            <span>Community</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-calendar-plus"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['new_this_month'] ?? 0) ?></div>
            <div class="admin-stat-label">New This Month</div>
        </div>
        <div class="admin-stat-trend">
            <span>30 Days</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-calculator"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['avg_members'] ?? 0) ?></div>
            <div class="admin-stat-label">Avg Members/Group</div>
        </div>
        <div class="admin-stat-trend">
            <span>Average</span>
        </div>
    </div>
</div>

<!-- Groups by Type Chart -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-chart-pie"></i>
            Groups by Type
        </h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($analytics['by_type'])): ?>
            <div class="admin-chart-container">
                <canvas id="groupTypeChart"></canvas>
            </div>
            <div class="admin-legend">
                <?php foreach ($analytics['by_type'] as $type): ?>
                    <div class="admin-legend-item">
                        <span class="admin-legend-color" style="background-color: <?= $type['color'] ?? '#6366f1' ?>"></span>
                        <span class="admin-legend-label">
                            <?= htmlspecialchars($type['name']) ?>
                            <strong><?= number_format($type['count']) ?></strong>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-chart-pie"></i>
                <p>No data available</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Growth Chart (30 Days) -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-chart-line"></i>
            Growth Trend (Last 30 Days)
        </h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($analytics['growth_chart'])): ?>
            <div class="admin-chart-container">
                <canvas id="growthChart"></canvas>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-chart-line"></i>
                <p>No growth data available</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Top Groups -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-trophy"></i>
            Top 10 Groups by Members
        </h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($analytics['top_groups'])): ?>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Group</th>
                            <th>Type</th>
                            <th>Members</th>
                            <th>Growth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['top_groups'] as $rank => $group): ?>
                            <tr>
                                <td>
                                    <span class="admin-rank-badge">
                                        <?php if ($rank < 3): ?>
                                            <i class="fa-solid fa-trophy" style="color: <?= ['#fbbf24', '#d1d5db', '#cd7f32'][$rank] ?>"></i>
                                        <?php endif; ?>
                                        <?= $rank + 1 ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($group['name']) ?></strong>
                                </td>
                                <td>
                                    <span class="admin-badge admin-badge-secondary">
                                        <?= htmlspecialchars($group['type_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-badge admin-badge-primary">
                                        <?= number_format($group['member_count']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($group['growth_30d'] ?? 0) > 0): ?>
                                        <span class="admin-trend-up">
                                            <i class="fa-solid fa-arrow-up"></i> +<?= $group['growth_30d'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.4);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-trophy"></i>
                <p>No groups found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Activity -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-clock"></i>
            Recent Activity
        </h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($analytics['recent_activity'])): ?>
            <div class="admin-activity-list">
                <?php foreach ($analytics['recent_activity'] as $activity): ?>
                    <div class="admin-activity-item">
                        <div class="admin-activity-icon">
                            <i class="fa-solid <?= $activity['icon'] ?? 'fa-circle' ?>"></i>
                        </div>
                        <div class="admin-activity-content">
                            <div class="admin-activity-text"><?= $activity['text'] ?></div>
                            <div class="admin-activity-time"><?= $activity['time'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-clock"></i>
                <p>No recent activity</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($analytics['growth_chart']) || !empty($analytics['by_type'])): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Group Type Pie Chart
<?php if (!empty($analytics['by_type'])): ?>
new Chart(document.getElementById('groupTypeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($analytics['by_type'], 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($analytics['by_type'], 'count')) ?>,
            backgroundColor: <?= json_encode(array_column($analytics['by_type'], 'color')) ?>,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    }
});
<?php endif; ?>

// Growth Line Chart
<?php if (!empty($analytics['growth_chart'])): ?>
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($analytics['growth_chart'], 'date')) ?>,
        datasets: [{
            label: 'New Groups',
            data: <?= json_encode(array_column($analytics['growth_chart'], 'groups')) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            tension: 0.4
        }, {
            label: 'New Members',
            data: <?= json_encode(array_column($analytics['growth_chart'], 'members')) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

<style>
/**
 * Groups Analytics - Gold Standard Styles
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
.admin-stat-cyan { --stat-color: #06b6d4; }
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

/* Glass Cards */
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

.admin-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-card-subtitle {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.125rem 0 0 0;
}

.admin-card-body {
    padding: 1.25rem 1.5rem;
}

/* Charts */
.admin-chart-container {
    height: 300px;
    margin: 20px 0;
}

.admin-legend {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 20px;
}

.admin-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-legend-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.875rem;
}

.admin-legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

/* Tables */
.admin-table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table thead {
    background: rgba(255, 255, 255, 0.02);
}

.admin-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.admin-table td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    color: rgba(255, 255, 255, 0.8);
}

.admin-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.admin-badge-primary {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

.admin-badge-secondary {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

.admin-rank-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
}

/* Activity */
.admin-activity-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.admin-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.admin-activity-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(99, 102, 241, 0.15);
    border-radius: 8px;
    color: #818cf8;
}

.admin-activity-content {
    flex: 1;
}

.admin-activity-text {
    color: #fff;
    margin-bottom: 4px;
}

.admin-activity-time {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.5);
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
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
