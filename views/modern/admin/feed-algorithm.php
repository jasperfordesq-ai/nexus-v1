<?php
/**
 * Feed Algorithm - Gold Standard Mission Control
 * STANDALONE admin interface - does NOT use main site header/footer
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Feed Algorithm';
$adminPageSubtitle = 'EdgeRank';
$adminPageIcon = 'fa-sliders';

// Include the standalone admin header
require __DIR__ . '/partials/admin-header.php';

// Get current algorithm settings from tenant config
if (!isset($configJson)) {
    $configJson = isset($tenant['configuration']) ? json_decode($tenant['configuration'], true) : [];
    if (!is_array($configJson)) $configJson = [];
}

$feedAlgo = $configJson['feed_algorithm'] ?? [];

// Default values matching FeedRankingService constants
$defaults = [
    'enabled' => true,
    'like_weight' => 1,
    'comment_weight' => 5,
    'share_weight' => 8,
    'vitality_full_days' => 7,
    'vitality_decay_days' => 30,
    'vitality_minimum' => 0.5,
    'geo_full_radius' => 10,
    'geo_decay_interval' => 10,
    'geo_decay_rate' => 0.10,
    'geo_minimum' => 0.1,
    'freshness_enabled' => true,
    'freshness_full_hours' => 24,
    'freshness_half_life' => 72,
    'freshness_minimum' => 0.3,
    'social_graph_enabled' => true,
    'social_graph_max_boost' => 2.0,
    'social_graph_lookback_days' => 90,
    'social_graph_follower_boost' => 1.5,
    'negative_signals_enabled' => true,
    'hide_penalty' => 0.0,
    'mute_penalty' => 0.1,
    'block_penalty' => 0.0,
    'report_penalty_per' => 0.15,
    'quality_enabled' => true,
    'quality_image_boost' => 1.3,
    'quality_link_boost' => 1.1,
    'quality_length_min' => 50,
    'quality_length_bonus' => 1.2,
    'quality_video_boost' => 1.4,
    'quality_hashtag_boost' => 1.1,
    'quality_mention_boost' => 1.15,
    'diversity_enabled' => true,
    'diversity_max_consecutive' => 2,
    'diversity_penalty' => 0.5,
    'diversity_type_enabled' => true,
    'diversity_type_max_consecutive' => 3,
];

$settings = array_merge($defaults, $feedAlgo);

// Check if algorithm service exists
$algorithmActive = class_exists('\Nexus\Services\FeedRankingService');

// Count enabled modules
$enabledModules = array_sum([
    $settings['enabled'] ? 1 : 0,
    $settings['freshness_enabled'] ? 1 : 0,
    $settings['social_graph_enabled'] ? 1 : 0,
    $settings['negative_signals_enabled'] ? 1 : 0,
    $settings['quality_enabled'] ? 1 : 0,
    $settings['diversity_enabled'] ? 1 : 0,
]);
$totalModules = 6;
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-sliders"></i>
            Feed Algorithm
        </h1>
        <p class="admin-page-subtitle">EdgeRank configuration for intelligent content ranking</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="admin-stats-grid">
    <!-- Algorithm Status -->
    <div class="admin-stat-card <?= $algorithmActive && $settings['enabled'] ? 'admin-stat-green' : 'admin-stat-gray' ?>">
        <div class="admin-stat-icon">
            <i class="fa-solid <?= $algorithmActive && $settings['enabled'] ? 'fa-check' : 'fa-power-off' ?>"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $algorithmActive && $settings['enabled'] ? 'Active' : 'Off' ?></div>
            <div class="admin-stat-label">Algorithm Status</div>
        </div>
        <div class="admin-stat-trend <?= $algorithmActive ? 'admin-stat-trend-up' : '' ?>">
            <i class="fa-solid fa-circle"></i>
            <span><?= $algorithmActive ? 'Loaded' : 'Disabled' ?></span>
        </div>
    </div>

    <!-- Enabled Modules -->
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-cubes"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $enabledModules ?>/<?= $totalModules ?></div>
            <div class="admin-stat-label">Modules Active</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-puzzle-piece"></i>
            <span>Features</span>
        </div>
    </div>

    <!-- Engagement Weight -->
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-heart"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $settings['like_weight'] ?>:<?= $settings['comment_weight'] ?></div>
            <div class="admin-stat-label">Like:Comment Ratio</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>Weights</span>
        </div>
    </div>

    <!-- Quality Boost -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-star"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $settings['quality_image_boost'] ?>x</div>
            <div class="admin-stat-label">Image Boost</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-image"></i>
            <span>Quality</span>
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
        <div class="admin-alert-title">Settings Saved</div>
        <div class="admin-alert-text">Algorithm settings have been updated successfully</div>
    </div>
</div>
<?php endif; ?>

<!-- Formula Banner -->
<div class="formula-banner">
    <div class="formula-header">
        <div class="formula-icon">
            <i class="fa-solid fa-function"></i>
        </div>
        <div class="formula-title">
            <h3>EdgeRank Formula</h3>
            <p>Prioritize engaging content from active, nearby users</p>
        </div>
        <div class="formula-status <?= $algorithmActive && $settings['enabled'] ? 'active' : 'inactive' ?>">
            <i class="fa-solid <?= $algorithmActive && $settings['enabled'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
            <?= $algorithmActive && $settings['enabled'] ? 'Active' : 'Inactive' ?>
        </div>
    </div>
    <div class="formula-code">
        <code><span class="highlight">rank_score</span> = engagement <span class="op">x</span> vitality <span class="op">x</span> geo_decay <span class="op">x</span> freshness <span class="op">x</span> social_graph <span class="op">x</span> negative <span class="op">x</span> quality</code>
    </div>
</div>

<form action="<?= $basePath ?>/admin-legacy/feed-algorithm/save" method="POST">
    <?= Csrf::input() ?>

    <!-- Main Content Grid -->
    <div class="admin-dashboard-grid">
        <!-- Left Column - Settings Cards -->
        <div class="admin-dashboard-main">

            <!-- Master Toggle -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-green">
                        <i class="fa-solid fa-power-off"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Algorithm Status</h3>
                        <p class="admin-card-subtitle">Enable or disable EdgeRank</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enabled" value="1" <?= $settings['enabled'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="admin-card-body info-banner info-banner-green">
                    <i class="fa-solid fa-info-circle"></i>
                    When disabled, the feed shows posts in reverse chronological order (newest first).
                </div>
            </div>

            <!-- Engagement Weights -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-blue">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Engagement Weights</h3>
                        <p class="admin-card-subtitle">How much each interaction type contributes</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid">
                        <div class="setting-group">
                            <label class="setting-label">Like Weight</label>
                            <div class="slider-row">
                                <input type="range" name="like_weight" min="0" max="10" step="0.5" value="<?= $settings['like_weight'] ?>" oninput="this.nextElementSibling.textContent = this.value">
                                <span class="slider-value"><?= $settings['like_weight'] ?></span>
                            </div>
                            <span class="setting-hint">Points added per like</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Comment Weight</label>
                            <div class="slider-row">
                                <input type="range" name="comment_weight" min="0" max="20" step="1" value="<?= $settings['comment_weight'] ?>" oninput="this.nextElementSibling.textContent = this.value">
                                <span class="slider-value"><?= $settings['comment_weight'] ?></span>
                            </div>
                            <span class="setting-hint">Points added per comment</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Share Weight</label>
                            <div class="slider-row">
                                <input type="range" name="share_weight" min="0" max="20" step="1" value="<?= $settings['share_weight'] ?>" oninput="this.nextElementSibling.textContent = this.value">
                                <span class="slider-value"><?= $settings['share_weight'] ?></span>
                            </div>
                            <span class="setting-hint">Points per share (high-intent)</span>
                        </div>
                    </div>
                    <div class="example-box example-box-blue">
                        <strong>Example:</strong> 10 likes + 3 comments + 2 shares = (10 x <?= $settings['like_weight'] ?>) + (3 x <?= $settings['comment_weight'] ?>) + (2 x <?= $settings['share_weight'] ?>) = <strong><?= (10 * $settings['like_weight']) + (3 * $settings['comment_weight']) + (2 * $settings['share_weight']) ?> points</strong>
                    </div>
                </div>
            </div>

            <!-- Creator Vitality -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-amber">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Creator Vitality</h3>
                        <p class="admin-card-subtitle">Boost active users, reduce dormant accounts</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid">
                        <div class="setting-group">
                            <label class="setting-label">Full Score Threshold</label>
                            <div class="input-with-suffix">
                                <input type="number" name="vitality_full_days" class="form-input" value="<?= $settings['vitality_full_days'] ?>" min="1" max="30">
                                <span class="input-suffix">days</span>
                            </div>
                            <span class="setting-hint">Active within = 100% score</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Decay Threshold</label>
                            <div class="input-with-suffix">
                                <input type="number" name="vitality_decay_days" class="form-input" value="<?= $settings['vitality_decay_days'] ?>" min="7" max="365">
                                <span class="input-suffix">days</span>
                            </div>
                            <span class="setting-hint">After this = minimum score</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Minimum Vitality</label>
                            <div class="slider-row">
                                <input type="range" name="vitality_minimum" min="0" max="1" step="0.05" value="<?= $settings['vitality_minimum'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['vitality_minimum'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">Floor for inactive users</span>
                        </div>
                    </div>
                    <div class="example-box example-box-amber">
                        <strong>How it works:</strong> Users active within <?= $settings['vitality_full_days'] ?> days get full score. Score decays linearly to <?= $settings['vitality_minimum'] * 100 ?>% at day <?= $settings['vitality_decay_days'] ?>.
                    </div>
                </div>
            </div>

            <!-- Geospatial Decay -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-pink">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Geospatial Decay</h3>
                        <p class="admin-card-subtitle">Prioritize local content over distant</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid settings-grid-4">
                        <div class="setting-group">
                            <label class="setting-label">Full Score Radius</label>
                            <div class="input-with-suffix">
                                <input type="number" name="geo_full_radius" class="form-input" value="<?= $settings['geo_full_radius'] ?>" min="1" max="100">
                                <span class="input-suffix">km</span>
                            </div>
                            <span class="setting-hint">Within = 100%</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Decay Interval</label>
                            <div class="input-with-suffix">
                                <input type="number" name="geo_decay_interval" class="form-input" value="<?= $settings['geo_decay_interval'] ?>" min="1" max="100">
                                <span class="input-suffix">km</span>
                            </div>
                            <span class="setting-hint">Drops every X km</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Decay Rate</label>
                            <div class="slider-row">
                                <input type="range" name="geo_decay_rate" min="0.05" max="0.5" step="0.05" value="<?= $settings['geo_decay_rate'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['geo_decay_rate'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">Per interval</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Minimum Geo</label>
                            <div class="slider-row">
                                <input type="range" name="geo_minimum" min="0" max="0.5" step="0.05" value="<?= $settings['geo_minimum'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['geo_minimum'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">Floor score</span>
                        </div>
                    </div>
                    <div class="example-box example-box-pink">
                        <strong>Example:</strong> Post 35km away = <?= $settings['geo_full_radius'] ?>km (full) + 25km extra. Decay: <?= floor(25 / $settings['geo_decay_interval']) ?> x <?= $settings['geo_decay_rate'] * 100 ?>% = <strong><?= max($settings['geo_minimum'], 1 - (floor(25 / $settings['geo_decay_interval']) * $settings['geo_decay_rate'])) * 100 ?>% score</strong>
                    </div>
                </div>
            </div>

            <!-- Content Freshness -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-cyan">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Content Freshness</h3>
                        <p class="admin-card-subtitle">Newer posts get higher priority</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="freshness_enabled" value="1" <?= $settings['freshness_enabled'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid">
                        <div class="setting-group">
                            <label class="setting-label">Full Score Period</label>
                            <div class="input-with-suffix">
                                <input type="number" name="freshness_full_hours" class="form-input" value="<?= $settings['freshness_full_hours'] ?>" min="1" max="168">
                                <span class="input-suffix">hours</span>
                            </div>
                            <span class="setting-hint">Within = 100% freshness</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Half-Life</label>
                            <div class="input-with-suffix">
                                <input type="number" name="freshness_half_life" class="form-input" value="<?= $settings['freshness_half_life'] ?>" min="12" max="336">
                                <span class="input-suffix">hours</span>
                            </div>
                            <span class="setting-hint">Time to decay 50%</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Minimum Freshness</label>
                            <div class="slider-row">
                                <input type="range" name="freshness_minimum" min="0.1" max="0.8" step="0.05" value="<?= $settings['freshness_minimum'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['freshness_minimum'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">Old posts don't disappear</span>
                        </div>
                    </div>
                    <div class="example-box example-box-cyan">
                        <strong>How it works:</strong> Posts within <?= $settings['freshness_full_hours'] ?>h = 100%. Decays to 50% after <?= $settings['freshness_half_life'] ?>h, 25% after <?= $settings['freshness_half_life'] * 2 ?>h. Minimum <?= $settings['freshness_minimum'] * 100 ?>%.
                    </div>
                </div>
            </div>

            <!-- Social Graph -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-purple">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Social Graph Boost</h3>
                        <p class="admin-card-subtitle">Prioritize users you interact with</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="social_graph_enabled" value="1" <?= $settings['social_graph_enabled'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid">
                        <div class="setting-group">
                            <label class="setting-label">Maximum Boost</label>
                            <div class="slider-row">
                                <input type="range" name="social_graph_max_boost" min="1.2" max="3.0" step="0.1" value="<?= $settings['social_graph_max_boost'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['social_graph_max_boost'] ?>x</span>
                            </div>
                            <span class="setting-hint">Max for frequent interactions</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Follower Boost</label>
                            <div class="slider-row">
                                <input type="range" name="social_graph_follower_boost" min="1.0" max="2.0" step="0.1" value="<?= $settings['social_graph_follower_boost'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['social_graph_follower_boost'] ?>x</span>
                            </div>
                            <span class="setting-hint">Boost for followed users</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Lookback Period</label>
                            <div class="input-with-suffix">
                                <input type="number" name="social_graph_lookback_days" class="form-input" value="<?= $settings['social_graph_lookback_days'] ?>" min="7" max="365">
                                <span class="input-suffix">days</span>
                            </div>
                            <span class="setting-hint">Interaction history window</span>
                        </div>
                    </div>
                    <div class="example-box example-box-purple">
                        <strong>Scaling:</strong> 1 interaction = 1.25x, 3 interactions = 1.5x, 7+ = <?= $settings['social_graph_max_boost'] ?>x. Following adds <?= $settings['social_graph_follower_boost'] ?>x multiplier.
                    </div>
                </div>
            </div>

            <!-- Negative Signals -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-red">
                        <i class="fa-solid fa-flag"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Negative Signals</h3>
                        <p class="admin-card-subtitle">Downrank blocked, hidden, muted content</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="negative_signals_enabled" value="1" <?= $settings['negative_signals_enabled'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid settings-grid-4">
                        <div class="setting-group">
                            <label class="setting-label">Blocked Visibility</label>
                            <div class="slider-row">
                                <input type="range" name="block_penalty" min="0" max="0.5" step="0.05" value="<?= $settings['block_penalty'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['block_penalty'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">0% = fully hidden</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Hidden Visibility</label>
                            <div class="slider-row">
                                <input type="range" name="hide_penalty" min="0" max="0.5" step="0.05" value="<?= $settings['hide_penalty'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['hide_penalty'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">For hidden posts</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Muted Visibility</label>
                            <div class="slider-row">
                                <input type="range" name="mute_penalty" min="0" max="0.5" step="0.05" value="<?= $settings['mute_penalty'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['mute_penalty'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">For muted users</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Report Penalty</label>
                            <div class="slider-row">
                                <input type="range" name="report_penalty_per" min="0.05" max="0.5" step="0.05" value="<?= $settings['report_penalty_per'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['report_penalty_per'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">Per report</span>
                        </div>
                    </div>
                    <div class="example-box example-box-red">
                        <strong>Priority:</strong> Blocked (<?= $settings['block_penalty'] * 100 ?>%) > Hidden (<?= $settings['hide_penalty'] * 100 ?>%) > Muted (<?= $settings['mute_penalty'] * 100 ?>%) > Reported (-<?= $settings['report_penalty_per'] * 100 ?>%/report). Blocking is bidirectional.
                    </div>
                </div>
            </div>

            <!-- Content Quality -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-emerald">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Content Quality</h3>
                        <p class="admin-card-subtitle">Boost rich media and substantial posts</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="quality_enabled" value="1" <?= $settings['quality_enabled'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid settings-grid-4">
                        <div class="setting-group">
                            <label class="setting-label">Image Boost</label>
                            <div class="slider-row">
                                <input type="range" name="quality_image_boost" min="1.0" max="2.0" step="0.1" value="<?= $settings['quality_image_boost'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['quality_image_boost'] ?>x</span>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Video Boost</label>
                            <div class="slider-row">
                                <input type="range" name="quality_video_boost" min="1.0" max="2.0" step="0.1" value="<?= $settings['quality_video_boost'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['quality_video_boost'] ?>x</span>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Link Boost</label>
                            <div class="slider-row">
                                <input type="range" name="quality_link_boost" min="1.0" max="1.5" step="0.05" value="<?= $settings['quality_link_boost'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['quality_link_boost'] ?>x</span>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Hashtag Boost</label>
                            <div class="slider-row">
                                <input type="range" name="quality_hashtag_boost" min="1.0" max="1.5" step="0.05" value="<?= $settings['quality_hashtag_boost'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['quality_hashtag_boost'] ?>x</span>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">@Mention Boost</label>
                            <div class="slider-row">
                                <input type="range" name="quality_mention_boost" min="1.0" max="1.5" step="0.05" value="<?= $settings['quality_mention_boost'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['quality_mention_boost'] ?>x</span>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Min Length</label>
                            <div class="input-with-suffix">
                                <input type="number" name="quality_length_min" class="form-input" value="<?= $settings['quality_length_min'] ?>" min="10" max="500">
                                <span class="input-suffix">chars</span>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Length Bonus</label>
                            <div class="slider-row">
                                <input type="range" name="quality_length_bonus" min="1.0" max="1.5" step="0.05" value="<?= $settings['quality_length_bonus'] ?>" oninput="this.nextElementSibling.textContent = this.value + 'x'">
                                <span class="slider-value"><?= $settings['quality_length_bonus'] ?>x</span>
                            </div>
                        </div>
                    </div>
                    <div class="example-box example-box-emerald">
                        <strong>Max boost:</strong> Image + video + hashtag + @mention + length = <?= number_format($settings['quality_image_boost'] * $settings['quality_video_boost'] * $settings['quality_hashtag_boost'] * $settings['quality_mention_boost'] * $settings['quality_length_bonus'], 2) ?>x multiplier.
                    </div>
                </div>
            </div>

            <!-- Content Diversity -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-orange">
                        <i class="fa-solid fa-shuffle"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Content Diversity</h3>
                        <p class="admin-card-subtitle">Prevent feed dominance by single users</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="diversity_enabled" value="1" <?= $settings['diversity_enabled'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="admin-card-body">
                    <div class="settings-grid">
                        <div class="setting-group">
                            <label class="setting-label">Max Consecutive (User)</label>
                            <input type="number" name="diversity_max_consecutive" class="form-input" value="<?= $settings['diversity_max_consecutive'] ?>" min="1" max="10">
                            <span class="setting-hint">Max posts from same user in a row</span>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Overflow Penalty</label>
                            <div class="slider-row">
                                <input type="range" name="diversity_penalty" min="0.1" max="0.9" step="0.1" value="<?= $settings['diversity_penalty'] ?>" oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'">
                                <span class="slider-value"><?= $settings['diversity_penalty'] * 100 ?>%</span>
                            </div>
                            <span class="setting-hint">Score for overflow posts</span>
                        </div>
                        <div class="setting-group setting-group-inline">
                            <div style="flex: 1;">
                                <label class="setting-label">Content-Type Diversity</label>
                                <span class="setting-hint">Mix posts, events, listings</span>
                            </div>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" name="diversity_type_enabled" value="1" <?= $settings['diversity_type_enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-group">
                            <label class="setting-label">Max Consecutive (Type)</label>
                            <input type="number" name="diversity_type_max_consecutive" class="form-input" value="<?= $settings['diversity_type_max_consecutive'] ?>" min="1" max="10">
                            <span class="setting-hint">Max same type in a row</span>
                        </div>
                    </div>
                    <div class="example-box example-box-orange">
                        <strong>User diversity:</strong> After <?= $settings['diversity_max_consecutive'] ?> posts, next get <?= $settings['diversity_penalty'] * 100 ?>% score. <strong>Type diversity:</strong> Max <?= $settings['diversity_type_max_consecutive'] ?> of same type consecutively.
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column - Quick Actions & Info -->
        <div class="admin-dashboard-sidebar">

            <!-- Quick Actions -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-cyan">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Quick Actions</h3>
                        <p class="admin-card-subtitle">Common tasks</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-quick-actions">
                        <a href="<?= $basePath ?>/admin-legacy/ai-settings" class="admin-quick-action">
                            <div class="admin-quick-action-icon admin-quick-action-icon-purple">
                                <i class="fa-solid fa-microchip"></i>
                            </div>
                            <span>AI Settings</span>
                        </a>
                        <a href="<?= $basePath ?>/admin-legacy/smart-matching" class="admin-quick-action">
                            <div class="admin-quick-action-icon admin-quick-action-icon-pink">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <span>Smart Matching</span>
                        </a>
                        <a href="<?= $basePath ?>/admin-legacy/algorithm-settings" class="admin-quick-action">
                            <div class="admin-quick-action-icon admin-quick-action-icon-orange">
                                <i class="fa-solid fa-gears"></i>
                            </div>
                            <span>Algorithm Settings</span>
                        </a>
                        <a href="<?= $basePath ?>/admin-legacy" class="admin-quick-action">
                            <div class="admin-quick-action-icon admin-quick-action-icon-blue">
                                <i class="fa-solid fa-gauge"></i>
                            </div>
                            <span>Dashboard</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Module Status -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-emerald">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Module Status</h3>
                        <p class="admin-card-subtitle">Algorithm components</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="module-status-list">
                        <div class="module-status-item">
                            <div class="module-status-indicator <?= $settings['enabled'] ? 'active' : 'inactive' ?>"></div>
                            <span class="module-status-label">EdgeRank Core</span>
                            <span class="module-status-badge <?= $settings['enabled'] ? 'on' : 'off' ?>"><?= $settings['enabled'] ? 'ON' : 'OFF' ?></span>
                        </div>
                        <div class="module-status-item">
                            <div class="module-status-indicator <?= $settings['freshness_enabled'] ? 'active' : 'inactive' ?>"></div>
                            <span class="module-status-label">Freshness Decay</span>
                            <span class="module-status-badge <?= $settings['freshness_enabled'] ? 'on' : 'off' ?>"><?= $settings['freshness_enabled'] ? 'ON' : 'OFF' ?></span>
                        </div>
                        <div class="module-status-item">
                            <div class="module-status-indicator <?= $settings['social_graph_enabled'] ? 'active' : 'inactive' ?>"></div>
                            <span class="module-status-label">Social Graph</span>
                            <span class="module-status-badge <?= $settings['social_graph_enabled'] ? 'on' : 'off' ?>"><?= $settings['social_graph_enabled'] ? 'ON' : 'OFF' ?></span>
                        </div>
                        <div class="module-status-item">
                            <div class="module-status-indicator <?= $settings['negative_signals_enabled'] ? 'active' : 'inactive' ?>"></div>
                            <span class="module-status-label">Negative Signals</span>
                            <span class="module-status-badge <?= $settings['negative_signals_enabled'] ? 'on' : 'off' ?>"><?= $settings['negative_signals_enabled'] ? 'ON' : 'OFF' ?></span>
                        </div>
                        <div class="module-status-item">
                            <div class="module-status-indicator <?= $settings['quality_enabled'] ? 'active' : 'inactive' ?>"></div>
                            <span class="module-status-label">Quality Boost</span>
                            <span class="module-status-badge <?= $settings['quality_enabled'] ? 'on' : 'off' ?>"><?= $settings['quality_enabled'] ? 'ON' : 'OFF' ?></span>
                        </div>
                        <div class="module-status-item">
                            <div class="module-status-indicator <?= $settings['diversity_enabled'] ? 'active' : 'inactive' ?>"></div>
                            <span class="module-status-label">Diversity Control</span>
                            <span class="module-status-badge <?= $settings['diversity_enabled'] ? 'on' : 'off' ?>"><?= $settings['diversity_enabled'] ? 'ON' : 'OFF' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-amber">
                        <i class="fa-solid fa-lightbulb"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Algorithm Tips</h3>
                        <p class="admin-card-subtitle">Best practices</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="tips-list">
                        <div class="tip-item">
                            <div class="tip-icon tip-icon-blue">
                                <i class="fa-solid fa-heart"></i>
                            </div>
                            <div class="tip-content">
                                <div class="tip-title">Engagement Balance</div>
                                <div class="tip-text">Comments typically indicate deeper engagement than likes. 5:1 ratio is recommended.</div>
                            </div>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon tip-icon-pink">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div class="tip-content">
                                <div class="tip-title">Local First</div>
                                <div class="tip-text">Geo decay helps surface local content. Set radius based on your community size.</div>
                            </div>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon tip-icon-purple">
                                <i class="fa-solid fa-shuffle"></i>
                            </div>
                            <div class="tip-content">
                                <div class="tip-title">Prevent Spam</div>
                                <div class="tip-text">Diversity settings prevent any single user from dominating the feed.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Save Button -->
    <div class="form-actions">
        <a href="<?= $basePath ?>/admin-legacy" class="admin-btn admin-btn-secondary">
            Cancel
        </a>
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
            <i class="fa-solid fa-check"></i>
            Save Algorithm Settings
        </button>
    </div>

</form>

<!-- Module Navigation -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-grid-2"></i>
        AI & Intelligence Modules
    </h2>
    <p class="admin-section-subtitle">Access all AI administrative functions</p>
</div>

<div class="admin-modules-grid">
    <a href="<?= $basePath ?>/admin-legacy/ai-settings" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-purple">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">AI Settings</h4>
            <p class="admin-module-desc">Provider configuration</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin-legacy/feed-algorithm" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-cyan">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Feed Algorithm</h4>
            <p class="admin-module-desc">EdgeRank configuration</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin-legacy/smart-matching" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-pink">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Smart Matching</h4>
            <p class="admin-module-desc">AI recommendations</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>

    <a href="<?= $basePath ?>/admin-legacy/algorithm-settings" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-emerald">
            <i class="fa-solid fa-gears"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Algorithm Settings</h4>
            <p class="admin-module-desc">Tuning parameters</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>
</div>

<style>
/**
 * Feed Algorithm Dashboard Specific Styles
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

.admin-page-title i { color: #06b6d4; }

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

@media (max-width: 1200px) { .admin-stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .admin-stats-grid { grid-template-columns: 1fr; } }

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
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
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3); }

.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-gray { --stat-color: #64748b; }
.admin-stat-purple { --stat-color: #8b5cf6; }
.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-orange { --stat-color: #f59e0b; }

.admin-stat-icon {
    width: 56px; height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.admin-stat-content { flex: 1; }
.admin-stat-value { font-size: 1.75rem; font-weight: 800; color: #fff; line-height: 1; }
.admin-stat-label { color: rgba(255, 255, 255, 0.6); font-size: 0.85rem; margin-top: 0.25rem; }

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

.admin-stat-trend-up { color: #22c55e; background: rgba(34, 197, 94, 0.1); }

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-alert-success .admin-alert-icon { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-alert-success .admin-alert-title { color: #22c55e; }

.admin-alert-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.admin-alert-content { flex: 1; }
.admin-alert-title { font-weight: 600; }
.admin-alert-text { font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); }

/* Formula Banner */
.formula-banner {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 2rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.formula-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
}

.formula-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    background: rgba(139, 92, 246, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #a5b4fc;
}

.formula-title { flex: 1; }
.formula-title h3 { margin: 0; color: #fff; font-size: 1.1rem; font-weight: 600; }
.formula-title p { margin: 0.25rem 0 0 0; color: #a5b4fc; font-size: 0.85rem; }

.formula-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.formula-status.active { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
.formula-status.inactive { background: rgba(239, 68, 68, 0.2); color: #f87171; }

.formula-code {
    padding: 1rem 1.5rem 1.5rem;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.9rem;
    color: #a5b4fc;
    overflow-x: auto;
}

.formula-code .highlight { color: #fbbf24; font-weight: 600; }
.formula-code .op { color: #94a3b8; margin: 0 0.25rem; }

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
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.admin-btn-primary:hover { box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3); transform: translateY(-1px); }

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-btn-secondary:hover { background: rgba(255, 255, 255, 0.15); border-color: rgba(255, 255, 255, 0.2); }

.admin-btn-lg { padding: 0.875rem 2rem; font-size: 1rem; }

/* Dashboard Grid */
.admin-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) { .admin-dashboard-grid { grid-template-columns: 1fr; } }

.admin-dashboard-main { display: flex; flex-direction: column; gap: 1.5rem; }
.admin-dashboard-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
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
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.admin-card-header-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-card-header-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.admin-card-header-icon-amber { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-card-header-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }
.admin-card-header-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-card-header-icon-red { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
.admin-card-header-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.admin-card-header-icon-orange { background: rgba(249, 115, 22, 0.2); color: #f97316; }

.admin-card-header-content { flex: 1; }
.admin-card-title { font-size: 1rem; font-weight: 600; color: #fff; margin: 0; }
.admin-card-subtitle { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin: 0.125rem 0 0 0; }

.admin-card-body { padding: 1.25rem 1.5rem; }

/* Toggle Switch */
.toggle-switch {
    position: relative;
    width: 48px; height: 26px;
    flex-shrink: 0;
}

.toggle-switch input { opacity: 0; width: 0; height: 0; }

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(100, 116, 139, 0.4);
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px; width: 20px;
    left: 3px; bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .toggle-slider { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
input:checked + .toggle-slider:before { transform: translateX(22px); }

.toggle-switch-sm { width: 40px; height: 22px; }
.toggle-switch-sm .toggle-slider:before { height: 16px; width: 16px; }
.toggle-switch-sm input:checked + .toggle-slider:before { transform: translateX(18px); }

/* Info Banner */
.info-banner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
}

.info-banner-green {
    background: rgba(34, 197, 94, 0.1);
    color: #4ade80;
    border-top: 1px solid rgba(34, 197, 94, 0.2);
}

/* Settings Grid */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}

.settings-grid-4 { grid-template-columns: repeat(4, 1fr); }

@media (max-width: 900px) {
    .settings-grid { grid-template-columns: repeat(2, 1fr); }
    .settings-grid-4 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .settings-grid, .settings-grid-4 { grid-template-columns: 1fr; }
}

.setting-group { display: flex; flex-direction: column; gap: 0.5rem; }
.setting-group-inline { flex-direction: row; align-items: center; }
.setting-label { font-size: 0.85rem; font-weight: 600; color: #e2e8f0; }
.setting-hint { font-size: 0.75rem; color: rgba(255, 255, 255, 0.4); }

/* Form Input */
.form-input {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.form-input:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }

.input-with-suffix {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.input-with-suffix .form-input { flex: 1; }
.input-suffix { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); }

/* Slider */
.slider-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.slider-row input[type="range"] {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: rgba(99, 102, 241, 0.2);
    appearance: none;
    cursor: pointer;
}

.slider-row input[type="range"]::-webkit-slider-thumb {
    appearance: none;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.3);
}

.slider-value {
    min-width: 45px;
    text-align: center;
    font-weight: 600;
    color: #a5b4fc;
    font-size: 0.9rem;
}

/* Example Box */
.example-box {
    margin-top: 1rem;
    padding: 0.875rem 1rem;
    border-radius: 10px;
    font-size: 0.8rem;
    line-height: 1.5;
}

.example-box-blue { background: rgba(59, 130, 246, 0.1); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.2); }
.example-box-amber { background: rgba(245, 158, 11, 0.1); color: #fcd34d; border: 1px solid rgba(245, 158, 11, 0.2); }
.example-box-pink { background: rgba(236, 72, 153, 0.1); color: #f9a8d4; border: 1px solid rgba(236, 72, 153, 0.2); }
.example-box-cyan { background: rgba(6, 182, 212, 0.1); color: #67e8f9; border: 1px solid rgba(6, 182, 212, 0.2); }
.example-box-purple { background: rgba(139, 92, 246, 0.1); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.2); }
.example-box-red { background: rgba(239, 68, 68, 0.1); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.2); }
.example-box-emerald { background: rgba(16, 185, 129, 0.1); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.2); }
.example-box-orange { background: rgba(249, 115, 22, 0.1); color: #fdba74; border: 1px solid rgba(249, 115, 22, 0.2); }

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
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.admin-quick-action-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-quick-action-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }
.admin-quick-action-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-quick-action-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }

/* Module Status */
.module-status-list { display: flex; flex-direction: column; gap: 0.5rem; }

.module-status-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.module-status-item:last-child { border-bottom: none; }

.module-status-indicator {
    width: 8px; height: 8px;
    border-radius: 50%;
}

.module-status-indicator.active { background: #22c55e; box-shadow: 0 0 8px rgba(34, 197, 94, 0.5); }
.module-status-indicator.inactive { background: #64748b; }

.module-status-label { flex: 1; font-size: 0.85rem; color: rgba(255, 255, 255, 0.8); }

.module-status-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    text-transform: uppercase;
}

.module-status-badge.on { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
.module-status-badge.off { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

/* Tips */
.tips-list { display: flex; flex-direction: column; gap: 1rem; }

.tip-item { display: flex; gap: 0.75rem; }

.tip-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.tip-icon-blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.tip-icon-pink { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
.tip-icon-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }

.tip-content { flex: 1; }
.tip-title { font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 0.125rem; }
.tip-text { font-size: 0.75rem; color: rgba(255, 255, 255, 0.5); line-height: 1.4; }

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1.5rem 0;
    margin-bottom: 2rem;
}

/* Section Header */
.admin-section-header { margin-bottom: 1.5rem; }

.admin-section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-section-title i { color: #06b6d4; }

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

@media (max-width: 1200px) { .admin-modules-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .admin-modules-grid { grid-template-columns: 1fr; } }

.admin-module-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
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
    border-color: rgba(6, 182, 212, 0.3);
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(59, 130, 246, 0.05));
}

.admin-module-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-module-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-module-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }
.admin-module-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }

.admin-module-icon-gradient-cyan {
    background: linear-gradient(135deg, #06b6d4, #3b82f6);
    color: white;
    box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
}

.admin-module-content { flex: 1; min-width: 0; }
.admin-module-title { font-size: 0.95rem; font-weight: 600; color: #fff; margin: 0; }
.admin-module-desc { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin: 0.125rem 0 0 0; }

.admin-module-arrow {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.admin-module-card:hover .admin-module-arrow { color: #06b6d4; transform: translateX(4px); }
</style>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
