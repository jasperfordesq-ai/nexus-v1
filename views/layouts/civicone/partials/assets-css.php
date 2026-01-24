<head>
    <meta charset="UTF-8">
    <!-- CRITICAL: Inline scroll fix - ensures mouse wheel scrolling works -->
    <style id="scroll-fix-inline">
        /* HTML: Always show scrollbar, allow vertical scroll */
        html,
        html:root,
        html[data-theme],
        html[data-layout] {
            overflow-y: scroll !important;
            overflow-x: hidden !important;
            height: auto !important;
        }

        /* BODY: Use overflow-y: auto (NOT visible - visible breaks mouse wheel scroll!) */
        body,
        html body,
        html[data-theme] body,
        html[data-layout] body {
            overflow-y: auto !important;
            overflow-x: hidden !important;
            position: static !important;
            height: auto !important;
        }

        /* Modal/drawer states: Lock scroll ONLY when actually open */
        body.drawer-open,
        body.modal-open,
        body.fds-sheet-open,
        body.keyboard-open,
        body.mobile-menu-open,
        body.menu-open {
            overflow: hidden !important;
            overflow-y: hidden !important;
        }
    </style>
    <meta name="csrf-token" content="<?= \Nexus\Core\Csrf::generate() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <!-- Critical CSS Inline (Lighthouse: Reduce FCP) -->
    <?php include __DIR__ . '/../critical-css.php'; ?>

    <!-- Performance: DNS Prefetch & Preconnect -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//api.mapbox.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Fonts: Async loading to prevent render blocking -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </noscript>
    <?php
    // Load global SEO defaults from database first
    \Nexus\Core\SEO::load('global');
    // Bridge Legacy Variables to SEO Engine (override globals)
    if (isset($pageTitle)) \Nexus\Core\SEO::setTitle($pageTitle);
    if (isset($hTitle)) \Nexus\Core\SEO::setTitle($hTitle); // Support Modern/Phoenix vars too
    if (isset($hSubtitle)) \Nexus\Core\SEO::setDescription($hSubtitle);

    echo \Nexus\Core\SEO::render();

    // GEOGRAPHIC SKINNING LOGIC
    $skinClass = '';

    // 1. Check Explicit Group Context (from Controller)
    if (isset($group) && is_array($group)) {
        $gName = strtolower($group['name'] . ' ' . ($group['description'] ?? ''));
        if (strpos($gName, 'cork') !== false || strpos($gName, 'bantry') !== false) $skinClass = 'skin-cork';
        elseif (strpos($gName, 'clare') !== false || strpos($gName, 'ennis') !== false) $skinClass = 'skin-clare';
        elseif (strpos($gName, 'galway') !== false) $skinClass = 'skin-galway';
    }

    // 2. Check URL Pattern (Fallback)
    if (!$skinClass && isset($_SERVER['REQUEST_URI'])) {
        $uri = strtolower($_SERVER['REQUEST_URI']);
        if (strpos($uri, 'cork') !== false) $skinClass = 'skin-cork';
        elseif (strpos($uri, 'clare') !== false) $skinClass = 'skin-clare';
        elseif (strpos($uri, 'galway') !== false) $skinClass = 'skin-galway';
    }
    ?>
    <?php
    // Dynamic CSS version for cache busting - uses deployment version to force refresh on all users
    $deploymentVersion = file_exists(__DIR__ . '/../../../config/deployment-version.php')
        ? require __DIR__ . '/../../../config/deployment-version.php'
        : ['version' => time()];
    $cssVersion = $deploymentVersion['version'] ?? time();
    ?>
    <!-- Updated 2026-01-17: All CSS now uses minified versions where available -->
    <!-- DESIGN TOKENS (Shared variables - must load first) -->
    <link rel="stylesheet" href="/assets/css/design-tokens.min.css?v=<?= $cssVersion ?>">
    <!-- LAYOUT ISOLATION (Prevent CSS conflicts) -->
    <link rel="stylesheet" href="/assets/css/layout-isolation.min.css?v=<?= $cssVersion ?>">
    <!-- CORE FRAMEWORK (Layout & Logic) -->
    <link rel="stylesheet" href="/assets/css/nexus-phoenix.min.css?v=<?= $cssVersion ?>">
    <!-- BRANDING (Global Styles - Must load BEFORE Skin) -->
    <link rel="stylesheet" href="/assets/css/branding.min.css?v=<?= $cssVersion ?>">
    <!-- THEME OVERRIDE (Government Skin - Must load LAST) -->
    <link rel="stylesheet" href="/assets/css/nexus-civicone.min.css?v=<?= $cssVersion ?>">
    <!-- MOBILE ENHANCEMENTS (Bottom Nav, FAB, Skeletons) -->
    <link rel="stylesheet" href="/assets/css/civicone-mobile.min.css?v=<?= $cssVersion ?>">
    <!-- NATIVE EXPERIENCE (Animations, Gestures, Haptics) -->
    <link rel="stylesheet" href="/assets/css/civicone-native.min.css?v=<?= $cssVersion ?>">
    <!-- MOBILE NAV V2 (Tab Bar, Fullscreen Menu, Notifications Sheet) -->
    <link rel="stylesheet" href="/assets/css/nexus-native-nav-v2.min.css?v=<?= $cssVersion ?>">

    <!-- Page-specific Component CSS -->
    <?php if ($isHome): ?>
        <link rel="stylesheet" href="/assets/css/feed-filter.min.css?v=<?= $cssVersion ?>">
        <!-- CivicOne Home Page Styles (v3.1.0 - GOV.UK Full Compliance) -->
        <link rel="stylesheet" href="/assets/css/civicone-home.css?v=<?= $cssVersion ?>">
    <?php endif; ?>
    <?php if (strpos($normPath, '/dashboard') !== false): ?>
        <link rel="stylesheet" href="/assets/css/dashboard.min.css?v=<?= $cssVersion ?>">
        <link rel="stylesheet" href="/assets/css/civicone-dashboard.min.css?v=<?= $cssVersion ?>">
    <?php endif; ?>
    <?php if (strpos($normPath, '/members') !== false): ?>
        <!-- MOJ Filter Component for Members Directory v1.6.0 (GOV.UK/MOJ Compliance) -->
        <link rel="stylesheet" href="/assets/css/moj-filter.min.css?v=<?= $cssVersion ?>">
        <!-- Members Directory v1.6.0 (Mobile Bottom Sheet + Prominent Tabs) -->
        <link rel="stylesheet" href="/assets/css/members-directory-v1.6.min.css?v=<?= $cssVersion ?>">
    <?php endif; ?>

    <!-- Mobile Sheets CSS (base styles always load, CSS handles desktop hiding) -->
    <link rel="stylesheet" href="/assets/css/mobile-sheets.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/pwa-install-modal.css?v=<?= $cssVersion ?>">
    <!-- Native app page enter animations (Capacitor only - uses .is-native class) -->
    <link rel="stylesheet" href="/assets/css/native-page-enter.css?v=<?= $cssVersion ?>">
    <!-- Native app form inputs (mobile/native only - desktop unchanged) -->
    <link rel="stylesheet" href="/assets/css/native-form-inputs.css?v=<?= $cssVersion ?>">
    <!-- Mobile select bottom sheet (mobile/native only) -->
    <link rel="stylesheet" href="/assets/css/mobile-select-sheet.css?v=<?= $cssVersion ?>">
    <!-- Mobile search overlay (mobile/native only) -->
    <link rel="stylesheet" href="/assets/css/mobile-search-overlay.css?v=<?= $cssVersion ?>">

    <!-- Social Interactions CSS -->
    <link rel="stylesheet" href="/assets/css/social-interactions.min.css?v=<?= $cssVersion ?>">

    <!-- GOV.UK Frontend v5.14.0 (Official Design System) - WCAG 2.1 AA Compliant -->
    <!-- Source: https://github.com/alphagov/govuk-frontend pinned to v5.14.0 -->
    <link rel="stylesheet" href="/assets/govuk-frontend-5.14.0/govuk-frontend.min.css?v=<?= $cssVersion ?>">

    <!-- GOV.UK Design System Extensions (Project-specific overrides) -->
    <link rel="stylesheet" href="/assets/css/bundles/civicone-govuk-all.min.css?v=<?= $cssVersion ?>">

    <!-- CivicOne Layout (includes grid system - must load for all pages) -->
    <link rel="stylesheet" href="/assets/css/civicone-header.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/civicone-footer.min.css?v=<?= $cssVersion ?>">

    <!-- Account Area Navigation (MOJ Sub navigation pattern) -->
    <link rel="stylesheet" href="/assets/css/civicone-account-nav.min.css?v=<?= $cssVersion ?>">

    <!-- Profile Components Bundle (Header + Social - Consolidated 2026-01-23) -->
    <link rel="stylesheet" href="/assets/css/bundles/civicone-profile-all.min.css?v=<?= $cssVersion ?>">

    <!-- Directory Utilities (Extracted inline styles for events, listings, volunteering, feed - 2026-01-21) -->
    <link rel="stylesheet" href="/assets/css/civicone-directory-utilities.min.css?v=<?= $cssVersion ?>">

    <!-- Extended Utilities (Extracted inline styles per CLAUDE.md - 2026-01-22) -->
    <link rel="stylesheet" href="/assets/css/civicone-utilities-extended.css?v=<?= $cssVersion ?>">

    <!-- Emergency Scroll Fix - MUST be last to override all other styles -->
    <link rel="stylesheet" href="/assets/css/scroll-fix-emergency.min.css?v=<?= $cssVersion ?>">
    <!-- Development Notice Modal -->
    <link rel="stylesheet" href="/assets/css/dev-notice-modal.min.css?v=<?= $cssVersion ?>">
    <!-- Development Banner (Prominent dev environment indicator) -->
    <link rel="stylesheet" href="/assets/css/civicone-dev-banner.min.css?v=<?= $cssVersion ?>">
    <!-- FONT AWESOME (Icons for mobile nav, buttons, etc.) - Async loaded -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    </noscript>
    <!-- DASHICONS (WordPress icons - via unpkg CDN) - Async loaded -->
    <link rel="stylesheet" href="https://unpkg.com/@icon/dashicons@0.9.0/dashicons.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://unpkg.com/@icon/dashicons@0.9.0/dashicons.css" crossorigin="anonymous">
    </noscript>
    <!-- Noscript Fallbacks: Ensure content visibility without JS (CSS Audit 2026-01) -->
    <noscript>
        <link rel="stylesheet" href="/assets/css/noscript-fallbacks.css">
    </noscript>

    <?php
    // Page-specific CSS (additionalCSS support - matches Modern layout)
    if (isset($additionalCSS) && !empty($additionalCSS)) {
        echo "<!-- Page-Specific CSS (Priority Load) -->\n";
        echo $additionalCSS . "\n";
    }

    // Note: Custom Layout Builder CSS removed 2026-01-17
    // The getCustomLayoutCSS() method was never implemented
    ?>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563EB">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(\Nexus\Core\TenantContext::get()['name'] ?? 'NEXUS') ?>">

    <!-- GOV.UK Layout Overrides (Extracted to external CSS per CLAUDE.md) -->
    <link rel="stylesheet" href="/assets/css/civicone-layout-overrides.css?v=<?= $cssVersion ?>">

    <!-- Error Trap: Catch any JavaScript errors before page reload -->
    <script>
        (function() {
            // Safety wrapper for toLowerCase to prevent undefined errors
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
                var errorMsg = 'ERROR TRAPPED!\n\nMessage: ' + msg + '\nURL: ' + url + '\nLine: ' + lineNo;
                try {
                    localStorage.setItem('LAST_JS_ERROR', errorMsg);
                    localStorage.setItem('LAST_JS_ERROR_TIME', new Date().toISOString());
                } catch (e) {}
                console.error('TRAPPED ERROR:', errorMsg);
                return true;
            };

            // Promise rejection handler
            window.addEventListener('unhandledrejection', function(event) {
                var errorMsg = 'PROMISE REJECTION: ' + (event.reason ? event.reason.message || event.reason : 'Unknown');
                try {
                    localStorage.setItem('LAST_PROMISE_ERROR', errorMsg);
                } catch (e) {}
                console.error('TRAPPED PROMISE REJECTION:', event.reason);
                event.preventDefault();
            });
        })();
    </script>

    <!-- Offline banner fix: Only show after verifying truly offline -->
    <script>
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
                }, 2000);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', verifyOffline);
            } else {
                verifyOffline();
            }
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

</head>
