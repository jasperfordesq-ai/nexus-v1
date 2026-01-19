<?php
// Edit Event View - High-End Adaptive Holographic Glassmorphism Edition
// ISOLATED LAYOUT: Uses #unique-glass-page-wrapper and html[data-theme] selectors.

require __DIR__ . '/../../layouts/header.php';

// PREPARATION LOGIC
// 1. Extract Date/Time components for HTML5 inputs
$startParts = explode(' ', $event['start_time']);
$startDate = $startParts[0];
$startTime = substr($startParts[1] ?? '00:00:00', 0, 5); // HH:MM

$endDate = '';
$endTime = '';
if (!empty($event['end_time'])) {
    $endParts = explode(' ', $event['end_time']);
    $endDate = $endParts[0];
    $endTime = substr($endParts[1] ?? '00:00:00', 0, 5);
}

// 2. Decode SDGs
$selectedSDGs = [];
if (!empty($event['sdg_goals'])) {
    $selectedSDGs = json_decode($event['sdg_goals'], true) ?? [];
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>



<div id="unique-glass-page-wrapper">
    <div class="glass-box">

        <div class="page-header">
            <h1>Edit Event</h1>
            <div class="page-subtitle">Make changes to your gathering.</div>
        </div>

        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="form-group">
                <label>Event Title</label>
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($event['title']) ?>" class="glass-input" required>
            </div>

            <!-- Location -->
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($event['location']) ?>" class="glass-input mapbox-location-input-v2" required>
                <input type="hidden" name="latitude" value="<?= $event['latitude'] ?? '' ?>">
                <input type="hidden" name="longitude" value="<?= $event['longitude'] ?? '' ?>">
            </div>

            <!-- Category -->
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="glass-input">
                    <option value="">-- Select Category --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($event['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Host as Group -->
            <?php if (!empty($myGroups)): ?>
                <div class="form-group">
                    <label>Host as Hub (Optional)</label>
                    <select name="group_id" class="glass-input">
                        <option value="">-- Personal Event --</option>
                        <?php foreach ($myGroups as $grp): ?>
                            <option value="<?= $grp['id'] ?>" <?= ($event['group_id'] == $grp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($grp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Dates & Times -->
            <div class="form-group dates-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" class="glass-input" required>
                </div>
                <div>
                    <label>Start Time</label>
                    <input type="time" name="start_time" value="<?= $startTime ?>" class="glass-input" required>
                </div>
            </div>

            <div class="form-group dates-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>End Date (Optional)</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" class="glass-input">
                </div>
                <div>
                    <label>End Time (Optional)</label>
                    <input type="time" name="end_time" value="<?= $endTime ?>" class="glass-input">
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Description</label>
                <?php
                $aiGenerateType = 'event';
                $aiTitleField = 'title';
                $aiDescriptionField = 'description';
                $aiTypeField = null;
                include __DIR__ . '/../../partials/ai-generate-button.php';
                ?>
                <textarea name="description" id="description" class="glass-input" rows="5" required><?= htmlspecialchars($event['description']) ?></textarea>
            </div>

            <!-- SDG Glass Accordion -->
            <details <?= !empty($selectedSDGs) ? 'open' : '' ?>>
                <summary>
                    <span style="display: flex; align-items: center; gap: 10px;">
                        Social Impact <span style="font-weight: 400; font-size: 0.85rem; opacity: 0.7;">(Optional)</span>
                    </span>
                    <span style="font-size: 1.2rem; opacity: 0.5;">▼</span>
                </summary>

                <div class="sdg-content">
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px; margin-top: 0;">Tag which goals this event supports.</p>

                    <?php
                    require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                    $sdgs = \Nexus\Helpers\SDG::all();
                    ?>

                    <div class="sdg-grid">
                        <?php foreach ($sdgs as $id => $goal): ?>
                            <?php $isChecked = in_array($id, $selectedSDGs); ?>
                            <label class="glass-sdg-card <?= $isChecked ? 'selected' : '' ?>" style="color: <?= $goal['color'] ?>;">
                                <input type="checkbox" name="sdg_goals[]" value="<?= $id ?>" <?= $isChecked ? 'checked' : '' ?> style="display: none;" onchange="toggleSDGClass(this)">
                                <span style="font-size: 1.2rem;"><?= $goal['icon'] ?></span>
                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-color); line-height: 1.2;"><?= $goal['label'] ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>

            <script>
                function toggleSDGClass(cb) {
                    const card = cb.parentElement;
                    if (cb.checked) {
                        card.classList.add('selected');
                    } else {
                        card.classList.remove('selected');
                    }
                }
            </script>

            <!-- Partner Timebanks (Federation) -->
            <?php if (!empty($federationEnabled)): ?>
            <div class="federation-section">
                <label style="margin-left: 0;">
                    <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                    Share with Partner Timebanks
                    <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span>
                </label>

                <?php if (!empty($userFederationOptedIn)): ?>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 12px;">
                    Make this event visible to members of our partner timebanks.
                </p>
                <div class="federation-options">
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="none" <?= ($event['federated_visibility'] ?? 'none') === 'none' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Local Only</span>
                            <span class="radio-desc">Only visible to members of this timebank</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="listed" <?= ($event['federated_visibility'] ?? '') === 'listed' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Visible</span>
                            <span class="radio-desc">Partner timebank members can see this event</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="joinable" <?= ($event['federated_visibility'] ?? '') === 'joinable' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Joinable</span>
                            <span class="radio-desc">Partner members can RSVP to this event</span>
                        </span>
                    </label>
                </div>
                <?php else: ?>
                <div class="federation-optin-notice">
                    <i class="fa-solid fa-info-circle"></i>
                    <div>
                        <strong>Enable federation to share events</strong>
                        <p>To share your events with partner timebanks, you need to opt into federation in your <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation">account settings</a>.</p>
                    </div>
                </div>
                <input type="hidden" name="federated_visibility" value="none">
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- SEO Settings Accordion -->
            <?php
            $seo = $seo ?? \Nexus\Models\SeoMetadata::get('event', $event['id']);
            $entityTitle = $event['title'] ?? '';
            $entityUrl = \Nexus\Core\TenantContext::getBasePath() . '/events/' . $event['id'];
            require __DIR__ . '/../../partials/seo-accordion.php';
            ?>

            <div class="actions-group">
                <button type="submit" class="glass-btn-primary">Save Changes</button>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>" class="glass-btn-secondary">Cancel</a>
            </div>

        </form>

    </div>
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.glass-btn-primary, .glass-btn-secondary, button').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#f97316';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#f97316');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>