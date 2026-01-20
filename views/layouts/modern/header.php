<?php
// Modern Layout Header - 3.4 Unified Fix (No External JS Dependency)

// ============================================
// NO-CACHE HEADERS FOR THEME SWITCHING
// Prevents browser from serving stale cached pages when theme changes
// ============================================
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Generate a dynamic CSS version for cache busting
// Uses deployment version file to force cache refresh on all users after deploy
$deploymentVersion = file_exists(__DIR__ . '/../../config/deployment-version.php')
    ? require __DIR__ . '/../../config/deployment-version.php'
    : ['version' => time()];
$cssVersionTimestamp = $deploymentVersion['version'] ?? time();

require_once __DIR__ . '/../onboarding_check.php';
require_once __DIR__ . '/../consent_check.php';
$mode = $_COOKIE['nexus_mode'] ?? 'dark';
$hTitle = $hero_title ?? $pageTitle ?? 'Project NEXUS';
$hSubtitle = $hero_subtitle ?? $pageSubtitle ?? 'Welcome to the future.';
$hGradient = $hero_gradient ?? 'htb-hero-gradient-brand';
$hType = $hero_type ?? 'Platform';

// --- STRICT HOME DETECTION ---
$isHome = false;
$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($reqUri, PHP_URL_PATH);
$normPath = rtrim($parsedPath, '/');
$normBase = '';
$assetBase = ''; // Assets always at root - subdirectory paths are virtual routes only
if (class_exists('\Nexus\Core\TenantContext')) {
    $normBase = rtrim(\Nexus\Core\TenantContext::getBasePath(), '/');
    // Assets stay at root path '/' since /hour-timebank/ is a virtual route, not a real directory
    // The actual files are in httpdocs/assets/ which is accessible at /assets/
    $assetBase = '';
    if ($normPath === '' || $normPath === '/' || $normPath === '/home' || $normPath === $normBase || $normPath === $normBase . '/home') {
        $isHome = true;
    }
}


// Fail-safe SEO - Load global settings from database first
try {
    if (class_exists('\Nexus\Core\SEO')) {
        // Load global SEO defaults from database
        \Nexus\Core\SEO::load('global');
        // Override with page-specific values if set
        if (isset($hTitle)) \Nexus\Core\SEO::setTitle($hTitle);
        if (isset($hSubtitle)) \Nexus\Core\SEO::setDescription($hSubtitle);
    }
} catch (\Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $mode ?>" data-layout="modern">

<head>
    <meta charset="UTF-8">
    <!-- Critical scroll/layout styles - loaded first to prevent FOUC -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-header-extracted.css?v=<?= $cssVersionTimestamp ?>">
    <meta name="csrf-token" content="<?= \Nexus\Core\Csrf::generate() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="<?= $mode === 'dark' ? 'dark' : 'light' ?>">
    <?= \Nexus\Core\SEO::render() ?>

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NEXUS">
    <link rel="apple-touch-icon" href="/assets/images/pwa/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/pwa/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/pwa/icon-512x512.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="NEXUS">
    <meta name="msapplication-TileColor" content="#6366f1">

    <!-- Performance: Preconnect to critical domains only (max 4) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://api.mapbox.com">

    <!-- Critical CSS Inline (Lighthouse: Save 660ms) -->
    <?php include __DIR__ . '/critical-css.php'; ?>

    <!-- DESIGN TOKENS (Shared variables - must load first) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/design-tokens.min.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Theme Transitions - Smooth dark/light mode switching -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/theme-transitions.min.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Base CSS - CSS variables, tokens, and global resets -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-phoenix.min.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Core CSS Bundle - layout isolation and framework styles -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/core.min.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Components CSS Bundle - UI components including mega-menu, dropdowns, header/navbar -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components.min.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Mobile Navigation v2 - mobile tab bar, menus, and bottom sheets (v1 removed) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-native-nav-v2.min.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Modern Header CSS - utility bar, navbar, and header-specific styles -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-modern-header.min.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Page-specific CSS -->
    <?php if ($isHome): ?>
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/post-box-home.min.css?v=<?= $cssVersionTimestamp ?>">
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/feed-filter.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Dashboard CSS (loaded when on dashboard page) -->
    <?php if (strpos($normPath, '/dashboard') !== false): ?>
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/dashboard.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Nexus Score CSS (loaded when on nexus-score pages) -->
    <?php if (strpos($normPath, '/nexus-score') !== false || strpos($normPath, '/score') !== false): ?>
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-score.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <!-- Consolidated polish files (replaces nexus-10x-polish + nexus-ux-polish) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-polish.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-interactions.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">

    <!-- Visual Polish Bundle (loading states, empty states, lazy loading, hover, focus) -->
    <!-- Combines 7 files: loading-skeletons, empty-states, image-lazy-load, hover-interactions, focus-rings, micro-interactions, modal-polish -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/polish.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Toast Notifications - Slide-in animations, stacking, auto-dismiss -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/toast-notifications.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Page Transitions - Smooth fade/slide between pages -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/page-transitions.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Pull-to-Refresh - Native iOS/Android style (mobile only) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/pull-to-refresh.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Button Ripple Effects - Material-style touch feedback -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/button-ripple.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Card Hover States - Lift/glow effects on interactive cards -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/card-hover-states.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Form Validation - Shake on error, checkmark on success -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/form-validation.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Avatar Placeholders - Shimmer loading, initials fallback -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/avatar-placeholders.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Scroll Progress - Top bar showing page scroll position -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/scroll-progress.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- FAB Polish - Floating action button animations -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/fab-polish.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Badge Animations - Pop effect when count changes -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/badge-animations.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Error States - Friendly error pages with animations -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/error-states.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">

    <!-- Enhancements Bundle (responsive, accessibility, extracted components) -->
    <!-- Combines 5 files: responsive-forms, responsive-tables, accessibility, feed-action-pills, ai-chat-widget -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/enhancements.min.css?v=<?= $cssVersionTimestamp ?>" media="print" onload="this.media='all'">
    <!-- Noscript fallbacks for async-loaded bundles -->
    <noscript>
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/polish.min.css?v=<?= $cssVersionTimestamp ?>">
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/enhancements.min.css?v=<?= $cssVersionTimestamp ?>">
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-polish.min.css?v=<?= $cssVersionTimestamp ?>">
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-interactions.min.css?v=<?= $cssVersionTimestamp ?>">
    </noscript>

    <!-- Mobile-only CSS -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-mobile.min.css?v=<?= $cssVersionTimestamp ?>" media="(max-width: 768px)">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/mobile-sheets.min.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/pwa-install-modal.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Native app page enter animations (Capacitor only - uses .is-native class) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/native-page-enter.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Native app form inputs (mobile/native only - desktop unchanged) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/native-form-inputs.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Mobile select bottom sheet (mobile/native only) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/mobile-select-sheet.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Mobile search overlay (mobile/native only) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/mobile-search-overlay.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Social Interactions CSS -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/social-interactions.min.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Footer CSS -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-modern-footer.min.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Auth Pages CSS (conditional) -->
    <?php if (preg_match('/\/(login|register|password)/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/auth.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Post Card CSS (conditional for feed/profile/post pages) -->
    <?php if ($isHome || strpos($normPath, '/profile') !== false || strpos($normPath, '/post') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/post-card.min.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/feed-item.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Shared Components CSS (achievement showcase, leaderboard, score widgets, org UI) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/components.min.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Shared Partials CSS (federation nav, impersonation banner, skeleton feed) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/partials.min.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Feed Page CSS (for /feed route) -->
    <?php if (strpos($normPath, '/feed') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/feed-page.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php if (preg_match('/\/feed\/\d+$/', $normPath) || preg_match('/\/post\/\d+$/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/feed-show.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Profile Edit CSS -->
    <?php if (strpos($normPath, '/profile/edit') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/profile-edit.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Messages CSS (conditional) -->
    <?php if (strpos($normPath, '/messages') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/messages-index.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php // Load thread CSS for /messages/{id} (numeric ID) or /messages/thread/{id} ?>
    <?php if (preg_match('#/messages/(\d+|thread/)#', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/messages-thread.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- Notifications CSS -->
    <?php if (strpos($normPath, '/notifications') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/notifications.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Groups CSS (index and show pages) -->
    <?php if (($normPath === '/groups' || preg_match('/\/groups$/', $normPath)) || preg_match('/\/groups\/\d+$/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/groups-show.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Events CSS -->
    <?php if ($normPath === '/events' || preg_match('/\/events$/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/events-index.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php if (strpos($normPath, '/events/calendar') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/events-calendar.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php if (strpos($normPath, '/events/create') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/events-create.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php if (preg_match('/\/events\/\d+$/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/events-show.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Blog/News CSS -->
    <?php if ($normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-index.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php if (preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-show.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Listings CSS -->
    <?php if ($normPath === '/listings' || preg_match('/\/listings$/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/listings-index.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php if (strpos($normPath, '/listings/create') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/listings-create.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php if (preg_match('/\/listings\/\d+$/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/listings-show.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Federation CSS (all /federation/* and /transactions/* routes) -->
    <?php if (strpos($normPath, '/federation') !== false || strpos($normPath, '/transactions') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/federation.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Volunteering CSS (all /volunteering/* routes) -->
    <?php if (strpos($normPath, '/volunteering') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/volunteering.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Groups CSS (all /groups/* and /edit-group/* routes) -->
    <?php if (strpos($normPath, '/groups') !== false || strpos($normPath, '/edit-group') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/groups.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Goals CSS (all /goals/* routes) -->
    <?php if (strpos($normPath, '/goals') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/goals.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Polls CSS (all /polls/* routes) -->
    <?php if (strpos($normPath, '/polls') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/polls.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Resources CSS (all /resources/* routes) -->
    <?php if (strpos($normPath, '/resources') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/resources.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Matches CSS (all /matches/* routes) -->
    <?php if (strpos($normPath, '/matches') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/matches.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Organizations CSS (all /organizations/* routes) -->
    <?php if (strpos($normPath, '/organizations') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/organizations.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Help CSS (all /help/* routes) -->
    <?php if (strpos($normPath, '/help') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/help.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Wallet CSS (all /wallet/* routes) -->
    <?php if (strpos($normPath, '/wallet') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/wallet.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Static Pages CSS (about, contact, privacy, terms, legal, etc.) -->
    <?php if (preg_match('/\/(about|accessibility|contact|how-it-works|legal|mobile-about|partner|privacy|terms|timebanking-guide)/', $normPath)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/static-pages.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Scattered Singles CSS (ai, connections, leaderboard, members, onboarding, reports, reviews, search, settings, master) -->
    <?php if (preg_match('/\/(ai|connections|forgot-password|leaderboard|volunteer-license|members|onboarding|nexus-impact-report|reviews|search|settings|master)/', $normPath) || (strpos($normPath, '/events') !== false && strpos($normPath, 'edit') !== false) || (strpos($normPath, '/listings') !== false && strpos($normPath, 'edit') !== false)): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/scattered-singles.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Performance Patch - GPU-optimized animations -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-performance-patch.min.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Emergency Scroll Fix - MUST be last to override all other styles -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/scroll-fix-emergency.min.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Font Awesome - Single file faster than multiple requests -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    </noscript>
    <!-- Dashicons - WordPress icons (via unpkg CDN) - Async loaded -->
    <link rel="stylesheet" href="https://unpkg.com/@icon/dashicons@0.9.0/dashicons.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://unpkg.com/@icon/dashicons@0.9.0/dashicons.css" crossorigin="anonymous">
    </noscript>

    <!-- Google Fonts - Loaded async to prevent render blocking -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    </noscript>
    <!-- Noscript Fallbacks: Ensure content visibility without JS (CSS Audit 2026-01) -->
    <noscript>
        <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/noscript-fallbacks.css">
    </noscript>

    <?php
    // PRIORITY: Load page-specific CSS if needed
    if (isset($additionalCSS) && !empty($additionalCSS)) {
        echo "<!-- Page-Specific CSS (Priority Load) -->\n";
        echo $additionalCSS . "\n";
    }
    ?>

    <!-- CRITICAL: Instant Load Script (deferred to not block CSS) -->
    <script defer src="/assets/js/nexus-instant-load.min.js?v=<?= $cssVersionTimestamp ?>"></script>
    <!-- Layout Switch Helper (prevents visual glitches) -->
    <script defer src="/assets/js/layout-switch-helper.min.js?v=<?= $cssVersionTimestamp ?>"></script>

    <?php
    // Note: Custom Layout Builder CSS removed 2026-01-17
    // The getCustomLayoutCSS() method was never implemented
    // Note: Layout stability + offline banner styles moved to nexus-header-extracted.css (2026-01-19)
    ?>

    <!-- 🛑 ERROR TRAP: Catch any JavaScript errors before page reload -->
    <script>
        (function() {
            // Safety wrapper for toLowerCase to prevent undefined errors - MUST BE FIRST!
            var originalToLowerCase = String.prototype.toLowerCase;
            String.prototype.toLowerCase = function() {
                if (this === undefined || this === null) {
                    console.warn('[SAFETY] toLowerCase called on undefined/null, returning empty string');
                    return '';
                }
                return originalToLowerCase.call(this);
            };

            // Global error handler
            window.onerror = function(msg, url, lineNo, columnNo, error) {
                var errorMsg = 'ERROR TRAPPED!\n\n' +
                    'Message: ' + msg + '\n' +
                    'URL: ' + url + '\n' +
                    'Line: ' + lineNo + '\n' +
                    'Column: ' + columnNo + '\n' +
                    'Error: ' + (error ? error.stack : 'N/A');

                // Save to localStorage
                try {
                    localStorage.setItem('LAST_JS_ERROR', errorMsg);
                    localStorage.setItem('LAST_JS_ERROR_TIME', new Date().toISOString());
                } catch (e) {
                    console.error('Failed to save error to localStorage:', e);
                }

                // Show alert
                alert(errorMsg);

                // Log to console
                console.error('🛑 TRAPPED ERROR:', errorMsg);

                // Try to prevent page reload
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                }

                // Return true to prevent default browser error handling
                return true;
            };

            // Promise rejection handler
            window.addEventListener('unhandledrejection', function(event) {
                var errorMsg = 'PROMISE REJECTION TRAPPED!\n\n' +
                    'Reason: ' + (event.reason ? event.reason.message || event.reason : 'Unknown');

                try {
                    localStorage.setItem('LAST_PROMISE_ERROR', errorMsg);
                    localStorage.setItem('LAST_PROMISE_ERROR_TIME', new Date().toISOString());
                } catch (e) {}

                alert(errorMsg);
                console.error('🛑 TRAPPED PROMISE REJECTION:', event.reason);

                event.preventDefault();
            });

            // Check for errors from previous page load
            try {
                var lastError = localStorage.getItem('LAST_JS_ERROR');
                var lastErrorTime = localStorage.getItem('LAST_JS_ERROR_TIME');
                if (lastError && lastErrorTime) {
                    console.warn('🛑 ERROR FROM PREVIOUS PAGE LOAD:', lastError);
                    console.warn('Time:', lastErrorTime);
                }
            } catch (e) {}

            console.log('🛑 ERROR TRAP ACTIVE - Any errors will be caught and displayed');
        })();
    </script>

    <script>
        // Offline banner fix: Only show after verifying truly offline (not just page load flicker)
        (function() {
            var verified = false;

            function verifyOffline() {
                if (verified) return;
                setTimeout(function() {
                    if (!navigator.onLine) {
                        var banner = document.getElementById('offlineBanner') || document.querySelector('.offline-banner');
                        if (banner) {
                            banner.classList.add('verified-offline');
                            banner.classList.add('visible');
                        }
                    }
                    verified = true;
                }, 2000); // Wait 2 seconds to avoid false positives
            }
            // Run after DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', verifyOffline);
            } else {
                verifyOffline();
            }
            // Also handle actual offline events immediately
            window.addEventListener('offline', function() {
                var banner = document.getElementById('offlineBanner') || document.querySelector('.offline-banner');
                if (banner) {
                    banner.classList.add('verified-offline');
                    banner.classList.add('visible');
                }
            });
            window.addEventListener('online', function() {
                var banner = document.getElementById('offlineBanner') || document.querySelector('.offline-banner');
                if (banner) {
                    banner.classList.remove('verified-offline');
                    banner.classList.remove('visible');
                }
            });
        })();
    </script>

    <script>
        // Ensure path never ends in a slash to avoid double-slash errors in AJAX
        const NEXUS_BASE = "<?= rtrim(\Nexus\Core\TenantContext::getBasePath(), '/') ?>";

        // Fix bfcache issue: clean up messages-page classes when on non-messages pages
        (function() {
            function cleanupMessagesClasses() {
                if (!window.location.pathname.match(/\/messages(\/|$)/)) {
                    document.documentElement.classList.remove('messages-page');
                    // Guard against body not being ready yet
                    if (document.body) {
                        document.body.classList.remove('messages-page', 'messages-fullscreen', 'no-ptr');
                        document.body.style.overflow = '';
                    }
                    document.documentElement.style.overflow = '';
                }
            }
            // Run on DOMContentLoaded to ensure body exists
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupMessagesClasses);
            } else {
                cleanupMessagesClasses();
            }
            // Also run on pageshow (for bfcache)
            window.addEventListener('pageshow', cleanupMessagesClasses);
        })();
    </script>

</head>

<body class="nexus-skin-modern <?= $isHome ? 'nexus-home-page' : '' ?> <?= isset($_SESSION['user_id']) ? 'logged-in' : '' ?> <?= ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])) ? 'user-is-admin' : '' ?> <?= $bodyClass ?? '' ?>">

    <?php if (!empty($bodyClass) && (strpos($bodyClass, 'no-ptr') !== false || strpos($bodyClass, 'chat-page') !== false || strpos($bodyClass, 'messages-fullscreen') !== false)): ?>
        <script>
            // CRITICAL: Prevent PTR before any other scripts load (for messages and chat pages)
            (function() {
                document.documentElement.classList.add('no-ptr');
                document.body.classList.add('no-ptr');
                // Apply chat-specific classes only for chat pages
                if (document.body.classList.contains('chat-page') || document.body.classList.contains('chat-fullscreen')) {
                    document.documentElement.classList.add('chat-page');
                }
                // Disable overscroll on html/body immediately
                var s = 'overflow:hidden!important;overscroll-behavior:none!important;position:fixed!important;inset:0!important;';
                document.documentElement.style.cssText += s;
                document.body.style.cssText += s;
                // Intercept and remove any PTR indicators that might be created
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(m) {
                        m.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && (node.classList.contains('nexus-ptr-indicator') || node.classList.contains('ptr-indicator'))) {
                                node.remove();
                            }
                        });
                    });
                });
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                // Stop observing after 5 seconds (scripts should be loaded by then)
                setTimeout(function() {
                    observer.disconnect();
                }, 5000);
            })();
        </script>
    <?php endif; ?>

    <!-- Layout Preview Banner (if in preview mode) -->
    <?php require __DIR__ . '/partials/preview-banner.php'; ?>

    <!-- LEGENDARY: Keyboard Shortcuts for Power Users -->
    <?php require __DIR__ . '/partials/keyboard-shortcuts.php'; ?>

    <?php if (empty($hideUtilityBar)): ?>
        <nav class="nexus-utility-bar">
            <div class="left-utils" style="display:flex; align-items:center;">
                <?php
                // Platform Switcher - Public to everyone
                $showPlatform = true;
                // Get the main platform domain for slug-based tenants
                $mainPlatformDomain = $_ENV['MAIN_DOMAIN'] ?? 'project-nexus.ie';

                if ($showPlatform):
                ?>
                    <div class="htb-dropdown">
                        <button class="util-link" style="font-weight:700; text-transform:uppercase; font-size:0.8rem;">Platform <span class="htb-arrow">▾</span></button>
                        <div class="htb-dropdown-content" style="min-width:200px; left:0; right:auto;">
                            <?php foreach (\Nexus\Models\Tenant::all() as $pt):
                                if ($pt['domain']) {
                                    // Tenant has its own domain
                                    $link = 'https://' . $pt['domain'];
                                } else {
                                    // Tenant uses slug on main platform domain
                                    $link = 'https://' . $mainPlatformDomain . '/' . $pt['slug'];
                                    if ($pt['id'] == 1) $link = 'https://' . $mainPlatformDomain;
                                }
                            ?>
                                <a href="<?= $link ?>"><?= htmlspecialchars($pt['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display:flex; align-items:center; margin-left:auto;">
                <!-- Mode Switcher - Light/Dark -->
                <button onclick="toggleMode()" class="mode-switcher" aria-label="Switch between light and dark mode" title="<?= $mode === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode' ?>">
                    <span class="mode-icon-container <?= $mode === 'dark' ? 'dark-mode' : 'light-mode' ?>" id="modeIconContainer">
                        <i class="fa-solid <?= $mode === 'dark' ? 'fa-moon' : 'fa-sun' ?> mode-icon" id="modeIcon"></i>
                    </span>
                    <span class="mode-label" id="modeLabel"><?= $mode === 'dark' ? 'Dark Mode' : 'Light Mode' ?></span>
                    <i class="fa-solid fa-chevron-right mode-arrow"></i>
                </button>
                <?php
                $tSlug = '';
                if (class_exists('\Nexus\Core\TenantContext')) {
                    $tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
                }
                if ($tSlug !== 'public-sector-demo'):
                ?>
                    <div class="htb-dropdown desktop-only-dd">
                        <button class="util-link">Layout <span class="htb-arrow">▾</span></button>
                        <div class="htb-dropdown-content">
                            <?php $lay = \Nexus\Services\LayoutHelper::get(); ?>
                            <a href="?layout=modern" style="<?= $lay === 'modern' ? 'font-weight:bold; color:#4f46e5;' : '' ?>">
                                ✓ Modern UI <span style="font-size:10px; color:#10b981;">(Stable)</span>
                            </a>
                            <div style="border-top:1px solid #e5e7eb; margin:8px 0;"></div>
                            <div style="padding: 6px 12px; font-size: 11px; color: #64748b; font-weight: 500;">
                                ⚠️ Experimental Layouts (Under Development)
                            </div>
                            <a href="?layout=civicone" style="<?= $lay === 'civicone' ? 'font-weight:bold; color:#059669;' : 'opacity:0.7;' ?>">
                                Accessible UI <span style="font-size:9px; color:#f59e0b;">BETA</span>
                            </a>
                            <?php
                            $tId = \Nexus\Core\TenantContext::getId();
                            $isAllowedSocial = ($tId == 1) || ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');
                            if ($isAllowedSocial):
                                $rootPath = \Nexus\Core\TenantContext::getBasePath();
                                if (empty($rootPath)) $rootPath = '/';

                            ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="htb-dropdown">
                        <button class="util-link" style="font-weight:700; color:#10b981;">+ Create <span class="htb-arrow">▾</span></button>
                        <div class="htb-dropdown-content">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=post">📝 New Post</a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=listing">🎁 New Listing</a>
                            <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=event">📅 New Event</a>
                            <?php endif; ?>
                            <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=volunteer">🤝 Volunteer Opp</a>
                            <?php endif; ?>
                            <?php if (Nexus\Core\TenantContext::hasFeature('polls')): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=poll">📊 New Poll</a>
                            <?php endif; ?>
                            <?php if (Nexus\Core\TenantContext::hasFeature('goals')): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=goal">🎯 New Goal</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                    $hasFederationUtilBar = false;
                    if (class_exists('\Nexus\Services\FederationFeatureService')) {
                        try {
                            $hasFederationUtilBar = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                        } catch (\Exception $e) {
                            $hasFederationUtilBar = false;
                        }
                    }
                    if ($hasFederationUtilBar): ?>
                        <div class="htb-dropdown">
                            <button class="util-link" style="font-weight:700; color:#8b5cf6;">
                                <i class="fa-solid fa-globe" style="margin-right: 4px;"></i>Partner Communities <span class="htb-arrow">▾</span>
                            </button>
                            <div class="htb-dropdown-content" style="min-width: 220px;">
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation" style="font-weight: 600; color: #8b5cf6;">
                                    <i class="fa-solid fa-house" style="margin-right: 6px;"></i>Partner Communities Hub
                                </a>
                                <div style="border-top:1px solid #e5e7eb; margin:5px 0;"></div>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/members">
                                    <i class="fa-solid fa-user-group" style="margin-right: 6px; color: #8b5cf6;"></i>Members
                                </a>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/listings">
                                    <i class="fa-solid fa-hand-holding-heart" style="margin-right: 6px; color: #ec4899;"></i>Listings
                                </a>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/events">
                                    <i class="fa-solid fa-calendar-days" style="margin-right: 6px; color: #f59e0b;"></i>Events
                                </a>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/groups">
                                    <i class="fa-solid fa-users" style="margin-right: 6px; color: #6366f1;"></i>Groups
                                </a>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/messages">
                                    <i class="fa-solid fa-envelope" style="margin-right: 6px; color: #3b82f6;"></i>Messages
                                </a>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/transactions">
                                    <i class="fa-solid fa-coins" style="margin-right: 6px; color: #10b981;"></i>Transactions
                                </a>
                                <div style="border-top:1px solid #e5e7eb; margin:5px 0;"></div>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation">
                                    <i class="fa-solid fa-sliders" style="margin-right: 6px; color: #6b7280;"></i>Settings
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'newsletter_admin')): ?>
                    <!-- Newsletter Admin - Limited Access -->
                    <div class="htb-dropdown">
                        <button class="util-link" style="font-weight:700; color:#f59e0b;">Newsletter <span class="htb-arrow">▾</span></button>
                        <div class="htb-dropdown-content">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters">All Newsletters</a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/create">Create Newsletter</a>
                            <div style="border-top:1px solid #e5e7eb; margin:5px 0;"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/subscribers">Subscribers</a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/segments">Segments</a>
                        </div>
                    </div>
                <?php elseif ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin" class="util-link" style="font-weight:700; color:#ea580c;">Admin</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/group-ranking" class="util-link" style="font-weight:700; color:#db2777;" title="Smart Group Ranking">
                        <i class="fa-solid fa-chart-line"></i> Ranking
                    </a>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])):
                    // Notification Logic
                    $nUserId = $_SESSION['user_id'];
                    $nUnread = \Nexus\Models\Notification::countUnread($nUserId);
                    $nRecent = \Nexus\Models\Notification::getLatest($nUserId, 5);

                    // Message count logic
                    $msgUnread = 0;
                    try {
                        if (class_exists('Nexus\Models\MessageThread')) {
                            $msgThreads = Nexus\Models\MessageThread::getForUser($nUserId);
                            foreach ($msgThreads as $msgThread) {
                                if (!empty($msgThread['unread_count'])) {
                                    $msgUnread += (int)$msgThread['unread_count'];
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $msgUnread = 0;
                    }
                ?>
                    <!-- Messages Icon -->
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages" class="nexus-header-icon-btn" title="Messages">
                        <i class="fa-solid fa-envelope"></i>
                        <?php if ($msgUnread > 0): ?>
                            <span class="nexus-header-icon-badge"><?= $msgUnread > 99 ? '99+' : $msgUnread ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Notifications Bell -->
                    <button class="nexus-header-icon-btn" title="Notifications" onclick="window.nexusNotifDrawer.open()" style="position: relative;">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($nUnread > 0): ?>
                            <span class="nexus-notif-indicator" style="position: absolute; top: 4px; right: 4px; width: 10px; height: 10px; background: #ef4444; border-radius: 50%; border: 2px solid var(--header-bg, #1e1b4b); box-shadow: 0 0 0 1px rgba(239,68,68,0.3);"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Notifications Drawer (slides in from right) -->
                    <div id="notif-drawer-overlay" class="notif-drawer-overlay" onclick="window.nexusNotifDrawer.close()"></div>
                    <aside id="notif-drawer" class="notif-drawer">
                        <div class="notif-drawer-header">
                            <span>NOTIFICATIONS</span>
                            <button class="notif-drawer-close" onclick="window.nexusNotifDrawer.close()" aria-label="Close">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div id="nexus-notif-list" class="notif-drawer-list">
                            <?php if (empty($nRecent)): ?>
                                <div class="notif-drawer-empty">
                                    <div class="notif-icon"><i class="fa-regular fa-bell-slash"></i></div>
                                    <div class="notif-text">No notifications yet</div>
                                </div>
                            <?php else: ?>
                                <?php
                                $notifBasePath = Nexus\Core\TenantContext::getBasePath();
                                foreach ($nRecent as $n):
                                    // Ensure link uses basePath if it's a relative path
                                    $notifLink = $n['link'] ?: '#';
                                    if ($notifLink !== '#' && strpos($notifLink, 'http') !== 0 && strpos($notifLink, $notifBasePath) !== 0) {
                                        // Relative path - prepend basePath
                                        $notifLink = $notifBasePath . $notifLink;
                                    }
                                ?>
                                    <a href="<?= htmlspecialchars($notifLink) ?>" data-notif-id="<?= $n['id'] ?>" class="notif-drawer-item<?= $n['is_read'] ? ' is-read' : '' ?>">
                                        <div class="notif-message">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </div>
                                        <div class="notif-time">
                                            <i class="fa-regular fa-clock"></i> <?= date('M j, g:i a', strtotime($n['created_at'])) ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="notif-drawer-footer">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/notifications">
                                <i class="fa-solid fa-list"></i> View all
                            </a>
                            <button type="button" onclick="window.nexusNotifications.markAllRead(this);">
                                <i class="fa-solid fa-check-double"></i> Mark all read
                            </button>
                        </div>
                    </aside>

                    <!-- User Avatar Dropdown (Premium) -->
                    <div class="htb-dropdown desktop-only">
                        <button class="util-link" style="padding: 4px 12px; display: flex; align-items: center; gap: 8px;">
                            <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>"
                                alt="Profile"
                                style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                            <span style="font-weight: 600;"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?></span>
                            <span class="htb-arrow">▾</span>
                        </button>
                        <div class="htb-dropdown-content" style="min-width: 220px;">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $_SESSION['user_id'] ?>">
                                <i class="fa-solid fa-user" style="margin-right: 10px; width: 16px; text-align: center; color: #6366f1;"></i>My Profile
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/dashboard">
                                <i class="fa-solid fa-gauge" style="margin-right: 10px; width: 16px; text-align: center; color: #8b5cf6;"></i>Dashboard
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet">
                                <i class="fa-solid fa-wallet" style="margin-right: 10px; width: 16px; text-align: center; color: #10b981;"></i>Wallet
                            </a>
                            <div style="border-top: 1px solid #e5e7eb; margin: 8px 0;"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/logout" style="color: #ef4444; font-weight: 600;">
                                <i class="fa-solid fa-right-from-bracket" style="margin-right: 10px; width: 16px; text-align: center;"></i>Sign Out
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="util-link">Login</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="util-link" style="font-weight:700; color:#fff;">Join</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>

    <?php if (empty($hideUtilityBar)): ?>
        <header class="nexus-navbar">
            <?php
            $tName = Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
            if (Nexus\Core\TenantContext::getId() == 1) $tName = 'Project NEXUS';

            // Smart word boundary split for brand name
            $knownSplits = [
                'timebankireland' => ['TIMEBANK', 'IRELAND'],
                'timebankire land' => ['TIMEBANK', 'IRELAND'],
                'timebank ireland' => ['TIMEBANK', 'IRELAND'],
                'projectnexus' => ['PROJECT', 'NEXUS'],
                'project nexus' => ['PROJECT', 'NEXUS'],
            ];

            $lowerName = strtolower(trim($tName));
            if (isset($knownSplits[$lowerName])) {
                $tFirst = $knownSplits[$lowerName][0];
                $tRest = $knownSplits[$lowerName][1];
            } elseif (strpos($tName, ' ') !== false) {
                $tParts = explode(' ', $tName, 2);
                $tFirst = strtoupper($tParts[0]);
                $tRest = strtoupper($tParts[1] ?? '');
            } else {
                $tFirst = strtoupper($tName);
                $tRest = '';
            }
            ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?: '/' ?>" class="nexus-brand-link" aria-label="<?= htmlspecialchars($tName) ?> - Go to homepage">
                <span class="brand-primary"><?= htmlspecialchars($tFirst) ?></span><?php if ($tRest): ?><span class="brand-secondary"><?= htmlspecialchars($tRest) ?></span><?php endif; ?>
            </a>
            <!-- Brand link styles moved to nexus-header-extracted.css -->

            <div class="desktop-only" style="display: flex; align-items: center; gap: 8px;">
                <?php
                // Premium Mega Menu Navigation
                $basePath = Nexus\Core\TenantContext::getBasePath();
                $currentTenant = Nexus\Core\TenantContext::get();
                $tenantId = Nexus\Core\TenantContext::getId();

                ?>
                <!-- Core Navigation Links (Always Visible) -->
                <a href="<?= $basePath ?>/" class="nav-link" data-nav-match="/">
                    <i class="fa-solid fa-house"></i> Feed
                </a>
                <a href="<?= $basePath ?>/listings" class="nav-link" data-nav-match="listings">
                    <i class="fa-solid fa-hand-holding-heart"></i> Listings
                </a>
                <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                    <a href="<?= $basePath ?>/volunteering" class="nav-link" data-nav-match="volunteering">
                        <i class="fa-solid fa-handshake-angle"></i> Volunteering
                    </a>
                <?php endif; ?>

                <!-- Premium Glassmorphism Mega Menus -->
                <?php require __DIR__ . '/partials/premium-mega-menu.php'; ?>
                <?php /*
                // OLD NAVIGATION CODE - COMMENTED OUT FOR FALLBACK
                // Define explore items inline with current tenant's basePath
                $exploreItemsConfig = [
                    'events' => [
                        'label' => 'Events',
                        'url' => $basePath . '/events',
                        'icon' => 'fa-solid fa-calendar-days',
                        'color' => '#8b5cf6',
                        'requires_feature' => 'events'
                    ],
                    'polls' => [
                        'label' => 'Polls',
                        'url' => $basePath . '/polls',
                        'icon' => 'fa-solid fa-square-poll-vertical',
                        'color' => '#06b6d4',
                        'requires_feature' => 'polls'
                    ],
                    'goals' => [
                        'label' => 'Goals',
                        'url' => $basePath . '/goals',
                        'icon' => 'fa-solid fa-bullseye',
                        'color' => '#f59e0b',
                        'requires_feature' => 'goals'
                    ],
                    'resources' => [
                        'label' => 'Resources',
                        'url' => $basePath . '/resources',
                        'icon' => 'fa-solid fa-folder-open',
                        'color' => '#10b981',
                        'requires_feature' => 'resources'
                    ],
                    'smart_matching' => [
                        'label' => 'Smart Matching',
                        'url' => $basePath . '/matches',
                        'icon' => 'fa-solid fa-wand-magic-sparkles',
                        'color' => '#ec4899',
                        'separator_before' => true
                    ],
                    'leaderboard' => [
                        'label' => 'Leaderboards',
                        'url' => $basePath . '/leaderboard',
                        'icon' => 'fa-solid fa-trophy',
                        'color' => '#f59e0b'
                    ],
                    'achievements' => [
                        'label' => 'Achievements',
                        'url' => $basePath . '/achievements',
                        'icon' => 'fa-solid fa-medal',
                        'color' => '#a855f7'
                    ],
                    'ai' => [
                        'label' => 'AI Assistant',
                        'url' => $basePath . '/ai',
                        'icon' => 'fa-solid fa-robot',
                        'color' => '#6366f1',
                        'highlight' => true,
                        'separator_before' => true
                    ],
                    'get_app' => [
                        'label' => 'Get App',
                        'url' => $basePath . '/mobile-download',
                        'icon' => 'fa-solid fa-mobile-screen-button',
                        'color' => 'var(--civic-brand-primary)',
                        'separator_before' => true
                    ]
                ];

                // Filter items based on tenant features
                $visibleExploreItems = [];
                foreach ($exploreItemsConfig as $key => $item) {
                    // Check feature requirements
                    if (isset($item['requires_feature'])) {
                        if (!Nexus\Core\TenantContext::hasFeature($item['requires_feature'])) {
                            continue;
                        }
                    }
                    $visibleExploreItems[$key] = $item;
                }

                // Only show dropdown if there are visible items
                if (!empty($visibleExploreItems)):
                ?>
                    <div class="htb-dropdown premium-dropdown">
                        <a href="#" class="nav-link premium-dropdown-trigger">
                            <i class="fa-solid fa-compass" style="margin-right:6px;"></i>Explore <span class="htb-arrow">▾</span>
                        </a>
                        <div class="htb-dropdown-content premium-dropdown-menu">
                            <?php foreach ($visibleExploreItems as $key => $item):
                                $icon = $item['icon'] ?? 'fa-solid fa-circle';
                                $color = $item['color'] ?? '#6366f1';
                                $label = $item['label'] ?? ucfirst($key);
                                $url = $item['url'] ?? '#';
                                $separator = $item['separator_before'] ?? false;
                                $highlight = $item['highlight'] ?? false;

                                if ($separator): ?>
                                    <div style="border-top: 1px solid #e5e7eb; margin: 5px 0;"></div>
                                <?php endif; ?>

                                <a href="<?= htmlspecialchars($url) ?>"
                                   style="<?= $highlight ? 'background: rgba(99, 102, 241, 0.1);' : '' ?>"
                                   data-debug-url="<?= htmlspecialchars($url) ?>"
                                   data-debug-basepath="<?= htmlspecialchars($basePath) ?>">
                                    <i class="<?= htmlspecialchars($icon) ?>"
                                       style="margin-right:10px; width:16px; text-align:center; color:<?= htmlspecialchars($color) ?>;"></i>
                                    <?= htmlspecialchars($label) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; // END: if (!empty($mainMenus)) else block
            */ ?>

            <?php
            // Database-driven pages (Page Builder) - shown as top-level nav links
            $dbPagesMain = \Nexus\Core\MenuGenerator::getMenuPages('main');
            foreach ($dbPagesMain as $mainPage):
            ?>
                <a href="<?= htmlspecialchars($mainPage['url']) ?>" class="nav-link"><i class="fa-solid fa-file-lines" style="margin-right:6px; opacity:0.7;"></i><?= htmlspecialchars($mainPage['title']) ?></a>
            <?php endforeach; ?>

                <!-- Collapsible Search Container -->
                <div class="collapsible-search-container" style="margin-left: 15px; display: flex; align-items: center;">
                    <!-- Search Toggle Button (visible when collapsed) -->
                    <button type="button" class="search-toggle-btn" id="searchToggleBtn" aria-label="Open search" aria-expanded="false">
                        <i class="fa fa-search"></i>
                    </button>

                    <!-- Expandable Search Form -->
                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/search" method="GET" class="htb-search-box premium-search collapsible-search" id="collapsibleSearch">
                        <button type="submit" class="search-icon-btn">
                            <i class="fa fa-search"></i>
                        </button>
                        <input type="text" name="q" placeholder="Search Nexus..." aria-label="Search" class="search-input" id="searchInput">
                        <button type="button" class="search-close-btn" id="searchCloseBtn" aria-label="Close search">
                            <i class="fa fa-times"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Mobile Right Actions (Notifications + Menu) -->
            <div class="nexus-mobile-actions">
                <?php if (isset($_SESSION['user_id'])):
                    $mobileNotifCount = 0;
                    if (class_exists('\Nexus\Models\Notification')) {
                        try {
                            $mobileNotifCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
                        } catch (\Exception $e) {
                            $mobileNotifCount = 0;
                        }
                    }
                ?>
                    <!-- Mobile Notifications Bell -->
                    <button class="nexus-notif-btn" aria-label="Notifications" onclick="if(typeof openMobileNotifications==='function'){openMobileNotifications();}else{window.location.href='<?= Nexus\Core\TenantContext::getBasePath() ?>/notifications';}">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($mobileNotifCount > 0): ?>
                            <span class="nexus-notif-badge"><?= $mobileNotifCount > 99 ? '99+' : $mobileNotifCount ?></span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>

                <!-- Mobile Menu Button -->
                <button class="nexus-menu-btn" aria-label="Open Menu" onclick="if(typeof openMobileMenu==='function'){openMobileMenu();}">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
            <!-- Mobile actions styles moved to nexus-header-extracted.css -->
        </header>
    <?php endif; ?>

    <!-- Admin Impersonation Banner -->
    <?php require __DIR__ . '/../../modern/partials/impersonation-banner.php'; ?>

    <!-- Main Content Area (for skip-link target) -->
    <main id="main-content" role="main">

        <!-- Modern Header Behavior (Extracted to external file) - defer to avoid render blocking -->
        <script src="/assets/js/modern-header-behavior.min.js?v=<?= time() ?>" defer></script>

        <!-- Hero section removed for cleaner mobile experience -->
        <!-- Notification scripts now loaded in footer.php with Pusher WebSocket support -->