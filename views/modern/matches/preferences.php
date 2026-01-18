<?php
// Match Preferences View - Smart Matching Engine Settings
$hero_title = $page_title ?? "Match Preferences";
$hero_subtitle = "Fine-tune your matching algorithm";
$hero_gradient = 'htb-hero-gradient-settings';
$hero_type = 'Settings';

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$preferences = $preferences ?? [];
$categories = $categories ?? [];

// Default values
$maxDistance = $preferences['max_distance_km'] ?? 25;
$minScore = $preferences['min_match_score'] ?? 50;
$notifyFreq = $preferences['notification_frequency'] ?? 'daily';
$notifyHot = $preferences['notify_hot_matches'] ?? true;
$notifyMutual = $preferences['notify_mutual_matches'] ?? true;
$selectedCategories = $preferences['categories'] ?? [];

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<style>
/* ============================================
   MATCH PREFERENCES - SETTINGS UI
   ============================================ */

.prefs-page {
    min-height: 100vh;
    padding: 100px 24px 60px;
    position: relative;
}

@media (max-width: 768px) {
    .prefs-page {
        padding: 20px 16px 100px;
    }
}

/* Background */
.prefs-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse at 20% 30%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 70%, rgba(139, 92, 246, 0.08) 0%, transparent 50%);
    z-index: -1;
}

.prefs-container {
    max-width: 800px;
    margin: 0 auto;
}

/* Header */
.prefs-header {
    text-align: center;
    margin-bottom: 40px;
}

.prefs-title {
    font-size: 2.2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.prefs-subtitle {
    color: #64748b;
    font-size: 1rem;
}

/* Flash Messages */
.flash-message {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.flash-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.flash-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Form Card */
.prefs-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 24px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
}

[data-theme="dark"] .prefs-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.prefs-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .prefs-card-title {
    color: #f1f5f9;
}

.prefs-card-title .icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.prefs-card-title .icon.distance { background: linear-gradient(135deg, #10b981, #06b6d4); }
.prefs-card-title .icon.score { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.prefs-card-title .icon.notify { background: linear-gradient(135deg, #f59e0b, #f97316); }
.prefs-card-title .icon.category { background: linear-gradient(135deg, #ec4899, #f43f5e); }

/* Form Groups */
.prefs-group {
    margin-bottom: 28px;
}

.prefs-group:last-child {
    margin-bottom: 0;
}

.prefs-label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

[data-theme="dark"] .prefs-label {
    color: #e2e8f0;
}

.prefs-help {
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 6px;
}

/* Slider Input */
.prefs-slider-container {
    position: relative;
    padding: 10px 0;
}

.prefs-slider {
    width: 100%;
    height: 8px;
    border-radius: 4px;
    background: #e2e8f0;
    outline: none;
    -webkit-appearance: none;
    cursor: pointer;
}

[data-theme="dark"] .prefs-slider {
    background: #334155;
}

.prefs-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(99, 102, 241, 0.4);
    transition: transform 0.2s;
}

.prefs-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

.prefs-slider::-moz-range-thumb {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 10px rgba(99, 102, 241, 0.4);
}

.prefs-slider-value {
    position: absolute;
    right: 0;
    top: -5px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.9rem;
}

.prefs-slider-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 0.8rem;
    color: #64748b;
}

/* Select Input */
.prefs-select {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 500;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.2s;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%236366f1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 20px;
}

[data-theme="dark"] .prefs-select {
    background-color: #1e293b;
    border-color: #334155;
    color: #f1f5f9;
}

.prefs-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

/* Toggle Switch */
.prefs-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: rgba(99, 102, 241, 0.05);
    border-radius: 12px;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.prefs-toggle:hover {
    background: rgba(99, 102, 241, 0.1);
}

.prefs-toggle-info {
    flex: 1;
}

.prefs-toggle-label {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 2px;
}

[data-theme="dark"] .prefs-toggle-label {
    color: #f1f5f9;
}

.prefs-toggle-desc {
    font-size: 0.85rem;
    color: #64748b;
}

.toggle-switch {
    position: relative;
    width: 52px;
    height: 28px;
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
    background: #cbd5e1;
    border-radius: 28px;
    transition: 0.3s;
}

.toggle-slider::before {
    content: '';
    position: absolute;
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.toggle-switch input:checked + .toggle-slider::before {
    transform: translateX(24px);
}

/* Category Grid */
.prefs-category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
}

.prefs-category-item {
    position: relative;
}

.prefs-category-item input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.prefs-category-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: rgba(99, 102, 241, 0.05);
    border: 2px solid transparent;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    font-size: 0.9rem;
    color: #475569;
}

[data-theme="dark"] .prefs-category-label {
    background: rgba(99, 102, 241, 0.1);
    color: #94a3b8;
}

.prefs-category-label:hover {
    background: rgba(99, 102, 241, 0.1);
}

.prefs-category-item input:checked + .prefs-category-label {
    background: rgba(99, 102, 241, 0.15);
    border-color: #6366f1;
    color: #6366f1;
}

[data-theme="dark"] .prefs-category-item input:checked + .prefs-category-label {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.prefs-category-check {
    width: 18px;
    height: 18px;
    border: 2px solid #cbd5e1;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.prefs-category-item input:checked + .prefs-category-label .prefs-category-check {
    background: #6366f1;
    border-color: #6366f1;
}

.prefs-category-item input:checked + .prefs-category-label .prefs-category-check::after {
    content: '✓';
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
}

/* Submit Button */
.prefs-submit {
    width: 100%;
    padding: 18px 32px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.prefs-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
}

.prefs-submit:active {
    transform: translateY(0);
}

/* Back Link */
.prefs-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #6366f1;
    font-weight: 600;
    text-decoration: none;
    margin-bottom: 24px;
    transition: all 0.2s;
}

.prefs-back:hover {
    gap: 12px;
}
</style>

<div class="prefs-page">
    <div class="prefs-container">
        <!-- Back Link -->
        <a href="<?= $basePath ?>/matches" class="prefs-back">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Matches
        </a>

        <!-- Header -->
        <div class="prefs-header">
            <h1 class="prefs-title">Match Preferences</h1>
            <p class="prefs-subtitle">Customize how the Smart Matching Engine finds matches for you</p>
        </div>

        <!-- Flash Messages -->
        <?php if ($flashSuccess): ?>
            <div class="flash-message flash-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <?= htmlspecialchars($flashSuccess) ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="flash-message flash-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <?= htmlspecialchars($flashError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $basePath ?>/matches/preferences">
            <!-- Distance Settings -->
            <div class="prefs-card">
                <h2 class="prefs-card-title">
                    <span class="icon distance">📍</span>
                    Distance Settings
                </h2>

                <div class="prefs-group">
                    <label class="prefs-label">Maximum Distance</label>
                    <div class="prefs-slider-container">
                        <span class="prefs-slider-value" id="distance-value"><?= $maxDistance ?> km</span>
                        <input type="range"
                               name="max_distance_km"
                               class="prefs-slider"
                               min="5"
                               max="100"
                               step="5"
                               value="<?= $maxDistance ?>"
                               id="distance-slider">
                        <div class="prefs-slider-labels">
                            <span>5 km (Walking)</span>
                            <span>50 km (Regional)</span>
                            <span>100 km (Max)</span>
                        </div>
                    </div>
                    <p class="prefs-help">Matches beyond this distance will be filtered out. Closer matches get higher scores.</p>
                </div>
            </div>

            <!-- Match Quality Settings -->
            <div class="prefs-card">
                <h2 class="prefs-card-title">
                    <span class="icon score">⭐</span>
                    Match Quality
                </h2>

                <div class="prefs-group">
                    <label class="prefs-label">Minimum Match Score</label>
                    <div class="prefs-slider-container">
                        <span class="prefs-slider-value" id="score-value"><?= $minScore ?>%</span>
                        <input type="range"
                               name="min_match_score"
                               class="prefs-slider"
                               min="30"
                               max="90"
                               step="5"
                               value="<?= $minScore ?>"
                               id="score-slider">
                        <div class="prefs-slider-labels">
                            <span>30% (More matches)</span>
                            <span>60%</span>
                            <span>90% (Best only)</span>
                        </div>
                    </div>
                    <p class="prefs-help">Only show matches with at least this compatibility score. Higher = fewer but better matches.</p>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="prefs-card">
                <h2 class="prefs-card-title">
                    <span class="icon notify">🔔</span>
                    Notifications
                </h2>

                <div class="prefs-group">
                    <label class="prefs-label">Notification Frequency</label>
                    <select name="notification_frequency" class="prefs-select">
                        <option value="instant" <?= $notifyFreq === 'instant' ? 'selected' : '' ?>>Instant - Notify me immediately</option>
                        <option value="daily" <?= $notifyFreq === 'daily' ? 'selected' : '' ?>>Daily Digest - Once per day</option>
                        <option value="weekly" <?= $notifyFreq === 'weekly' ? 'selected' : '' ?>>Weekly Summary - Once per week</option>
                        <option value="never" <?= $notifyFreq === 'never' ? 'selected' : '' ?>>Never - Don't notify me</option>
                    </select>
                </div>

                <div class="prefs-group">
                    <label class="prefs-toggle">
                        <div class="prefs-toggle-info">
                            <div class="prefs-toggle-label">🔥 Hot Match Alerts</div>
                            <div class="prefs-toggle-desc">Get notified when a 85%+ match appears</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" name="notify_hot_matches" <?= $notifyHot ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </label>

                    <label class="prefs-toggle">
                        <div class="prefs-toggle-info">
                            <div class="prefs-toggle-label">🤝 Mutual Match Alerts</div>
                            <div class="prefs-toggle-desc">Get notified when you can help each other</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" name="notify_mutual_matches" <?= $notifyMutual ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Category Filters -->
            <?php if (!empty($categories)): ?>
            <div class="prefs-card">
                <h2 class="prefs-card-title">
                    <span class="icon category">🏷️</span>
                    Category Filters
                </h2>

                <div class="prefs-group">
                    <label class="prefs-label">Show matches from these categories</label>
                    <p class="prefs-help" style="margin-bottom: 16px;">Leave all unchecked to show matches from all categories.</p>

                    <div class="prefs-category-grid">
                        <?php foreach ($categories as $cat): ?>
                            <div class="prefs-category-item">
                                <input type="checkbox"
                                       name="categories[]"
                                       value="<?= $cat['id'] ?>"
                                       id="cat-<?= $cat['id'] ?>"
                                       <?= in_array($cat['id'], $selectedCategories) ? 'checked' : '' ?>>
                                <label for="cat-<?= $cat['id'] ?>" class="prefs-category-label">
                                    <span class="prefs-category-check"></span>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Submit Button -->
            <button type="submit" class="prefs-submit">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save Preferences
            </button>
        </form>
    </div>
</div>

<script>
// Update slider value displays
document.getElementById('distance-slider').addEventListener('input', function() {
    document.getElementById('distance-value').textContent = this.value + ' km';
});

document.getElementById('score-slider').addEventListener('input', function() {
    document.getElementById('score-value').textContent = this.value + '%';
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
