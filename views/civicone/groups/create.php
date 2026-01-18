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

<style>
    /* ============================================
       GOLD STANDARD - Native App Features
       ============================================ */

    /* Offline Banner */
    .offline-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 10001;
        padding: 12px 20px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transform: translateY(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .offline-banner.visible {
        transform: translateY(0);
    }

    /* Content Reveal Animation */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .htb-card {
        animation: fadeInUp 0.4s ease-out;
    }

    /* Button Press States */
    .create-hub-submit:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .create-hub-submit,
    .create-hub-input,
    button {
        min-height: 44px;
    }

    .create-hub-input {
        font-size: 16px !important; /* Prevent iOS zoom */
    }

    /* Focus Visible */
    .create-hub-submit:focus-visible,
    .create-hub-input:focus-visible,
    button:focus-visible,
    a:focus-visible {
        outline: 3px solid rgba(219, 39, 119, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive - Gold Standard */
    @media (max-width: 768px) {
        .create-hub-submit,
        button {
            min-height: 48px;
        }
    }
</style>

<style>
    /* Mobile-First Create Hub Form */
    .create-hub-wrapper {
        padding-top: 120px;
        padding-bottom: 40px;
    }

    .create-hub-wrapper .htb-header-box {
        margin-bottom: 20px;
    }

    .create-hub-wrapper .htb-card {
        padding: 24px;
    }

    .create-hub-input {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background: white;
        color: var(--htb-text-main);
        -webkit-appearance: none;
    }

    .create-hub-input:focus {
        outline: none;
        border-color: #db2777;
        box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.1);
    }

    .create-hub-input::placeholder {
        color: #9ca3af;
    }

    textarea.create-hub-input {
        resize: vertical;
        min-height: 120px;
    }

    .create-hub-label {
        display: block;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--htb-text-main);
        font-size: 0.95rem;
    }

    .create-hub-submit {
        width: 100%;
        padding: 16px;
        font-size: 1.05rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #db2777, #ec4899);
        border: none;
        color: white;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(219, 39, 119, 0.3);
        transition: all 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .create-hub-submit:active {
        transform: scale(0.98);
    }

    [data-theme="dark"] .create-hub-input {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    @media (max-width: 768px) {
        .create-hub-wrapper {
            padding-top: 100px;
            padding-bottom: 100px;
        }

        .create-hub-wrapper .htb-card {
            padding: 20px 16px;
            border-radius: 16px;
        }

        .create-hub-input {
            padding: 12px 14px;
            font-size: 16px; /* Prevents iOS zoom */
        }
    }
</style>

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