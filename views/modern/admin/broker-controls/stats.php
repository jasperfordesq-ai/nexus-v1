<?php
/**
 * Broker Controls Statistics
 * Analytics and metrics for broker control features
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Broker Statistics';
$adminPageSubtitle = 'Analytics for broker control features';
$adminPageIcon = 'fa-chart-pie';

require dirname(__DIR__) . '/partials/admin-header.php';

$stats = $stats ?? [];
$period = $period ?? '30';
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Broker Statistics
        </h1>
        <p class="admin-page-subtitle">Analytics and metrics for broker control features</p>
    </div>
    <div class="admin-page-header-actions">
        <div class="period-selector">
            <a href="?period=7" class="period-btn <?= $period === '7' ? 'active' : '' ?>">7 days</a>
            <a href="?period=30" class="period-btn <?= $period === '30' ? 'active' : '' ?>">30 days</a>
            <a href="?period=90" class="period-btn <?= $period === '90' ? 'active' : '' ?>">90 days</a>
            <a href="?period=365" class="period-btn <?= $period === '365' ? 'active' : '' ?>">1 year</a>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card stat-card-primary">
        <div class="stat-icon">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['total_exchanges'] ?? 0) ?></div>
            <div class="stat-label">Total Exchanges</div>
            <div class="stat-change <?= ($stats['exchanges_change'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                <i class="fa-solid fa-arrow-<?= ($stats['exchanges_change'] ?? 0) >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($stats['exchanges_change'] ?? 0) ?>% vs previous period
            </div>
        </div>
    </div>

    <div class="stat-card stat-card-success">
        <div class="stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['completed_exchanges'] ?? 0) ?></div>
            <div class="stat-label">Completed Exchanges</div>
            <div class="stat-secondary">
                <?= $stats['completion_rate'] ?? 0 ?>% completion rate
            </div>
        </div>
    </div>

    <div class="stat-card stat-card-warning">
        <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['pending_broker'] ?? 0) ?></div>
            <div class="stat-label">Pending Approval</div>
            <div class="stat-secondary">
                Avg wait: <?= $stats['avg_approval_time'] ?? '0' ?>h
            </div>
        </div>
    </div>

    <div class="stat-card stat-card-info">
        <div class="stat-icon">
            <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['messages_reviewed'] ?? 0) ?></div>
            <div class="stat-label">Messages Reviewed</div>
            <div class="stat-secondary">
                <?= $stats['flagged_messages'] ?? 0 ?> flagged
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-row">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-chart-line"></i> Exchange Activity</h2>
        </div>
        <div class="admin-card-body">
            <div class="chart-placeholder">
                <canvas id="exchangeChart"></canvas>
            </div>
        </div>
    </div>

    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-chart-pie"></i> Exchange Status Distribution</h2>
        </div>
        <div class="admin-card-body">
            <div class="status-distribution">
                <?php
                $statuses = [
                    ['label' => 'Completed', 'count' => $stats['status_completed'] ?? 0, 'color' => '#10b981'],
                    ['label' => 'In Progress', 'count' => $stats['status_in_progress'] ?? 0, 'color' => '#6366f1'],
                    ['label' => 'Pending', 'count' => $stats['status_pending'] ?? 0, 'color' => '#f59e0b'],
                    ['label' => 'Cancelled', 'count' => $stats['status_cancelled'] ?? 0, 'color' => '#ef4444'],
                ];
                $total = array_sum(array_column($statuses, 'count')) ?: 1;
                ?>
                <?php foreach ($statuses as $status): ?>
                <div class="status-bar-item">
                    <div class="status-bar-header">
                        <span class="status-bar-label"><?= $status['label'] ?></span>
                        <span class="status-bar-value"><?= number_format($status['count']) ?></span>
                    </div>
                    <div class="status-bar-track">
                        <div class="status-bar-fill" style="width: <?= round(($status['count'] / $total) * 100) ?>%; background: <?= $status['color'] ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Risk Tags & Monitoring -->
<div class="charts-row">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-tags"></i> Risk Tag Distribution</h2>
        </div>
        <div class="admin-card-body">
            <div class="risk-distribution">
                <?php
                $riskLevels = [
                    ['level' => 'Critical', 'count' => $stats['risk_critical'] ?? 0, 'color' => '#ef4444', 'icon' => 'fa-skull-crossbones'],
                    ['level' => 'High', 'count' => $stats['risk_high'] ?? 0, 'color' => '#f59e0b', 'icon' => 'fa-exclamation-triangle'],
                    ['level' => 'Medium', 'count' => $stats['risk_medium'] ?? 0, 'color' => '#3b82f6', 'icon' => 'fa-exclamation-circle'],
                    ['level' => 'Low', 'count' => $stats['risk_low'] ?? 0, 'color' => '#6b7280', 'icon' => 'fa-info-circle'],
                ];
                ?>
                <div class="risk-cards">
                    <?php foreach ($riskLevels as $risk): ?>
                    <div class="risk-card" style="--risk-color: <?= $risk['color'] ?>;">
                        <i class="fa-solid <?= $risk['icon'] ?>"></i>
                        <span class="risk-count"><?= number_format($risk['count']) ?></span>
                        <span class="risk-label"><?= $risk['level'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-user-shield"></i> User Monitoring</h2>
        </div>
        <div class="admin-card-body">
            <div class="monitoring-stats-grid">
                <div class="monitoring-stat">
                    <div class="monitoring-stat-icon" style="--stat-color: #ef4444;">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div class="monitoring-stat-content">
                        <span class="monitoring-stat-value"><?= $stats['users_restricted'] ?? 0 ?></span>
                        <span class="monitoring-stat-label">Messaging Disabled</span>
                    </div>
                </div>
                <div class="monitoring-stat">
                    <div class="monitoring-stat-icon" style="--stat-color: #f59e0b;">
                        <i class="fa-solid fa-eye"></i>
                    </div>
                    <div class="monitoring-stat-content">
                        <span class="monitoring-stat-value"><?= $stats['users_monitored'] ?? 0 ?></span>
                        <span class="monitoring-stat-label">Under Monitoring</span>
                    </div>
                </div>
                <div class="monitoring-stat">
                    <div class="monitoring-stat-icon" style="--stat-color: #10b981;">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="monitoring-stat-content">
                        <span class="monitoring-stat-value"><?= $stats['new_members_period'] ?? 0 ?></span>
                        <span class="monitoring-stat-label">New Members</span>
                    </div>
                </div>
                <div class="monitoring-stat">
                    <div class="monitoring-stat-icon" style="--stat-color: #6366f1;">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                    <div class="monitoring-stat-content">
                        <span class="monitoring-stat-value"><?= $stats['first_contacts_period'] ?? 0 ?></span>
                        <span class="monitoring-stat-label">First Contacts</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title"><i class="fa-solid fa-history"></i> Recent Broker Activity</h2>
    </div>
    <div class="admin-card-body">
        <?php if (empty($stats['recent_activity'])): ?>
        <p class="text-muted text-center">No recent broker activity.</p>
        <?php else: ?>
        <div class="activity-list">
            <?php foreach ($stats['recent_activity'] ?? [] as $activity): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <?php
                    $iconClass = match($activity['type'] ?? '') {
                        'exchange_approved' => 'fa-check text-success',
                        'exchange_rejected' => 'fa-times text-danger',
                        'message_reviewed' => 'fa-envelope text-info',
                        'message_flagged' => 'fa-flag text-warning',
                        'user_restricted' => 'fa-ban text-danger',
                        'risk_tag_added' => 'fa-tag text-warning',
                        default => 'fa-circle text-secondary'
                    };
                    ?>
                    <i class="fa-solid <?= $iconClass ?>"></i>
                </div>
                <div class="activity-content">
                    <span class="activity-text"><?= htmlspecialchars($activity['description'] ?? '') ?></span>
                    <span class="activity-time"><?= isset($activity['created_at']) ? date('M j, g:i A', strtotime($activity['created_at'])) : '' ?></span>
                </div>
                <div class="activity-actor">
                    <?= htmlspecialchars($activity['actor_name'] ?? 'System') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.stat-card {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
    padding: 1.5rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
}
.stat-card .stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.stat-card-primary .stat-icon { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
.stat-card-success .stat-icon { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.stat-card-warning .stat-icon { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.stat-card-info .stat-icon { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
.stat-content {
    flex: 1;
}
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
}
.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}
.stat-change {
    font-size: 0.8rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.stat-change.positive { color: #34d399; }
.stat-change.negative { color: #f87171; }
.stat-secondary {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
}
.charts-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.chart-placeholder {
    height: 250px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.status-distribution {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.status-bar-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.status-bar-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}
.status-bar-value {
    font-weight: 600;
}
.status-bar-track {
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
}
.status-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
.risk-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.risk-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.25rem;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    color: var(--risk-color);
}
.risk-card i {
    font-size: 1.5rem;
}
.risk-count {
    font-size: 1.5rem;
    font-weight: 700;
}
.risk-label {
    font-size: 0.8rem;
    opacity: 0.8;
}
.monitoring-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
.monitoring-stat {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
}
.monitoring-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--stat-color) 15%, transparent);
    color: var(--stat-color);
}
.monitoring-stat-value {
    font-size: 1.25rem;
    font-weight: 700;
}
.monitoring-stat-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
}
.monitoring-stat-content {
    display: flex;
    flex-direction: column;
}
.activity-list {
    display: flex;
    flex-direction: column;
}
.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
}
.activity-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.activity-text {
    font-size: 0.95rem;
}
.activity-time {
    font-size: 0.8rem;
    color: var(--text-secondary);
}
.activity-actor {
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.text-success { color: #34d399; }
.text-danger { color: #f87171; }
.text-warning { color: #fbbf24; }
.text-info { color: #60a5fa; }
.text-secondary { color: var(--text-secondary); }
.text-center { text-align: center; }
.text-muted { color: var(--text-secondary); }
.period-selector {
    display: flex;
    gap: 0.25rem;
    background: rgba(255,255,255,0.05);
    padding: 0.25rem;
    border-radius: 8px;
}
.period-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s;
}
.period-btn:hover {
    color: var(--text-primary);
}
.period-btn.active {
    background: var(--color-primary-500, #6366f1);
    color: #fff;
}
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 0.75rem;
    opacity: 0.7;
}
.back-link:hover { opacity: 1; }

@media (max-width: 1280px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .risk-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 1024px) {
    .charts-row {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .risk-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .monitoring-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('exchangeChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($stats['chart_labels'] ?? ['Week 1', 'Week 2', 'Week 3', 'Week 4']) ?>,
                datasets: [{
                    label: 'Exchanges',
                    data: <?= json_encode($stats['chart_data'] ?? [10, 15, 12, 18]) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4
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
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.6)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.6)'
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
