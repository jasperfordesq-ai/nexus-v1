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
// This ensures CSS is reloaded when themes change
$cssVersionTimestamp = time();

require_once __DIR__ . '/../onboarding_check.php';
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
    <!-- CRITICAL: Inline scroll fix - cannot be overridden -->
    <style id="scroll-fix-inline">
        html,
        html:root,
        html[data-theme],
        html[data-layout] {
            overflow-y: scroll !important;
            overflow-x: hidden !important;
        }

        body,
        body.drawer-open,
        body.modal-open,
        body.fds-sheet-open,
        body.keyboard-open,
        body.mobile-menu-open,
        body.menu-open,
        html body,
        html[data-theme] body,
        html[data-layout] body {
            overflow: visible !important;
            overflow-y: visible !important;
            overflow-x: hidden !important;
            position: relative !important;
        }
    </style>
    <meta name="csrf-token" content="<?= \Nexus\Core\Csrf::generate() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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

    <!-- Mobile-only CSS -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-mobile.min.css?v=<?= $cssVersionTimestamp ?>" media="(max-width: 768px)">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/mobile-sheets.min.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/pwa-install-modal.css?v=<?= $cssVersionTimestamp ?>">

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

    <!-- Feed Page CSS (for /feed route) -->
    <?php if (strpos($normPath, '/feed') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/feed-page.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Profile Edit CSS -->
    <?php if (strpos($normPath, '/profile/edit') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/profile-edit.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Messages CSS (conditional) -->
    <?php if (strpos($normPath, '/messages') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/messages-index.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php if (strpos($normPath, '/messages/thread') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/messages-thread.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- Notifications CSS -->
    <?php if (strpos($normPath, '/notifications') !== false): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/notifications.min.css?v=<?= $cssVersionTimestamp ?>">
    <?php endif; ?>

    <!-- Groups CSS -->
    <?php if (preg_match('/\/groups\/\d+$/', $normPath)): ?>
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
    ?>

    <style>
        /* --- LAYOUT STABILITY LOCK --- */
        /* Prevent JavaScript from causing layout shifts during page load */
        html[data-layout-stable] * {
            transition-duration: 0ms !important;
            animation-duration: 0ms !important;
        }

        /* --- OFFLINE BANNER FIX --- */
        /* Hide by default, only show after delay via JS */
        .offline-banner,
        #offlineBanner {
            display: none !important;
        }

        .offline-banner.verified-offline,
        #offlineBanner.verified-offline {
            display: flex !important;
        }

        /* Always hide on desktop (unreliable navigator.onLine) */
        @media (min-width: 769px) {

            .offline-banner,
            #offlineBanner,
            .offline-banner.verified-offline,
            #offlineBanner.verified-offline {
                display: none !important;
            }
        }
    </style>

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
    <script>
        function toggleNexusDrawer() {
            var d = document.querySelector('.nexus-native-drawer'),
                o = document.querySelector('.nexus-native-drawer-overlay');
            if (d) d.classList.toggle('active');
            if (o) o.classList.toggle('active');
        }

        function closeNexusDrawer() {
            var d = document.querySelector('.nexus-native-drawer'),
                o = document.querySelector('.nexus-native-drawer-overlay');
            if (d) d.classList.remove('active');
            if (o) o.classList.remove('active');
        }
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
            <style>
                .nexus-brand-link {
                    display: inline-flex !important;
                    align-items: baseline !important;
                    gap: 0.3em !important;
                    font-size: clamp(1.15rem, 2.5vw, 1.5rem) !important;
                    font-weight: 800 !important;
                    letter-spacing: -0.5px !important;
                    text-transform: uppercase !important;
                    text-decoration: none !important;
                    transition: all 0.25s ease !important;
                }

                /* Primary word - gradient text for modern look */
                .nexus-brand-link .brand-primary {
                    background: linear-gradient(135deg, #a5b4fc 0%, #818cf8 50%, #6366f1 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    font-weight: 900;
                }

                /* Secondary word - complementary warm tone */
                .nexus-brand-link .brand-secondary {
                    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    font-weight: 700;
                }

                .nexus-brand-link:hover {
                    transform: translateY(-1px);
                }

                .nexus-brand-link:hover .brand-primary {
                    background: linear-gradient(135deg, #c7d2fe 0%, #a5b4fc 50%, #818cf8 100%);
                    -webkit-background-clip: text;
                    background-clip: text;
                    filter: drop-shadow(0 2px 8px rgba(99, 102, 241, 0.4));
                }

                .nexus-brand-link:hover .brand-secondary {
                    background: linear-gradient(135deg, #fcd34d 0%, #fbbf24 100%);
                    -webkit-background-clip: text;
                    background-clip: text;
                    filter: drop-shadow(0 2px 8px rgba(251, 191, 36, 0.4));
                }

                /* Light mode - deeper colors for contrast */
                [data-theme="light"] .nexus-brand-link .brand-primary {
                    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                [data-theme="light"] .nexus-brand-link .brand-secondary {
                    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                [data-theme="light"] .nexus-brand-link:hover .brand-primary {
                    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
                    -webkit-background-clip: text;
                    background-clip: text;
                }

                [data-theme="light"] .nexus-brand-link:hover .brand-secondary {
                    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                    -webkit-background-clip: text;
                    background-clip: text;
                }
            </style>

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

                <!-- New Native Menu Button -->
                <button class="nexus-menu-btn" aria-label="Open Menu" onclick="if(window.innerWidth<=1024&&typeof openMobileMenu==='function'){openMobileMenu();}else{toggleNexusDrawer();}">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
            <style>
                .nexus-mobile-actions {
                    display: none;
                    align-items: center;
                    gap: 4px;
                }

                .nexus-notif-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 40px;
                    height: 40px;
                    border: none;
                    background: transparent;
                    color: #fff;
                    font-size: 1.1rem;
                    cursor: pointer;
                    border-radius: 50%;
                    position: relative;
                    transition: background-color 0.2s ease;
                }

                .nexus-notif-btn:hover,
                .nexus-notif-btn:focus {
                    background: rgba(255, 255, 255, 0.1);
                }

                .nexus-notif-badge {
                    position: absolute;
                    top: 2px;
                    right: 2px;
                    min-width: 16px;
                    height: 16px;
                    background: #ff3b30;
                    color: white;
                    font-size: 10px;
                    font-weight: 600;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 4px;
                }

                @media (max-width: 1024px) {
                    .nexus-mobile-actions {
                        display: flex;
                    }

                    /* Hide standalone menu button when wrapped */
                    .nexus-navbar>.nexus-menu-btn {
                        display: none !important;
                    }
                }

                /* Light mode mobile notification button */
                [data-theme="light"] .nexus-notif-btn {
                    color: #374151 !important;
                }

                [data-theme="light"] .nexus-notif-btn:hover,
                [data-theme="light"] .nexus-notif-btn:focus {
                    background: rgba(99, 102, 241, 0.1) !important;
                    color: #4f46e5 !important;
                }

                /* Light mode mobile menu button (hamburger) */
                [data-theme="light"] .nexus-menu-btn span {
                    background: #374151 !important;
                }
            </style>
        </header>
    <?php endif; ?>

    <!-- Admin Impersonation Banner -->
    <?php require __DIR__ . '/../../modern/partials/impersonation-banner.php'; ?>

    <div class="app-drawer" id="appDrawer">
        <div class="drawer-header">
            <?php if (isset($_SESSION['user_id'])):
                $mUser = \Nexus\Models\User::findById($_SESSION['user_id']);
                if ($mUser): ?>
                    <div style="display:flex; align-items:center; gap:15px;">
                        <img src="<?= $mUser['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp' ?>" style="width:50px; height:50px; border-radius:12px; object-fit:cover; border:2px solid white;" onerror="this.src='/assets/img/defaults/default_avatar.webp'">
                        <div>
                            <div style="font-size:1.1rem; font-weight:700; line-height:1.2;"><?= htmlspecialchars($mUser['name'] ?? '') ?></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $_SESSION['user_id'] ?>" style="color:rgba(255,255,255,0.9); font-size:0.85rem; text-decoration:none;">View Profile</a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div>
                    <h2 style="margin:0; font-size:1.5rem;">Hello!</h2>
                    <p style="margin:0; opacity:0.9;">Welcome to the community.</p>
                </div>
            <?php endif; ?>
            <button class="drawer-close" onclick="closeAppDrawer()" aria-label="Close Menu"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="drawer-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" style="background:#4f46e5; color:white; padding:12px; border-radius:10px; text-align:center; font-weight:700; text-decoration:none;">Log In</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" style="background:#f3f4f6; color:#111827; padding:12px; border-radius:10px; text-align:center; font-weight:700; text-decoration:none;">Sign Up</a>
                </div>
            <?php endif; ?>

            <div class="app-group-title">Menu</div>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/" class="app-item">
                <div class="app-icon icon-red"><span class="dashicons dashicons-format-status"></span></div>
                <div><span class="app-label">Feed</span></div>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="app-item">
                <div class="app-icon icon-blue"><span class="dashicons dashicons-list-view"></span></div>
                <div><span class="app-label">Offers & Requests</span></div>
            </a>
            <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="app-item">
                    <div class="app-icon icon-teal"><span class="dashicons dashicons-heart"></span></div>
                    <div><span class="app-label">Volunteering</span></div>
                </a>
            <?php endif; ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/community-groups" class="app-item">
                <div class="app-icon icon-indigo"><span class="dashicons dashicons-groups"></span></div>
                <div><span class="app-label">Groups</span></div>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="app-item">
                <div class="app-icon icon-purple"><span class="dashicons dashicons-location"></span></div>
                <div><span class="app-label">Local Hubs</span></div>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/members" class="app-item" data-turbo="false">
                <div class="app-icon icon-orange"><span class="dashicons dashicons-admin-users"></span></div>
                <div><span class="app-label">People</span></div>
            </a>
            <div class="app-group-title">Explore</div>
            <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events" class="app-item">
                    <div class="app-icon icon-green"><span class="dashicons dashicons-calendar-alt"></span></div>
                    <div><span class="app-label">Events</span></div>
                </a>
            <?php endif; ?>
            <?php if (Nexus\Core\TenantContext::hasFeature('resources')): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources" class="app-item">
                    <div class="app-icon icon-cyan"><span class="dashicons dashicons-book"></span></div>
                    <div><span class="app-label">Resources</span></div>
                </a>
            <?php endif; ?>
            <div class="app-group-title">Create</div>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=listing" class="app-item">
                <div class="app-icon icon-emerald"><span class="dashicons dashicons-plus"></span></div>
                <div><span class="app-label">New Listing</span></div>
            </a>
        </div>
        <div class="drawer-footer">
            <button onclick="toggleMode()" style="background:none; border:none; cursor:pointer; color:inherit; font-weight:600; display:flex; align-items:center; gap:6px;">
                <i class="fa-solid <?= $mode === 'dark' ? 'fa-moon' : 'fa-sun' ?> mode-toggle-icon" style="color: <?= $mode === 'dark' ? '#6366f1' : '#f59e0b' ?>;"></i>
                <span class="mode-toggle-text"><?= $mode === 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
            </button>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/logout" style="color:#dc2626; text-decoration:none; font-weight:600;">Sign Out</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================
         NEW NATIVE DRAWER (Gesture-Enabled)
         ================================ -->
    <div class="nexus-native-drawer-overlay" onclick="closeNexusDrawer()"></div>
    <div class="nexus-native-drawer" onclick="event.stopPropagation()">
        <div class="nexus-drawer-header">
            <?php if (isset($_SESSION['user_id'])):
                $mUser = \Nexus\Models\User::findById($_SESSION['user_id']);
                // Get notification count for drawer
                $drawerUnread = 0;
                try {
                    $drawerUnread = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
                } catch (Exception $e) {
                }
            ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $_SESSION['user_id'] ?>" class="nexus-drawer-header-content" style="text-decoration: none; color: inherit;">
                    <div class="nexus-drawer-avatar">
                        <?php if (!empty($mUser['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($mUser['avatar_url']) ?>" alt="Avatar" onerror="this.style.display='none';this.parentElement.innerHTML='<?= strtoupper(substr($mUser['name'] ?? 'U', 0, 1)) ?>'">
                        <?php else: ?>
                            <?= strtoupper(substr($mUser['name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="nexus-drawer-user-info">
                        <h3><?= htmlspecialchars($mUser['name'] ?? 'User') ?></h3>
                        <p>View Profile →</p>
                    </div>
                </a>
                <!-- Notification Bell in Drawer Header -->
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/notifications" class="nexus-drawer-notif-btn" style="position: relative; width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; margin-right: 8px; text-decoration: none; color: white;">
                    <i class="fa-solid fa-bell" style="font-size: 1.1rem;"></i>
                    <?php if ($drawerUnread > 0): ?>
                        <span style="position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; border-radius: 50%; min-width: 18px; height: 18px; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid rgba(79, 70, 229, 0.9);">
                            <?= $drawerUnread > 9 ? '9+' : $drawerUnread ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <div class="nexus-drawer-header-content">
                    <div class="nexus-drawer-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="nexus-drawer-user-info">
                        <h3>Welcome!</h3>
                        <p>Join our community</p>
                    </div>
                </div>
            <?php endif; ?>
            <button class="nexus-drawer-close" aria-label="Close Menu" onclick="closeNexusDrawer()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="nexus-drawer-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; padding: 0 4px;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="htb-btn htb-btn-primary" style="padding: 12px; font-size: 0.9rem;">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Log In
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="htb-btn htb-btn-secondary" style="padding: 12px; font-size: 0.9rem; color: #1f2937;">
                        <i class="fa-solid fa-user-plus"></i> Sign Up
                    </a>
                </div>
            <?php endif; ?>

            <!-- Main Navigation -->
            <div class="nexus-drawer-group">
                <h4 class="nexus-drawer-group-title">Navigation</h4>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-rose">
                        <i class="fa-solid fa-newspaper"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Feed</span>
                        <span class="nexus-drawer-item-desc">Latest updates</span>
                    </div>
                </a>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-blue">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Offers & Requests</span>
                        <span class="nexus-drawer-item-desc">Browse listings</span>
                    </div>
                </a>

                <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-teal">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Volunteering</span>
                            <span class="nexus-drawer-item-desc">Give back</span>
                        </div>
                    </a>
                <?php endif; ?>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/community-groups" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-purple">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Community Groups</span>
                        <span class="nexus-drawer-item-desc">Interest-based groups</span>
                    </div>
                </a>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-blue">
                        <i class="fa-solid fa-map-pin"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Local Hubs</span>
                        <span class="nexus-drawer-item-desc">Geographic communities</span>
                    </div>
                </a>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/members" class="nexus-drawer-item" data-turbo="false">
                    <div class="nexus-drawer-item-icon icon-orange">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">People</span>
                        <span class="nexus-drawer-item-desc">Meet members</span>
                    </div>
                </a>
            </div>

            <!-- Explore Section -->
            <div class="nexus-drawer-group">
                <h4 class="nexus-drawer-group-title">Explore</h4>

                <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-green">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Events</span>
                            <span class="nexus-drawer-item-desc">Upcoming activities</span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (Nexus\Core\TenantContext::hasFeature('resources')): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-cyan">
                            <i class="fa-solid fa-book-open"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Resources</span>
                            <span class="nexus-drawer-item-desc">Helpful materials</span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (Nexus\Core\TenantContext::hasFeature('blog')): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/news" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-amber">
                            <i class="fa-solid fa-rss"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">News</span>
                            <span class="nexus-drawer-item-desc">Community updates</span>
                        </div>
                    </a>
                <?php endif; ?>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/leaderboard" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-amber">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Leaderboards</span>
                        <span class="nexus-drawer-item-desc">Top contributors</span>
                    </div>
                </a>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/achievements" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-purple">
                        <i class="fa-solid fa-medal"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Achievements</span>
                        <span class="nexus-drawer-item-desc">Badges & progress</span>
                    </div>
                </a>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/ai" class="nexus-drawer-item" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.08));">
                    <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white;">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">AI Assistant</span>
                        <span class="nexus-drawer-item-desc">Chat with AI</span>
                    </div>
                </a>

                <?php if (Nexus\Core\TenantContext::hasFeature('goals')): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-amber">
                            <i class="fa-solid fa-bullseye"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Goals</span>
                            <span class="nexus-drawer-item-desc">Set & track goals</span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (Nexus\Core\TenantContext::hasFeature('polls')): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-cyan">
                            <i class="fa-solid fa-square-poll-vertical"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Polls</span>
                            <span class="nexus-drawer-item-desc">Vote & share opinions</span>
                        </div>
                    </a>
                <?php endif; ?>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/matches" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-pink">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Smart Matching</span>
                        <span class="nexus-drawer-item-desc">AI-powered connections</span>
                    </div>
                </a>
            </div>

            <!-- About Section -->
            <div class="nexus-drawer-group">
                <h4 class="nexus-drawer-group-title">About</h4>

                <?php
                // Load custom pages for About section
                $mobileCustomPages = \Nexus\Core\TenantContext::getCustomPages('modern');
                $mobileAboutPages = ['about us', 'our story', 'about story', 'timebanking guide', 'partner with us', 'partner', 'social prescribing', 'timebanking faqs', 'timebanking faq', 'faq'];
                $mobileImpactPages = ['impact summary', 'impact report', 'strategic plan'];

                $mobilePageIcons = [
                    'about us' => ['icon' => 'fa-solid fa-heart', 'class' => 'icon-pink'],
                    'our story' => ['icon' => 'fa-solid fa-heart', 'class' => 'icon-pink'],
                    'about story' => ['icon' => 'fa-solid fa-heart', 'class' => 'icon-pink'],
                    'timebanking guide' => ['icon' => 'fa-solid fa-book-open', 'class' => 'icon-purple'],
                    'partner' => ['icon' => 'fa-solid fa-handshake', 'class' => 'icon-amber'],
                    'partner with us' => ['icon' => 'fa-solid fa-handshake', 'class' => 'icon-amber'],
                    'social prescribing' => ['icon' => 'fa-solid fa-hand-holding-medical', 'class' => 'icon-teal'],
                    'faq' => ['icon' => 'fa-solid fa-circle-question', 'class' => 'icon-cyan'],
                    'timebanking faq' => ['icon' => 'fa-solid fa-circle-question', 'class' => 'icon-cyan'],
                    'timebanking faqs' => ['icon' => 'fa-solid fa-circle-question', 'class' => 'icon-cyan'],
                    'impact summary' => ['icon' => 'fa-solid fa-leaf', 'class' => 'icon-emerald'],
                    'impact report' => ['icon' => 'fa-solid fa-file-contract', 'class' => 'icon-blue'],
                    'strategic plan' => ['icon' => 'fa-solid fa-route', 'class' => 'icon-purple'],
                ];

                foreach ($mobileCustomPages as $page):
                    $pageName = strtolower($page['name']);
                    if (!in_array($pageName, $mobileAboutPages)) continue;
                    $iconData = $mobilePageIcons[$pageName] ?? ['icon' => 'fa-solid fa-file-lines', 'class' => 'icon-slate'];
                ?>
                    <a href="<?= htmlspecialchars($page['url']) ?>" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon <?= $iconData['class'] ?>">
                            <i class="<?= $iconData['icon'] ?>"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label"><?= htmlspecialchars($page['name']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>

                <?php
                // Impact pages
                $hasImpact = false;
                foreach ($mobileCustomPages as $page):
                    $pageName = strtolower($page['name']);
                    if (!in_array($pageName, $mobileImpactPages)) continue;
                    $hasImpact = true;
                    $iconData = $mobilePageIcons[$pageName] ?? ['icon' => 'fa-solid fa-file-lines', 'class' => 'icon-slate'];
                ?>
                    <a href="<?= htmlspecialchars($page['url']) ?>" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon <?= $iconData['class'] ?>">
                            <i class="<?= $iconData['icon'] ?>"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label"><?= htmlspecialchars($page['name']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Help & Support Section -->
            <div class="nexus-drawer-group">
                <h4 class="nexus-drawer-group-title">Help & Support</h4>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/help" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-orange">
                        <i class="fa-solid fa-circle-question"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Help Center</span>
                        <span class="nexus-drawer-item-desc">Guides & support</span>
                    </div>
                </a>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/contact" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-blue">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Contact Us</span>
                        <span class="nexus-drawer-item-desc">Get in touch</span>
                    </div>
                </a>

                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/accessibility" class="nexus-drawer-item">
                    <div class="nexus-drawer-item-icon icon-emerald">
                        <i class="fa-solid fa-universal-access"></i>
                    </div>
                    <div class="nexus-drawer-item-text">
                        <span class="nexus-drawer-item-label">Accessibility</span>
                        <span class="nexus-drawer-item-desc">Our commitment</span>
                    </div>
                </a>
            </div>

            <?php
            $hasFederationDrawer = false;
            if (isset($_SESSION['user_id']) && class_exists('\Nexus\Services\FederationFeatureService')) {
                try {
                    $hasFederationDrawer = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                } catch (\Exception $e) {
                    $hasFederationDrawer = false;
                }
            }
            if ($hasFederationDrawer): ?>
                <!-- Federation Section -->
                <div class="nexus-drawer-group">
                    <h4 class="nexus-drawer-group-title" style="color: #8b5cf6;">
                        <i class="fa-solid fa-globe" style="margin-right: 6px;"></i>Partner Timebanks
                    </h4>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation" class="nexus-drawer-item" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.08)); border-radius: 12px; margin-bottom: 8px;">
                        <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa); color: white;">
                            <i class="fa-solid fa-house"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label" style="font-weight: 700;">Partner Timebanks Hub</span>
                            <span class="nexus-drawer-item-desc">Explore the federation network</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/members" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Members</span>
                            <span class="nexus-drawer-item-desc">Partner timebank members</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/listings" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Listings</span>
                            <span class="nexus-drawer-item-desc">Partner services</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/events" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Events</span>
                            <span class="nexus-drawer-item-desc">Partner events</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/groups" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Groups</span>
                            <span class="nexus-drawer-item-desc">Partner groups</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/messages" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Messages</span>
                            <span class="nexus-drawer-item-desc">Cross-timebank messages</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/transactions" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Transactions</span>
                            <span class="nexus-drawer-item-desc">Exchange hours</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon" style="background: rgba(107, 114, 128, 0.15); color: #6b7280;">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Settings</span>
                            <span class="nexus-drawer-item-desc">Manage preferences</span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Account Section -->
                <div class="nexus-drawer-group">
                    <h4 class="nexus-drawer-group-title">Account</h4>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $_SESSION['user_id'] ?>" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-indigo">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">My Profile</span>
                            <span class="nexus-drawer-item-desc">View & edit</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-emerald">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Wallet</span>
                            <span class="nexus-drawer-item-desc">Time credits</span>
                        </div>
                    </a>

                    <?php if (\Nexus\Core\TenantContext::hasFeature('timebanking')): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet/insights" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">My Insights</span>
                                <span class="nexus-drawer-item-desc">Analytics & trends</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-sky">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Messages</span>
                            <span class="nexus-drawer-item-desc">Conversations</span>
                        </div>
                        <?php
                        // Show unread badge if any
                        $unreadMsgs = 0;
                        try {
                            if (class_exists('Nexus\Models\MessageThread')) {
                                $threads = Nexus\Models\MessageThread::getForUser($_SESSION['user_id']);
                                foreach ($threads as $thread) {
                                    if (!empty($thread['unread_count'])) {
                                        $unreadMsgs += (int)$thread['unread_count'];
                                    }
                                }
                            }
                        } catch (Exception $e) {
                        }
                        if ($unreadMsgs > 0): ?>
                            <span class="nexus-drawer-item-badge"><?= $unreadMsgs > 9 ? '9+' : $unreadMsgs ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/notifications" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-rose">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Notifications</span>
                            <span class="nexus-drawer-item-desc">Activity alerts</span>
                        </div>
                        <?php if (isset($drawerUnread) && $drawerUnread > 0): ?>
                            <span class="nexus-drawer-item-badge"><?= $drawerUnread > 9 ? '9+' : $drawerUnread ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/dashboard" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-slate">
                            <i class="fa-solid fa-gauge-high"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">Dashboard</span>
                            <span class="nexus-drawer-item-desc">Your overview</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/nexus-score" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-emerald">
                            <i class="fa-solid fa-trophy"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">My Nexus Score</span>
                            <span class="nexus-drawer-item-desc">Track your impact</span>
                        </div>
                    </a>
                </div>

                <!-- Quick Actions -->
                <div class="nexus-drawer-group">
                    <h4 class="nexus-drawer-group-title">Quick Create</h4>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=listing&type=offer" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-lime">
                            <i class="fa-solid fa-plus"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">New Offer</span>
                            <span class="nexus-drawer-item-desc">Share your skills</span>
                        </div>
                    </a>

                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=listing&type=request" class="nexus-drawer-item">
                        <div class="nexus-drawer-item-icon icon-pink">
                            <i class="fa-solid fa-hand"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">New Request</span>
                            <span class="nexus-drawer-item-desc">Ask for help</span>
                        </div>
                    </a>

                    <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=event" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon icon-green">
                                <i class="fa-solid fa-calendar-plus"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">New Event</span>
                                <span class="nexus-drawer-item-desc">Create an event</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?php if (Nexus\Core\TenantContext::hasFeature('volunteering') && (empty($_SESSION['user_role']) || $_SESSION['user_role'] === 'admin' || !empty($_SESSION['is_org_admin']))): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=volunteer" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon icon-teal">
                                <i class="fa-solid fa-hands-helping"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Volunteer Opp</span>
                                <span class="nexus-drawer-item-desc">Post opportunity</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                    <!-- Admin Section -->
                    <div class="nexus-drawer-group">
                        <h4 class="nexus-drawer-group-title" style="color: #ea580c;">Admin Tools</h4>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #ea580c, #f97316);">
                                <i class="fa-solid fa-gauge-high"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Admin Dashboard</span>
                                <span class="nexus-drawer-item-desc">Site overview</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/nexus-score/analytics" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Score Analytics</span>
                                <span class="nexus-drawer-item-desc">Community scores</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/users" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
                                <i class="fa-solid fa-users-cog"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Manage Users</span>
                                <span class="nexus-drawer-item-desc">User administration</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/categories" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                                <i class="fa-solid fa-tags"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Categories</span>
                                <span class="nexus-drawer-item-desc">Manage categories</span>
                            </div>
                        </a>

                        <?php if (\Nexus\Core\TenantContext::hasFeature('timebanking')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/timebanking" class="nexus-drawer-item">
                                <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #14b8a6, #06b6d4);">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </div>
                                <div class="nexus-drawer-item-text">
                                    <span class="nexus-drawer-item-label">Timebanking</span>
                                    <span class="nexus-drawer-item-desc">Analytics & alerts</span>
                                </div>
                            </a>
                        <?php endif; ?>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/pages" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #0284c7, #0ea5e9);">
                                <i class="fa-solid fa-file-alt"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Pages</span>
                                <span class="nexus-drawer-item-desc">Content management</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/group-ranking" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #db2777, #ec4899);">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Group Ranking</span>
                                <span class="nexus-drawer-item-desc">Smart featured groups</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #059669, #10b981);">
                                <i class="fa-solid fa-envelope-open-text"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Newsletters</span>
                                <span class="nexus-drawer-item-desc">Email campaigns</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/subscribers" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Subscribers</span>
                                <span class="nexus-drawer-item-desc">Newsletter mailing list</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/settings" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #475569, #64748b);">
                                <i class="fa-solid fa-cog"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Settings</span>
                                <span class="nexus-drawer-item-desc">Site configuration</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/gamification" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #a855f7, #c084fc);">
                                <i class="fa-solid fa-gamepad"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Gamification</span>
                                <span class="nexus-drawer-item-desc">Badges & XP management</span>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/cron-jobs" class="nexus-drawer-item">
                            <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="nexus-drawer-item-text">
                                <span class="nexus-drawer-item-label">Cron Jobs</span>
                                <span class="nexus-drawer-item-desc">Scheduled task manager</span>
                            </div>
                        </a>

                        <?php if (!empty($_SESSION['is_super_admin'])): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/super-admin" class="nexus-drawer-item">
                                <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #4f46e5, #6366f1);">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div class="nexus-drawer-item-text">
                                    <span class="nexus-drawer-item-label">Platform Admin</span>
                                    <span class="nexus-drawer-item-desc">Super admin panel</span>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Layout Switcher Section - AVAILABLE FOR ALL USERS -->
            <?php
            $tSlug = '';
            if (class_exists('\Nexus\Core\TenantContext')) {
                $tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
            }

            // Show layout switcher to all users (except forced layout tenants)
            $showLayoutSwitcher = isset($_SESSION['user_id']) && $tSlug !== 'public-sector-demo';

            if ($showLayoutSwitcher):
                // Use centralized layout detection
                $lay = layout();

                // Get available layouts with access information
                $availableLayouts = \Nexus\Services\LayoutValidator::getAvailableLayouts();
                $layoutsMap = [];
                foreach ($availableLayouts as $layoutInfo) {
                    $layoutsMap[$layoutInfo['slug']] = $layoutInfo;
                }
            ?>
                <div class="nexus-drawer-group">
                    <h4 class="nexus-drawer-group-title">Choose Layout</h4>

                    <!-- LEGENDARY: Visual Layout Settings Page Link -->
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/layouts" class="nexus-drawer-item" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); border: 2px solid rgba(99, 102, 241, 0.3);">
                        <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                            <i class="fa-solid fa-images"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label" style="font-weight: 700; color: #6366f1;">
                                Visual Layout Picker
                                <span style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-left: 6px;">New</span>
                            </span>
                            <span class="nexus-drawer-item-desc">See thumbnails & preview layouts</span>
                        </div>
                        <i class="fa-solid fa-arrow-right" style="color: #6366f1; margin-left: auto;"></i>
                    </a>

                    <!-- Stable Layout Section -->
                    <div style="padding: 12px 16px; background: rgba(16, 185, 129, 0.05); border-left: 3px solid #10b981; margin: 8px 0;">
                        <div style="font-size: 11px; font-weight: 600; color: #10b981; margin-bottom: 2px;">
                            ✓ STABLE & RECOMMENDED
                        </div>
                        <div style="font-size: 10px; color: #64748b;">
                            The Modern UI layout is production-ready
                        </div>
                    </div>

                    <?php
                    $modernAvailable = $layoutsMap['modern']['available'] ?? true;
                    $modernClass = !$modernAvailable ? 'layout-locked' : '';
                    ?>
                    <a href="?layout=modern"
                        class="nexus-drawer-item <?= $modernClass ?>"
                        <?= $lay === 'modern' ? 'style="background: rgba(99, 102, 241, 0.1); border: 2px solid rgba(99, 102, 241, 0.3);"' : '' ?>
                        <?= !$modernAvailable ? 'onclick="return false;"' : '' ?>>
                        <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #4f46e5, #6366f1);">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">
                                Modern UI
                                <span style="background: #10b981; color: white; font-size: 8px; font-weight: 700; padding: 2px 5px; border-radius: 3px; margin-left: 4px;">STABLE</span>
                                <?php if (!$modernAvailable): ?>
                                    <i class="fa-solid fa-lock" style="color: #ef4444; font-size: 12px; margin-left: 4px;"></i>
                                <?php endif; ?>
                            </span>
                            <span class="nexus-drawer-item-desc">
                                <?php if ($lay === 'modern'): ?>
                                    Currently active
                                <?php elseif (!$modernAvailable): ?>
                                    Upgrade required
                                <?php else: ?>
                                    Rich & interactive
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($lay === 'modern'): ?>
                            <i class="fa-solid fa-check" style="color: #4f46e5; margin-left: auto;"></i>
                        <?php elseif ($modernAvailable && $lay !== 'modern'): ?>
                            <a href="?preview_layout=modern"
                                onclick="event.stopPropagation();"
                                style="margin-left: auto; padding: 4px 8px; background: rgba(99, 102, 241, 0.1); border-radius: 6px; font-size: 11px; color: #6366f1; text-decoration: none; font-weight: 600; transition: all 0.2s;">
                                <i class="fa-solid fa-eye" style="font-size: 10px;"></i> Preview
                            </a>
                        <?php endif; ?>
                    </a>

                    <!-- Experimental Layouts Section -->
                    <div style="padding: 12px 16px; background: rgba(245, 158, 11, 0.05); border-left: 3px solid #f59e0b; margin: 12px 0 8px 0;">
                        <div style="font-size: 11px; font-weight: 600; color: #f59e0b; margin-bottom: 2px;">
                            ⚠️ EXPERIMENTAL LAYOUTS
                        </div>
                        <div style="font-size: 10px; color: #64748b;">
                            Under active development - may have bugs or incomplete features
                        </div>
                    </div>

                    <?php
                    $civiconeAvailable = $layoutsMap['civicone']['available'] ?? false;
                    $civiconeClass = !$civiconeAvailable ? 'layout-locked' : '';
                    ?>
                    <a href="?layout=civicone"
                        class="nexus-drawer-item <?= $civiconeClass ?>"
                        <?= $lay === 'civicone' ? 'style="background: rgba(5, 150, 105, 0.1);"' : 'style="opacity: 0.8;"' ?>
                        <?= !$civiconeAvailable ? 'onclick="return false;"' : '' ?>>
                        <div class="nexus-drawer-item-icon" style="background: linear-gradient(135deg, #059669, #10b981);">
                            <i class="fa-solid fa-universal-access"></i>
                        </div>
                        <div class="nexus-drawer-item-text">
                            <span class="nexus-drawer-item-label">
                                Accessible UI
                                <span style="background: #f59e0b; color: white; font-size: 8px; font-weight: 700; padding: 2px 5px; border-radius: 3px; margin-left: 4px;">BETA</span>
                                <?php if (!$civiconeAvailable): ?>
                                    <i class="fa-solid fa-lock" style="color: #ef4444; font-size: 12px; margin-left: 4px;"></i>
                                <?php endif; ?>
                            </span>
                            <span class="nexus-drawer-item-desc">
                                <?php if ($lay === 'civicone'): ?>
                                    Currently active
                                <?php elseif (!$civiconeAvailable): ?>
                                    Upgrade required
                                <?php else: ?>
                                    High contrast (in development)
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($lay === 'civicone'): ?>
                            <i class="fa-solid fa-check" style="color: #059669; margin-left: auto;"></i>
                        <?php elseif ($civiconeAvailable && $lay !== 'civicone'): ?>
                            <a href="?preview_layout=civicone"
                                onclick="event.stopPropagation();"
                                style="margin-left: auto; padding: 4px 8px; background: rgba(5, 150, 105, 0.1); border-radius: 6px; font-size: 11px; color: #059669; text-decoration: none; font-weight: 600; transition: all 0.2s;">
                                <i class="fa-solid fa-eye" style="font-size: 10px;"></i> Preview
                            </a>
                        <?php endif; ?>
                    </a>

                    <?php
                    // Show upgrade CTA if any layouts are locked
                    $socialAvailable = false; // Social layout not currently available
                    $hasLockedLayouts = !$modernAvailable || !$civiconeAvailable || !$socialAvailable;
                    if ($hasLockedLayouts):
                    ?>
                        <div style="padding: 12px 16px; margin-top: 8px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(168, 85, 247, 0.05)); border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.2);">
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 6px;">
                                <i class="fa-solid fa-sparkles" style="color: #6366f1;"></i>
                                Unlock more layouts
                            </div>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/plans"
                                style="display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;">
                                View Plans →
                            </a>
                        </div>
                    <?php endif; ?>

                </div>

                <style>
                    /* Layout locked styling */
                    .nexus-drawer-item.layout-locked {
                        opacity: 0.6;
                        cursor: not-allowed;
                        position: relative;
                    }

                    .nexus-drawer-item.layout-locked:hover {
                        background: rgba(239, 68, 68, 0.05) !important;
                    }

                    [data-theme="dark"] .nexus-drawer-item.layout-locked:hover {
                        background: rgba(239, 68, 68, 0.1) !important;
                    }
                </style>

            <?php endif; ?>

            <!-- New App Link removed - was causing users to get stuck in beta mobile view -->
        </div>

        <div class="nexus-drawer-footer">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/legal" class="nexus-drawer-footer-action">
                <i class="fa-solid fa-scale-balanced"></i>
                Legal & Info
            </a>

            <button class="nexus-drawer-footer-action mode-drawer-btn" onclick="toggleMode()">
                <span class="mode-drawer-icon <?= $mode === 'dark' ? 'dark-mode' : 'light-mode' ?>">
                    <i class="fa-solid <?= $mode === 'dark' ? 'fa-moon' : 'fa-sun' ?> mode-toggle-icon"></i>
                </span>
                <span class="mode-toggle-text"><?= $mode === 'dark' ? 'Switch to Light' : 'Switch to Dark' ?></span>
            </button>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/logout" class="nexus-drawer-footer-action danger">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Sign Out
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edge Swipe Indicator -->
    <div class="nexus-edge-indicator"></div>

    <!-- Main Content Area (for skip-link target) -->
    <main id="main-content" role="main">

        <!-- Modern Header Behavior (Extracted to external file) - defer to avoid render blocking -->
        <script src="/assets/js/modern-header-behavior.min.js?v=<?= time() ?>" defer></script>

        <!-- Hero section removed for cleaner mobile experience -->
        <!-- Notification scripts now loaded in footer.php with Pusher WebSocket support -->