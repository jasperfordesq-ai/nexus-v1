<?php
/**
 * Admin Smart Matching - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Smart Matching';
$adminPageSubtitle = 'AI Engine';
$adminPageIcon = 'fa-bolt';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$stats = $stats ?? [];
$score_distribution = $score_distribution ?? [];
$conversion_funnel = $conversion_funnel ?? [];
$top_categories = $top_categories ?? [];
$recent_activity = $recent_activity ?? [];
$geocoding_status = $geocoding_status ?? [];
$user_engagement = $user_engagement ?? [];
$config = $config ?? [];

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-bolt"></i>
            Smart Matching Engine
        </h1>
        <p class="admin-page-subtitle">AI-powered connection intelligence</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/smart-matching/analytics" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-bar"></i>
            Analytics
        </a>
        <a href="<?= $basePath ?>/admin-legacy/smart-matching/configuration" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-cog"></i>
            Configure
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($flashSuccess): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="sm-stats-grid">
    <div class="sm-stat-card sm-stat-purple">
        <div class="sm-stat-icon">
            <i class="fa-solid fa-chart-bar"></i>
        </div>
        <div class="sm-stat-content">
            <div class="sm-stat-value"><?= number_format($stats['total_matches_week'] ?? 0) ?></div>
            <div class="sm-stat-label">Matches This Week</div>
            <div class="sm-stat-sublabel">+<?= number_format($stats['total_matches_today'] ?? 0) ?> today</div>
        </div>
    </div>

    <div class="sm-stat-card sm-stat-orange">
        <div class="sm-stat-icon">
            <i class="fa-solid fa-fire"></i>
        </div>
        <div class="sm-stat-content">
            <div class="sm-stat-value"><?= number_format($stats['hot_matches_count'] ?? 0) ?></div>
            <div class="sm-stat-label">Hot Matches (85%+)</div>
            <span class="sm-stat-badge sm-badge-hot">Premium</span>
        </div>
    </div>

    <div class="sm-stat-card sm-stat-cyan">
        <div class="sm-stat-icon">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="sm-stat-content">
            <div class="sm-stat-value"><?= number_format($stats['mutual_matches_count'] ?? 0) ?></div>
            <div class="sm-stat-label">Mutual Matches</div>
            <span class="sm-stat-badge sm-badge-mutual">Reciprocal</span>
        </div>
    </div>

    <div class="sm-stat-card sm-stat-amber">
        <div class="sm-stat-icon">
            <i class="fa-solid fa-arrow-trend-up"></i>
        </div>
        <div class="sm-stat-content">
            <div class="sm-stat-value"><?= $conversion_funnel['conversion_rate'] ?? 0 ?>%</div>
            <div class="sm-stat-label">Conversion Rate</div>
            <div class="sm-stat-sublabel"><?= number_format($conversion_funnel['completed'] ?? 0) ?> transactions</div>
        </div>
    </div>

    <div class="sm-stat-card sm-stat-blue">
        <div class="sm-stat-icon">
            <i class="fa-solid fa-location-dot"></i>
        </div>
        <div class="sm-stat-content">
            <div class="sm-stat-value"><?= $stats['avg_distance_km'] ?? 0 ?> <small>km</small></div>
            <div class="sm-stat-label">Avg Distance</div>
        </div>
    </div>

    <div class="sm-stat-card sm-stat-pink">
        <div class="sm-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="sm-stat-content">
            <div class="sm-stat-value"><?= number_format($stats['active_users_matching'] ?? 0) ?></div>
            <div class="sm-stat-label">Active Users</div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="sm-grid-main">
    <!-- Left Column -->
    <div class="sm-column-left">
        <!-- Score Distribution -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-chart-bar"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Score Distribution</h3>
                    <p class="admin-card-subtitle">Match quality breakdown</p>
                </div>
                <span class="sm-live-badge">Live</span>
            </div>
            <div class="admin-card-body">
                <?php
                $maxDist = max(1, max($score_distribution['0-40'] ?? 1, $score_distribution['40-60'] ?? 1, $score_distribution['60-80'] ?? 1, $score_distribution['80-100'] ?? 1));
                ?>
                <div class="sm-distribution">
                    <div class="sm-dist-bar-wrapper">
                        <div class="sm-dist-bar sm-dist-cold" style="height: <?= max(20, (($score_distribution['0-40'] ?? 0) / $maxDist) * 140) ?>px;">
                            <span class="sm-dist-value"><?= number_format($score_distribution['0-40'] ?? 0) ?></span>
                        </div>
                        <span class="sm-dist-label">0-40%</span>
                        <span class="sm-dist-type">Cold</span>
                    </div>
                    <div class="sm-dist-bar-wrapper">
                        <div class="sm-dist-bar sm-dist-warm" style="height: <?= max(20, (($score_distribution['40-60'] ?? 0) / $maxDist) * 140) ?>px;">
                            <span class="sm-dist-value"><?= number_format($score_distribution['40-60'] ?? 0) ?></span>
                        </div>
                        <span class="sm-dist-label">40-60%</span>
                        <span class="sm-dist-type">Warm</span>
                    </div>
                    <div class="sm-dist-bar-wrapper">
                        <div class="sm-dist-bar sm-dist-good" style="height: <?= max(20, (($score_distribution['60-80'] ?? 0) / $maxDist) * 140) ?>px;">
                            <span class="sm-dist-value"><?= number_format($score_distribution['60-80'] ?? 0) ?></span>
                        </div>
                        <span class="sm-dist-label">60-80%</span>
                        <span class="sm-dist-type">Good</span>
                    </div>
                    <div class="sm-dist-bar-wrapper">
                        <div class="sm-dist-bar sm-dist-hot" style="height: <?= max(20, (($score_distribution['80-100'] ?? 0) / $maxDist) * 140) ?>px;">
                            <span class="sm-dist-value"><?= number_format($score_distribution['80-100'] ?? 0) ?></span>
                        </div>
                        <span class="sm-dist-label">80-100%</span>
                        <span class="sm-dist-type">Hot</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversion Funnel -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-amber">
                    <i class="fa-solid fa-filter"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Conversion Funnel</h3>
                    <p class="admin-card-subtitle">User journey</p>
                </div>
            </div>
            <div class="admin-card-body">
                <?php $maxFunnel = max(1, $conversion_funnel['matched'] ?? 1); ?>
                <div class="sm-funnel">
                    <div class="sm-funnel-step">
                        <div class="sm-funnel-info">
                            <span class="sm-funnel-label">Matched</span>
                            <span class="sm-funnel-count"><?= number_format($conversion_funnel['matched'] ?? 0) ?></span>
                        </div>
                        <div class="sm-funnel-track">
                            <div class="sm-funnel-bar sm-funnel-matched" style="width: 100%;"></div>
                        </div>
                    </div>
                    <div class="sm-funnel-step">
                        <div class="sm-funnel-info">
                            <span class="sm-funnel-label">Viewed</span>
                            <span class="sm-funnel-count"><?= number_format($conversion_funnel['viewed'] ?? 0) ?></span>
                        </div>
                        <div class="sm-funnel-track">
                            <div class="sm-funnel-bar sm-funnel-viewed" style="width: <?= $maxFunnel > 0 ? max(5, (($conversion_funnel['viewed'] ?? 0) / $maxFunnel * 100)) : 5 ?>%;"></div>
                        </div>
                    </div>
                    <div class="sm-funnel-step">
                        <div class="sm-funnel-info">
                            <span class="sm-funnel-label">Contacted</span>
                            <span class="sm-funnel-count"><?= number_format($conversion_funnel['contacted'] ?? 0) ?></span>
                        </div>
                        <div class="sm-funnel-track">
                            <div class="sm-funnel-bar sm-funnel-contacted" style="width: <?= $maxFunnel > 0 ? max(5, (($conversion_funnel['contacted'] ?? 0) / $maxFunnel * 100)) : 5 ?>%;"></div>
                        </div>
                    </div>
                    <div class="sm-funnel-step">
                        <div class="sm-funnel-info">
                            <span class="sm-funnel-label">Completed</span>
                            <span class="sm-funnel-count"><?= number_format($conversion_funnel['completed'] ?? 0) ?></span>
                        </div>
                        <div class="sm-funnel-track">
                            <div class="sm-funnel-bar sm-funnel-completed" style="width: <?= $maxFunnel > 0 ? max(5, (($conversion_funnel['completed'] ?? 0) / $maxFunnel * 100)) : 5 ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Categories -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-pink">
                    <i class="fa-solid fa-tags"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Top Categories</h3>
                    <p class="admin-card-subtitle">By match volume</p>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($top_categories)): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <h3 class="admin-empty-title">No data yet</h3>
                    <p class="admin-empty-text">Category data will appear here</p>
                </div>
                <?php else: ?>
                <div class="sm-category-list">
                    <?php foreach (array_slice($top_categories, 0, 5) as $i => $cat): ?>
                    <div class="sm-category-item">
                        <div class="sm-category-rank"><?= $i + 1 ?></div>
                        <div class="sm-category-color" style="background: <?= htmlspecialchars($cat['color'] ?? '#6366f1') ?>;"></div>
                        <div class="sm-category-info">
                            <div class="sm-category-name"><?= htmlspecialchars($cat['name']) ?></div>
                            <div class="sm-category-meta">Score: <?= round($cat['avg_score'] ?? 0, 1) ?>% | <?= $cat['conversions'] ?? 0 ?> conversions</div>
                        </div>
                        <div class="sm-category-count"><?= number_format($cat['match_count'] ?? 0) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="sm-column-right">
        <!-- Geocoding Status -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-teal">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Geocoding Status</h3>
                    <p class="admin-card-subtitle">Location data</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="sm-geo-grid">
                    <div class="sm-geo-item sm-geo-success">
                        <div class="sm-geo-value"><?= number_format($geocoding_status['users_with_coords'] ?? 0) ?></div>
                        <div class="sm-geo-label">Users Geocoded</div>
                        <span class="sm-geo-dot sm-dot-green"></span>
                    </div>
                    <div class="sm-geo-item sm-geo-pending">
                        <div class="sm-geo-value"><?= number_format($geocoding_status['users_without_coords'] ?? 0) ?></div>
                        <div class="sm-geo-label">Users Pending</div>
                        <span class="sm-geo-dot sm-dot-amber"></span>
                    </div>
                    <div class="sm-geo-item sm-geo-success">
                        <div class="sm-geo-value"><?= number_format($geocoding_status['listings_with_coords'] ?? 0) ?></div>
                        <div class="sm-geo-label">Listings Geocoded</div>
                        <span class="sm-geo-dot sm-dot-green"></span>
                    </div>
                    <div class="sm-geo-item sm-geo-pending">
                        <div class="sm-geo-value"><?= number_format($geocoding_status['listings_without_coords'] ?? 0) ?></div>
                        <div class="sm-geo-label">Listings Pending</div>
                        <span class="sm-geo-dot sm-dot-amber"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-blue">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Quick Actions</h3>
                    <p class="admin-card-subtitle">System controls</p>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="sm-quick-actions">
                    <form action="<?= $basePath ?>/admin-legacy/smart-matching/clear-cache" method="POST">
                        <?= Csrf::input() ?>
                        <button type="submit" class="sm-quick-action sm-action-danger" onclick="return confirm('Clear all match cache?');">
                            <div class="sm-action-icon"><i class="fa-solid fa-trash"></i></div>
                            <span>Clear Cache</span>
                        </button>
                    </form>
                    <form action="<?= $basePath ?>/admin-legacy/smart-matching/warmup-cache" method="POST">
                        <?= Csrf::input() ?>
                        <button type="submit" class="sm-quick-action sm-action-warning">
                            <div class="sm-action-icon"><i class="fa-solid fa-fire"></i></div>
                            <span>Warm Cache</span>
                        </button>
                    </form>
                    <form action="<?= $basePath ?>/admin-legacy/smart-matching/run-geocoding" method="POST">
                        <?= Csrf::input() ?>
                        <button type="submit" class="sm-quick-action sm-action-success">
                            <div class="sm-action-icon"><i class="fa-solid fa-globe"></i></div>
                            <span>Run Geocoding</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-violet">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Recent Activity</h3>
                    <p class="admin-card-subtitle">Latest matches</p>
                </div>
                <a href="<?= $basePath ?>/admin-legacy/smart-matching/analytics" class="sm-card-link">View All</a>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($recent_activity)): ?>
                <div class="admin-empty-state" style="padding: 2rem;">
                    <p class="admin-empty-text">No recent activity</p>
                </div>
                <?php else: ?>
                <div class="sm-activity-list">
                    <?php foreach (array_slice($recent_activity, 0, 8) as $activity): ?>
                    <div class="sm-activity-item">
                        <div class="sm-activity-avatar">
                            <?php if (!empty($activity['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($activity['avatar_url']) ?>" loading="lazy" alt="">
                            <?php else: ?>
                                <?= strtoupper(substr($activity['first_name'] ?? 'U', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="sm-activity-content">
                            <div class="sm-activity-name"><?= htmlspecialchars($activity['first_name'] ?? 'User') ?></div>
                            <div class="sm-activity-meta">
                                <span class="sm-activity-score"><?= round($activity['match_score'] ?? 0) ?>%</span>
                                <?php if ($activity['distance_km']): ?>
                                    <span><?= round($activity['distance_km'], 1) ?>km</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="sm-activity-badge sm-badge-<?= $activity['action'] ?>"><?= ucfirst($activity['action']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Stats Grid */
.sm-stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.sm-stat-card {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    padding: 1.25rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.sm-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.sm-stat-purple::before { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.sm-stat-orange::before { background: linear-gradient(90deg, #f97316, #ef4444); }
.sm-stat-cyan::before { background: linear-gradient(90deg, #06b6d4, #10b981); }
.sm-stat-amber::before { background: linear-gradient(90deg, #f59e0b, #eab308); }
.sm-stat-blue::before { background: linear-gradient(90deg, #3b82f6, #6366f1); }
.sm-stat-pink::before { background: linear-gradient(90deg, #ec4899, #f43f5e); }

.sm-stat-card:hover {
    transform: translateY(-3px);
    border-color: rgba(99, 102, 241, 0.3);
}

.sm-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: white;
    font-size: 1.25rem;
}

.sm-stat-purple .sm-stat-icon { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.sm-stat-orange .sm-stat-icon { background: linear-gradient(135deg, #f97316, #ef4444); }
.sm-stat-cyan .sm-stat-icon { background: linear-gradient(135deg, #06b6d4, #10b981); }
.sm-stat-amber .sm-stat-icon { background: linear-gradient(135deg, #f59e0b, #eab308); }
.sm-stat-blue .sm-stat-icon { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.sm-stat-pink .sm-stat-icon { background: linear-gradient(135deg, #ec4899, #f43f5e); }

.sm-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.sm-stat-value small { font-size: 0.9rem; opacity: 0.7; }

.sm-stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    font-weight: 500;
}

.sm-stat-sublabel {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.25rem;
}

.sm-stat-badge {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sm-badge-hot { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
.sm-badge-mutual { background: rgba(6, 182, 212, 0.2); color: #22d3ee; }

/* Main Grid */
.sm-grid-main {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.5rem;
}

.sm-column-left,
.sm-column-right {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Live Badge */
.sm-live-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Score Distribution */
.sm-distribution {
    display: flex;
    gap: 1.5rem;
    align-items: flex-end;
    justify-content: space-around;
    height: 200px;
    padding-top: 1.25rem;
}

.sm-dist-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    max-width: 80px;
}

.sm-dist-bar {
    width: 100%;
    border-radius: 10px 10px 4px 4px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 0.75rem;
    transition: all 0.3s;
}

.sm-dist-bar:hover { transform: scale(1.05); }

.sm-dist-cold { background: linear-gradient(180deg, #64748b, #475569); }
.sm-dist-warm { background: linear-gradient(180deg, #6366f1, #4f46e5); }
.sm-dist-good { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }
.sm-dist-hot { background: linear-gradient(180deg, #f97316, #ef4444); }

.sm-dist-value { font-size: 0.85rem; font-weight: 800; color: white; }
.sm-dist-label { font-size: 0.8rem; font-weight: 600; color: rgba(255, 255, 255, 0.7); }
.sm-dist-type { font-size: 0.65rem; color: rgba(255, 255, 255, 0.4); text-transform: uppercase; letter-spacing: 0.5px; }

/* Conversion Funnel */
.sm-funnel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sm-funnel-step { display: flex; flex-direction: column; gap: 0.5rem; }

.sm-funnel-info { display: flex; justify-content: space-between; align-items: center; }

.sm-funnel-label { font-size: 0.9rem; font-weight: 500; color: rgba(255, 255, 255, 0.7); }
.sm-funnel-count { font-size: 0.9rem; font-weight: 700; color: #fff; }

.sm-funnel-track {
    height: 10px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 5px;
    overflow: hidden;
}

.sm-funnel-bar { height: 100%; border-radius: 5px; transition: width 0.5s; }

.sm-funnel-matched { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.sm-funnel-viewed { background: linear-gradient(90deg, #3b82f6, #6366f1); }
.sm-funnel-contacted { background: linear-gradient(90deg, #10b981, #06b6d4); }
.sm-funnel-completed { background: linear-gradient(90deg, #f59e0b, #f97316); }

/* Category List */
.sm-category-list { display: flex; flex-direction: column; }

.sm-category-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.sm-category-item:last-child { border-bottom: none; }
.sm-category-item:hover { background: rgba(99, 102, 241, 0.05); }

.sm-category-rank {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    font-size: 0.85rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sm-category-color { width: 4px; height: 36px; border-radius: 2px; }
.sm-category-info { flex: 1; }
.sm-category-name { font-weight: 600; color: #fff; font-size: 0.95rem; margin-bottom: 2px; }
.sm-category-meta { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); }

.sm-category-count {
    font-size: 1rem;
    font-weight: 800;
    color: #818cf8;
    background: rgba(99, 102, 241, 0.15);
    padding: 0.5rem 1rem;
    border-radius: 10px;
}

/* Geocoding Status */
.sm-geo-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.sm-geo-item {
    position: relative;
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    text-align: center;
}

.sm-geo-success { border-left: 3px solid #34d399; }
.sm-geo-pending { border-left: 3px solid #fbbf24; }

.sm-geo-value { font-size: 1.5rem; font-weight: 800; color: #fff; }
.sm-geo-label { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin-top: 0.25rem; }

.sm-geo-dot {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.sm-dot-green { background: #34d399; box-shadow: 0 0 8px rgba(52, 211, 153, 0.5); }
.sm-dot-amber { background: #fbbf24; box-shadow: 0 0 8px rgba(251, 191, 36, 0.5); animation: pulse 2s infinite; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Quick Actions */
.sm-quick-actions {
    display: flex;
    flex-direction: column;
}

.sm-quick-action {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.sm-quick-action:last-of-type { border-bottom: none; }

.sm-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.sm-action-danger { color: #f87171; }
.sm-action-danger .sm-action-icon { background: linear-gradient(135deg, #ef4444, #f97316); }
.sm-action-danger:hover { background: rgba(239, 68, 68, 0.1); }

.sm-action-warning { color: #fbbf24; }
.sm-action-warning .sm-action-icon { background: linear-gradient(135deg, #f59e0b, #eab308); }
.sm-action-warning:hover { background: rgba(245, 158, 11, 0.1); }

.sm-action-success { color: #34d399; }
.sm-action-success .sm-action-icon { background: linear-gradient(135deg, #10b981, #14b8a6); }
.sm-action-success:hover { background: rgba(16, 185, 129, 0.1); }

/* Activity List */
.sm-activity-list {
    display: flex;
    flex-direction: column;
    max-height: 400px;
    overflow-y: auto;
}

.sm-activity-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.sm-activity-item:last-child { border-bottom: none; }
.sm-activity-item:hover { background: rgba(99, 102, 241, 0.05); }

.sm-activity-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
    overflow: hidden;
    flex-shrink: 0;
}

.sm-activity-avatar img { width: 100%; height: 100%; object-fit: cover; }

.sm-activity-content { flex: 1; }
.sm-activity-name { font-weight: 600; color: #fff; font-size: 0.9rem; }

.sm-activity-meta {
    display: flex;
    gap: 0.75rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 2px;
}

.sm-activity-score { font-weight: 700; color: #818cf8; }

.sm-activity-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    flex-shrink: 0;
}

.sm-badge-viewed { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
.sm-badge-contacted { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.sm-badge-saved { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.sm-badge-completed { background: rgba(236, 72, 153, 0.15); color: #f472b6; }

/* Card Link */
.sm-card-link {
    font-size: 0.85rem;
    font-weight: 600;
    color: #818cf8;
    text-decoration: none;
}

.sm-card-link:hover { text-decoration: underline; }

/* Card Header Icons */
.admin-card-header-icon-amber { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.admin-card-header-icon-pink { background: rgba(236, 72, 153, 0.15); color: #f472b6; }
.admin-card-header-icon-teal { background: rgba(20, 184, 166, 0.15); color: #2dd4bf; }
.admin-card-header-icon-blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
.admin-card-header-icon-violet { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }

/* Alerts */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.admin-alert-success {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
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

.admin-empty-title { font-size: 1.1rem; font-weight: 600; color: #fff; margin: 0 0 0.5rem 0; }
.admin-empty-text { color: rgba(255, 255, 255, 0.5); margin: 0; font-size: 0.9rem; }

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

/* Responsive */
@media (max-width: 1400px) {
    .sm-stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 1024px) {
    .sm-grid-main { grid-template-columns: 1fr; }
    .sm-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .sm-stats-grid { grid-template-columns: 1fr; }
    .sm-geo-grid { grid-template-columns: 1fr; }
    .sm-distribution { height: 160px; }
}

/* Scrollbar */
.sm-activity-list::-webkit-scrollbar { width: 6px; }
.sm-activity-list::-webkit-scrollbar-track { background: transparent; }
.sm-activity-list::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.2); border-radius: 3px; }
.sm-activity-list::-webkit-scrollbar-thumb:hover { background: rgba(99, 102, 241, 0.4); }
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
