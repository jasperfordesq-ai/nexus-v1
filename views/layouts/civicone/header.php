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

    <style>
        /* CSS Variables & Theming */
        :root {
            --civic-header-height: auto;
        }

        /* 1. Utility Bar Styles - WCAG 2.1 AA Compliant */
        .civic-utility-bar {
            background-color: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 0;
            font-size: 0.9rem;
            color: #1f2937;
            /* Darker for 7:1+ contrast on #f3f4f6 */
        }

        .civic-utility-wrapper {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 20px;
        }

        .civic-utility-link {
            color: inherit;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .civic-utility-link:hover {
            text-decoration: underline;
            color: #000;
        }

        /* 2. Main Header Styles - SINGLE AUTHORITATIVE DEFINITION */
        .civic-header {
            background-color: #ffffff;
            padding: 16px 0 84px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-bottom: 4px solid var(--civic-brand, #2563EB);
            position: relative;
            z-index: 50;
            margin-bottom: 0;
        }

        .civic-header-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Logo */
        .civic-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--civic-brand, #00796B);
            text-decoration: none;
            letter-spacing: -0.02em;
        }

        /* Navigation */
        #civic-main-nav {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .civic-nav-link {
            text-decoration: none;
            color: var(--civic-text-main, #111827);
            font-weight: 600;
            font-size: 1.05rem;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .civic-nav-link:hover {
            background-color: #f3f4f6;
            color: var(--civic-brand, #00796B);
        }

        /* Dropdowns */
        .civic-dropdown {
            position: relative;
            display: inline-block;
        }

        .civic-dropdown-trigger {
            background: none;
            border: none;
            font-family: inherit;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--civic-text-main, #111827);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
        }

        .civic-dropdown-trigger:hover {
            background-color: #f3f4f6;
            color: var(--civic-brand, #00796B);
        }

        .civic-dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: #fff;
            min-width: 220px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 0;
            z-index: 100;
            margin-top: 5px;
        }

        /* FIX: Bridge gap for hover stability */
        .civic-dropdown-content::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 0;
            width: 100%;
            height: 10px;
            background: transparent;
        }

        .civic-dropdown-content a {
            display: block;
            padding: 10px 16px;
            color: #374151;
            text-decoration: none;
            font-weight: 500;
        }

        .civic-dropdown-content a:hover {
            background-color: #f9fafb;
            color: var(--civic-brand, #00796B);
        }

        /* MEGA MENU STYLES */
        .civic-menu-btn {
            background: none;
            border: 1px solid #e5e7eb;
            color: #111827;
            font-weight: 700;
            cursor: pointer;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .civic-menu-btn:hover,
        .civic-menu-btn.active {
            background-color: var(--civic-brand, #00796B);
            color: white;
            border-color: var(--civic-brand, #00796B);
        }

        .civic-mega-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            padding: 40px 0;
            z-index: 900;
        }

        .civic-mega-menu.active {
            display: block;
        }

        .civic-mega-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .civic-mega-col h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #374151;
            /* Darkened for WCAG AA 4.5:1 contrast */
            margin-bottom: 20px;
            font-weight: 700;
        }

        .civic-mega-col a {
            display: block;
            text-decoration: none;
            color: #111827;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
            transition: color 0.2s;
        }

        .civic-mega-col a:hover {
            color: var(--civic-brand, #00796B);
            padding-left: 5px;
        }

        /* Dark Mode Mega Menu */
        body.dark-mode .civic-mega-menu {
            background: #1f2937;
            border-top-color: #374151;
        }

        body.dark-mode .civic-menu-btn {
            border-color: #374151;
            color: #e5e7eb;
        }

        body.dark-mode .civic-menu-btn:hover {
            border-color: var(--civic-brand);
            color: white;
        }

        body.dark-mode .civic-mega-col h3 {
            color: #9CA3AF;
        }

        body.dark-mode .civic-mega-col a {
            color: #e5e7eb;
        }

        body.dark-mode .civic-mega-col a:hover {
            color: var(--civic-brand);
        }

        /* Mobile Toggle */
        #civic-menu-toggle {
            display: none;
            background: none;
            border: 2px solid transparent;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
        }

        /* Mobile Search Button - Hidden on Desktop */
        .civic-mobile-search-btn {
            display: none;
        }

        .civic-mobile-search-bar {
            display: none;
        }

        .civic-hamburger {
            display: block;
            width: 24px;
            height: 2px;
            background: #111827;
            position: relative;
            transition: all 0.3s;
        }

        .civic-hamburger::before,
        .civic-hamburger::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 2px;
            background: #111827;
            left: 0;
            transition: all 0.3s;
        }

        .civic-hamburger::before {
            top: -8px;
        }

        .civic-hamburger::after {
            top: 8px;
        }

        /* Dark Mode Overrides */
        body.dark-mode .civic-utility-bar {
            background-color: #1f2937;
            border-bottom-color: #374151;
            color: #e5e7eb;
        }

        body.dark-mode .civic-header {
            background-color: #111827;
            border-bottom: 1px solid #374151;
        }

        body.dark-mode .civic-logo {
            color: #fff;
        }

        /* White logo in dark mode */
        body.dark-mode .civic-nav-link,
        body.dark-mode .civic-dropdown-trigger {
            color: #e5e7eb;
        }

        body.dark-mode .civic-nav-link:hover,
        body.dark-mode .civic-dropdown-trigger:hover {
            background-color: #374151;
            color: #fff;
        }

        body.dark-mode .civic-dropdown-content {
            background-color: #1f2937;
            border-color: #374151;
        }

        body.dark-mode .civic-dropdown-content a {
            color: #d1d5db;
        }

        body.dark-mode .civic-dropdown-content a:hover {
            background-color: #374151;
            color: #fff;
        }

        body.dark-mode .civic-hamburger,
        body.dark-mode .civic-hamburger::before,
        body.dark-mode .civic-hamburger::after {
            background: #fff;
        }


        /* Responsive Design */
        @media (max-width: 1024px) {
            .civic-nav-link {
                font-size: 0.95rem;
            }

            #civic-main-nav {
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            .civic-search-container {
                display: none;
            }

            /* Hide Utility Bar on mobile - links moved to drawer menu */
            .civic-utility-bar {
                display: none !important;
            }

            .civic-arrow {
                display: none;
            }

            .civic-utility-wrapper {
                justify-content: space-between;
            }

            /* Hide non-essential utility links on very small screens if needed, 
               but keeping them for now as per "crowded" fix */

            /* Hamburger hidden - mobile nav v2 has Menu in bottom tab bar */
            #civic-menu-toggle {
                display: none !important;
            }

            /* Mobile Search Button */
            .civic-mobile-search-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                background: none;
                border: none;
                padding: 8px;
                margin-right: 10px;
                cursor: pointer;
                color: var(--civic-text-main, #1F2937);
                min-width: 44px;
                min-height: 44px;
            }

            .civic-mobile-search-btn .dashicons {
                font-size: 24px;
                width: 24px;
                height: 24px;
            }

            /* Mobile Search Bar (expandable) */
            .civic-mobile-search-bar {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                padding: 15px 20px;
                border-top: 1px solid #e5e7eb;
                border-bottom: 1px solid #e5e7eb;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .civic-mobile-search-bar.active {
                display: block;
            }

            .civic-mobile-search-bar form {
                display: flex;
                gap: 10px;
            }

            .civic-mobile-search-bar input {
                flex: 1;
                padding: 12px 15px;
                border: 2px solid #e5e7eb;
                border-radius: 6px;
                font-size: 16px;
            }

            .civic-mobile-search-bar button {
                padding: 12px 20px;
                background: var(--civic-brand, #00796B);
                color: white;
                border: none;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                min-width: 44px;
            }

            body.dark-mode .civic-mobile-search-bar {
                background: #1F2937;
                border-color: #374151;
            }

            body.dark-mode .civic-mobile-search-bar input {
                background: #374151;
                border-color: #4B5563;
                color: #F3F4F6;
            }

            /* Mobile nav now handled by mobile-nav-v2 */
            /* Notification drawer styles now handled by mobile-nav-v2 */

            .civic-nav-link,
            .civic-dropdown-trigger {
                width: 100%;
                text-align: left;
                padding: 15px 20px;
                border-radius: 0;
                border-bottom: 1px solid #f3f4f6;
            }

            body.dark-mode .civic-nav-link,
            body.dark-mode .civic-dropdown-trigger {
                border-bottom-color: #374151;
            }

            .civic-dropdown {
                width: 100%;
            }

            .civic-dropdown-content {
                position: static;
                width: 100%;
                box-shadow: none;
                border: none;
                background-color: #f9fafb;
                padding-left: 20px;
            }

            /* On mobile, show Theme switcher only for admins via body class */
            body:not(.user-is-admin) .civic-interface-switcher {
                display: none !important;
            }

            body.dark-mode .civic-dropdown-content {
                background-color: #111827;
            }
        }
    </style>

    <style>
        /* WCAG 2.1 AA Skip Link - High visibility focus state */
        .skip-link {
            position: absolute;
            top: -50px;
            left: 10px;
            background: #1e40af;
            color: #ffffff;
            padding: 12px 20px;
            z-index: 10001;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: top 0.15s ease-in-out;
        }

        .skip-link:focus {
            top: 0;
            outline: 3px solid #fbbf24;
            outline-offset: 2px;
        }

        /* Visually hidden but accessible */
        .visually-hidden,
        .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        /* WCAG 2.1 AA Focus States - Universal */
        :focus-visible {
            outline: 3px solid var(--civic-brand, #00796B) !important;
            outline-offset: 2px !important;
        }

        /* High contrast focus for buttons and links */
        .civic-btn:focus-visible,
        .civic-nav-link:focus-visible,
        .civic-dropdown-trigger:focus-visible,
        .civic-utility-link:focus-visible,
        .civic-menu-btn:focus-visible {
            outline: 3px solid #1e40af !important;
            outline-offset: 2px !important;
            background-color: rgba(30, 64, 175, 0.1);
        }

        /* Focus ring for dark mode */
        body.dark-mode :focus-visible {
            outline-color: #60a5fa !important;
        }
    </style>

    <!-- Skip Link for Accessibility (WCAG 2.4.1) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Experimental Layout Notice Banner - Integrated into utility bar flow -->
    <div class="civic-experimental-banner" role="status" aria-live="polite">
        <div class="civic-container" style="display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <span class="civic-experimental-badge">BETA</span>
            <span class="civic-experimental-text">
                <strong>Accessible Layout (Experimental)</strong> — WCAG 2.1 AA compliant version
            </span>
            <a href="?layout=modern" class="civic-experimental-switch">
                <span aria-hidden="true">←</span> Switch to Modern Layout
            </a>
        </div>
    </div>
    <style>
        /* Experimental Layout Banner - WCAG 2.1 AA Compliant */
        .civic-experimental-banner {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            color: #ffffff;
            padding: 8px 16px;
            font-size: 0.85rem;
            text-align: center;
            border-bottom: 2px solid #5b21b6;
        }

        .civic-experimental-badge {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .civic-experimental-text {
            color: #ffffff;
        }

        .civic-experimental-switch {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }

        .civic-experimental-switch:hover {
            background: rgba(255, 255, 255, 0.3);
            text-decoration: underline;
        }

        .civic-experimental-switch:focus {
            outline: 3px solid #ffffff;
            outline-offset: 2px;
        }

        /* Dark mode adjustments */
        body.dark-mode .civic-experimental-banner {
            background: linear-gradient(135deg, #581c87 0%, #4c1d95 100%);
            border-bottom-color: #3b0764;
        }

        /* Mobile: Stack vertically */
        @media (max-width: 600px) {
            .civic-experimental-banner .civic-container {
                flex-direction: column;
                gap: 6px;
            }

            .civic-experimental-text {
                font-size: 0.8rem;
            }
        }
    </style>

    <!-- 1. Utility Bar (Top Row) - WCAG 2.1 AA Compliant -->
    <nav class="civic-utility-bar" aria-label="Utility navigation" style="position: relative; z-index: 2000;">
        <div class="civic-container civic-utility-wrapper">

            <!-- Platform Dropdown - Public to everyone -->
            <?php
            $showPlatform = true; // Made public to everyone
            if ($showPlatform):
            ?>
                <div class="civic-dropdown" style="display:inline-block; margin-right: auto;">
                    <button class="civic-utility-link" style="background:none; border:none; cursor:pointer; font-size:0.9rem; padding:0; font-weight:700; text-transform:uppercase;" aria-haspopup="menu" aria-expanded="false" aria-controls="platform-dropdown-menu">
                        Platform <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="platform-dropdown-menu" role="menu" style="min-width:200px; left:0; right:auto;">
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
            <button id="civic-theme-toggle" class="civic-utility-link" style="background:none; border:none; cursor:pointer;" aria-label="Toggle High Contrast">
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
                <div class="civic-dropdown civic-interface-switcher" style="display:inline-block;">
                    <button class="civic-utility-link" style="background:none; border:none; cursor:pointer; font-size:0.9rem; padding:0; font-weight:700;" aria-haspopup="menu" aria-expanded="false" aria-controls="interface-dropdown-menu">
                        Layout <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="interface-dropdown-menu" role="menu" style="min-width:200px; right:0; left:auto;">
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

                <!-- Create Dropdown - SYNCHRONIZED -->
                <div class="civic-dropdown" style="display:inline-block;">
                    <button class="civic-utility-link" style="background:none; border:none; cursor:pointer; font-size:0.9rem; padding:0; color:#10b981; font-weight:700;" aria-haspopup="menu" aria-expanded="false" aria-controls="utility-create-dropdown-menu">
                        + Create <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="utility-create-dropdown-menu" role="menu" style="min-width:180px; right:0; left:auto;">
                        <?php
                        $utilCreateItems = \Nexus\Config\Navigation::getCreateItems();
                        $firstItem = true;
                        foreach ($utilCreateItems as $createKey => $createItem):
                            // Add separator before events and volunteering
                            if (!$firstItem && in_array($createKey, ['event', 'volunteering'])):
                        ?>
                                <hr style="margin:5px 0; border:0; border-top:1px solid #efefef;" role="separator">
                            <?php
                            endif;
                            $firstItem = false;
                            ?>
                            <a href="<?= htmlspecialchars($createItem['url']) ?>" role="menuitem" style="color:<?= htmlspecialchars($createItem['color']) ?>;">
                                <span class="dashicons <?= htmlspecialchars($createItem['icon']) ?>" style="margin-right:5px;" aria-hidden="true"></span> <?= htmlspecialchars($createItem['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Admin Link (Single Link - Matches Modern Header) -->
                <?php if (!empty($_SESSION['is_super_admin']) || ($_SESSION['user_role'] ?? '') === 'admin'): ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin" class="civic-utility-link" style="color:#ea580c; font-weight:700;">Admin</a>
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
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/messages" class="civic-utility-link nexus-header-icon-btn" title="Messages" style="position:relative;">
                    <span class="dashicons dashicons-email" aria-hidden="true"></span>
                    <?php if ($msgUnread > 0): ?>
                        <span class="nexus-header-icon-badge" style="position:absolute; top:-6px; right:-6px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; padding:2px 5px; border-radius:10px; min-width:14px; text-align:center;"><?= $msgUnread > 99 ? '99+' : $msgUnread ?></span>
                    <?php endif; ?>
                </a>

                <!-- Notifications Bell (triggers drawer - Matches Modern) -->
                <button class="civic-utility-link nexus-header-icon-btn" title="Notifications" onclick="window.nexusNotifDrawer.open()" style="background:none; border:none; cursor:pointer; position:relative;">
                    <span class="dashicons dashicons-bell" aria-hidden="true"></span>
                    <?php if ($nUnread > 0): ?>
                        <span id="nexus-bell-badge" class="nexus-header-icon-badge" style="position:absolute; top:-6px; right:-6px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; padding:2px 5px; border-radius:10px; min-width:14px; text-align:center;"><?= $nUnread > 99 ? '99+' : $nUnread ?></span>
                    <?php endif; ?>
                </button>

                <!-- User Avatar Dropdown (Premium - Matches Modern) -->
                <div class="civic-dropdown civic-user-dropdown desktop-only-dd">
                    <button class="civic-utility-link" style="padding: 4px 12px; display: flex; align-items: center; gap: 8px; background:none; border:none; cursor:pointer;" aria-haspopup="menu" aria-expanded="false">
                        <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>"
                            alt="Profile"
                            style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                        <span style="font-weight: 600;"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?></span>
                        <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" role="menu" style="min-width: 220px; right:0; left:auto;">
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

            <!-- Search Bar (WCAG 2.1 AA Accessible) -->
            <div class="civic-search-container" role="search" style="flex-grow:1; margin:0 30px; max-width:500px;">
                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/search" method="GET" style="display:flex; width:100%;" role="search">
                    <label for="site-search" class="visually-hidden">Search the site</label>
                    <input type="search" id="site-search" name="q" placeholder="Search..." aria-label="Search content" autocomplete="off"
                        style="width:100%; padding:12px 16px; border:2px solid #6b7280; border-radius:6px 0 0 6px; font-size:1rem; color:#1f2937;">
                    <button type="submit" aria-label="Submit search" style="background:var(--civic-brand, #00796B); color:white; border:none; padding:0 20px; border-radius:0 6px 6px 0; font-weight:600; cursor:pointer; min-width:80px;">
                        <span aria-hidden="true">Search</span>
                    </button>
                </form>
            </div>

            <!-- Wallet Link (Desktop) -->
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet" class="civic-utility-link" style="background: var(--civic-brand, #00796B); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 700; text-decoration: none; margin-right: 15px; display: none;">
                <span class="dashicons dashicons-wallet" style="margin-right:5px;"></span> Wallet
            </a>
            <style>
                @media(min-width: 900px) {
                    a[href*="/wallet"] {
                        display: inline-flex !important;
                    }
                }
            </style>

            <!-- Create Dropdown (Desktop) -->
            <div class="civic-dropdown" style="display:none;">
                <button class="civic-dropdown-trigger" style="background: #fff; border: 2px solid var(--civic-brand, #00796B); color: var(--civic-brand, #00796B); border-radius: 20px; padding: 6px 16px; display: inline-flex; align-items: center; gap: 5px;" aria-haspopup="menu" aria-expanded="false" aria-controls="header-create-dropdown-menu">
                    <span class="dashicons dashicons-plus" aria-hidden="true"></span> Create
                </button>
                <div class="civic-dropdown-content" id="header-create-dropdown-menu" role="menu" style="right:0; left:auto; min-width:200px;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/create?type=offer" role="menuitem">
                        <span class="dashicons dashicons-plus-alt" style="color:#059669; margin-right:5px;" aria-hidden="true"></span> New Offer
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/create?type=request" role="menuitem">
                        <span class="dashicons dashicons-sos" style="color:#d97706; margin-right:5px;" aria-hidden="true"></span> New Request
                    </a>
                    <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <div style="border-top:1px solid #eee; margin:5px 0;" role="separator"></div>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create" role="menuitem">
                            <span class="dashicons dashicons-calendar-alt" style="color:#4f46e5; margin-right:5px;" aria-hidden="true"></span> New Event
                        </a>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('volunteering') && (empty($_SESSION['user_role']) || $_SESSION['user_role'] === 'admin' || !empty($_SESSION['is_org_admin']))): ?>
                        <div style="border-top:1px solid #eee; margin:5px 0;" role="separator"></div>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opportunities/create" role="menuitem">
                            <span class="dashicons dashicons-heart" style="color:#be185d; margin-right:5px;" aria-hidden="true"></span> Volunteer Opp
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <style>
                @media(min-width: 900px) {
                    .civic-header .civic-dropdown {
                        display: inline-block !important;
                    }
                }
            </style>

            <!-- Mobile Search Toggle -->
            <button id="civic-mobile-search-toggle" class="civic-mobile-search-btn" aria-label="Open search" aria-expanded="false">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
            </button>

            <!-- Mobile Menu Button -->
            <button id="civic-menu-toggle" aria-label="Open Menu" onclick="if(typeof openMobileMenu==='function'){openMobileMenu();}">
                <span class="civic-hamburger"></span>
            </button>

            <!-- Desktop Mega Menu Trigger (Hidden on Master) -->
            <?php if (\Nexus\Core\TenantContext::getId() != 1): ?>
                <div id="civic-desktop-menu" style="margin-left: 20px;">
                    <button id="civic-mega-trigger" class="civic-menu-btn" aria-expanded="false" aria-controls="civic-mega-menu" aria-label="Open main menu">
                        <span style="font-size: 1.2rem;" aria-hidden="true">☰</span> Menu
                    </button>
                </div>
            <?php endif; ?>

            <!-- Mega Menu Overlay -->
            <div id="civic-mega-menu" class="civic-mega-menu">
                <div class="civic-mega-grid">
                    <!-- Col 1: Explore -->
                    <div class="civic-mega-col">
                        <h3>Explore</h3>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings">Offers & Requests</a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups">Local Hubs</a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/members">Community Members</a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/">Activity Feed</a>
                    </div>

                    <!-- Col 2: Participate -->
                    <div class="civic-mega-col">
                        <h3>Participate</h3>
                        <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering">Volunteering</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events">Events</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('polls')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls">Polls</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('goals')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals">Goal Buddies</a>
                        <?php endif; ?>
                    </div>

                    <!-- Col 3: About (Modified logic for Hour Timebank) -->
                    <div class="civic-mega-col">
                        <h3>About</h3>
                        <?php
                        // Determine Tenant for Custom Logic
                        $tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
                        $isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');

                        if ($isHourTimebank) {
                            // --- EXPLICIT LOGIC FOR HOUR TIMEBANK ---
                            echo '<!-- HTB-STRICT-MENU-ACTIVE -->'; // Debug Marker

                            // Latest News
                            if (\Nexus\Core\TenantContext::hasFeature('blog')) {
                                echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/blog">Latest News</a>';
                                echo '<div style="border-top:1px solid #e5e7eb; margin:10px 0;"></div>';
                            }

                            // Core Links
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/our-story">About Us</a>';
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/timebanking-guide">Timebanking Guide</a>';

                            // Partner & Social
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/partner">Partner With Us</a>';
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/social-prescribing">Social Prescribing</a>';
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/faq">Timebanking FAQ\'s</a>';

                            // Our Impact (Colored)
                            echo '<div style="border-top:1px solid #e5e7eb; margin:12px 0;"></div>';
                            echo '<div style="padding: 0 0 8px 0; font-size: 0.8rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;">Our Impact</div>';

                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/impact-summary" style="color:#059669 !important;">Impact Summary</a>';
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/impact-report" style="color:#2563eb !important;">Impact Report</a>';
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/strategic-plan" style="color:#7c3aed !important;">Strategic Plan</a>';

                            // Contact
                            echo '<div style="border-top:1px solid #e5e7eb; margin:12px 0;"></div>';
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/contact">Contact Us</a>';
                            echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/help">Help Center</a>';
                        } else {
                            // --- DEFAULT LOGIC (Other Tenants) ---
                            $customCivicPages = Nexus\Core\TenantContext::getCustomPages('civicone');
                            if (empty($customCivicPages)) $customCivicPages = Nexus\Core\TenantContext::getCustomPages('modern');

                            // Intelligent Deduplication Array
                            $shownPages = [];

                            // 1. Render Custom Pages
                            foreach ($customCivicPages as $page) {
                                $pNameNorm = strtolower(trim($page['name']));
                                $pUrl = $page['url'];

                                if ($pNameNorm == 'about') continue;
                                $shownPages[$pNameNorm] = true;
                                echo '<a href="' . htmlspecialchars($page['url']) . '">' . htmlspecialchars($page['name']) . '</a>';
                            }

                            // 2. Render Standard Pages

                            // PUBLIC SECTOR DEMO NAV OVERRIDE
                            $tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
                            if ($tSlug === 'public-sector-demo') {
                                $uri = $_SERVER['REQUEST_URI'] ?? '';

                                $actHome = ($uri == '/public-sector-demo' || $uri == '/public-sector-demo/') ? 'active' : '';
                                $actComp = (strpos($uri, 'compliance') !== false) ? 'active' : '';
                                $actHSE = (strpos($uri, 'hse-case-study') !== false) ? 'active' : '';
                                $actCouncil = (strpos($uri, 'council-case-study') !== false) ? 'active' : '';
                                $actSpecs = (strpos($uri, 'technical-specs') !== false) ? 'active' : '';

                                echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/" class="civic-nav-link ' . $actHome . '" style="color:#002d72; font-weight:bold;">Demo Home</a>';
                                echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/compliance" class="civic-nav-link ' . $actComp . '">Compliance</a>';
                                echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/hse-case-study" class="civic-nav-link ' . $actHSE . '" style="color:#007b5f;">HSE Case Study</a>';
                                echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/council-case-study" class="civic-nav-link ' . $actCouncil . '" style="color:#002d72;">Council Case Study</a>';
                                echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . '/technical-specs" class="civic-nav-link ' . $actSpecs . '">Technical Specs</a>';
                                echo '<div style="border-top:1px solid #e5e7eb; margin:10px 0;"></div>';
                            }

                            $standardPages = [
                                'How it Works' => '/how-it-works',
                                'Timebanking Guide' => '/timebanking-guide',
                                'Partner With Us' => '/partner',
                                'FAQ' => '/faq',
                                'Contact Us' => '/contact',
                                'Help Center' => '/help'
                            ];

                            // PUBLIC SECTOR DEMO: Override to remove generic pages
                            if ($tSlug === 'public-sector-demo') {
                                $standardPages = [
                                    'Contact Us' => '/contact'
                                    // User removed FAQ, Partner, Guide, How it Works
                                ];
                            }


                            foreach ($standardPages as $label => $url) {
                                $labelNorm = strtolower($label);
                                if (isset($shownPages[$labelNorm])) continue;
                                if ($labelNorm == 'contact us' && isset($shownPages['contact'])) continue;

                                echo '<a href="' . \Nexus\Core\TenantContext::getBasePath() . $url . '">' . $label . '</a>';
                            }
                        }
                        ?>
                    </div>

                    <!-- Col 4: Resources + My Account -->
                    <div class="civic-mega-col">
                        <h3>Resources</h3>
                        <?php if (Nexus\Core\TenantContext::hasFeature('resources')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources">Resources</a>
                        <?php endif; ?>

                        <!-- Fixed Blog Link (Globally use /blog if using standard logic, or respect configured slug) -->
                        <?php if (Nexus\Core\TenantContext::hasFeature('blog') && !$isHourTimebank): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/blog">Community News</a>
                        <?php endif; ?>

                        <h3 style="margin-top:20px;">My Account</h3>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" style="color:var(--civic-brand);">My Wallet</a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/dashboard">Dashboard</a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/edit">Edit Profile</a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/settings">Settings</a>
                        <?php else: ?>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login">Sign In</a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register">Join Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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

                // Mobile Menu Toggle - Now handled by mobile-nav-v2.php
                // The drawer script provides full accessibility support including:
                // - Close button handling
                // - Backdrop click to close
                // - Escape key to close
                // - Focus trapping
                // - Swipe gestures

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

        <script>
            // Mega Menu Logic
            document.addEventListener('DOMContentLoaded', function() {
                const megaBtn = document.getElementById('civic-mega-trigger');
                const megaMenu = document.getElementById('civic-mega-menu');

                if (megaBtn && megaMenu) {
                    megaBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        // Close mobile menu if open
                        if (typeof closeMobileMenu === 'function') {
                            closeMobileMenu();
                        }

                        this.classList.toggle('active');
                        megaMenu.classList.toggle('active');
                    });

                    // Close when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!megaMenu.contains(e.target) && !megaBtn.contains(e.target)) {
                            megaMenu.classList.remove('active');
                            megaBtn.classList.remove('active');
                        }
                    });
                }
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