<?php
/**
 * Admin Gamification Analytics - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Analytics';
$adminPageSubtitle = 'Gamification';
$adminPageIcon = 'fa-chart-line';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$overview = $data['overview'] ?? [];
$badgeTrends = $data['badge_trends'] ?? [];
$popularBadges = $data['popular_badges'] ?? [];
$rarestBadges = $data['rarest_badges'] ?? [];
$topEarners = $data['top_earners'] ?? [];
$recentActivity = $data['recent_activity'] ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Achievement Analytics
        </h1>
        <p class="admin-page-subtitle">Insights into your community's gamification engagement</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/gamification" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Gamification
        </a>
    </div>
</div>

<!-- Stats Overview -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-star"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($overview['total_xp'] ?? 0) ?></div>
            <div class="admin-stat-label">Total XP Earned</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-amber">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-medal"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($overview['total_badges'] ?? 0) ?></div>
            <div class="admin-stat-label">Badges Awarded</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $overview['engagement_rate'] ?? 0 ?>%</div>
            <div class="admin-stat-label">Engagement Rate</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-chart-bar"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($overview['avg_xp'] ?? 0) ?></div>
            <div class="admin-stat-label">Avg XP per User</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="admin-charts-row">
    <!-- Badge Trends Chart -->
    <div class="admin-glass-card admin-chart-card-wide">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-purple">
                <i class="fa-solid fa-chart-area"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Badge Earning Trends</h3>
                <p class="admin-card-subtitle">Last 30 days</p>
            </div>
        </div>
        <div class="admin-card-body">
            <canvas id="badgeTrendsChart" height="200"></canvas>
        </div>
    </div>

    <!-- Level Distribution Chart -->
    <div class="admin-glass-card admin-chart-card-narrow">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-pink">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Level Distribution</h3>
                <p class="admin-card-subtitle">User breakdown</p>
            </div>
        </div>
        <div class="admin-card-body">
            <canvas id="levelChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Three Column Cards -->
<div class="admin-three-col-grid">
    <!-- Popular Badges -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-red">
                <i class="fa-solid fa-fire"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Most Popular Badges</h3>
                <p class="admin-card-subtitle">Most earned</p>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($popularBadges)): ?>
            <div class="admin-empty-state" style="padding: 2rem;">
                <p class="admin-empty-text">No badges awarded yet</p>
            </div>
            <?php else: ?>
            <div class="admin-badge-list">
                <?php foreach ($popularBadges as $badge): ?>
                <div class="admin-badge-item">
                    <span class="admin-badge-icon-lg"><?= $badge['icon'] ?? '🏆' ?></span>
                    <div class="admin-badge-info">
                        <div class="admin-badge-name"><?= htmlspecialchars($badge['name'] ?? $badge['badge_key']) ?></div>
                        <div class="admin-badge-meta"><?= number_format($badge['award_count']) ?> earned</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rarest Badges -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-violet">
                <i class="fa-solid fa-gem"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Rarest Badges</h3>
                <p class="admin-card-subtitle">Hardest to earn</p>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($rarestBadges)): ?>
            <div class="admin-empty-state" style="padding: 2rem;">
                <p class="admin-empty-text">No badges awarded yet</p>
            </div>
            <?php else: ?>
            <div class="admin-badge-list">
                <?php foreach ($rarestBadges as $badge): ?>
                <div class="admin-badge-item">
                    <span class="admin-badge-icon-lg"><?= $badge['icon'] ?? '🏆' ?></span>
                    <div class="admin-badge-info">
                        <div class="admin-badge-name"><?= htmlspecialchars($badge['name'] ?? $badge['badge_key']) ?></div>
                        <div class="admin-badge-meta"><?= $badge['rarity_percent'] ?? 0 ?>% of users</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Earners -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-amber">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Top XP Earners</h3>
                <p class="admin-card-subtitle">Leaderboard</p>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($topEarners)): ?>
            <div class="admin-empty-state" style="padding: 2rem;">
                <p class="admin-empty-text">No users yet</p>
            </div>
            <?php else: ?>
            <div class="admin-leaderboard-list">
                <?php foreach ($topEarners as $i => $user): ?>
                <div class="admin-leaderboard-item">
                    <div class="admin-rank-badge <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>">
                        <?= $i + 1 ?>
                    </div>
                    <?php if (!empty($user['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" class="admin-leaderboard-avatar" alt="">
                    <?php else: ?>
                        <div class="admin-leaderboard-avatar-placeholder">
                            <?= strtoupper(substr($user['first_name'] ?? '?', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="admin-leaderboard-info">
                        <div class="admin-leaderboard-name"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></div>
                        <div class="admin-leaderboard-meta">Level <?= $user['level'] ?? 1 ?> &bull; <?= number_format($user['xp'] ?? 0) ?> XP</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-slate">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Recent Badge Activity</h3>
            <p class="admin-card-subtitle">Latest awards</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($recentActivity)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-clock"></i>
            </div>
            <h3 class="admin-empty-title">No recent activity</h3>
            <p class="admin-empty-text">Badge awards will appear here</p>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Badge</th>
                        <th class="hide-mobile">Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $activity): ?>
                    <tr>
                        <td>
                            <div class="admin-user-cell">
                                <?php if (!empty($activity['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($activity['avatar_url']) ?>" loading="lazy" class="admin-user-avatar-sm" alt="">
                                <?php else: ?>
                                    <div class="admin-user-avatar-placeholder-sm">
                                        <?= strtoupper(substr($activity['first_name'] ?? '?', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="admin-user-name"><?= htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')) ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="admin-badge-cell">
                                <span class="admin-badge-emoji"><?= $activity['badge_icon'] ?? '🏆' ?></span>
                                <span><?= htmlspecialchars($activity['badge_name'] ?? $activity['badge_key'] ?? 'Unknown') ?></span>
                            </div>
                        </td>
                        <td class="hide-mobile admin-date-cell">
                            <?= date('M j, Y g:i A', strtotime($activity['awarded_at'] ?? 'now')) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.admin-stat-purple .admin-stat-icon {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

.admin-stat-amber .admin-stat-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.admin-stat-emerald .admin-stat-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.admin-stat-cyan .admin-stat-icon {
    background: rgba(6, 182, 212, 0.2);
    color: #22d3ee;
}

.admin-stat-content {
    flex: 1;
}

.admin-stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}

.admin-stat-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    margin-top: 2px;
}

/* Charts Row */
.admin-charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.admin-chart-card-wide,
.admin-chart-card-narrow {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
}

/* Three Column Grid */
.admin-three-col-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Badge List */
.admin-badge-list {
    padding: 0;
}

.admin-badge-item {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.admin-badge-item:last-child {
    border-bottom: none;
}

.admin-badge-icon-lg {
    font-size: 2rem;
    margin-right: 1rem;
    width: 48px;
    text-align: center;
}

.admin-badge-info {
    flex: 1;
}

.admin-badge-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.admin-badge-meta {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 2px;
}

/* Leaderboard */
.admin-leaderboard-list {
    padding: 0;
}

.admin-leaderboard-item {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.admin-leaderboard-item:last-child {
    border-bottom: none;
}

.admin-rank-badge {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.admin-rank-badge.gold {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.admin-rank-badge.silver {
    background: rgba(156, 163, 175, 0.2);
    color: #d1d5db;
}

.admin-rank-badge.bronze {
    background: rgba(251, 146, 60, 0.2);
    color: #fb923c;
}

.admin-leaderboard-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    object-fit: cover;
    margin-right: 0.75rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-leaderboard-avatar-placeholder {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.admin-leaderboard-info {
    flex: 1;
}

.admin-leaderboard-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.admin-leaderboard-meta {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 2px;
}

/* Table Enhancements */
.admin-user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-user-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-user-avatar-placeholder-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.admin-user-name {
    font-weight: 500;
    color: #fff;
}

.admin-badge-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-badge-emoji {
    font-size: 1.25rem;
}

.admin-date-cell {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Card Header Icons */
.admin-card-header-icon-red {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.admin-card-header-icon-violet {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.admin-card-header-icon-amber {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}

.admin-card-header-icon-slate {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

.admin-card-header-icon-pink {
    background: rgba(236, 72, 153, 0.15);
    color: #f472b6;
}

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.admin-empty-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    border-radius: 16px;
    background: rgba(139, 92, 246, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
    font-size: 0.9rem;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
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

/* Responsive */
@media (max-width: 1200px) {
    .admin-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .admin-charts-row {
        grid-template-columns: 1fr;
    }
    .admin-three-col-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-stats-grid {
        grid-template-columns: 1fr;
    }

    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dark theme chart options
Chart.defaults.color = 'rgba(255, 255, 255, 0.6)';
Chart.defaults.borderColor = 'rgba(99, 102, 241, 0.1)';

// Badge Trends Chart
const trendsData = <?= json_encode($badgeTrends) ?>;
if (trendsData.length > 0) {
    new Chart(document.getElementById('badgeTrendsChart'), {
        type: 'line',
        data: {
            labels: trendsData.map(d => d.date),
            datasets: [{
                label: 'Badges Earned',
                data: trendsData.map(d => d.count),
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointBackgroundColor: '#8b5cf6',
                pointBorderColor: '#8b5cf6',
                pointHoverBackgroundColor: '#a78bfa',
                pointHoverBorderColor: '#a78bfa'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(99, 102, 241, 0.1)' },
                    ticks: { color: 'rgba(255, 255, 255, 0.5)' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: 'rgba(255, 255, 255, 0.5)' }
                }
            }
        }
    });
}

// Level Distribution Chart
const levelData = <?= json_encode($overview['level_distribution'] ?? []) ?>;
if (levelData.length > 0) {
    new Chart(document.getElementById('levelChart'), {
        type: 'doughnut',
        data: {
            labels: levelData.map(d => 'Level ' + d.level),
            datasets: [{
                data: levelData.map(d => d.count),
                backgroundColor: [
                    '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
                    '#ec4899', '#f43f5e', '#f97316', '#eab308',
                    '#84cc16', '#22c55e', '#14b8a6', '#06b6d4'
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        color: 'rgba(255, 255, 255, 0.7)'
                    }
                }
            }
        }
    });
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
