<?php
// Match Preferences View - WCAG 2.1 AA Compliant
// CSS extracted to civicone-matches.css
$hero_title = $page_title ?? "Match Preferences";
$hero_subtitle = "Fine-tune your matching algorithm";
$hero_gradient = 'htb-hero-gradient-settings';
$hero_type = 'Settings';

require __DIR__ . '/../../layouts/civicone/header.php';

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

<div class="prefs-page">
    <div class="prefs-container">
        <!-- Back Link -->
        <a href="<?= $basePath ?>/matches" class="prefs-back">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Matches
        </a>

        <!-- Header -->
        <header class="prefs-header">
            <h1 class="prefs-title">Match Preferences</h1>
            <p class="prefs-subtitle">Customize how the Smart Matching Engine finds matches for you</p>
        </header>

        <!-- Flash Messages -->
        <?php if ($flashSuccess): ?>
            <div class="flash-message flash-success" role="status">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <?= htmlspecialchars($flashSuccess) ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="flash-message flash-error" role="alert">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <?= htmlspecialchars($flashError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $basePath ?>/matches/preferences">
            <!-- Distance Settings -->
            <fieldset class="prefs-card">
                <legend class="prefs-card-title">
                    <span class="icon distance" aria-hidden="true">📍</span>
                    Distance Settings
                </legend>

                <div class="prefs-group">
                    <label for="distance-slider" class="prefs-label">Maximum Distance</label>
                    <div class="prefs-slider-container">
                        <span class="prefs-slider-value" id="distance-value" aria-live="polite"><?= $maxDistance ?> km</span>
                        <input type="range"
                               name="max_distance_km"
                               class="prefs-slider"
                               min="5"
                               max="100"
                               step="5"
                               value="<?= $maxDistance ?>"
                               id="distance-slider"
                               aria-describedby="distance-help">
                        <div class="prefs-slider-labels" aria-hidden="true">
                            <span>5 km (Walking)</span>
                            <span>50 km (Regional)</span>
                            <span>100 km (Max)</span>
                        </div>
                    </div>
                    <p class="prefs-help" id="distance-help">Matches beyond this distance will be filtered out. Closer matches get higher scores.</p>
                </div>
            </fieldset>

            <!-- Match Quality Settings -->
            <fieldset class="prefs-card">
                <legend class="prefs-card-title">
                    <span class="icon score" aria-hidden="true">⭐</span>
                    Match Quality
                </legend>

                <div class="prefs-group">
                    <label for="score-slider" class="prefs-label">Minimum Match Score</label>
                    <div class="prefs-slider-container">
                        <span class="prefs-slider-value" id="score-value" aria-live="polite"><?= $minScore ?>%</span>
                        <input type="range"
                               name="min_match_score"
                               class="prefs-slider"
                               min="30"
                               max="90"
                               step="5"
                               value="<?= $minScore ?>"
                               id="score-slider"
                               aria-describedby="score-help">
                        <div class="prefs-slider-labels" aria-hidden="true">
                            <span>30% (More matches)</span>
                            <span>60%</span>
                            <span>90% (Best only)</span>
                        </div>
                    </div>
                    <p class="prefs-help" id="score-help">Only show matches with at least this compatibility score. Higher = fewer but better matches.</p>
                </div>
            </fieldset>

            <!-- Notification Settings -->
            <fieldset class="prefs-card">
                <legend class="prefs-card-title">
                    <span class="icon notify" aria-hidden="true">🔔</span>
                    Notifications
                </legend>

                <div class="prefs-group">
                    <label for="notification-frequency" class="prefs-label">Notification Frequency</label>
                    <select name="notification_frequency" id="notification-frequency" class="prefs-select">
                        <option value="instant" <?= $notifyFreq === 'instant' ? 'selected' : '' ?>>Instant - Notify me immediately</option>
                        <option value="daily" <?= $notifyFreq === 'daily' ? 'selected' : '' ?>>Daily Digest - Once per day</option>
                        <option value="weekly" <?= $notifyFreq === 'weekly' ? 'selected' : '' ?>>Weekly Summary - Once per week</option>
                        <option value="never" <?= $notifyFreq === 'never' ? 'selected' : '' ?>>Never - Don't notify me</option>
                    </select>
                </div>

                <div class="prefs-group">
                    <label class="prefs-toggle">
                        <div class="prefs-toggle-info">
                            <div class="prefs-toggle-label"><span aria-hidden="true">🔥</span> Hot Match Alerts</div>
                            <div class="prefs-toggle-desc">Get notified when a 85%+ match appears</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" name="notify_hot_matches" <?= $notifyHot ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </label>

                    <label class="prefs-toggle">
                        <div class="prefs-toggle-info">
                            <div class="prefs-toggle-label"><span aria-hidden="true">🤝</span> Mutual Match Alerts</div>
                            <div class="prefs-toggle-desc">Get notified when you can help each other</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" name="notify_mutual_matches" <?= $notifyMutual ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </label>
                </div>
            </fieldset>

            <!-- Category Filters -->
            <?php if (!empty($categories)): ?>
            <fieldset class="prefs-card">
                <legend class="prefs-card-title">
                    <span class="icon category" aria-hidden="true">🏷️</span>
                    Category Filters
                </legend>

                <div class="prefs-group">
                    <p class="prefs-label">Show matches from these categories</p>
                    <p class="prefs-help" id="category-help">Leave all unchecked to show matches from all categories.</p>

                    <div class="prefs-category-grid" role="group" aria-describedby="category-help">
                        <?php foreach ($categories as $cat): ?>
                            <div class="prefs-category-item">
                                <input type="checkbox"
                                       name="categories[]"
                                       value="<?= $cat['id'] ?>"
                                       id="cat-<?= $cat['id'] ?>"
                                       <?= in_array($cat['id'], $selectedCategories) ? 'checked' : '' ?>>
                                <label for="cat-<?= $cat['id'] ?>" class="prefs-category-label">
                                    <span class="prefs-category-check" aria-hidden="true"></span>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </fieldset>
            <?php endif; ?>

            <!-- Submit Button -->
            <button type="submit" class="prefs-submit">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
