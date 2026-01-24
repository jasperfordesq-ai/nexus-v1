<?php
/**
 * Match Preferences View
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
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

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/matches">Matches</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Preferences</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/matches" class="govuk-back-link govuk-!-margin-bottom-6">Back to Matches</a>

<h1 class="govuk-heading-xl">Match Preferences</h1>
<p class="govuk-body-l govuk-!-margin-bottom-6">Customize how the Smart Matching Engine finds matches for you</p>

<!-- Flash Messages -->
<?php if ($flashSuccess): ?>
    <div class="govuk-notification-banner govuk-notification-banner--success" role="status" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
        </div>
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading"><?= htmlspecialchars($flashSuccess) ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="govuk-error-summary" role="alert" aria-labelledby="error-summary-title" data-module="govuk-error-summary">
        <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
        <div class="govuk-error-summary__body">
            <p><?= htmlspecialchars($flashError) ?></p>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $basePath ?>/matches/preferences">
    <!-- Distance Settings -->
    <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
            <h2 class="govuk-fieldset__heading"><span aria-hidden="true">📍</span> Distance Settings</h2>
        </legend>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="distance-slider">Maximum Distance</label>
            <div id="distance-hint" class="govuk-hint">Matches beyond this distance will be filtered out. Closer matches get higher scores.</div>
            <p class="govuk-body govuk-!-margin-bottom-2"><strong id="distance-value"><?= $maxDistance ?> km</strong></p>
            <input type="range"
                   name="max_distance_km"
                   class="govuk-range"
                   min="5"
                   max="100"
                   step="5"
                   value="<?= $maxDistance ?>"
                   id="distance-slider"
                   aria-describedby="distance-hint"
                   style="width: 100%;">
            <div class="govuk-grid-row govuk-!-margin-top-1">
                <div class="govuk-grid-column-one-third"><span class="govuk-body-s">5 km (Walking)</span></div>
                <div class="govuk-grid-column-one-third govuk-!-text-align-centre"><span class="govuk-body-s">50 km (Regional)</span></div>
                <div class="govuk-grid-column-one-third govuk-!-text-align-right"><span class="govuk-body-s">100 km (Max)</span></div>
            </div>
        </div>
    </fieldset>

    <!-- Match Quality Settings -->
    <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
            <h2 class="govuk-fieldset__heading"><span aria-hidden="true">⭐</span> Match Quality</h2>
        </legend>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="score-slider">Minimum Match Score</label>
            <div id="score-hint" class="govuk-hint">Only show matches with at least this compatibility score. Higher = fewer but better matches.</div>
            <p class="govuk-body govuk-!-margin-bottom-2"><strong id="score-value"><?= $minScore ?>%</strong></p>
            <input type="range"
                   name="min_match_score"
                   class="govuk-range"
                   min="30"
                   max="90"
                   step="5"
                   value="<?= $minScore ?>"
                   id="score-slider"
                   aria-describedby="score-hint"
                   style="width: 100%;">
            <div class="govuk-grid-row govuk-!-margin-top-1">
                <div class="govuk-grid-column-one-third"><span class="govuk-body-s">30% (More matches)</span></div>
                <div class="govuk-grid-column-one-third govuk-!-text-align-centre"><span class="govuk-body-s">60%</span></div>
                <div class="govuk-grid-column-one-third govuk-!-text-align-right"><span class="govuk-body-s">90% (Best only)</span></div>
            </div>
        </div>
    </fieldset>

    <!-- Notification Settings -->
    <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
            <h2 class="govuk-fieldset__heading"><span aria-hidden="true">🔔</span> Notifications</h2>
        </legend>

        <div class="govuk-form-group">
            <label class="govuk-label" for="notification-frequency">Notification Frequency</label>
            <select name="notification_frequency" id="notification-frequency" class="govuk-select">
                <option value="instant" <?= $notifyFreq === 'instant' ? 'selected' : '' ?>>Instant - Notify me immediately</option>
                <option value="daily" <?= $notifyFreq === 'daily' ? 'selected' : '' ?>>Daily Digest - Once per day</option>
                <option value="weekly" <?= $notifyFreq === 'weekly' ? 'selected' : '' ?>>Weekly Summary - Once per week</option>
                <option value="never" <?= $notifyFreq === 'never' ? 'selected' : '' ?>>Never - Don't notify me</option>
            </select>
        </div>

        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
            <div class="govuk-checkboxes__item">
                <input class="govuk-checkboxes__input" id="notify-hot" name="notify_hot_matches" type="checkbox" value="1" <?= $notifyHot ? 'checked' : '' ?>>
                <label class="govuk-label govuk-checkboxes__label" for="notify-hot">
                    <span aria-hidden="true">🔥</span> Hot Match Alerts
                    <span class="govuk-hint govuk-!-margin-bottom-0">Get notified when a 85%+ match appears</span>
                </label>
            </div>
            <div class="govuk-checkboxes__item">
                <input class="govuk-checkboxes__input" id="notify-mutual" name="notify_mutual_matches" type="checkbox" value="1" <?= $notifyMutual ? 'checked' : '' ?>>
                <label class="govuk-label govuk-checkboxes__label" for="notify-mutual">
                    <span aria-hidden="true">🤝</span> Mutual Match Alerts
                    <span class="govuk-hint govuk-!-margin-bottom-0">Get notified when you can help each other</span>
                </label>
            </div>
        </div>
    </fieldset>

    <!-- Category Filters -->
    <?php if (!empty($categories)): ?>
    <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
            <h2 class="govuk-fieldset__heading"><span aria-hidden="true">🏷️</span> Category Filters</h2>
        </legend>

        <div id="category-hint" class="govuk-hint">Leave all unchecked to show matches from all categories.</div>

        <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes" aria-describedby="category-hint">
            <?php foreach ($categories as $cat): ?>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input"
                           type="checkbox"
                           name="categories[]"
                           value="<?= $cat['id'] ?>"
                           id="cat-<?= $cat['id'] ?>"
                           <?= in_array($cat['id'], $selectedCategories) ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-checkboxes__label" for="cat-<?= $cat['id'] ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <?php endif; ?>

    <!-- Submit Button -->
    <button type="submit" class="govuk-button" data-module="govuk-button">
        <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> Save Preferences
    </button>
</form>

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
