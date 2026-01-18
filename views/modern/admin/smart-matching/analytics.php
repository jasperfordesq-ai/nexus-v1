<?php
/**
 * Admin Smart Matching Analytics - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Analytics';
$adminPageSubtitle = 'Smart Matching';
$adminPageIcon = 'fa-chart-line';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Safe defaults
$stats = $stats ?? [];
$score_distribution = $score_distribution ?? [];
$distance_distribution = $distance_distribution ?? [];
$weekly_trends = $weekly_trends ?? [];
$daily_trends = $daily_trends ?? [];
$conversion_metrics = $conversion_metrics ?? [];
$top_categories = $top_categories ?? [];
$user_engagement = $user_engagement ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/smart-matching" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Smart Matching Analytics
        </h1>
        <p class="admin-page-subtitle">Deep performance insights and metrics</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/smart-matching/configuration" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-gear"></i> Configure
        </a>
    </div>
</div>

<!-- Time Period Filter -->
<div class="time-filter-row">
    <button class="time-btn" data-period="7d">7 Days</button>
    <button class="time-btn active" data-period="30d">30 Days</button>
    <button class="time-btn" data-period="90d">90 Days</button>
    <button class="time-btn" data-period="all">All Time</button>
</div>

<!-- Key Metrics Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['total_matches_month'] ?? 0) ?></div>
        <div class="stat-label">Total Matches</div>
        <span class="stat-badge neutral">This Month</span>
    </div>
    <div class="stat-card positive">
        <div class="stat-value"><?= number_format($conversion_metrics['total_conversions'] ?? 0) ?></div>
        <div class="stat-label">Conversions</div>
        <span class="stat-badge up"><i class="fa-solid fa-arrow-up"></i> Active</span>
    </div>
    <div class="stat-card warning">
        <div class="stat-value"><?= $conversion_metrics['conversion_rate'] ?? 0 ?>%</div>
        <div class="stat-label">Conversion Rate</div>
        <span class="stat-badge <?= ($conversion_metrics['conversion_rate'] ?? 0) >= 5 ? 'up' : 'down' ?>">Match to Transaction</span>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= round($stats['avg_match_score'] ?? 0, 1) ?></div>
        <div class="stat-label">Avg Match Score</div>
        <span class="stat-badge neutral">Platform Wide</span>
    </div>
    <div class="stat-card hot">
        <div class="stat-value"><?= number_format($stats['hot_matches_count'] ?? 0) ?></div>
        <div class="stat-label">Hot Matches</div>
        <span class="stat-badge up">Score 85+</span>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= round($conversion_metrics['avg_time_to_conversion_hours'] ?? 0, 0) ?>h</div>
        <div class="stat-label">Time to Convert</div>
        <span class="stat-badge neutral">Average</span>
    </div>
</div>

<!-- Weekly Trends Chart -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <i class="fa-solid fa-chart-area"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Weekly Match Trends</h3>
            <p class="admin-card-subtitle">Total and hot matches over time</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="chart-container tall">
            <canvas id="trendsChart"></canvas>
        </div>
    </div>
</div>

<div class="analytics-grid">
    <!-- Score Distribution -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #f97316);">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Score Distribution</h3>
                <p class="admin-card-subtitle">Match scores breakdown</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="chart-container">
                <canvas id="scoreChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Distance Distribution -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #14b8a6);">
                <i class="fa-solid fa-location-dot"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Distance Distribution</h3>
                <p class="admin-card-subtitle">Geographic match spread</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="chart-container">
                <canvas id="distanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- User Engagement -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ec4899, #f43f5e);">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">User Engagement</h3>
                <p class="admin-card-subtitle">Matching preferences</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="engagement-grid">
                <div class="engagement-item">
                    <div class="engagement-value"><?= number_format($user_engagement['users_with_preferences'] ?? 0) ?></div>
                    <div class="engagement-label">Set Preferences</div>
                </div>
                <div class="engagement-item">
                    <div class="engagement-value"><?= number_format($user_engagement['users_with_hot_notifications'] ?? 0) ?></div>
                    <div class="engagement-label">Hot Notifications</div>
                </div>
                <div class="engagement-item">
                    <div class="engagement-value"><?= number_format($user_engagement['users_with_mutual_notifications'] ?? 0) ?></div>
                    <div class="engagement-label">Mutual Notifications</div>
                </div>
                <div class="engagement-item">
                    <div class="engagement-value"><?= round($user_engagement['avg_max_distance'] ?? 25, 0) ?> km</div>
                    <div class="engagement-label">Avg Max Distance</div>
                </div>
                <div class="engagement-item">
                    <div class="engagement-value"><?= round($user_engagement['avg_min_score'] ?? 50, 0) ?></div>
                    <div class="engagement-label">Avg Min Score</div>
                </div>
                <div class="engagement-item">
                    <div class="engagement-value"><?= number_format($stats['active_users_matching'] ?? 0) ?></div>
                    <div class="engagement-label">Active Users</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Conversion Funnel -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #3b82f6, #6366f1);">
                <i class="fa-solid fa-filter"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Conversion Funnel</h3>
                <p class="admin-card-subtitle">Match to completion flow</p>
            </div>
        </div>
        <div class="admin-card-body">
            <?php
            $funnel = $conversion_metrics['conversion_funnel'] ?? [
                'matched' => $conversion_metrics['matched'] ?? 0,
                'viewed' => $conversion_metrics['viewed'] ?? 0,
                'contacted' => $conversion_metrics['contacted'] ?? 0,
                'completed' => $conversion_metrics['completed'] ?? 0,
            ];
            $maxFunnel = max(1, $funnel['matched'] ?? 1);
            ?>
            <div class="funnel-container">
                <div class="funnel-stage">
                    <div class="funnel-label">Matched</div>
                    <div class="funnel-bar-wrap">
                        <div class="funnel-bar stage-1" style="width: 100%;">
                            <?= number_format($funnel['matched'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="funnel-rate">100%</div>
                </div>
                <div class="funnel-stage">
                    <div class="funnel-label">Viewed</div>
                    <?php $viewedPct = $maxFunnel > 0 ? (($funnel['viewed'] ?? 0) / $maxFunnel * 100) : 0; ?>
                    <div class="funnel-bar-wrap">
                        <div class="funnel-bar stage-2" style="width: <?= max(10, $viewedPct) ?>%;">
                            <?= number_format($funnel['viewed'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="funnel-rate"><?= round($viewedPct, 1) ?>%</div>
                </div>
                <div class="funnel-stage">
                    <div class="funnel-label">Contacted</div>
                    <?php $contactedPct = $maxFunnel > 0 ? (($funnel['contacted'] ?? 0) / $maxFunnel * 100) : 0; ?>
                    <div class="funnel-bar-wrap">
                        <div class="funnel-bar stage-3" style="width: <?= max(10, $contactedPct) ?>%;">
                            <?= number_format($funnel['contacted'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="funnel-rate"><?= round($contactedPct, 1) ?>%</div>
                </div>
                <div class="funnel-stage">
                    <div class="funnel-label">Completed</div>
                    <?php $completedPct = $maxFunnel > 0 ? (($funnel['completed'] ?? 0) / $maxFunnel * 100) : 0; ?>
                    <div class="funnel-bar-wrap">
                        <div class="funnel-bar stage-4" style="width: <?= max(10, $completedPct) ?>%;">
                            <?= number_format($funnel['completed'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="funnel-rate"><?= round($completedPct, 1) ?>%</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Performance -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #0ea5e9);">
            <i class="fa-solid fa-tags"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Category Performance</h3>
            <p class="admin-card-subtitle">Match statistics by category</p>
        </div>
    </div>
    <?php if (empty($top_categories)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-chart-bar"></i></div>
        <h3>No Data Yet</h3>
        <p>Category analytics will appear here once matches are generated.</p>
    </div>
    <?php else: ?>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Matches</th>
                    <th>Avg Score</th>
                    <th>Conversions</th>
                    <th>Conv. Rate</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_categories as $cat): ?>
                <?php
                $matchCount = (int)($cat['match_count'] ?? 0);
                $conversions = (int)($cat['conversions'] ?? 0);
                $avgScore = round((float)($cat['avg_score'] ?? 0), 1);
                $convRate = $matchCount > 0 ? round(($conversions / $matchCount) * 100, 1) : 0;
                $scoreClass = $avgScore >= 70 ? 'good' : ($avgScore >= 50 ? 'medium' : 'low');
                ?>
                <tr>
                    <td>
                        <div class="category-badge">
                            <span class="category-dot" style="background: <?= htmlspecialchars($cat['color'] ?? '#6366f1') ?>;"></span>
                            <?= htmlspecialchars($cat['name'] ?? 'Unknown') ?>
                        </div>
                    </td>
                    <td><strong><?= number_format($matchCount) ?></strong></td>
                    <td><span class="score-badge <?= $scoreClass ?>"><?= $avgScore ?>%</span></td>
                    <td><?= number_format($conversions) ?></td>
                    <td><strong><?= $convRate ?>%</strong></td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= min(100, $convRate * 5) ?>%;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Time Filter */
.time-filter-row {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    background: rgba(30, 41, 59, 0.6);
    padding: 0.5rem;
    border-radius: 12px;
    width: fit-content;
}

.time-btn {
    padding: 0.6rem 1.25rem;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.6);
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.time-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.time-btn:hover:not(.active) {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(30, 41, 59, 0.7));
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.25rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
}

.stat-card.positive::before { background: linear-gradient(90deg, #10b981, #06b6d4); }
.stat-card.warning::before { background: linear-gradient(90deg, #f59e0b, #f97316); }
.stat-card.hot::before { background: linear-gradient(90deg, #f97316, #ef4444); }

.stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #f1f5f9;
}

.stat-card.positive .stat-value { color: #34d399; }
.stat-card.warning .stat-value { color: #fbbf24; }
.stat-card.hot .stat-value { color: #f97316; }

.stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.25rem;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.stat-badge {
    display: inline-block;
    padding: 0.25rem 0.65rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.stat-badge.neutral { background: rgba(100, 116, 139, 0.3); color: #94a3b8; }
.stat-badge.up { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.stat-badge.down { background: rgba(239, 68, 68, 0.2); color: #f87171; }

/* Analytics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Chart Container */
.chart-container {
    height: 280px;
    position: relative;
}

.chart-container.tall {
    height: 350px;
}

/* Engagement Grid */
.engagement-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.engagement-item {
    padding: 1rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    text-align: center;
}

.engagement-value {
    font-size: 1.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.engagement-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.25rem;
    font-weight: 600;
}

/* Funnel */
.funnel-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.funnel-stage {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.funnel-label {
    width: 80px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    text-align: right;
}

.funnel-bar-wrap {
    flex: 1;
}

.funnel-bar {
    height: 36px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 12px;
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
}

.funnel-bar.stage-1 { background: linear-gradient(135deg, #6366f1, #818cf8); }
.funnel-bar.stage-2 { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
.funnel-bar.stage-3 { background: linear-gradient(135deg, #06b6d4, #22d3ee); }
.funnel-bar.stage-4 { background: linear-gradient(135deg, #10b981, #34d399); }

.funnel-rate {
    width: 50px;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Category Badge */
.category-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.category-dot {
    width: 12px;
    height: 12px;
    border-radius: 4px;
}

/* Score Badge */
.score-badge {
    display: inline-block;
    padding: 0.25rem 0.65rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 700;
}

.score-badge.good { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.score-badge.medium { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.score-badge.low { background: rgba(239, 68, 68, 0.2); color: #f87171; }

/* Progress Bar */
.progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    min-width: 100px;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, #10b981, #06b6d4);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state-icon {
    font-size: 2.5rem;
    opacity: 0.3;
    margin-bottom: 1rem;
    color: #6366f1;
}

.empty-state h3 {
    margin: 0 0 0.5rem;
    color: rgba(255, 255, 255, 0.7);
}

.empty-state p {
    margin: 0;
    color: rgba(255, 255, 255, 0.5);
}

/* Mobile */
@media (max-width: 1200px) {
    .analytics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .engagement-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .funnel-label {
        width: 60px;
        font-size: 0.75rem;
    }

    .time-filter-row {
        flex-wrap: wrap;
        width: 100%;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const textColor = '#94a3b8';
    const gridColor = 'rgba(255, 255, 255, 0.05)';

    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;

    // Weekly Trends Chart
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        new Chart(trendsCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($w) {
                    return isset($w['week_start']) ? date('M d', strtotime($w['week_start'])) : $w['week'] ?? '';
                }, $weekly_trends)) ?>,
                datasets: [{
                    label: 'Total Matches',
                    data: <?= json_encode(array_column($weekly_trends, 'match_count')) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }, {
                    label: 'Hot Matches',
                    data: <?= json_encode(array_column($weekly_trends, 'hot_count')) ?>,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { color: gridColor } }
                }
            }
        });
    }

    // Score Distribution Chart
    const scoreCtx = document.getElementById('scoreChart');
    if (scoreCtx) {
        new Chart(scoreCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['0-40 (Low)', '40-60 (Medium)', '60-80 (Good)', '80-100 (Hot)'],
                datasets: [{
                    data: [
                        <?= (int)($score_distribution['0-40'] ?? 0) ?>,
                        <?= (int)($score_distribution['40-60'] ?? 0) ?>,
                        <?= (int)($score_distribution['60-80'] ?? 0) ?>,
                        <?= (int)($score_distribution['80-100'] ?? 0) ?>
                    ],
                    backgroundColor: ['#64748b', '#6366f1', '#8b5cf6', '#f97316'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } } }
            }
        });
    }

    // Distance Distribution Chart
    const distanceCtx = document.getElementById('distanceChart');
    if (distanceCtx) {
        new Chart(distanceCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Walking', 'Local', 'City', 'Regional', 'Distant'],
                datasets: [{
                    label: 'Matches',
                    data: [
                        <?= (int)($distance_distribution['walking'] ?? 0) ?>,
                        <?= (int)($distance_distribution['local'] ?? 0) ?>,
                        <?= (int)($distance_distribution['city'] ?? 0) ?>,
                        <?= (int)($distance_distribution['regional'] ?? 0) ?>,
                        <?= (int)($distance_distribution['distant'] ?? 0) ?>
                    ],
                    backgroundColor: ['#10b981', '#14b8a6', '#06b6d4', '#3b82f6', '#6366f1'],
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Time filter buttons
    document.querySelectorAll('.time-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
