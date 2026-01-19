<?php
// Phoenix Create Event View - Full Holographic Glassmorphism Edition
$hero_title = "Host an Event";
$hero_subtitle = "Rally your community.";
$hero_gradient = 'htb-hero-gradient-events';
$hero_type = 'Event';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-event-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-event-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">📅</div>
            <h1 class="holo-page-title">Host an Event</h1>
            <p class="holo-page-subtitle">Rally your community together</p>
        </div>

        <!-- Glass Card Form -->
        <div class="holo-glass-card">
            <form action="<?= $basePath ?>/events/store" method="POST" id="createEventForm">
                <?= \Nexus\Core\Csrf::input() ?>

                <!-- Title -->
                <div class="holo-section">
                    <label class="holo-label" for="title">Event Title</label>
                    <input type="text" name="title" id="title" class="holo-input" placeholder="e.g. Summer Potluck, Community Clean-up..." required>
                </div>

                <!-- Location -->
                <div class="holo-section">
                    <label class="holo-label" for="location">Location</label>
                    <input type="text" name="location" id="location" class="holo-input mapbox-location-input-v2" placeholder="Start typing your town or city..." required>
                    <input type="hidden" name="latitude">
                    <input type="hidden" name="longitude">
                </div>

                <!-- Category -->
                <div class="holo-section">
                    <label class="holo-label" for="category_id">Category</label>
                    <select name="category_id" id="category_id" class="holo-select">
                        <option value="">-- Select Category --</option>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Host as Hub -->
                <?php if (!empty($myGroups)): ?>
                <div class="holo-section">
                    <label class="holo-label" for="group_id">Host as Hub <span class="holo-label-optional">(Optional)</span></label>
                    <select name="group_id" id="group_id" class="holo-select">
                        <option value="">-- Personal Event --</option>
                        <?php foreach ($myGroups as $grp): ?>
                            <option value="<?= $grp['id'] ?>" <?= (isset($selectedGroupId) && $selectedGroupId == $grp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($grp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="holo-hub-hint">
                        <i class="fa-solid fa-info-circle"></i>
                        Select a Hub to list this event on its page.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Date & Time Section -->
                <div class="holo-section">
                    <div class="holo-section-title">When is it happening?</div>

                    <div class="holo-datetime-grid" style="margin-bottom: 16px;">
                        <div>
                            <label class="holo-label" for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="holo-input" required>
                        </div>
                        <div>
                            <label class="holo-label" for="start_time">Start Time</label>
                            <input type="time" name="start_time" id="start_time" class="holo-input" required>
                        </div>
                    </div>

                    <div class="holo-datetime-grid">
                        <div>
                            <label class="holo-label" for="end_date">End Date <span class="holo-label-optional">(Optional)</span></label>
                            <input type="date" name="end_date" id="end_date" class="holo-input">
                        </div>
                        <div>
                            <label class="holo-label" for="end_time">End Time <span class="holo-label-optional">(Optional)</span></label>
                            <input type="time" name="end_time" id="end_time" class="holo-input">
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="holo-section">
                    <label class="holo-label" for="description">Description</label>
                    <?php
                    $aiGenerateType = 'event';
                    $aiTitleField = 'title';
                    $aiDescriptionField = 'description';
                    $aiTypeField = null;
                    include __DIR__ . '/../../partials/ai-generate-button.php';
                    ?>
                    <textarea name="description" id="description" class="holo-textarea" placeholder="What's happening? Tell people what to expect..." required></textarea>
                </div>

                <!-- SDGs -->
                <details class="holo-sdg-accordion">
                    <summary class="holo-sdg-header">
                        <span>🌍 Social Impact <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </summary>
                    <div class="holo-sdg-content">
                        <p class="holo-sdg-intro">Tag which UN Sustainable Development Goals this event supports.</p>
                        <?php
                        require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                        $sdgs = \Nexus\Helpers\SDG::all();
                        ?>
                        <div class="holo-sdg-grid">
                            <?php foreach ($sdgs as $id => $goal): ?>
                                <label class="holo-sdg-card" data-color="<?= $goal['color'] ?>">
                                    <input type="checkbox" name="sdg_goals[]" value="<?= $id ?>" onchange="toggleSDG(this, '<?= $goal['color'] ?>')">
                                    <span class="sdg-icon"><?= $goal['icon'] ?></span>
                                    <span class="sdg-label"><?= $goal['label'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>

                <!-- Partner Timebanks (Federation) -->
                <?php if (!empty($federationEnabled)): ?>
                <div class="holo-federation-section">
                    <label class="holo-label">
                        <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                        Share with Partner Timebanks
                        <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span>
                    </label>

                    <?php if (!empty($userFederationOptedIn)): ?>
                    <p style="font-size: 0.9rem; color: var(--htb-text-muted, #64748b); margin-bottom: 12px;">
                        Make this event visible to members of our partner timebanks.
                    </p>
                    <div class="holo-federation-options">
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="none" checked>
                            <span class="radio-content">
                                <span class="radio-label">Local Only</span>
                                <span class="radio-desc">Only visible to members of this timebank</span>
                            </span>
                        </label>
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="listed">
                            <span class="radio-content">
                                <span class="radio-label">Visible</span>
                                <span class="radio-desc">Partner timebank members can see this event</span>
                            </span>
                        </label>
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="joinable">
                            <span class="radio-content">
                                <span class="radio-label">Joinable</span>
                                <span class="radio-desc">Partner members can RSVP to this event</span>
                            </span>
                        </label>
                    </div>
                    <?php else: ?>
                    <div class="holo-federation-optin-notice">
                        <i class="fa-solid fa-info-circle"></i>
                        <div>
                            <strong>Enable federation to share events</strong>
                            <p>To share your events with partner timebanks, you need to opt into federation in your <a href="<?= $basePath ?>/settings?section=federation">account settings</a>.</p>
                        </div>
                    </div>
                    <input type="hidden" name="federated_visibility" value="none">
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Submit Button -->
                <button type="submit" class="holo-submit-btn" id="submitBtn">
                    <i class="fa-solid fa-calendar-plus" style="margin-right: 10px;"></i>
                    Publish Event
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// SDG Toggle
function toggleSDG(checkbox, color) {
    const card = checkbox.closest('.holo-sdg-card');
    if (checkbox.checked) {
        card.style.borderColor = color;
        card.style.backgroundColor = color + '18';
        card.style.boxShadow = `0 4px 15px ${color}30`;
    } else {
        card.style.borderColor = '';
        card.style.backgroundColor = '';
        card.style.boxShadow = '';
    }
}

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

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createEventForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to publish your event.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Publishing...';
        });
    }

    // Touch feedback
    document.querySelectorAll('.holo-sdg-card, .holo-submit-btn').forEach(el => {
        el.addEventListener('pointerdown', () => el.style.transform = 'scale(0.97)');
        el.addEventListener('pointerup', () => el.style.transform = '');
        el.addEventListener('pointerleave', () => el.style.transform = '');
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    let metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        metaTheme = document.createElement('meta');
        metaTheme.name = 'theme-color';
        document.head.appendChild(metaTheme);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        metaTheme.setAttribute('content', isDark ? '#0f172a' : '#f97316');
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
