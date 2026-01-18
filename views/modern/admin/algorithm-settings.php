<?php
/**
 * Admin Algorithm Settings - Gold Standard
 * Unified MatchRank (Listings) & CommunityRank (Members) Configuration
 * STANDALONE admin interface - does NOT use main site header/footer
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Database;

$basePath = TenantContext::getBasePath();
$tenantId = TenantContext::getId();

// Get current algorithm settings from tenant config
$db = Database::getInstance();
$tenantStmt = $db->prepare("SELECT configuration FROM tenants WHERE id = ?");
$tenantStmt->execute([$tenantId]);
$tenantRow = $tenantStmt->fetch();
$configJson = $tenantRow ? json_decode($tenantRow['configuration'] ?? '{}', true) : [];
if (!is_array($configJson)) $configJson = [];

$algorithms = $configJson['algorithms'] ?? [];
$sharedSettings = $algorithms['shared'] ?? [];
$listingsSettings = $algorithms['listings'] ?? [];
$membersSettings = $algorithms['members'] ?? [];

// Shared defaults
$sharedDefaults = [
    'geo_enabled' => true,
    'geo_full_radius_km' => 15,
    'geo_half_life_km' => 50,
    'geo_minimum' => 0.1,
    'freshness_enabled' => true,
    'freshness_full_days' => 7,
    'freshness_half_life_days' => 30,
    'freshness_minimum' => 0.3,
];

// MatchRank (Listings) defaults
$listingsDefaults = [
    'enabled' => true,
    'relevance_category_match' => 2.0,
    'relevance_search_boost' => 1.5,
    'freshness_full_days' => 7,
    'freshness_half_life_days' => 30,
    'engagement_view_weight' => 0.1,
    'engagement_inquiry_weight' => 5.0,
    'engagement_save_weight' => 3.0,
    'quality_description_min' => 100,
    'quality_description_boost' => 1.3,
    'quality_image_boost' => 1.4,
    'quality_location_boost' => 1.2,
    'quality_verified_boost' => 1.5,
    'reciprocity_enabled' => true,
    'reciprocity_boost' => 1.8,
    'weight_relevance' => 0.25,
    'weight_freshness' => 0.20,
    'weight_engagement' => 0.15,
    'weight_proximity' => 0.15,
    'weight_quality' => 0.15,
    'weight_reciprocity' => 0.10,
];

// CommunityRank (Members) defaults
$membersDefaults = [
    'enabled' => true,
    'activity_full_days' => 7,
    'activity_decay_days' => 60,
    'activity_minimum' => 0.2,
    'contribution_listing_points' => 5,
    'contribution_hours_multiplier' => 2,
    'contribution_max_score' => 100,
    'reputation_account_age_months' => 12,
    'reputation_verified_boost' => 1.5,
    'reputation_profile_complete_boost' => 1.3,
    'connectivity_shared_group_boost' => 1.2,
    'connectivity_past_interaction_boost' => 1.5,
    'complementary_enabled' => true,
    'complementary_offer_request_boost' => 2.0,
    'weight_activity' => 0.20,
    'weight_contribution' => 0.20,
    'weight_reputation' => 0.15,
    'weight_connectivity' => 0.15,
    'weight_proximity' => 0.15,
    'weight_complementary' => 0.15,
];

// Merge defaults with saved settings
$shared = array_merge($sharedDefaults, $sharedSettings);
$listings = array_merge($listingsDefaults, $listingsSettings);
$members = array_merge($membersDefaults, $membersSettings);

// Count active modules
$activeModules = 0;
if ($shared['geo_enabled']) $activeModules++;
if ($shared['freshness_enabled']) $activeModules++;
if ($listings['enabled']) $activeModules++;
if ($members['enabled']) $activeModules++;
if ($listings['reciprocity_enabled']) $activeModules++;
if ($members['complementary_enabled']) $activeModules++;

// Admin header configuration
$adminPageTitle = 'Algorithm Settings';
$adminPageSubtitle = 'Ranking Intelligence';
$adminPageIcon = 'fa-scale-balanced';

// Include the standalone admin header
require __DIR__ . '/partials/admin-header.php';
?>

<!-- Flash Messages -->
<?php if (isset($_GET['saved'])): ?>
<div class="admin-flash admin-flash-success">
    <div class="admin-flash-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-flash-content">
        <strong>Success!</strong> Algorithm settings saved successfully.
    </div>
    <button class="admin-flash-close" onclick="this.parentElement.remove()">
        <i class="fa-solid fa-times"></i>
    </button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="admin-flash admin-flash-error">
    <div class="admin-flash-icon">
        <i class="fa-solid fa-exclamation-circle"></i>
    </div>
    <div class="admin-flash-content">
        <strong>Error!</strong> Failed to save algorithm settings. Please try again.
    </div>
    <button class="admin-flash-close" onclick="this.parentElement.remove()">
        <i class="fa-solid fa-times"></i>
    </button>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-scale-balanced"></i>
            Algorithm Settings
        </h1>
        <p class="admin-page-subtitle">Configure MatchRank for Listings and CommunityRank for Members</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/feed-algorithm" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-sliders"></i>
            Feed Algorithm
        </a>
        <a href="<?= $basePath ?>/admin" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-store"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $listings['enabled'] ? 'Active' : 'Off' ?></div>
            <div class="admin-stat-label">MatchRank</div>
        </div>
        <div class="admin-stat-trend <?= $listings['enabled'] ? 'admin-stat-trend-up' : '' ?>">
            <i class="fa-solid fa-<?= $listings['enabled'] ? 'check' : 'pause' ?>"></i>
            <span>Listings</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $members['enabled'] ? 'Active' : 'Off' ?></div>
            <div class="admin-stat-label">CommunityRank</div>
        </div>
        <div class="admin-stat-trend <?= $members['enabled'] ? 'admin-stat-trend-up' : '' ?>">
            <i class="fa-solid fa-<?= $members['enabled'] ? 'check' : 'pause' ?>"></i>
            <span>Members</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-cubes"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $activeModules ?></div>
            <div class="admin-stat-label">Active Modules</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-layer-group"></i>
            <span>Features</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-pink">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-location-dot"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $shared['geo_full_radius_km'] ?>km</div>
            <div class="admin-stat-label">Geo Radius</div>
        </div>
        <div class="admin-stat-trend <?= $shared['geo_enabled'] ? 'admin-stat-trend-up' : '' ?>">
            <i class="fa-solid fa-<?= $shared['geo_enabled'] ? 'check' : 'pause' ?>"></i>
            <span><?= $shared['geo_enabled'] ? 'Enabled' : 'Disabled' ?></span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="admin-dashboard-grid">
    <!-- Main Column -->
    <div class="admin-dashboard-main">

        <!-- Algorithm Tabs -->
        <div class="algo-tabs">
            <button type="button" class="algo-tab active" data-tab="shared">
                <i class="fa-solid fa-sliders"></i>
                <span>Shared Settings</span>
            </button>
            <button type="button" class="algo-tab" data-tab="listings">
                <i class="fa-solid fa-store"></i>
                <span>MatchRank</span>
            </button>
            <button type="button" class="algo-tab" data-tab="members">
                <i class="fa-solid fa-users"></i>
                <span>CommunityRank</span>
            </button>
        </div>

        <!-- ============================================ -->
        <!-- SHARED SETTINGS TAB -->
        <!-- ============================================ -->
        <div id="tab-shared" class="algo-tab-content active">
            <form action="<?= $basePath ?>/admin/algorithm-settings/save" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="algorithm_type" value="shared">

                <!-- Geospatial Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-pink">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Geospatial Proximity</h3>
                            <p class="admin-card-subtitle">Shared location-based scoring for all algorithms</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="geo_enabled" value="1" <?= $shared['geo_enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label">Full Score Radius (km)</label>
                                <input type="number" name="geo_full_radius_km" class="setting-input" value="<?= $shared['geo_full_radius_km'] ?>" min="1" max="100" step="1">
                                <span class="setting-hint">Items within this distance = 100% score</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Half-Life Distance (km)</label>
                                <input type="number" name="geo_half_life_km" class="setting-input" value="<?= $shared['geo_half_life_km'] ?>" min="10" max="500" step="5">
                                <span class="setting-hint">Score drops to 50% at this distance</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Minimum Score</label>
                                <div class="slider-row">
                                    <input type="range" name="geo_minimum" class="slider-input" min="0" max="0.5" step="0.05" value="<?= $shared['geo_minimum'] ?>">
                                    <span class="slider-value"><?= $shared['geo_minimum'] * 100 ?>%</span>
                                </div>
                                <span class="setting-hint">Floor for very distant items</span>
                            </div>
                        </div>
                        <div class="example-box example-box-pink">
                            <i class="fa-solid fa-lightbulb"></i>
                            <div>
                                <strong>How it works:</strong> Uses exponential decay with Haversine distance calculation.
                                Items within <?= $shared['geo_full_radius_km'] ?>km get 100%, dropping to 50% at <?= $shared['geo_half_life_km'] ?>km,
                                25% at <?= $shared['geo_half_life_km'] * 2 ?>km, etc. Never below <?= $shared['geo_minimum'] * 100 ?>%.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Freshness Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-cyan">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Content Freshness</h3>
                            <p class="admin-card-subtitle">Default freshness decay settings (can be overridden)</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="freshness_enabled" value="1" <?= $shared['freshness_enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label">Full Score Period (days)</label>
                                <input type="number" name="freshness_full_days" class="setting-input" value="<?= $shared['freshness_full_days'] ?>" min="1" max="30">
                                <span class="setting-hint">Items within this age = 100% freshness</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Half-Life (days)</label>
                                <input type="number" name="freshness_half_life_days" class="setting-input" value="<?= $shared['freshness_half_life_days'] ?>" min="7" max="180">
                                <span class="setting-hint">Score drops to 50% after this many days</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Minimum Score</label>
                                <div class="slider-row">
                                    <input type="range" name="freshness_minimum" class="slider-input" min="0.1" max="0.8" step="0.05" value="<?= $shared['freshness_minimum'] ?>">
                                    <span class="slider-value"><?= $shared['freshness_minimum'] * 100 ?>%</span>
                                </div>
                                <span class="setting-hint">Floor for very old items</span>
                            </div>
                        </div>
                        <div class="example-box example-box-cyan">
                            <i class="fa-solid fa-info-circle"></i>
                            <div>
                                <strong>Note:</strong> These are default values. MatchRank and CommunityRank can override these
                                with algorithm-specific freshness settings if needed.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
                        <i class="fa-solid fa-save"></i>
                        Save Shared Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================================ -->
        <!-- MATCHRANK (LISTINGS) TAB -->
        <!-- ============================================ -->
        <div id="tab-listings" class="algo-tab-content">
            <form action="<?= $basePath ?>/admin/algorithm-settings/save" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="algorithm_type" value="listings">

                <!-- Algorithm Overview Banner -->
                <div class="algo-banner algo-banner-green">
                    <div class="algo-banner-header">
                        <div class="algo-banner-title">
                            <i class="fa-solid fa-store"></i>
                            MatchRank Algorithm
                        </div>
                        <label class="toggle-switch toggle-switch-light">
                            <input type="checkbox" name="enabled" value="1" <?= $listings['enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="algo-formula">
                        <span class="formula-var">match_score</span> =
                        (<span class="formula-weight"><?= $listings['weight_relevance'] * 100 ?>%</span> × relevance) +
                        (<span class="formula-weight"><?= $listings['weight_freshness'] * 100 ?>%</span> × freshness) +
                        (<span class="formula-weight"><?= $listings['weight_engagement'] * 100 ?>%</span> × engagement) +
                        (<span class="formula-weight"><?= $listings['weight_proximity'] * 100 ?>%</span> × proximity) +
                        (<span class="formula-weight"><?= $listings['weight_quality'] * 100 ?>%</span> × quality) +
                        (<span class="formula-weight"><?= $listings['weight_reciprocity'] * 100 ?>%</span> × reciprocity)
                    </div>
                </div>

                <!-- Factor Weights -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-green">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Factor Weights</h3>
                            <p class="admin-card-subtitle">How much each factor contributes to the final score (must sum to 100%)</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="weight-visualizer" id="listings-weight-viz">
                            <div class="weight-bar" style="width: <?= $listings['weight_relevance'] * 100 ?>%; background: #3b82f6;" title="Relevance">R</div>
                            <div class="weight-bar" style="width: <?= $listings['weight_freshness'] * 100 ?>%; background: #8b5cf6;" title="Freshness">F</div>
                            <div class="weight-bar" style="width: <?= $listings['weight_engagement'] * 100 ?>%; background: #ec4899;" title="Engagement">E</div>
                            <div class="weight-bar" style="width: <?= $listings['weight_proximity'] * 100 ?>%; background: #f59e0b;" title="Proximity">P</div>
                            <div class="weight-bar" style="width: <?= $listings['weight_quality'] * 100 ?>%; background: #10b981;" title="Quality">Q</div>
                            <div class="weight-bar" style="width: <?= $listings['weight_reciprocity'] * 100 ?>%; background: #6366f1;" title="Reciprocity">Rc</div>
                        </div>
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label" style="color: #3b82f6;">Relevance Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_relevance" class="slider-input listing-weight" min="0" max="0.5" step="0.05" value="<?= $listings['weight_relevance'] ?>">
                                    <span class="slider-value"><?= $listings['weight_relevance'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #8b5cf6;">Freshness Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_freshness" class="slider-input listing-weight" min="0" max="0.5" step="0.05" value="<?= $listings['weight_freshness'] ?>">
                                    <span class="slider-value"><?= $listings['weight_freshness'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #ec4899;">Engagement Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_engagement" class="slider-input listing-weight" min="0" max="0.5" step="0.05" value="<?= $listings['weight_engagement'] ?>">
                                    <span class="slider-value"><?= $listings['weight_engagement'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #f59e0b;">Proximity Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_proximity" class="slider-input listing-weight" min="0" max="0.5" step="0.05" value="<?= $listings['weight_proximity'] ?>">
                                    <span class="slider-value"><?= $listings['weight_proximity'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #10b981;">Quality Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_quality" class="slider-input listing-weight" min="0" max="0.5" step="0.05" value="<?= $listings['weight_quality'] ?>">
                                    <span class="slider-value"><?= $listings['weight_quality'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #6366f1;">Reciprocity Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_reciprocity" class="slider-input listing-weight" min="0" max="0.5" step="0.05" value="<?= $listings['weight_reciprocity'] ?>">
                                    <span class="slider-value"><?= $listings['weight_reciprocity'] * 100 ?>%</span>
                                </div>
                            </div>
                        </div>
                        <div id="listings-weight-warning" class="example-box example-box-amber" style="display: none;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <strong>Warning:</strong> Weights should sum to 100%. Current: <span id="listings-weight-total">100</span>%
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Relevance Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-blue">
                            <i class="fa-solid fa-bullseye"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Relevance Scoring</h3>
                            <p class="admin-card-subtitle">Category matching and search term boosts</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid settings-grid-2">
                            <div class="setting-group">
                                <label class="setting-label">Category Match Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="relevance_category_match" class="slider-input" min="1.0" max="3.0" step="0.1" value="<?= $listings['relevance_category_match'] ?>">
                                    <span class="slider-value"><?= $listings['relevance_category_match'] ?>x</span>
                                </div>
                                <span class="setting-hint">Boost when listing matches user's preferred category</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Search Term Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="relevance_search_boost" class="slider-input" min="1.0" max="2.5" step="0.1" value="<?= $listings['relevance_search_boost'] ?>">
                                    <span class="slider-value"><?= $listings['relevance_search_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Boost when listing matches search query</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Engagement Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-pink">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Engagement Metrics</h3>
                            <p class="admin-card-subtitle">How user interactions affect listing scores</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label">View Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="engagement_view_weight" class="slider-input" min="0" max="1" step="0.1" value="<?= $listings['engagement_view_weight'] ?>">
                                    <span class="slider-value"><?= $listings['engagement_view_weight'] ?></span>
                                </div>
                                <span class="setting-hint">Points per view (low-intent signal)</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Inquiry Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="engagement_inquiry_weight" class="slider-input" min="1" max="10" step="0.5" value="<?= $listings['engagement_inquiry_weight'] ?>">
                                    <span class="slider-value"><?= $listings['engagement_inquiry_weight'] ?></span>
                                </div>
                                <span class="setting-hint">Points per inquiry (high-intent signal)</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Save/Favorite Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="engagement_save_weight" class="slider-input" min="1" max="10" step="0.5" value="<?= $listings['engagement_save_weight'] ?>">
                                    <span class="slider-value"><?= $listings['engagement_save_weight'] ?></span>
                                </div>
                                <span class="setting-hint">Points per save/favorite</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quality Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-emerald">
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Quality Signals</h3>
                            <p class="admin-card-subtitle">Boost well-crafted listings</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label">Min Description Length</label>
                                <input type="number" name="quality_description_min" class="setting-input" value="<?= $listings['quality_description_min'] ?>" min="20" max="500">
                                <span class="setting-hint">Chars needed for description boost</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Description Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="quality_description_boost" class="slider-input" min="1.0" max="2.0" step="0.1" value="<?= $listings['quality_description_boost'] ?>">
                                    <span class="slider-value"><?= $listings['quality_description_boost'] ?>x</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Image Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="quality_image_boost" class="slider-input" min="1.0" max="2.0" step="0.1" value="<?= $listings['quality_image_boost'] ?>">
                                    <span class="slider-value"><?= $listings['quality_image_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Listings with images</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Location Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="quality_location_boost" class="slider-input" min="1.0" max="2.0" step="0.1" value="<?= $listings['quality_location_boost'] ?>">
                                    <span class="slider-value"><?= $listings['quality_location_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Listings with location set</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Verified Owner Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="quality_verified_boost" class="slider-input" min="1.0" max="2.0" step="0.1" value="<?= $listings['quality_verified_boost'] ?>">
                                    <span class="slider-value"><?= $listings['quality_verified_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Listings from verified users</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reciprocity Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-indigo">
                            <i class="fa-solid fa-handshake"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Reciprocity Matching</h3>
                            <p class="admin-card-subtitle">Boost listings where mutual exchange is possible</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="reciprocity_enabled" value="1" <?= $listings['reciprocity_enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid settings-grid-1">
                            <div class="setting-group">
                                <label class="setting-label">Reciprocity Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="reciprocity_boost" class="slider-input" min="1.0" max="3.0" step="0.1" value="<?= $listings['reciprocity_boost'] ?>">
                                    <span class="slider-value"><?= $listings['reciprocity_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Boost when viewer's offers match listing owner's requests (and vice versa)</span>
                            </div>
                        </div>
                        <div class="example-box example-box-purple">
                            <i class="fa-solid fa-lightbulb"></i>
                            <div>
                                <strong>Example:</strong> If you're offering "Gardening" and viewing a listing from someone requesting "Gardening help",
                                that listing gets a <?= $listings['reciprocity_boost'] ?>x boost. This creates mutual exchange opportunities.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="form-actions">
                    <button type="submit" class="admin-btn admin-btn-success admin-btn-lg">
                        <i class="fa-solid fa-save"></i>
                        Save MatchRank Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================================ -->
        <!-- COMMUNITYRANK (MEMBERS) TAB -->
        <!-- ============================================ -->
        <div id="tab-members" class="algo-tab-content">
            <form action="<?= $basePath ?>/admin/algorithm-settings/save" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="algorithm_type" value="members">

                <!-- Algorithm Overview Banner -->
                <div class="algo-banner algo-banner-purple">
                    <div class="algo-banner-header">
                        <div class="algo-banner-title">
                            <i class="fa-solid fa-users"></i>
                            CommunityRank Algorithm
                        </div>
                        <label class="toggle-switch toggle-switch-light">
                            <input type="checkbox" name="enabled" value="1" <?= $members['enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="algo-formula">
                        <span class="formula-var">community_score</span> =
                        (<span class="formula-weight"><?= $members['weight_activity'] * 100 ?>%</span> × activity) +
                        (<span class="formula-weight"><?= $members['weight_contribution'] * 100 ?>%</span> × contribution) +
                        (<span class="formula-weight"><?= $members['weight_reputation'] * 100 ?>%</span> × reputation) +
                        (<span class="formula-weight"><?= $members['weight_connectivity'] * 100 ?>%</span> × connectivity) +
                        (<span class="formula-weight"><?= $members['weight_proximity'] * 100 ?>%</span> × proximity) +
                        (<span class="formula-weight"><?= $members['weight_complementary'] * 100 ?>%</span> × complementary)
                    </div>
                </div>

                <!-- Factor Weights -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-purple">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Factor Weights</h3>
                            <p class="admin-card-subtitle">How much each factor contributes to member ranking</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="weight-visualizer" id="members-weight-viz">
                            <div class="weight-bar" style="width: <?= $members['weight_activity'] * 100 ?>%; background: #3b82f6;" title="Activity">A</div>
                            <div class="weight-bar" style="width: <?= $members['weight_contribution'] * 100 ?>%; background: #10b981;" title="Contribution">C</div>
                            <div class="weight-bar" style="width: <?= $members['weight_reputation'] * 100 ?>%; background: #f59e0b;" title="Reputation">R</div>
                            <div class="weight-bar" style="width: <?= $members['weight_connectivity'] * 100 ?>%; background: #ec4899;" title="Connectivity">Cn</div>
                            <div class="weight-bar" style="width: <?= $members['weight_proximity'] * 100 ?>%; background: #8b5cf6;" title="Proximity">P</div>
                            <div class="weight-bar" style="width: <?= $members['weight_complementary'] * 100 ?>%; background: #6366f1;" title="Complementary">Cm</div>
                        </div>
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label" style="color: #3b82f6;">Activity Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_activity" class="slider-input member-weight" min="0" max="0.5" step="0.05" value="<?= $members['weight_activity'] ?>">
                                    <span class="slider-value"><?= $members['weight_activity'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #10b981;">Contribution Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_contribution" class="slider-input member-weight" min="0" max="0.5" step="0.05" value="<?= $members['weight_contribution'] ?>">
                                    <span class="slider-value"><?= $members['weight_contribution'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #f59e0b;">Reputation Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_reputation" class="slider-input member-weight" min="0" max="0.5" step="0.05" value="<?= $members['weight_reputation'] ?>">
                                    <span class="slider-value"><?= $members['weight_reputation'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #ec4899;">Connectivity Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_connectivity" class="slider-input member-weight" min="0" max="0.5" step="0.05" value="<?= $members['weight_connectivity'] ?>">
                                    <span class="slider-value"><?= $members['weight_connectivity'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #8b5cf6;">Proximity Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_proximity" class="slider-input member-weight" min="0" max="0.5" step="0.05" value="<?= $members['weight_proximity'] ?>">
                                    <span class="slider-value"><?= $members['weight_proximity'] * 100 ?>%</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label" style="color: #6366f1;">Complementary Weight</label>
                                <div class="slider-row">
                                    <input type="range" name="weight_complementary" class="slider-input member-weight" min="0" max="0.5" step="0.05" value="<?= $members['weight_complementary'] ?>">
                                    <span class="slider-value"><?= $members['weight_complementary'] * 100 ?>%</span>
                                </div>
                            </div>
                        </div>
                        <div id="members-weight-warning" class="example-box example-box-amber" style="display: none;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <strong>Warning:</strong> Weights should sum to 100%. Current: <span id="members-weight-total">100</span>%
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-blue">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Activity Scoring</h3>
                            <p class="admin-card-subtitle">Prioritize recently active members</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label">Full Score Period (days)</label>
                                <input type="number" name="activity_full_days" class="setting-input" value="<?= $members['activity_full_days'] ?>" min="1" max="30">
                                <span class="setting-hint">Active within this time = 100% activity score</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Decay Period (days)</label>
                                <input type="number" name="activity_decay_days" class="setting-input" value="<?= $members['activity_decay_days'] ?>" min="14" max="365">
                                <span class="setting-hint">Score decays to minimum over this period</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Minimum Activity Score</label>
                                <div class="slider-row">
                                    <input type="range" name="activity_minimum" class="slider-input" min="0" max="0.5" step="0.05" value="<?= $members['activity_minimum'] ?>">
                                    <span class="slider-value"><?= $members['activity_minimum'] * 100 ?>%</span>
                                </div>
                                <span class="setting-hint">Floor for inactive members</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contribution Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-emerald">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Contribution Scoring</h3>
                            <p class="admin-card-subtitle">Reward members who actively contribute</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label">Listing Points</label>
                                <input type="number" name="contribution_listing_points" class="setting-input" value="<?= $members['contribution_listing_points'] ?>" min="1" max="20">
                                <span class="setting-hint">Points per active listing</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Hours Given Multiplier</label>
                                <div class="slider-row">
                                    <input type="range" name="contribution_hours_multiplier" class="slider-input" min="0.5" max="5" step="0.5" value="<?= $members['contribution_hours_multiplier'] ?>">
                                    <span class="slider-value"><?= $members['contribution_hours_multiplier'] ?>x</span>
                                </div>
                                <span class="setting-hint">Points per timebank hour given</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Max Contribution Score</label>
                                <input type="number" name="contribution_max_score" class="setting-input" value="<?= $members['contribution_max_score'] ?>" min="50" max="500">
                                <span class="setting-hint">Cap to prevent top-heavy scores</span>
                            </div>
                        </div>
                        <div class="example-box example-box-green">
                            <i class="fa-solid fa-calculator"></i>
                            <div>
                                <strong>Calculation:</strong> contribution_score = min((listings × <?= $members['contribution_listing_points'] ?>) + (hours_given × <?= $members['contribution_hours_multiplier'] ?>), <?= $members['contribution_max_score'] ?>)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reputation Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-amber">
                            <i class="fa-solid fa-award"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Reputation Signals</h3>
                            <p class="admin-card-subtitle">Account age, verification, and profile completeness</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid">
                            <div class="setting-group">
                                <label class="setting-label">Full Account Age (months)</label>
                                <input type="number" name="reputation_account_age_months" class="setting-input" value="<?= $members['reputation_account_age_months'] ?>" min="1" max="36">
                                <span class="setting-hint">Months for max age bonus</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Verified User Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="reputation_verified_boost" class="slider-input" min="1.0" max="2.0" step="0.1" value="<?= $members['reputation_verified_boost'] ?>">
                                    <span class="slider-value"><?= $members['reputation_verified_boost'] ?>x</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Complete Profile Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="reputation_profile_complete_boost" class="slider-input" min="1.0" max="2.0" step="0.1" value="<?= $members['reputation_profile_complete_boost'] ?>">
                                    <span class="slider-value"><?= $members['reputation_profile_complete_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Bio, avatar, location filled</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Connectivity Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-pink">
                            <i class="fa-solid fa-people-group"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Connectivity Scoring</h3>
                            <p class="admin-card-subtitle">Shared groups and past interactions</p>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid settings-grid-2">
                            <div class="setting-group">
                                <label class="setting-label">Shared Group Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="connectivity_shared_group_boost" class="slider-input" min="1.0" max="2.0" step="0.1" value="<?= $members['connectivity_shared_group_boost'] ?>">
                                    <span class="slider-value"><?= $members['connectivity_shared_group_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Boost per shared group membership</span>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Past Interaction Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="connectivity_past_interaction_boost" class="slider-input" min="1.0" max="2.5" step="0.1" value="<?= $members['connectivity_past_interaction_boost'] ?>">
                                    <span class="slider-value"><?= $members['connectivity_past_interaction_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Boost for previous transactions</span>
                            </div>
                        </div>
                        <div class="example-box example-box-pink">
                            <i class="fa-solid fa-diagram-project"></i>
                            <div>
                                <strong>Social Graph:</strong> Members who share groups with the viewer or have had past timebank transactions
                                are ranked higher to encourage existing community connections.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Complementary Skills Settings -->
                <div class="admin-glass-card">
                    <div class="admin-card-header">
                        <div class="admin-card-header-icon admin-card-header-icon-indigo">
                            <i class="fa-solid fa-puzzle-piece"></i>
                        </div>
                        <div class="admin-card-header-content">
                            <h3 class="admin-card-title">Complementary Skills</h3>
                            <p class="admin-card-subtitle">Match members with complementary offers and requests</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="complementary_enabled" value="1" <?= $members['complementary_enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="admin-card-body">
                        <div class="settings-grid settings-grid-1">
                            <div class="setting-group">
                                <label class="setting-label">Offer/Request Match Boost</label>
                                <div class="slider-row">
                                    <input type="range" name="complementary_offer_request_boost" class="slider-input" min="1.0" max="3.0" step="0.1" value="<?= $members['complementary_offer_request_boost'] ?>">
                                    <span class="slider-value"><?= $members['complementary_offer_request_boost'] ?>x</span>
                                </div>
                                <span class="setting-hint">Boost when viewer's offers match member's requests (and vice versa)</span>
                            </div>
                        </div>
                        <div class="example-box example-box-purple">
                            <i class="fa-solid fa-lightbulb"></i>
                            <div>
                                <strong>Example:</strong> If you're offering "Web Design" and a member is requesting "Website Help",
                                they get a <?= $members['complementary_offer_request_boost'] ?>x boost to facilitate mutual exchange.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="form-actions">
                    <button type="submit" class="admin-btn admin-btn-purple admin-btn-lg">
                        <i class="fa-solid fa-save"></i>
                        Save CommunityRank Settings
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- Sidebar -->
    <div class="admin-dashboard-sidebar">

        <!-- Quick Actions -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-cyan">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Quick Actions</h3>
                    <p class="admin-card-subtitle">Common tasks</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-quick-actions-list">
                    <a href="<?= $basePath ?>/admin/feed-algorithm" class="admin-quick-action-item">
                        <div class="admin-quick-action-icon admin-quick-action-icon-indigo">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <span>Feed Algorithm</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin/smart-matching" class="admin-quick-action-item">
                        <div class="admin-quick-action-icon admin-quick-action-icon-pink">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                        <span>Smart Matching</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin/ai-settings" class="admin-quick-action-item">
                        <div class="admin-quick-action-icon admin-quick-action-icon-purple">
                            <i class="fa-solid fa-microchip"></i>
                        </div>
                        <span>AI Settings</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin/listings" class="admin-quick-action-item">
                        <div class="admin-quick-action-icon admin-quick-action-icon-green">
                            <i class="fa-solid fa-list"></i>
                        </div>
                        <span>View Listings</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Module Status -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-emerald">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Module Status</h3>
                    <p class="admin-card-subtitle">Current configuration</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="module-status-list">
                    <div class="module-status-item">
                        <span class="module-status-name">MatchRank (Listings)</span>
                        <span class="module-status-badge <?= $listings['enabled'] ? 'badge-success' : 'badge-muted' ?>">
                            <?= $listings['enabled'] ? 'ON' : 'OFF' ?>
                        </span>
                    </div>
                    <div class="module-status-item">
                        <span class="module-status-name">CommunityRank (Members)</span>
                        <span class="module-status-badge <?= $members['enabled'] ? 'badge-success' : 'badge-muted' ?>">
                            <?= $members['enabled'] ? 'ON' : 'OFF' ?>
                        </span>
                    </div>
                    <div class="module-status-item">
                        <span class="module-status-name">Geospatial Proximity</span>
                        <span class="module-status-badge <?= $shared['geo_enabled'] ? 'badge-success' : 'badge-muted' ?>">
                            <?= $shared['geo_enabled'] ? 'ON' : 'OFF' ?>
                        </span>
                    </div>
                    <div class="module-status-item">
                        <span class="module-status-name">Content Freshness</span>
                        <span class="module-status-badge <?= $shared['freshness_enabled'] ? 'badge-success' : 'badge-muted' ?>">
                            <?= $shared['freshness_enabled'] ? 'ON' : 'OFF' ?>
                        </span>
                    </div>
                    <div class="module-status-item">
                        <span class="module-status-name">Reciprocity Matching</span>
                        <span class="module-status-badge <?= $listings['reciprocity_enabled'] ? 'badge-success' : 'badge-muted' ?>">
                            <?= $listings['reciprocity_enabled'] ? 'ON' : 'OFF' ?>
                        </span>
                    </div>
                    <div class="module-status-item">
                        <span class="module-status-name">Complementary Skills</span>
                        <span class="module-status-badge <?= $members['complementary_enabled'] ? 'badge-success' : 'badge-muted' ?>">
                            <?= $members['complementary_enabled'] ? 'ON' : 'OFF' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Algorithm Tips -->
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
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Weights must sum to 100% for balanced scoring</span>
                    </div>
                    <div class="tip-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Higher geo radius = broader local matches</span>
                    </div>
                    <div class="tip-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Reciprocity boosts mutual exchange opportunities</span>
                    </div>
                    <div class="tip-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Quality signals reward well-crafted content</span>
                    </div>
                    <div class="tip-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Test changes with a small user group first</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Module Navigation -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-grid-2"></i>
        Related Modules
    </h2>
    <p class="admin-section-subtitle">Other intelligence and ranking tools</p>
</div>

<div class="admin-modules-grid admin-modules-grid-4">
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

    <a href="<?= $basePath ?>/admin/ai-settings" class="admin-module-card admin-module-card-gradient">
        <div class="admin-module-icon admin-module-icon-gradient-indigo">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">AI Settings</h4>
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

    <a href="<?= $basePath ?>/admin/gamification" class="admin-module-card">
        <div class="admin-module-icon admin-module-icon-amber">
            <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="admin-module-content">
            <h4 class="admin-module-title">Gamification</h4>
            <p class="admin-module-desc">Badges & achievements</p>
        </div>
        <i class="fa-solid fa-arrow-right admin-module-arrow"></i>
    </a>
</div>

<style>
/**
 * Algorithm Settings - Gold Standard Styles
 */

/* Flash Messages */
.admin-flash {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.admin-flash-success {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-flash-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-flash-icon {
    font-size: 1.25rem;
}

.admin-flash-success .admin-flash-icon { color: #22c55e; }
.admin-flash-error .admin-flash-icon { color: #ef4444; }

.admin-flash-content {
    flex: 1;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.admin-flash-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s;
}

.admin-flash-close:hover {
    color: rgba(255, 255, 255, 0.8);
}

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
    color: #10b981;
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
    .admin-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .admin-stats-grid { grid-template-columns: 1fr; }
}

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

.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-purple { --stat-color: #8b5cf6; }
.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-pink { --stat-color: #ec4899; }

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

.admin-stat-content { flex: 1; }

.admin-stat-value {
    font-size: 1.5rem;
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

/* Dashboard Grid */
.admin-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    margin-bottom: 3rem;
}

@media (max-width: 1200px) {
    .admin-dashboard-grid { grid-template-columns: 1fr; }
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

/* Tabs */
.algo-tabs {
    display: flex;
    gap: 0.5rem;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    padding: 0.5rem;
    border-radius: 14px;
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.algo-tab {
    flex: 1;
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    background: transparent;
    color: rgba(255, 255, 255, 0.6);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
}

.algo-tab:hover {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.8);
}

.algo-tab.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.algo-tab-content {
    display: none;
}

.algo-tab-content.active {
    display: block;
}

/* Algorithm Banner */
.algo-banner {
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.algo-banner-green {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.algo-banner-purple {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
}

.algo-banner-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.algo-banner-title {
    color: white;
    font-size: 1.25rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.algo-formula {
    background: rgba(0, 0, 0, 0.2);
    padding: 1rem 1.25rem;
    border-radius: 10px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.9);
    overflow-x: auto;
}

.formula-var {
    color: #a7f3d0;
    font-weight: 600;
}

.algo-banner-purple .formula-var {
    color: #ddd6fe;
}

.formula-weight {
    color: #fbbf24;
    font-weight: 600;
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 1.5rem;
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
.admin-card-header-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }
.admin-card-header-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.admin-card-header-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-card-header-icon-indigo { background: rgba(99, 102, 241, 0.2); color: #6366f1; }
.admin-card-header-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.admin-card-header-icon-amber { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }

.admin-card-header-content { flex: 1; }

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

.admin-card-body {
    padding: 1.5rem;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.1);
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: rgba(255, 255, 255, 0.6);
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
    background-color: white;
}

.toggle-switch-light .toggle-slider {
    background-color: rgba(255, 255, 255, 0.2);
}

.toggle-switch-light .toggle-slider:before {
    background-color: white;
}

/* Settings Grid */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.settings-grid-2 {
    grid-template-columns: repeat(2, 1fr);
}

.settings-grid-1 {
    grid-template-columns: 1fr;
}

@media (max-width: 900px) {
    .settings-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .settings-grid, .settings-grid-2 { grid-template-columns: 1fr; }
}

.setting-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.setting-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

.setting-input {
    padding: 0.75rem 1rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    font-size: 0.95rem;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.setting-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.setting-hint {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

/* Slider */
.slider-row {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.slider-input {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.1);
    appearance: none;
    cursor: pointer;
}

.slider-input::-webkit-slider-thumb {
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);
}

.slider-value {
    min-width: 50px;
    text-align: center;
    font-weight: 600;
    color: #8b5cf6;
    font-size: 0.9rem;
}

/* Weight Visualizer */
.weight-visualizer {
    display: flex;
    height: 28px;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
}

.weight-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
    transition: width 0.3s ease;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

/* Example Box */
.example-box {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-top: 1.5rem;
}

.example-box i {
    font-size: 1rem;
    margin-top: 0.1rem;
}

.example-box-cyan {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.2);
    color: #67e8f9;
}

.example-box-cyan i { color: #06b6d4; }

.example-box-pink {
    background: rgba(236, 72, 153, 0.1);
    border: 1px solid rgba(236, 72, 153, 0.2);
    color: #f9a8d4;
}

.example-box-pink i { color: #ec4899; }

.example-box-purple {
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.2);
    color: #c4b5fd;
}

.example-box-purple i { color: #8b5cf6; }

.example-box-green {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.2);
    color: #86efac;
}

.example-box-green i { color: #22c55e; }

.example-box-amber {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}

.example-box-amber i { color: #f59e0b; }

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
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

.admin-btn-lg {
    padding: 0.875rem 1.75rem;
    font-size: 0.95rem;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
    transform: translateY(-1px);
}

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
}

.admin-btn-success:hover {
    box-shadow: 0 4px 20px rgba(34, 197, 94, 0.4);
    transform: translateY(-1px);
}

.admin-btn-purple {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: #fff;
}

.admin-btn-purple:hover {
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
}

/* Quick Actions List */
.admin-quick-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.admin-quick-action-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 10px;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    transition: all 0.2s;
}

.admin-quick-action-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateX(4px);
}

.admin-quick-action-item span {
    flex: 1;
}

.admin-quick-action-item i:last-child {
    font-size: 0.7rem;
    opacity: 0.5;
}

.admin-quick-action-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.admin-quick-action-icon-indigo { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
.admin-quick-action-icon-pink { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
.admin-quick-action-icon-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
.admin-quick-action-icon-green { background: rgba(34, 197, 94, 0.2); color: #4ade80; }

/* Module Status List */
.module-status-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.module-status-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.module-status-item:last-child {
    border-bottom: none;
}

.module-status-name {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

.module-status-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.25rem 0.625rem;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
}

.badge-muted {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.4);
}

/* Tips List */
.tips-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

.tip-item i {
    color: #22c55e;
    margin-top: 0.1rem;
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

.admin-modules-grid-4 {
    grid-template-columns: repeat(4, 1fr);
}

@media (max-width: 1200px) {
    .admin-modules-grid, .admin-modules-grid-4 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .admin-modules-grid, .admin-modules-grid-4 { grid-template-columns: 1fr; }
}

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

.admin-module-icon-indigo { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
.admin-module-icon-amber { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }

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
    color: #10b981;
    transform: translateX(4px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabs = document.querySelectorAll('.algo-tab');
    const contents = document.querySelectorAll('.algo-tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;

            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        });
    });

    // Slider value updates
    document.querySelectorAll('.slider-input').forEach(slider => {
        slider.addEventListener('input', function() {
            const valueSpan = this.parentElement.querySelector('.slider-value');
            if (valueSpan) {
                let value = parseFloat(this.value);
                // Determine format based on slider
                if (this.name.includes('weight_') || this.name.includes('minimum')) {
                    valueSpan.textContent = Math.round(value * 100) + '%';
                } else if (value === Math.floor(value)) {
                    valueSpan.textContent = value + (this.name.includes('boost') || this.name.includes('multiplier') ? 'x' : '');
                } else {
                    valueSpan.textContent = value + (this.name.includes('boost') || this.name.includes('multiplier') ? 'x' : '');
                }
            }
        });
    });

    // Weight validation for listings
    function updateListingWeights() {
        const inputs = document.querySelectorAll('.listing-weight');
        let total = 0;

        inputs.forEach(input => {
            total += parseFloat(input.value);
        });

        const warning = document.getElementById('listings-weight-warning');
        const totalSpan = document.getElementById('listings-weight-total');
        const roundedTotal = Math.round(total * 100);

        if (roundedTotal !== 100) {
            warning.style.display = 'flex';
            totalSpan.textContent = roundedTotal;
        } else {
            warning.style.display = 'none';
        }

        // Update visualizer
        const viz = document.getElementById('listings-weight-viz');
        const bars = viz.querySelectorAll('.weight-bar');
        let i = 0;
        inputs.forEach(input => {
            const pct = total > 0 ? (parseFloat(input.value) / total) * 100 : 0;
            bars[i].style.width = pct + '%';
            i++;
        });
    }

    // Weight validation for members
    function updateMemberWeights() {
        const inputs = document.querySelectorAll('.member-weight');
        let total = 0;

        inputs.forEach(input => {
            total += parseFloat(input.value);
        });

        const warning = document.getElementById('members-weight-warning');
        const totalSpan = document.getElementById('members-weight-total');
        const roundedTotal = Math.round(total * 100);

        if (roundedTotal !== 100) {
            warning.style.display = 'flex';
            totalSpan.textContent = roundedTotal;
        } else {
            warning.style.display = 'none';
        }

        // Update visualizer
        const viz = document.getElementById('members-weight-viz');
        const bars = viz.querySelectorAll('.weight-bar');
        let i = 0;
        inputs.forEach(input => {
            const pct = total > 0 ? (parseFloat(input.value) / total) * 100 : 0;
            bars[i].style.width = pct + '%';
            i++;
        });
    }

    // Attach weight listeners
    document.querySelectorAll('.listing-weight').forEach(input => {
        input.addEventListener('input', updateListingWeights);
    });

    document.querySelectorAll('.member-weight').forEach(input => {
        input.addEventListener('input', updateMemberWeights);
    });

    // Auto-dismiss flash messages
    setTimeout(() => {
        document.querySelectorAll('.admin-flash').forEach(flash => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(() => flash.remove(), 300);
        });
    }, 5000);
});
</script>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
