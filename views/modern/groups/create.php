<?php
// Create Hub View - Mobile Optimized
$hero_title = "Start a Hub";
$hero_subtitle = "Mobilize your neighborhood or interest group.";
$hero_gradient = 'htb-hero-gradient-hub';
$hero_type = 'Community';

require __DIR__ . '/../../layouts/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>



<div class="htb-container-focused create-hub-wrapper">

    <!-- Header Box -->
    <div class="htb-header-box">
        <h1>Create a New Hub</h1>
        <p>Build a space for your local community or shared interest.</p>
    </div>

    <div class="htb-card">
        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/store" method="POST">
            <?= Nexus\Core\Csrf::input() ?>

            <!-- Name -->
            <div style="margin-bottom: 24px;">
                <label class="create-hub-label">Hub Name</label>
                <input type="text" name="name" class="create-hub-input" placeholder="e.g. West Cork Gardeners" required>
            </div>

            <!-- Description -->
            <div style="margin-bottom: 28px;">
                <label class="create-hub-label">Description</label>
                <textarea name="description" rows="5" class="create-hub-input" placeholder="What is this group about? Who should join?" required></textarea>
            </div>

            <!-- Location -->
            <div style="margin-bottom: 28px;">
                <label class="create-hub-label">Location</label>
                <input type="text"
                       name="location"
                       id="location"
                       class="create-hub-input mapbox-location-input-v2"
                       placeholder="Start typing a location..."
                       autocomplete="off">
                <input type="hidden" name="latitude" id="location_lat">
                <input type="hidden" name="longitude" id="location_lng">
                <p style="font-size: 0.85rem; color: #6b7280; margin-top: 8px;">
                    <i class="fa-solid fa-info-circle" style="margin-right: 4px;"></i>
                    Optional. Add a location to help members find local hubs.
                </p>
            </div>

            <!-- Partner Timebanks (Federation) -->
            <?php if (!empty($federationEnabled)): ?>
            <div class="federation-section">
                <label class="create-hub-label">
                    <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                    Share with Partner Timebanks
                    <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span>
                </label>

                <?php if (!empty($userFederationOptedIn)): ?>
                <p style="font-size: 0.9rem; color: #6b7280; margin-bottom: 12px;">
                    Make this hub visible to members of our partner timebanks.
                </p>
                <div class="federation-options">
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="none" checked>
                        <span class="radio-content">
                            <span class="radio-label">Local Only</span>
                            <span class="radio-desc">Only visible to members of this timebank</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="listed">
                        <span class="radio-content">
                            <span class="radio-label">Visible</span>
                            <span class="radio-desc">Partner timebank members can see this hub</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="joinable">
                        <span class="radio-content">
                            <span class="radio-label">Joinable</span>
                            <span class="radio-desc">Partner members can join this hub</span>
                        </span>
                    </label>
                </div>
                <?php else: ?>
                <div class="federation-optin-notice">
                    <i class="fa-solid fa-info-circle"></i>
                    <div>
                        <strong>Enable federation to share hubs</strong>
                        <p>To share your hubs with partner timebanks, you need to opt into federation in your <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation">account settings</a>.</p>
                    </div>
                </div>
                <input type="hidden" name="federated_visibility" value="none">
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <button type="submit" class="create-hub-submit">Create Hub</button>

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
            alert('You are offline. Please connect to the internet to create a hub.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.create-hub-submit, button').forEach(btn => {
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
        meta.content = '#db2777';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
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