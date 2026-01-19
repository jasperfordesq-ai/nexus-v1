<?php
/**
 * Smart Matching Configuration - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Algorithm Configuration';
$adminPageSubtitle = 'Smart Matching';
$adminPageIcon = 'fa-sliders';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$config = $config ?? [];

// Default weights if not configured (convert from decimal to percentage if needed)
$rawWeights = $config['weights'] ?? [];
$weights = [
    'category' => isset($rawWeights['category']) ? (int)($rawWeights['category'] <= 1 ? $rawWeights['category'] * 100 : $rawWeights['category']) : 25,
    'skill' => isset($rawWeights['skill']) ? (int)($rawWeights['skill'] <= 1 ? $rawWeights['skill'] * 100 : $rawWeights['skill']) : 20,
    'proximity' => isset($rawWeights['proximity']) ? (int)($rawWeights['proximity'] <= 1 ? $rawWeights['proximity'] * 100 : $rawWeights['proximity']) : 25,
    'freshness' => isset($rawWeights['freshness']) ? (int)($rawWeights['freshness'] <= 1 ? $rawWeights['freshness'] * 100 : $rawWeights['freshness']) : 10,
    'reciprocity' => isset($rawWeights['reciprocity']) ? (int)($rawWeights['reciprocity'] <= 1 ? $rawWeights['reciprocity'] * 100 : $rawWeights['reciprocity']) : 15,
    'quality' => isset($rawWeights['quality']) ? (int)($rawWeights['quality'] <= 1 ? $rawWeights['quality'] * 100 : $rawWeights['quality']) : 5
];

// Default proximity tiers (in km)
$proximity_tiers = $config['proximity_tiers'] ?? [
    'walking' => 5,
    'local' => 15,
    'city' => 30,
    'regional' => 50,
    'max' => 100
];

// Default thresholds
$thresholds = [
    'hot_match' => $config['hot_match_threshold'] ?? 85,
    'min_score' => $config['min_match_score'] ?? 40,
    'max_distance' => $config['max_distance_km'] ?? 50
];

// Is enabled
$enabled = $config['enabled'] ?? true;

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/smart-matching" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Algorithm Configuration
        </h1>
        <p class="admin-page-subtitle">Fine-tune matching weights and thresholds</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/smart-matching" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="<?= $basePath ?>/admin/smart-matching/analytics" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-line"></i> Analytics
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($flashSuccess): ?>
<div class="config-flash config-flash-success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="config-flash config-flash-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<form action="<?= $basePath ?>/admin/smart-matching/configuration" method="POST" id="configForm">
    <?= Csrf::input() ?>

    <!-- Enable/Disable Card -->
    <div class="admin-glass-card" style="max-width: 1100px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #06b6d4);">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Smart Matching Engine</h3>
                <p class="admin-card-subtitle">Enable or disable the AI-powered matching system</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="config-toggle-row">
                <div class="config-toggle-info">
                    <strong>Enable Smart Matching</strong>
                    <p>When enabled, the AI-powered matching algorithm will suggest relevant matches to users based on their listings and preferences.</p>
                </div>
                <label class="config-toggle">
                    <input type="checkbox" name="enabled" <?= $enabled ? 'checked' : '' ?>>
                    <span class="config-toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- Algorithm Weights Card -->
    <div class="admin-glass-card" style="max-width: 1100px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Algorithm Weights</h3>
                <p class="admin-card-subtitle">Adjust how each factor influences match scores</p>
            </div>
            <div class="config-weight-total">
                <span class="config-weight-total-label">Total:</span>
                <span class="config-weight-total-value" id="weightTotal">100%</span>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="config-info-box">
                <i class="fa-solid fa-info-circle"></i>
                <span>Adjust how much each factor influences match scores. Weights must total 100%.</span>
            </div>

            <div class="config-weights-grid">
                <div class="config-weight-item">
                    <div class="config-weight-header">
                        <div class="config-weight-label">
                            <span class="config-weight-emoji">🏷️</span>
                            Category Match
                        </div>
                        <span class="config-weight-value" id="weightCategoryValue"><?= $weights['category'] ?>%</span>
                    </div>
                    <p class="config-weight-desc">Matching offers with complementary requests in the same category</p>
                    <input type="range" class="config-slider config-slider-purple" name="weight_category"
                           id="weightCategory" min="0" max="50" value="<?= $weights['category'] ?>"
                           oninput="updateWeights()">
                </div>

                <div class="config-weight-item">
                    <div class="config-weight-header">
                        <div class="config-weight-label">
                            <span class="config-weight-emoji">🎯</span>
                            Skill Alignment
                        </div>
                        <span class="config-weight-value" id="weightSkillValue"><?= $weights['skill'] ?>%</span>
                    </div>
                    <p class="config-weight-desc">User skills matching listing requirements and keywords</p>
                    <input type="range" class="config-slider config-slider-blue" name="weight_skill"
                           id="weightSkill" min="0" max="50" value="<?= $weights['skill'] ?>"
                           oninput="updateWeights()">
                </div>

                <div class="config-weight-item">
                    <div class="config-weight-header">
                        <div class="config-weight-label">
                            <span class="config-weight-emoji">📍</span>
                            Proximity
                        </div>
                        <span class="config-weight-value" id="weightProximityValue"><?= $weights['proximity'] ?>%</span>
                    </div>
                    <p class="config-weight-desc">Geographic closeness between users (uses Haversine formula)</p>
                    <input type="range" class="config-slider config-slider-teal" name="weight_proximity"
                           id="weightProximity" min="0" max="50" value="<?= $weights['proximity'] ?>"
                           oninput="updateWeights()">
                </div>

                <div class="config-weight-item">
                    <div class="config-weight-header">
                        <div class="config-weight-label">
                            <span class="config-weight-emoji">✨</span>
                            Freshness
                        </div>
                        <span class="config-weight-value" id="weightFreshnessValue"><?= $weights['freshness'] ?>%</span>
                    </div>
                    <p class="config-weight-desc">Boost for newer listings to encourage fresh activity</p>
                    <input type="range" class="config-slider config-slider-amber" name="weight_freshness"
                           id="weightFreshness" min="0" max="30" value="<?= $weights['freshness'] ?>"
                           oninput="updateWeights()">
                </div>

                <div class="config-weight-item">
                    <div class="config-weight-header">
                        <div class="config-weight-label">
                            <span class="config-weight-emoji">🤝</span>
                            Reciprocity
                        </div>
                        <span class="config-weight-value" id="weightReciprocityValue"><?= $weights['reciprocity'] ?>%</span>
                    </div>
                    <p class="config-weight-desc">Boost when both users can help each other (mutual matches)</p>
                    <input type="range" class="config-slider config-slider-pink" name="weight_reciprocity"
                           id="weightReciprocity" min="0" max="30" value="<?= $weights['reciprocity'] ?>"
                           oninput="updateWeights()">
                </div>

                <div class="config-weight-item">
                    <div class="config-weight-header">
                        <div class="config-weight-label">
                            <span class="config-weight-emoji">⭐</span>
                            Quality
                        </div>
                        <span class="config-weight-value" id="weightQualityValue"><?= $weights['quality'] ?>%</span>
                    </div>
                    <p class="config-weight-desc">Listing completeness: description, images, verification status</p>
                    <input type="range" class="config-slider config-slider-orange" name="weight_quality"
                           id="weightQuality" min="0" max="20" value="<?= $weights['quality'] ?>"
                           oninput="updateWeights()">
                </div>
            </div>
        </div>
    </div>

    <!-- Proximity Tiers Card -->
    <div class="admin-glass-card" style="max-width: 1100px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #14b8a6, #22c55e);">
                <i class="fa-solid fa-location-dot"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Proximity Tiers</h3>
                <p class="admin-card-subtitle">Define distance thresholds for proximity scoring</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="config-info-box">
                <i class="fa-solid fa-info-circle"></i>
                <span>Define distance thresholds for proximity scoring. Closer users receive higher scores.</span>
            </div>

            <div class="config-proximity-grid">
                <div class="config-proximity-item config-prox-walking">
                    <div class="config-proximity-icon">🚶</div>
                    <div class="config-proximity-info">
                        <label>Walking Distance</label>
                        <span class="config-proximity-score">100% score</span>
                    </div>
                    <div class="config-proximity-input-wrap">
                        <input type="number" class="config-proximity-input" name="proximity_walking"
                               value="<?= $proximity_tiers['walking'] ?>" min="1" max="10" step="1">
                        <span class="config-proximity-unit">km</span>
                    </div>
                </div>

                <div class="config-proximity-item config-prox-local">
                    <div class="config-proximity-icon">🚲</div>
                    <div class="config-proximity-info">
                        <label>Local Area</label>
                        <span class="config-proximity-score">90% score</span>
                    </div>
                    <div class="config-proximity-input-wrap">
                        <input type="number" class="config-proximity-input" name="proximity_local"
                               value="<?= $proximity_tiers['local'] ?>" min="5" max="25" step="1">
                        <span class="config-proximity-unit">km</span>
                    </div>
                </div>

                <div class="config-proximity-item config-prox-city">
                    <div class="config-proximity-icon">🚗</div>
                    <div class="config-proximity-info">
                        <label>City-Wide</label>
                        <span class="config-proximity-score">70% score</span>
                    </div>
                    <div class="config-proximity-input-wrap">
                        <input type="number" class="config-proximity-input" name="proximity_city"
                               value="<?= $proximity_tiers['city'] ?>" min="10" max="50" step="5">
                        <span class="config-proximity-unit">km</span>
                    </div>
                </div>

                <div class="config-proximity-item config-prox-regional">
                    <div class="config-proximity-icon">🚄</div>
                    <div class="config-proximity-info">
                        <label>Regional</label>
                        <span class="config-proximity-score">50% score</span>
                    </div>
                    <div class="config-proximity-input-wrap">
                        <input type="number" class="config-proximity-input" name="proximity_regional"
                               value="<?= $proximity_tiers['regional'] ?>" min="25" max="100" step="5">
                        <span class="config-proximity-unit">km</span>
                    </div>
                </div>

                <div class="config-proximity-item config-prox-max">
                    <div class="config-proximity-icon">✈️</div>
                    <div class="config-proximity-info">
                        <label>Maximum Distance</label>
                        <span class="config-proximity-score">30% score (cutoff)</span>
                    </div>
                    <div class="config-proximity-input-wrap">
                        <input type="number" class="config-proximity-input" name="proximity_max"
                               value="<?= $proximity_tiers['max'] ?>" min="50" max="500" step="25">
                        <span class="config-proximity-unit">km</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Match Thresholds Card -->
    <div class="admin-glass-card" style="max-width: 1100px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #f97316);">
                <i class="fa-solid fa-gauge"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Match Thresholds</h3>
                <p class="admin-card-subtitle">Set minimum scores and distance limits</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="config-thresholds-grid">
                <div class="config-threshold-item">
                    <div class="config-threshold-header">
                        <div class="config-threshold-label">
                            <span class="config-threshold-emoji">🔥</span>
                            Hot Match Threshold
                        </div>
                        <span class="config-threshold-value" id="thresholdHotValue"><?= $thresholds['hot_match'] ?>%</span>
                    </div>
                    <p class="config-threshold-desc">Minimum score to be flagged as a "Hot Match" (premium)</p>
                    <input type="range" class="config-slider config-slider-red" name="hot_match_threshold"
                           id="thresholdHot" min="70" max="95" value="<?= $thresholds['hot_match'] ?>"
                           oninput="document.getElementById('thresholdHotValue').textContent = this.value + '%'">
                </div>

                <div class="config-threshold-item">
                    <div class="config-threshold-header">
                        <div class="config-threshold-label">
                            <span class="config-threshold-emoji">📊</span>
                            Minimum Match Score
                        </div>
                        <span class="config-threshold-value" id="thresholdMinValue"><?= $thresholds['min_score'] ?>%</span>
                    </div>
                    <p class="config-threshold-desc">Matches below this score are filtered out completely</p>
                    <input type="range" class="config-slider config-slider-blue" name="min_match_score"
                           id="thresholdMin" min="20" max="70" value="<?= $thresholds['min_score'] ?>"
                           oninput="document.getElementById('thresholdMinValue').textContent = this.value + '%'">
                </div>

                <div class="config-threshold-item">
                    <div class="config-threshold-header">
                        <div class="config-threshold-label">
                            <span class="config-threshold-emoji">🌍</span>
                            Max Distance Filter
                        </div>
                        <span class="config-threshold-value" id="thresholdDistValue"><?= $thresholds['max_distance'] ?> km</span>
                    </div>
                    <p class="config-threshold-desc">Don't show matches beyond this distance (hard cutoff)</p>
                    <input type="range" class="config-slider config-slider-teal" name="max_distance_km"
                           id="thresholdDist" min="10" max="200" step="5" value="<?= $thresholds['max_distance'] ?>"
                           oninput="document.getElementById('thresholdDistValue').textContent = this.value + ' km'">
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="config-actions" style="max-width: 1100px;">
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
            <i class="fa-solid fa-check"></i> Save Configuration
        </button>
        <button type="button" class="admin-btn admin-btn-secondary admin-btn-lg" onclick="resetToDefaults()">
            <i class="fa-solid fa-rotate-left"></i> Reset Defaults
        </button>
    </div>
</form>

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

/* Flash Messages */
.config-flash {
    max-width: 1100px;
    padding: 1rem 1.5rem;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    animation: slideIn 0.4s ease-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.config-flash-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.config-flash-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Toggle Row */
.config-toggle-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.config-toggle-info strong {
    display: block;
    font-size: 1rem;
    color: #fff;
    margin-bottom: 0.35rem;
}

.config-toggle-info p {
    margin: 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    max-width: 500px;
    line-height: 1.5;
}

/* Toggle Switch */
.config-toggle {
    position: relative;
    width: 60px;
    height: 32px;
    flex-shrink: 0;
}

.config-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.config-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 32px;
    transition: all 0.3s;
}

.config-toggle-slider::before {
    position: absolute;
    content: "";
    height: 24px;
    width: 24px;
    left: 4px;
    bottom: 4px;
    background: white;
    border-radius: 50%;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.config-toggle input:checked + .config-toggle-slider {
    background: linear-gradient(135deg, #10b981, #06b6d4);
}

.config-toggle input:checked + .config-toggle-slider::before {
    transform: translateX(28px);
}

/* Weight Total Badge */
.config-weight-total {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    background: rgba(99, 102, 241, 0.15);
    margin-left: auto;
}

.config-weight-total-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
}

.config-weight-total-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: #10b981;
    transition: color 0.3s;
}

.config-weight-total-value.invalid {
    color: #ef4444;
}

/* Info Box */
.config-info-box {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 0.75rem;
    color: #60a5fa;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}

/* Weights Grid */
.config-weights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.25rem;
}

.config-weight-item {
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s;
}

.config-weight-item:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.2);
}

.config-weight-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.config-weight-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #fff;
}

.config-weight-emoji {
    font-size: 1.2rem;
}

.config-weight-value {
    font-size: 1.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.config-weight-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 1rem;
    line-height: 1.4;
}

/* Sliders */
.config-slider {
    width: 100%;
    height: 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
    outline: none;
    -webkit-appearance: none;
    appearance: none;
}

.config-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    border: 3px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    transition: transform 0.2s;
}

.config-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

.config-slider-purple::-webkit-slider-thumb { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.config-slider-blue::-webkit-slider-thumb { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.config-slider-teal::-webkit-slider-thumb { background: linear-gradient(135deg, #14b8a6, #22c55e); }
.config-slider-amber::-webkit-slider-thumb { background: linear-gradient(135deg, #f59e0b, #eab308); }
.config-slider-pink::-webkit-slider-thumb { background: linear-gradient(135deg, #ec4899, #f43f5e); }
.config-slider-orange::-webkit-slider-thumb { background: linear-gradient(135deg, #f97316, #ef4444); }
.config-slider-red::-webkit-slider-thumb { background: linear-gradient(135deg, #ef4444, #f97316); }

/* Proximity Grid */
.config-proximity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.config-proximity-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 1rem;
    border-left: 4px solid;
    transition: all 0.3s;
}

.config-prox-walking { border-color: #10b981; }
.config-prox-local { border-color: #06b6d4; }
.config-prox-city { border-color: #3b82f6; }
.config-prox-regional { border-color: #8b5cf6; }
.config-prox-max { border-color: #f59e0b; }

.config-proximity-icon {
    font-size: 2rem;
}

.config-proximity-info {
    text-align: center;
}

.config-proximity-info label {
    display: block;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.25rem;
}

.config-proximity-score {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    font-weight: 600;
}

.config-proximity-input-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.config-proximity-input {
    width: 70px;
    padding: 0.6rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.3);
    font-size: 1rem;
    font-weight: 700;
    text-align: center;
    color: #fff;
}

.config-proximity-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.config-proximity-unit {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.5);
}

/* Thresholds Grid */
.config-thresholds-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
}

.config-threshold-item {
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 1rem;
}

.config-threshold-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.config-threshold-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #fff;
}

.config-threshold-emoji {
    font-size: 1.2rem;
}

.config-threshold-value {
    font-size: 1.3rem;
    font-weight: 800;
    color: #f59e0b;
}

.config-threshold-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 1rem;
}

/* Form Actions */
.config-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.admin-btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .config-toggle-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .config-weights-grid,
    .config-proximity-grid,
    .config-thresholds-grid {
        grid-template-columns: 1fr;
    }

    .config-actions {
        flex-direction: column;
    }

    .config-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .config-weight-total {
        margin-left: 0;
        margin-top: 0.5rem;
    }
}
</style>

<script>
function updateWeights() {
    const weights = {
        category: parseInt(document.getElementById('weightCategory').value),
        skill: parseInt(document.getElementById('weightSkill').value),
        proximity: parseInt(document.getElementById('weightProximity').value),
        freshness: parseInt(document.getElementById('weightFreshness').value),
        reciprocity: parseInt(document.getElementById('weightReciprocity').value),
        quality: parseInt(document.getElementById('weightQuality').value)
    };

    // Update displayed values
    Object.keys(weights).forEach(key => {
        const el = document.getElementById('weight' + key.charAt(0).toUpperCase() + key.slice(1) + 'Value');
        if (el) el.textContent = weights[key] + '%';
    });

    // Calculate total
    const total = Object.values(weights).reduce((sum, val) => sum + val, 0);
    const totalEl = document.getElementById('weightTotal');
    totalEl.textContent = total + '%';
    totalEl.className = 'config-weight-total-value' + (total === 100 ? '' : ' invalid');
}

function resetToDefaults() {
    if (!confirm('Reset all settings to defaults?')) return;

    // Default weights
    document.getElementById('weightCategory').value = 25;
    document.getElementById('weightSkill').value = 20;
    document.getElementById('weightProximity').value = 25;
    document.getElementById('weightFreshness').value = 10;
    document.getElementById('weightReciprocity').value = 15;
    document.getElementById('weightQuality').value = 5;

    // Default proximity
    document.querySelector('[name="proximity_walking"]').value = 5;
    document.querySelector('[name="proximity_local"]').value = 15;
    document.querySelector('[name="proximity_city"]').value = 30;
    document.querySelector('[name="proximity_regional"]').value = 50;
    document.querySelector('[name="proximity_max"]').value = 100;

    // Default thresholds
    document.getElementById('thresholdHot').value = 85;
    document.getElementById('thresholdMin').value = 40;
    document.getElementById('thresholdDist').value = 50;
    document.getElementById('thresholdHotValue').textContent = '85%';
    document.getElementById('thresholdMinValue').textContent = '40%';
    document.getElementById('thresholdDistValue').textContent = '50 km';

    updateWeights();
}

// Initialize
updateWeights();
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
