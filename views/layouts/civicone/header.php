<?php
// CivicOne Layout Header - Government/Public Sector Theme

// ============================================
// NO-CACHE HEADERS FOR THEME SWITCHING
// Prevents browser from serving stale cached pages when theme changes
// ============================================
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../onboarding_check.php';
// Load unified navigation configuration
require_once __DIR__ . '/config/navigation.php';

// Theme mode support
$mode = $_COOKIE['nexus_mode'] ?? 'light'; // CivicOne defaults to light for accessibility
$hTitle = $hero_title ?? $pageTitle ?? 'CivicOne';
$hSubtitle = $hero_subtitle ?? $pageSubtitle ?? 'Public Sector Platform';
$hGradient = $hero_gradient ?? 'civic-hero-gradient';
$hType = $hero_type ?? 'Government';

// --- STRICT HOME DETECTION ---
$isHome = false;
$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($reqUri, PHP_URL_PATH);
$normPath = rtrim($parsedPath, '/');
$normBase = '';
if (class_exists('\Nexus\Core\TenantContext')) {
    $normBase = rtrim(\Nexus\Core\TenantContext::getBasePath(), '/');
    if ($normPath === '' || $normPath === '/' || $normPath === '/home' || $normPath === $normBase || $normPath === $normBase . '/home') {
        $isHome = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $mode ?>" data-layout="civicone">

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
    <?php include __DIR__ . '/critical-css.php'; ?>

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
    $deploymentVersion = file_exists(__DIR__ . '/../../config/deployment-version.php')
        ? require __DIR__ . '/../../config/deployment-version.php'
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
    <?php endif; ?>
    <?php if (strpos($normPath, '/dashboard') !== false): ?>
        <link rel="stylesheet" href="/assets/css/dashboard.min.css?v=<?= $cssVersion ?>">
    <?php endif; ?>

    <!-- Mobile Sheets CSS (base styles always load, CSS handles desktop hiding) -->
    <link rel="stylesheet" href="/assets/css/mobile-sheets.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/pwa-install-modal.css?v=<?= $cssVersion ?>">

    <!-- Social Interactions CSS -->
    <link rel="stylesheet" href="/assets/css/social-interactions.min.css?v=<?= $cssVersion ?>">

    <!-- Emergency Scroll Fix - MUST be last to override all other styles -->
    <link rel="stylesheet" href="/assets/css/scroll-fix-emergency.min.css?v=<?= $cssVersion ?>">
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

    <!-- Deep Style Sync: Gov Colors Overrides -->
    <style>
        :root {
            --civic-gov-blue: #002d72;
            --civic-hse-green: #007b5f;
            --civic-primary: var(--civic-gov-blue);
            /* Default to Gov Blue for Demo */
            --civic-accent: var(--civic-hse-green);
        }

        /* Refine Buttons */
        .civic-btn {
            border-radius: 4px !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .civic-btn-primary {
            background-color: var(--civic-gov-blue) !important;
            border-color: var(--civic-gov-blue) !important;
        }

        .civic-btn-accent {
            background-color: var(--civic-hse-green) !important;
            border-color: var(--civic-hse-green) !important;
        }

        /* Nav Active State */
        .civic-nav-link.active {
            border-bottom: 2px solid var(--civic-gov-blue);
            color: var(--civic-gov-blue) !important;
            font-weight: 700;
        }

        /* .civic-logo font override */
        .civic-logo {
            font-family: 'Roboto', sans-serif;
            letter-spacing: -0.5px;
        }

        /* --- LAYOUT STABILITY LOCK --- */
        html[data-layout-stable] * {
            transition-duration: 0ms !important;
            animation-duration: 0ms !important;
        }

        /* --- OFFLINE BANNER FIX --- */
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

<body class="nexus-skin-civicone <?= $skinClass ?> <?= $isHome ? 'nexus-home-page' : '' ?> <?= isset($_SESSION['user_id']) ? 'logged-in' : '' ?> <?= ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])) ? 'user-is-admin' : '' ?> <?= $bodyClass ?? '' ?>">

    <!-- Layout switch link moved to utility bar - cleaner approach -->

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

    <!-- Wrong Turn Popup - REMOVED: Banner at top is sufficient -->
    <!-- The purple "Accessible Layout (Beta)" banner already provides switch-back functionality -->
    <?php


    // Path Resolution
    $basePath = '/';
    if (class_exists('\Nexus\Core\TenantContext')) {
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        if (empty($basePath)) $basePath = '/';
    }
    $basePath = rtrim($basePath, '/') . '/';
    ?>
    <!-- Font loading moved to head with preload -->

    <!-- Layout Switch Helper (prevents visual glitches) -->
    <script defer src="/assets/js/layout-switch-helper.min.js?v=<?= $cssVersion ?>"></script>

    <script>
        const NEXUS_BASE = "<?= \Nexus\Core\TenantContext::getBasePath() ?>";
        const mtBasePath = NEXUS_BASE; // Compatibility alias
    </script>

    <!-- CivicOne Header CSS (Extracted per CLAUDE.md) -->
    <link rel="stylesheet" href="/assets/css/civicone-header.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Events CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-events.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Profile CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-profile.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Groups CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-groups.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Volunteering CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-volunteering.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Mini Modules CSS (Polls, Goals, Resources - WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-mini-modules.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Messages & Notifications CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-messages.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Wallet & Insights CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-wallet.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Blog CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-blog.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Help & Settings CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-help.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Matches & Connections CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-matches.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Federation CSS (WCAG 2.1 AA 2026-01-20) -->
    <link rel="stylesheet" href="/assets/css/civicone-federation.css?v=<?= $cssVersion ?>">

    <!-- Skip Link for Accessibility (WCAG 2.4.1) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Accessible Layout Notice Banner - Premium Design -->
    <div class="civic-experimental-banner" role="status" aria-live="polite">
        <div class="civic-container civic-banner-content">
            <div class="civic-banner-content">
                <span class="civic-experimental-badge">
                    <i class="fa-solid fa-universal-access" aria-hidden="true"></i>
                    ACCESSIBLE Experimental in Development
                </span>
                <span class="civic-experimental-text">
                    <strong>WCAG 2.1 AA Compliant</strong> — High contrast, keyboard-friendly design
                </span>
            </div>
            <a href="?layout=modern" class="civic-experimental-switch">
                <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
                Switch to Modern
            </a>
        </div>
    </div>

    <!-- 1. Utility Bar (Top Row) - WCAG 2.1 AA Compliant -->
    <nav class="civic-utility-bar" aria-label="Utility navigation">
        <div class="civic-container civic-utility-wrapper">

            <!-- Platform Dropdown - Public to everyone -->
            <?php
            $showPlatform = true; // Made public to everyone
            if ($showPlatform):
            ?>
                <div class="civic-dropdown civic-dropdown--left">
                    <button class="civic-utility-link civic-utility-btn civic-utility-btn--uppercase" aria-haspopup="menu" aria-expanded="false" aria-controls="platform-dropdown-menu">
                        Platform <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="platform-dropdown-menu" role="menu">
                        <?php
                        $tenants = [];
                        try {
                            $tenants = \Nexus\Models\Tenant::all() ?? [];
                        } catch (\Exception $e) {
                            $tenants = [];
                        }
                        foreach ($tenants as $pt):
                            if (!empty($pt['domain'])) {
                                $link = 'https://' . $pt['domain'];
                            } else {
                                $link = '/' . ($pt['slug'] ?? '');
                                if (($pt['id'] ?? 0) == 1) $link = '/';
                            }
                        ?>
                            <a href="<?= htmlspecialchars($link) ?>" role="menuitem"><?= htmlspecialchars($pt['name'] ?? 'Unknown') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <style>
                /* Force Dropdown Hover Logic */
                .civic-dropdown:hover .civic-dropdown-content {
                    display: block;
                    animation: fadeIn 0.2s;
                }
            </style>

            <!-- Dark Mode Toggle -->
            <button id="civic-theme-toggle" class="civic-utility-link civic-utility-btn" aria-label="Toggle High Contrast">
                <span class="icon">◑</span> Contrast
            </button>

            <!-- Theme Switcher - Visible for everyone on desktop, admins only on mobile -->
            <?php
            // VISIBILITY LOGIC: Hide on 'public-sector-demo' tenant only
            $currentSlug = '';
            if (class_exists('\Nexus\Core\TenantContext')) {
                $currentSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
            }
            if ($currentSlug !== 'public-sector-demo'):
            ?>
                <div class="civic-dropdown civic-dropdown--right civic-interface-switcher">
                    <button class="civic-utility-link civic-utility-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="interface-dropdown-menu">
                        Layout <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="interface-dropdown-menu" role="menu">
                        <?php
                        // Use LayoutHelper for consistent layout detection
                        $lay = \Nexus\Services\LayoutHelper::get();
                        ?>
                        <a href="?layout=modern" role="menuitem" <?= $lay === 'modern' ? 'aria-current="true"' : '' ?> style="<?= $lay === 'modern' ? 'font-weight:bold; color:var(--civic-brand, #00796B); background: rgba(0, 121, 107, 0.1);' : '' ?>">
                            <span style="display:inline-block; margin-right:8px;">✨</span> Modern UI
                            <?php if ($lay === 'modern'): ?>
                                <span style="float:right; color: var(--civic-brand, #00796B);">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="?layout=civicone" role="menuitem" <?= $lay === 'civicone' ? 'aria-current="true"' : '' ?> style="<?= $lay === 'civicone' ? 'font-weight:bold; color:var(--civic-brand, #00796B); background: rgba(0, 121, 107, 0.1);' : '' ?>">
                            <span style="display:inline-block; margin-right:8px;">♿</span> Accessible UI
                            <?php if ($lay === 'civicone'): ?>
                                <span style="float:right; color: var(--civic-brand, #00796B);">✓</span>
                            <?php endif; ?>
                        </a>

                        <?php
                        // Nexus Social: Available on Master (ID 1) and Hour Timebank
                        // Open to ALL users (Guest or Logged In)
                        $tId = \Nexus\Core\TenantContext::getId();
                        $isAllowedSocial = ($tId == 1) || ($currentSlug === 'hour-timebank' || $currentSlug === 'hour_timebank');

                        if ($isAllowedSocial):
                            $rootPath = \Nexus\Core\TenantContext::getBasePath();
                            if (empty($rootPath)) $rootPath = '/';

                        ?>
                            <div style="border-top:1px solid #e5e7eb; margin:5px 0;" role="separator"></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>


            <!-- Auth / User Links -->
            <?php if (isset($_SESSION['user_id'])): ?>

                <!-- Create Dropdown - SYNCHRONIZED WITH MODERN (uses /compose?tab= URLs) -->
                <div class="civic-dropdown civic-dropdown--right">
                    <button class="civic-utility-link civic-utility-btn civic-utility-btn--create" aria-haspopup="menu" aria-expanded="false" aria-controls="utility-create-dropdown-menu">
                        + Create <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="utility-create-dropdown-menu" role="menu">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=post" role="menuitem">📝 New Post</a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=listing" role="menuitem">🎁 New Listing</a>
                        <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=event" role="menuitem">📅 New Event</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=volunteer" role="menuitem">🤝 Volunteer Opp</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('polls')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=poll" role="menuitem">📊 New Poll</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('goals')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=goal" role="menuitem">🎯 New Goal</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // Federation Dropdown - SYNCHRONIZED WITH MODERN
                $hasFederationUtilBar = false;
                if (class_exists('\Nexus\Services\FederationFeatureService')) {
                    try {
                        $hasFederationUtilBar = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                    } catch (\Exception $e) {
                        $hasFederationUtilBar = false;
                    }
                }
                if ($hasFederationUtilBar): ?>
                    <div class="civic-dropdown civic-dropdown--right">
                        <button class="civic-utility-link civic-utility-btn civic-utility-btn--federation" aria-haspopup="menu" aria-expanded="false">
                            <i class="fa-solid fa-globe civic-menu-icon"></i>Partner Communities <span class="civic-arrow" aria-hidden="true">▾</span>
                        </button>
                        <div class="civic-dropdown-content" role="menu">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--purple"><i class="fa-solid fa-house"></i></span>Partner Communities Hub
                            </a>
                            <div class="civic-dropdown-separator" role="separator"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/members" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--purple"><i class="fa-solid fa-user-group"></i></span>Members
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/listings" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--pink"><i class="fa-solid fa-hand-holding-heart"></i></span>Listings
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/events" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--amber"><i class="fa-solid fa-calendar-days"></i></span>Events
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/groups" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--indigo"><i class="fa-solid fa-users"></i></span>Groups
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/messages" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--blue"><i class="fa-solid fa-envelope"></i></span>Messages
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/transactions" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--green"><i class="fa-solid fa-coins"></i></span>Transactions
                            </a>
                            <div class="civic-dropdown-separator" role="separator"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--gray"><i class="fa-solid fa-sliders"></i></span>Settings
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'newsletter_admin')): ?>
                    <!-- Newsletter Admin - Limited Access (Matches Modern) -->
                    <div class="civic-dropdown civic-dropdown--right">
                        <button class="civic-utility-link civic-utility-btn civic-utility-btn--newsletter" aria-haspopup="menu" aria-expanded="false">
                            Newsletter <span class="civic-arrow" aria-hidden="true">▾</span>
                        </button>
                        <div class="civic-dropdown-content" role="menu">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters" role="menuitem">All Newsletters</a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/create" role="menuitem">Create Newsletter</a>
                            <div class="civic-dropdown-separator" role="separator"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/subscribers" role="menuitem">Subscribers</a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/segments" role="menuitem">Segments</a>
                        </div>
                    </div>
                <?php elseif ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                    <!-- Admin Links (Matches Modern Header) -->
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin" class="civic-utility-link civic-utility-btn civic-utility-btn--admin">Admin</a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/group-ranking" class="civic-utility-link civic-utility-btn civic-utility-btn--ranking" title="Smart Group Ranking">
                        <i class="fa-solid fa-chart-line"></i> Ranking
                    </a>
                <?php endif; ?>

                <?php
                // Notification & Message counts (matches Modern header)
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

                <!-- Messages Icon (Matches Modern) -->
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/messages" class="civic-utility-link nexus-header-icon-btn badge-container" title="Messages">
                    <span class="dashicons dashicons-email" aria-hidden="true"></span>
                    <?php if ($msgUnread > 0): ?>
                        <span class="badge badge--danger badge--sm notification-badge"><?= $msgUnread > 99 ? '99+' : $msgUnread ?></span>
                    <?php endif; ?>
                </a>

                <!-- Notifications Bell (triggers drawer - Matches Modern) -->
                <button class="civic-utility-link nexus-header-icon-btn badge-container" title="Notifications" onclick="window.nexusNotifDrawer.open()" style="background:none; border:none; cursor:pointer;">
                    <span class="dashicons dashicons-bell" aria-hidden="true"></span>
                    <?php if ($nUnread > 0): ?>
                        <span id="nexus-bell-badge" class="badge badge--danger badge--sm notification-badge"><?= $nUnread > 99 ? '99+' : $nUnread ?></span>
                    <?php endif; ?>
                </button>

                <!-- User Avatar Dropdown (Premium - Matches Modern) -->
                <div class="civic-dropdown civic-dropdown--right civic-user-dropdown desktop-only-dd">
                    <button class="civic-utility-link civic-user-avatar-btn" aria-haspopup="menu" aria-expanded="false">
                        <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>" alt="Profile">
                        <span><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?></span>
                        <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" role="menu">
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $_SESSION['user_id'] ?>" role="menuitem">
                            <i class="fa-solid fa-user" style="margin-right: 10px; width: 16px; text-align: center; color: var(--civic-brand, #00796B);"></i>My Profile
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/dashboard" role="menuitem">
                            <i class="fa-solid fa-gauge" style="margin-right: 10px; width: 16px; text-align: center; color: #8b5cf6;"></i>Dashboard
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" role="menuitem">
                            <i class="fa-solid fa-wallet" style="margin-right: 10px; width: 16px; text-align: center; color: #10b981;"></i>Wallet
                        </a>
                        <div style="border-top: 1px solid #e5e7eb; margin: 8px 0;" role="separator"></div>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/logout" role="menuitem" style="color: #ef4444; font-weight: 600;">
                            <i class="fa-solid fa-right-from-bracket" style="margin-right: 10px; width: 16px; text-align: center;"></i>Sign Out
                        </a>
                    </div>
                </div>
                <!-- Mobile fallback links -->
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/dashboard" class="civic-utility-link mobile-only-link" style="font-weight:700; display:none;">Dashboard</a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/logout" class="civic-utility-link mobile-only-link" style="color:#dc2626; display:none;">Sign Out</a>
            <?php else: ?>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="civic-utility-link">Sign In</a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="civic-utility-link" style="font-weight:700;">Join Now</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Notifications Drawer (slides in from right - Matches Modern) -->
            <div id="notif-drawer-overlay" class="notif-drawer-overlay" onclick="window.nexusNotifDrawer.close()"></div>
            <aside id="notif-drawer" class="notif-drawer" role="dialog" aria-labelledby="notif-drawer-title" aria-modal="true">
                <div class="notif-drawer-header">
                    <span id="notif-drawer-title">NOTIFICATIONS</span>
                    <button class="notif-drawer-close" onclick="window.nexusNotifDrawer.close()" aria-label="Close notifications">
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
        <?php endif; ?>
    </nav><!-- End Utility Navigation -->

    <!-- 2. Main Header (Bottom Row) - WCAG 2.1 AA Landmark -->
    <header class="civic-header" role="banner">
        <div class="civic-container civic-header-wrapper">

            <!-- Logo -->
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?: '/' ?>" class="civic-logo" aria-label="<?= htmlspecialchars(\Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS') ?> - Go to homepage">
                <?php
                $civicName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
                if (\Nexus\Core\TenantContext::getId() == 1) {
                    $civicName = 'Project NEXUS';
                }
                echo htmlspecialchars($civicName);
                ?>
            </a>

            <!-- Desktop Navigation - ACCESSIBLE VERSION (2026-01-19) -->
            <!-- WCAG 2.1 AA: Core links visible + single hamburger Menu for all other navigation -->
            <nav id="civic-main-nav" class="civic-desktop-nav" aria-label="Main navigation">
                <?php
                $basePath = \Nexus\Core\TenantContext::getBasePath();
                $isLoggedIn = isset($_SESSION['user_id']);
                ?>

                <!-- Core Navigation Links - Always visible -->
                <a href="<?= $basePath ?>/" class="civic-nav-link" data-nav-match="/">Feed</a>
                <a href="<?= $basePath ?>/listings" class="civic-nav-link" data-nav-match="listings">Listings</a>
                <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                    <a href="<?= $basePath ?>/volunteering" class="civic-nav-link" data-nav-match="volunteering">Volunteering</a>
                <?php endif; ?>

                <?php
                // Database-driven pages (Page Builder)
                $dbPagesMain = \Nexus\Core\MenuGenerator::getMenuPages('main');
                foreach ($dbPagesMain as $mainPage):
                ?>
                    <a href="<?= htmlspecialchars($mainPage['url']) ?>" class="civic-nav-link"><?= htmlspecialchars($mainPage['title']) ?></a>
                <?php endforeach; ?>

                <!-- Single Menu Button - Opens combined mega menu -->
                <button id="civic-mega-menu-btn" class="civic-menu-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="civic-mega-menu">
                    Menu <span class="civic-arrow" aria-hidden="true">▾</span>
                </button>
            </nav>

            <!-- Combined Mega Menu - All links organized in columns -->
            <div id="civic-mega-menu" class="civic-mega-menu" role="dialog" aria-labelledby="civic-mega-menu-btn" aria-modal="false">
                <div class="civic-mega-grid">
                    <!-- Column 1: Community -->
                    <div class="civic-mega-col">
                        <h3>Community</h3>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <a href="<?= $basePath ?>/events">Events</a>
                        <?php endif; ?>
                        <a href="<?= $basePath ?>/members">Members</a>
                        <a href="<?= $basePath ?>/community-groups">Community Groups</a>
                        <a href="<?= $basePath ?>/groups">Local Hubs</a>
                    </div>

                    <!-- Column 2: Explore -->
                    <div class="civic-mega-col">
                        <h3>Explore</h3>
                        <?php if ($isLoggedIn): ?>
                        <a href="<?= $basePath ?>/compose">Create New</a>
                        <?php endif; ?>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('goals')): ?>
                        <a href="<?= $basePath ?>/goals">Goals</a>
                        <?php endif; ?>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('polls')): ?>
                        <a href="<?= $basePath ?>/polls">Polls</a>
                        <?php endif; ?>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('resources')): ?>
                        <a href="<?= $basePath ?>/resources">Resources</a>
                        <?php endif; ?>
                        <a href="<?= $basePath ?>/leaderboard">Leaderboards</a>
                        <a href="<?= $basePath ?>/achievements">Achievements</a>
                    </div>

                    <!-- Column 3: Tools & Features -->
                    <div class="civic-mega-col">
                        <h3>Tools</h3>
                        <?php if ($isLoggedIn): ?>
                        <a href="<?= $basePath ?>/nexus-score">My Nexus Score</a>
                        <?php endif; ?>
                        <a href="<?= $basePath ?>/matches">Smart Matching</a>
                        <a href="<?= $basePath ?>/ai">AI Assistant</a>
                        <a href="<?= $basePath ?>/mobile-download">Get Mobile App</a>
                    </div>

                    <!-- Column 4: About & Help -->
                    <div class="civic-mega-col">
                        <h3>About</h3>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('blog')): ?>
                        <a href="<?= $basePath ?>/news">Latest News</a>
                        <?php endif; ?>
                        <?php
                        // Custom file-based pages
                        $customPages = \Nexus\Core\TenantContext::getCustomPages('civicone');
                        if (empty($customPages)) {
                            $customPages = \Nexus\Core\TenantContext::getCustomPages('modern');
                        }
                        $excludedPages = ['about', 'privacy', 'terms', 'privacy policy', 'terms of service',
                            'terms and conditions', 'help', 'contact', 'contact us', 'accessibility',
                            'how it works', 'mobile download'];
                        foreach ($customPages as $page):
                            $pageName = strtolower($page['name']);
                            if (in_array($pageName, $excludedPages)) continue;
                        ?>
                        <a href="<?= htmlspecialchars($page['url']) ?>"><?= htmlspecialchars($page['name']) ?></a>
                        <?php endforeach; ?>
                        <a href="<?= $basePath ?>/help">Help Center</a>
                        <a href="<?= $basePath ?>/contact">Contact Us</a>
                        <a href="<?= $basePath ?>/accessibility">Accessibility</a>
                    </div>
                </div>
            </div>

            <!-- Desktop Search - Simple accessible design -->
            <div class="civic-search-container civic-desktop-search" role="search">
                <form action="<?= $basePath ?>/search" method="GET" class="civic-search-form">
                    <label for="civicSearchInput" class="visually-hidden">Search</label>
                    <input type="search" name="q" id="civicSearchInput" placeholder="Search..." aria-label="Search content" autocomplete="off">
                    <button type="submit" aria-label="Submit search">Search</button>
                </form>
            </div>

            <!-- Mobile Search Toggle -->
            <button id="civic-mobile-search-toggle" class="civic-mobile-search-btn" aria-label="Open search" aria-expanded="false">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
            </button>

            <!-- Mobile Menu Button -->
            <button id="civic-menu-toggle" aria-label="Open Menu" onclick="if(typeof openMobileMenu==='function'){openMobileMenu();}">
                <span class="civic-hamburger"></span>
            </button>
        </div>

        <!-- Mobile Search Bar (Expandable) -->
        <div id="civic-mobile-search-bar" class="civic-mobile-search-bar">
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/search" method="GET">
                <label for="mobile-search-input" class="visually-hidden">Search the site</label>
                <input type="text" id="mobile-search-input" name="q" placeholder="Search..." autocomplete="off">
                <button type="submit" aria-label="Submit search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                </button>
            </form>
        </div>
    </header>

        <!-- CivicOne Hero - MadeOpen Community Style -->
    <?php
    // Resolve variables (Contract)
    $heroTitle = $hTitle ?? $pageTitle ?? 'Project NEXUS';
    $heroSub = $hSubtitle ?? $pageSubtitle ?? '';
    $heroType = $hType ?? 'Platform';
    ?>
    <style>
        /* ===========================================
               HERO BANNER - Clean MadeOpen Style
               =========================================== */
        .civicone-hero-banner {
            background: var(--civic-brand);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
            position: relative;
        }

        /* Torfaen skin uses purple gradient */
        body.skin-torfaen .civicone-hero-banner {
            background: linear-gradient(135deg, #96206d 0%, #7a1a59 100%);
        }

        .civicone-hero-banner .civic-container {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            font-weight: 600;
            font-size: 12px;
            padding: 4px 10px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }

        .hero-title {
            color: white !important;
            margin-bottom: 8px;
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.125rem);
            opacity: 0.9;
            max-width: 600px;
            margin: 0;
            line-height: 1.5;
        }

        /* Dark mode hero */
        body.dark-mode .civicone-hero-banner {
            background: #1E3A8A;
        }

        @media (max-width: 768px) {
            .civicone-hero-banner {
                padding: 28px 0;
                margin-bottom: 28px;
            }
        }
    </style>
    <div class="civicone-hero-banner">
        <div class="civic-container">
            <span class="hero-badge">
                <?= htmlspecialchars($heroType) ?>
            </span>
            <h1 class="hero-title">
                <?= htmlspecialchars($heroTitle) ?>
            </h1>
            <?php if ($heroSub): ?>
                <p class="hero-subtitle">
                    <?= htmlspecialchars($heroSub) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admin Impersonation Banner -->
    <?php
    $impersonationBannerPath = __DIR__ . '/../../civicone/partials/impersonation-banner.php';
    if (!file_exists($impersonationBannerPath)) {
        $impersonationBannerPath = __DIR__ . '/../../modern/partials/impersonation-banner.php';
    }
    if (file_exists($impersonationBannerPath)) {
        require $impersonationBannerPath;
    }
    ?>

    <main id="main-content" class="civic-container">

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Theme Toggle Logic
                var themeToggle = document.getElementById('civic-theme-toggle');
                var body = document.body;

                // Function to apply theme
                function applyTheme(theme) {
                    if (theme === 'dark') {
                        body.classList.add('dark-mode');
                    } else {
                        body.classList.remove('dark-mode');
                    }
                }

                // Check saved preference
                var savedTheme = localStorage.getItem('civic-theme');
                if (savedTheme) {
                    applyTheme(savedTheme);
                }

                // Toggle theme function
                function toggleTheme() {
                    if (body.classList.contains('dark-mode')) {
                        applyTheme('light');
                        localStorage.setItem('civic-theme', 'light');
                    } else {
                        applyTheme('dark');
                        localStorage.setItem('civic-theme', 'dark');
                    }
                }

                if (themeToggle) {
                    themeToggle.addEventListener('click', toggleTheme);
                }

                // Drawer theme toggle (mobile menu)
                var drawerThemeToggle = document.getElementById('civic-drawer-theme-toggle');
                if (drawerThemeToggle) {
                    drawerThemeToggle.addEventListener('click', toggleTheme);
                }

                // ===========================================
                // CURRENT PAGE DETECTION - Highlight active nav
                // ===========================================
                (function() {
                    var currentPath = window.location.pathname;
                    var navLinks = document.querySelectorAll('.civic-nav-link[data-nav-match]');

                    navLinks.forEach(function(link) {
                        var matchPath = link.getAttribute('data-nav-match');

                        // Check for exact match or section match
                        var isActive = false;

                        if (matchPath === '/') {
                            // Home page - exact match only
                            isActive = (currentPath === '/' || currentPath === NEXUS_BASE + '/');
                        } else {
                            // Section match - starts with the path segment
                            var pathSegment = '/' + matchPath;
                            var fullPath = NEXUS_BASE + pathSegment;
                            isActive = currentPath === fullPath || currentPath.startsWith(fullPath + '/');
                        }

                        if (isActive) {
                            link.classList.add('active');
                            link.setAttribute('aria-current', 'page');
                        }
                    });
                })();

                // Mobile Menu Toggle - Now handled by mobile-nav-v2.php
                // The drawer script provides full accessibility support including:
                // - Close button handling
                // - Backdrop click to close
                // - Escape key to close
                // - Focus trapping
                // - Swipe gestures

                // ===========================================
                // MEGA MENU TOGGLE - WCAG 2.1 AA Compliant
                // ===========================================
                var megaMenuBtn = document.getElementById('civic-mega-menu-btn');
                var megaMenu = document.getElementById('civic-mega-menu');

                if (megaMenuBtn && megaMenu) {
                    // Toggle mega menu on click
                    megaMenuBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        if (isExpanded) {
                            closeMegaMenu();
                        } else {
                            openMegaMenu();
                        }
                    });

                    // Open mega menu
                    function openMegaMenu() {
                        megaMenuBtn.setAttribute('aria-expanded', 'true');
                        megaMenuBtn.classList.add('active');
                        megaMenu.classList.add('active');
                        megaMenu.style.display = 'block';

                        // Focus first link in mega menu
                        var firstLink = megaMenu.querySelector('a');
                        if (firstLink) {
                            setTimeout(function() { firstLink.focus(); }, 50);
                        }
                    }

                    // Close mega menu
                    function closeMegaMenu() {
                        megaMenuBtn.setAttribute('aria-expanded', 'false');
                        megaMenuBtn.classList.remove('active');
                        megaMenu.classList.remove('active');
                        megaMenu.style.display = 'none';
                    }

                    // Close on Escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && megaMenu.classList.contains('active')) {
                            closeMegaMenu();
                            megaMenuBtn.focus();
                        }
                    });

                    // Close when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!megaMenu.contains(e.target) && !megaMenuBtn.contains(e.target)) {
                            if (megaMenu.classList.contains('active')) {
                                closeMegaMenu();
                            }
                        }
                    });

                    // Keyboard navigation within mega menu
                    megaMenu.addEventListener('keydown', function(e) {
                        var links = megaMenu.querySelectorAll('a');
                        var currentIndex = Array.from(links).indexOf(document.activeElement);

                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            if (currentIndex < links.length - 1) {
                                links[currentIndex + 1].focus();
                            }
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            if (currentIndex > 0) {
                                links[currentIndex - 1].focus();
                            } else {
                                megaMenuBtn.focus();
                            }
                        } else if (e.key === 'Tab' && e.shiftKey && currentIndex === 0) {
                            // Shift+Tab on first link - close menu and focus button
                            e.preventDefault();
                            closeMegaMenu();
                            megaMenuBtn.focus();
                        }
                    });
                }

                // Mobile Search Toggle
                var mobileSearchToggle = document.getElementById('civic-mobile-search-toggle');
                var mobileSearchBar = document.getElementById('civic-mobile-search-bar');

                if (mobileSearchToggle && mobileSearchBar) {
                    mobileSearchToggle.addEventListener('click', function() {
                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        // Close mobile menu if open
                        if (typeof closeMobileMenu === 'function') {
                            closeMobileMenu();
                        }

                        if (isExpanded) {
                            mobileSearchBar.classList.remove('active');
                            this.setAttribute('aria-expanded', 'false');
                        } else {
                            mobileSearchBar.classList.add('active');
                            this.setAttribute('aria-expanded', 'true');
                            // Focus the search input
                            var searchInput = document.getElementById('mobile-search-input');
                            if (searchInput) searchInput.focus();
                        }
                    });
                }

                // ===========================================
                // Desktop Collapsible Search - Synced with Modern
                // ===========================================
                var searchToggleBtn = document.getElementById('civicSearchToggleBtn');
                var collapsibleSearch = document.getElementById('civicCollapsibleSearch');
                var searchInput = document.getElementById('civicSearchInput');
                var searchCloseBtn = document.getElementById('civicSearchCloseBtn');

                if (searchToggleBtn && collapsibleSearch) {
                    // Open search
                    searchToggleBtn.addEventListener('click', function() {
                        collapsibleSearch.classList.add('active');
                        searchToggleBtn.style.display = 'none';
                        searchToggleBtn.setAttribute('aria-expanded', 'true');
                        if (searchInput) searchInput.focus();
                    });

                    // Close search
                    if (searchCloseBtn) {
                        searchCloseBtn.addEventListener('click', function() {
                            collapsibleSearch.classList.remove('active');
                            searchToggleBtn.style.display = '';
                            searchToggleBtn.setAttribute('aria-expanded', 'false');
                            searchToggleBtn.focus();
                        });
                    }

                    // Close on Escape key
                    if (searchInput) {
                        searchInput.addEventListener('keydown', function(e) {
                            if (e.key === 'Escape') {
                                collapsibleSearch.classList.remove('active');
                                searchToggleBtn.style.display = '';
                                searchToggleBtn.setAttribute('aria-expanded', 'false');
                                searchToggleBtn.focus();
                            }
                        });
                    }
                }

                // ===========================================
                // WCAG 2.1 AA Compliant Dropdown Navigation
                // Supports: Click, Enter, Space, Escape, Arrow Keys
                // ===========================================

                var allDropdowns = document.querySelectorAll('.civic-dropdown button');

                // Helper: Close all dropdowns
                function closeAllDropdowns(exceptTrigger) {
                    allDropdowns.forEach(function(trigger) {
                        if (trigger !== exceptTrigger) {
                            trigger.setAttribute('aria-expanded', 'false');
                            var container = trigger.closest('.civic-dropdown');
                            var content = container ? container.querySelector('.civic-dropdown-content') : null;
                            if (content) content.style.display = 'none';
                        }
                    });
                }

                // Helper: Open dropdown
                function openDropdown(trigger, content) {
                    closeAllDropdowns(trigger);
                    content.style.display = 'block';
                    trigger.setAttribute('aria-expanded', 'true');

                    // Focus first menu item
                    var firstItem = content.querySelector('a, button');
                    if (firstItem) firstItem.focus();
                }

                // Helper: Close dropdown and return focus
                function closeDropdown(trigger, content) {
                    content.style.display = 'none';
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                }

                // Helper: Get focusable items in dropdown
                function getFocusableItems(content) {
                    return content.querySelectorAll('a:not([disabled]), button:not([disabled])');
                }

                allDropdowns.forEach(function(trigger) {
                    var container = trigger.closest('.civic-dropdown');
                    var content = container ? container.querySelector('.civic-dropdown-content') : null;

                    if (!content) return;

                    // Click handler
                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        if (isExpanded) {
                            closeDropdown(trigger, content);
                        } else {
                            openDropdown(trigger, content);
                        }
                    });

                    // Keyboard handler for trigger
                    trigger.addEventListener('keydown', function(e) {
                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        switch (e.key) {
                            case 'Enter':
                            case ' ':
                                e.preventDefault();
                                if (isExpanded) {
                                    closeDropdown(trigger, content);
                                } else {
                                    openDropdown(trigger, content);
                                }
                                break;
                            case 'Escape':
                                if (isExpanded) {
                                    e.preventDefault();
                                    closeDropdown(trigger, content);
                                }
                                break;
                            case 'ArrowDown':
                                e.preventDefault();
                                if (!isExpanded) {
                                    openDropdown(trigger, content);
                                } else {
                                    var items = getFocusableItems(content);
                                    if (items.length > 0) items[0].focus();
                                }
                                break;
                            case 'ArrowUp':
                                e.preventDefault();
                                if (isExpanded) {
                                    var items = getFocusableItems(content);
                                    if (items.length > 0) items[items.length - 1].focus();
                                }
                                break;
                        }
                    });

                    // Keyboard navigation within dropdown content
                    content.addEventListener('keydown', function(e) {
                        var items = getFocusableItems(content);
                        var currentIndex = Array.from(items).indexOf(document.activeElement);

                        switch (e.key) {
                            case 'Escape':
                                e.preventDefault();
                                closeDropdown(trigger, content);
                                break;
                            case 'ArrowDown':
                                e.preventDefault();
                                if (currentIndex < items.length - 1) {
                                    items[currentIndex + 1].focus();
                                } else {
                                    items[0].focus(); // Wrap to first
                                }
                                break;
                            case 'ArrowUp':
                                e.preventDefault();
                                if (currentIndex > 0) {
                                    items[currentIndex - 1].focus();
                                } else {
                                    items[items.length - 1].focus(); // Wrap to last
                                }
                                break;
                            case 'Home':
                                e.preventDefault();
                                items[0].focus();
                                break;
                            case 'End':
                                e.preventDefault();
                                items[items.length - 1].focus();
                                break;
                            case 'Tab':
                                // Allow Tab to close dropdown and move focus naturally
                                closeDropdown(trigger, content);
                                break;
                        }
                    });
                });

                // Close dropdowns when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.civic-dropdown')) {
                        closeAllDropdowns(null);
                    }
                });

                // Close dropdowns on Escape anywhere
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeAllDropdowns(null);
                    }
                });
            });
        </script>

        <!-- Notif Scripts (notifications.js now loaded in footer with Pusher) -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <script>
                // Fetch unread notification count for civicone header badge
                (function() {
                    function updateNotifBadge() {
                        fetch(NEXUS_BASE + '/api/notifications/unread-count')
                            .then(r => r.json())
                            .then(data => {
                                const badge = document.getElementById('civic-notif-badge');
                                if (badge && data.count > 0) {
                                    badge.textContent = data.count > 99 ? '99+' : data.count;
                                    badge.style.display = 'block';
                                } else if (badge) {
                                    badge.style.display = 'none';
                                }
                            })
                            .catch(() => {});
                    }
                    // Initial fetch
                    updateNotifBadge();
                    // Refresh every 60 seconds
                    setInterval(updateNotifBadge, 60000);
                })();
            </script>
        <?php endif; ?>