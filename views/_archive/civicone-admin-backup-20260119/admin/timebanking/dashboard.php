<?php
/**
 * Admin Timebanking Dashboard - Gold Standard
 * STANDALONE admin interface with analytics
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Analytics';
$adminPageSubtitle = 'Timebanking';
$adminPageIcon = 'fa-chart-line';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Timebanking Analytics
        </h1>
        <p class="admin-page-subtitle">Monitor transactions, alerts, and user activity</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/timebanking/alerts" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-bell"></i> Alerts
            <?php if (($alertCounts['new'] ?? 0) > 0): ?>
            <span class="alert-count"><?= $alertCounts['new'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/admin-legacy/timebanking/org-wallets" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-building"></i> Org Wallets
        </a>
        <form action="<?= $basePath ?>/admin-legacy/timebanking/run-detection" method="POST" style="display: inline;">
            <?= Csrf::input() ?>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-radar"></i> Run Detection
            </button>
        </form>
    </div>
</div>

<!-- Stats Grid -->
<div class="tb-stats-grid">
    <div class="admin-glass-card tb-stat-card">
        <div class="tb-stat-icon purple">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div class="tb-stat-value"><?= number_format($stats['total_credits_circulation'] ?? 0, 0) ?></div>
        <div class="tb-stat-label">Total Credits in Circulation</div>
    </div>
    <div class="admin-glass-card tb-stat-card">
        <div class="tb-stat-icon green">
            <i class="fa-solid fa-arrow-right-arrow-left"></i>
        </div>
        <div class="tb-stat-value"><?= number_format($stats['transaction_volume_30d'] ?? 0, 0) ?></div>
        <div class="tb-stat-label">Transaction Volume (30d)</div>
    </div>
    <div class="admin-glass-card tb-stat-card">
        <div class="tb-stat-icon blue">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="tb-stat-value"><?= number_format($stats['active_traders_30d'] ?? 0) ?></div>
        <div class="tb-stat-label">Active Traders (30d)</div>
    </div>
    <div class="admin-glass-card tb-stat-card">
        <div class="tb-stat-icon orange">
            <i class="fa-solid fa-calculator"></i>
        </div>
        <div class="tb-stat-value"><?= number_format($stats['avg_transaction_size'] ?? 0, 1) ?></div>
        <div class="tb-stat-label">Avg Transaction Size</div>
    </div>
</div>

<!-- Main Grid: Chart + Alerts -->
<div class="tb-main-grid">
    <!-- Monthly Trends Chart -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-cyan">
                <i class="fa-solid fa-chart-area"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Monthly Transaction Trends</h3>
                <p class="admin-card-subtitle">Volume and count over time</p>
            </div>
        </div>
        <div class="tb-chart-container">
            <canvas id="trendsChart"></canvas>
        </div>
    </div>

    <!-- Recent Alerts -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Recent Alerts</h3>
                <p class="admin-card-subtitle">
                    <?php if (($alertCounts['new'] ?? 0) > 0): ?>
                        <span class="new-badge"><?= $alertCounts['new'] ?> new</span>
                    <?php else: ?>
                        System monitoring
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= $basePath ?>/admin-legacy/timebanking/alerts" class="admin-btn admin-btn-secondary admin-btn-sm">View all</a>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($recentAlerts)): ?>
            <div class="admin-empty-state" style="padding: 2rem;">
                <div class="admin-empty-icon" style="background: rgba(34, 197, 94, 0.1);">
                    <i class="fa-solid fa-shield-check" style="color: #4ade80;"></i>
                </div>
                <h3 class="admin-empty-title">All Clear!</h3>
                <p class="admin-empty-text">No alerts to review.</p>
            </div>
            <?php else: ?>
            <div class="tb-alerts-list">
                <?php foreach ($recentAlerts as $alert): ?>
                <a href="<?= $basePath ?>/admin-legacy/timebanking/alert/<?= $alert['id'] ?>" class="tb-alert-item">
                    <div class="tb-alert-icon <?= $alert['severity'] ?>">
                        <?php if ($alert['alert_type'] === 'large_transfer'): ?>
                            <i class="fa-solid fa-dollar-sign"></i>
                        <?php elseif ($alert['alert_type'] === 'high_velocity'): ?>
                            <i class="fa-solid fa-bolt"></i>
                        <?php elseif ($alert['alert_type'] === 'circular_transfer'): ?>
                            <i class="fa-solid fa-rotate"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-clock"></i>
                        <?php endif; ?>
                    </div>
                    <div class="tb-alert-content">
                        <div class="tb-alert-title"><?= ucwords(str_replace('_', ' ', $alert['alert_type'])) ?></div>
                        <div class="tb-alert-desc"><?= htmlspecialchars($alert['user_name'] ?? 'Unknown user') ?></div>
                        <div class="tb-alert-time"><?= date('M d, g:i A', strtotime($alert['created_at'])) ?></div>
                    </div>
                    <span class="tb-alert-badge <?= $alert['severity'] ?>"><?= $alert['severity'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Three Column Grid: Top Earners, Top Spenders, Highest Balances -->
<div class="tb-triple-grid">
    <!-- Top Earners -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #fbbf24, #d97706);">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Top Earners (30d)</h3>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($topEarners)): ?>
            <div class="admin-empty-state" style="padding: 2rem;">
                <p class="admin-empty-text">No data available</p>
            </div>
            <?php else: ?>
            <div class="tb-leaderboard">
                <?php foreach ($topEarners as $i => $user): ?>
                <div class="tb-leader-item">
                    <div class="tb-leader-rank <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>">
                        <?= $i + 1 ?>
                    </div>
                    <div class="tb-leader-avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="tb-leader-info">
                        <div class="tb-leader-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                        <div class="tb-leader-meta"><?= $user['transaction_count'] ?> transactions</div>
                    </div>
                    <div class="tb-leader-value green">+<?= number_format($user['total_earned'], 1) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Spenders -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f87171, #ef4444);">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Top Spenders (30d)</h3>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($topSpenders)): ?>
            <div class="admin-empty-state" style="padding: 2rem;">
                <p class="admin-empty-text">No data available</p>
            </div>
            <?php else: ?>
            <div class="tb-leaderboard">
                <?php foreach ($topSpenders as $i => $user): ?>
                <div class="tb-leader-item">
                    <div class="tb-leader-rank <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>">
                        <?= $i + 1 ?>
                    </div>
                    <div class="tb-leader-avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="tb-leader-info">
                        <div class="tb-leader-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                        <div class="tb-leader-meta"><?= $user['transaction_count'] ?> transactions</div>
                    </div>
                    <div class="tb-leader-value red">-<?= number_format($user['total_spent'], 1) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Highest Balances -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #a78bfa, #8b5cf6);">
                <i class="fa-solid fa-piggy-bank"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Highest Balances</h3>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($highestBalances)): ?>
            <div class="admin-empty-state" style="padding: 2rem;">
                <p class="admin-empty-text">No data available</p>
            </div>
            <?php else: ?>
            <div class="tb-leaderboard">
                <?php foreach ($highestBalances as $i => $user): ?>
                <div class="tb-leader-item">
                    <div class="tb-leader-rank <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>">
                        <?= $i + 1 ?>
                    </div>
                    <div class="tb-leader-avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="tb-leader-info">
                        <div class="tb-leader-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                        <div class="tb-leader-meta"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <div class="tb-leader-value blue"><?= number_format($user['balance'], 1) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Organization Wallets Summary -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #2dd4bf, #14b8a6);">
            <i class="fa-solid fa-building-columns"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Organization Wallets Overview</h3>
            <p class="admin-card-subtitle">Aggregate organization balances</p>
        </div>
        <a href="<?= $basePath ?>/admin-legacy/timebanking/org-wallets" class="admin-btn admin-btn-secondary admin-btn-sm">View all</a>
    </div>
    <div class="admin-card-body">
        <div class="tb-org-stats">
            <div class="tb-org-stat">
                <div class="tb-org-stat-value"><?= $orgSummary['org_count'] ?? 0 ?></div>
                <div class="tb-org-stat-label">Organizations</div>
            </div>
            <div class="tb-org-stat">
                <div class="tb-org-stat-value"><?= number_format($orgSummary['total_balance'] ?? 0, 1) ?></div>
                <div class="tb-org-stat-label">Total Balance (HRS)</div>
            </div>
            <div class="tb-org-stat">
                <div class="tb-org-stat-value"><?= number_format($orgSummary['avg_balance'] ?? 0, 1) ?></div>
                <div class="tb-org-stat-label">Average Balance</div>
            </div>
        </div>
        <?php if (($pendingRequests ?? 0) > 0): ?>
        <div style="text-align: center; margin-top: 1rem;">
            <a href="<?= $basePath ?>/admin-legacy/timebanking/org-wallets" class="admin-btn admin-btn-secondary">
                <i class="fa-solid fa-clock"></i> <?= $pendingRequests ?> Pending Transfer Requests
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Timebanking Dashboard Specific Styles */

/* Stats Grid */
.tb-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.tb-stat-card {
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.tb-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(255,255,255,0.05), transparent);
    transform: translate(20px, -20px);
}

.tb-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

.tb-stat-icon.purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
.tb-stat-icon.green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.tb-stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.tb-stat-icon.orange { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }

.tb-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    line-height: 1.2;
}

.tb-stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 4px;
}

/* Main Grid */
.tb-main-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Chart Container */
.tb-chart-container {
    padding: 1.5rem;
    height: 300px;
}

/* Triple Grid */
.tb-triple-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

/* Alerts List */
.tb-alerts-list {
    max-height: 350px;
    overflow-y: auto;
}

.tb-alert-item {
    display: flex;
    align-items: flex-start;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    gap: 1rem;
    transition: background 0.15s;
    text-decoration: none;
}

.tb-alert-item:hover {
    background: rgba(99, 102, 241, 0.05);
}

.tb-alert-item:last-child {
    border-bottom: none;
}

.tb-alert-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tb-alert-icon.high { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.tb-alert-icon.medium { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
.tb-alert-icon.low { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }

.tb-alert-content {
    flex: 1;
    min-width: 0;
}

.tb-alert-title {
    font-weight: 600;
    color: #f1f5f9;
    font-size: 0.9rem;
}

.tb-alert-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

.tb-alert-time {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 4px;
}

.tb-alert-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.tb-alert-badge.high { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.tb-alert-badge.medium { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
.tb-alert-badge.low { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }

/* Leaderboard */
.tb-leaderboard {
    max-height: 350px;
    overflow-y: auto;
}

.tb-leader-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    gap: 0.75rem;
}

.tb-leader-item:last-child {
    border-bottom: none;
}

.tb-leader-rank {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    flex-shrink: 0;
}

.tb-leader-rank.gold { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
.tb-leader-rank.silver { background: rgba(156, 163, 175, 0.2); color: #d1d5db; }
.tb-leader-rank.bronze { background: rgba(180, 83, 9, 0.2); color: #d97706; }

.tb-leader-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
    overflow: hidden;
}

.tb-leader-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tb-leader-info {
    flex: 1;
    min-width: 0;
}

.tb-leader-name {
    font-weight: 600;
    color: #f1f5f9;
    font-size: 0.9rem;
}

.tb-leader-meta {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.tb-leader-value {
    font-weight: 700;
    font-size: 1rem;
}

.tb-leader-value.green { color: #34d399; }
.tb-leader-value.red { color: #f87171; }
.tb-leader-value.blue { color: #60a5fa; }

/* Org Stats */
.tb-org-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.tb-org-stat {
    text-align: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
}

.tb-org-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #f1f5f9;
}

.tb-org-stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 4px;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.alert-count {
    background: #ef4444;
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7rem;
}

.new-badge {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
}

.admin-empty-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.admin-empty-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.25rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .tb-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .tb-main-grid {
        grid-template-columns: 1fr;
    }

    .tb-triple-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .tb-stats-grid {
        grid-template-columns: 1fr;
    }

    .tb-stat-value {
        font-size: 1.5rem;
    }

    .tb-org-stats {
        grid-template-columns: 1fr;
    }

    .admin-page-header-actions {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Trends Chart
const ctx = document.getElementById('trendsChart');
if (ctx) {
    const monthlyData = <?= json_encode($monthlyTrends ?? []) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthlyData.map(d => d.month),
            datasets: [{
                label: 'Transaction Volume',
                data: monthlyData.map(d => d.total_volume || 0),
                borderColor: '#60a5fa',
                backgroundColor: 'rgba(96, 165, 250, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
            }, {
                label: 'Transaction Count',
                data: monthlyData.map(d => d.transaction_count || 0),
                borderColor: '#34d399',
                backgroundColor: 'transparent',
                borderDash: [5, 5],
                tension: 0.4,
                borderWidth: 2,
                yAxisID: 'y1',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#94a3b8',
                        padding: 20,
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#94a3b8' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#94a3b8' }
                },
                x: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
