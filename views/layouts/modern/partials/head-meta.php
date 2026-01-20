<?php
/**
 * Modern Layout - Head Meta Tags & Styles
 * Includes: DOCTYPE, meta tags, PWA config, CSS links, critical inline styles
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $mode ?>" data-layout="modern">

<head>
    <meta charset="UTF-8">
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

    <!-- Performance: Preconnect to external domains -->
    <link rel="preconnect" href="https://api.mapbox.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://api.mapbox.com">

    <!-- CRITICAL: Anti-Flicker CSS - Must be inline BEFORE any external CSS -->
    <style id="nexus-critical-antiflicker">
        /* Hide body completely until CSS loads - prevents ALL flicker */
        body {
            opacity: 0 !important;
            visibility: hidden !important;
            overflow: hidden !important;
        }

        /* Background matches theme to prevent white flash */
        html {
            background-color: #0f172a;
            overflow-y: scroll;
        }

        html[data-theme="light"] {
            background-color: #f8fafc;
        }

        /* Show body when CSS is loaded */
        html.css-loaded body {
            opacity: 1 !important;
            visibility: visible !important;
            overflow: visible !important;
            transition: opacity 0.15s ease-in;
        }

        /* Remove anti-flicker after load */
        html.css-loaded #nexus-critical-antiflicker {
            display: none;
        }
    </style>

    <!-- Stylesheets - Critical CSS loaded first for fastest paint -->
    <!-- Updated 2026-01-17: Using minified versions with dynamic cache busting -->
    <?php $cssVersion = time(); ?>
    <link rel="stylesheet" href="/assets/css/nexus-phoenix.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&family=Outfit:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Profile Header (MOJ Identity Bar - WCAG 2.1 AA 2026-01-20) -->
    <link rel="stylesheet" href="/assets/css/civicone-profile-header.min.css?v=<?= $cssVersion ?>">

    <link rel="stylesheet" href="/assets/css/nexus-mobile.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/nexus-native-nav-v2.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/nexus-polish.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/nexus-interactions.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/nexus-shared-transitions.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/post-box-home.min.css?v=<?= $cssVersion ?>">

    <?php if (file_exists(__DIR__ . '/inline-styles.php')) require __DIR__ . '/inline-styles.php'; ?>

    <!-- CRITICAL: CSS Load Detection - Removes flicker -->
    <script>
    (function() {
        'use strict';

        // List of ALL CSS files that must load before showing content
        // Updated 2026-01-17: Consolidated to fewer files (19 -> 13)
        var criticalCSS = [
            // Core framework
            'nexus-phoenix.css',
            'nexus-mobile.css',
            'nexus-shared-transitions.css',
            'post-box-home.css',
            // Navigation (v2 only)
            'nexus-native-nav-v2.css',
            // Consolidated polish (replaces 5 files)
            'nexus-polish.css',
            'nexus-interactions.css',
            // Header and components (from header.php)
            'nexus-loading-fix.css',
            'nexus-performance-patch.css',
            'nexus-modern-header.css',
            'premium-search.css',
            'premium-dropdowns.css',
            'nexus-premium-mega-menu.css'
        ];

        var loadedCount = 0;
        var foundCSS = 0; // Track across function calls for timeout
        var checkInterval;

        function checkCSSLoaded() {
            var stylesheets = document.styleSheets;
            foundCSS = 0; // Update global variable for timeout

            for (var i = 0; i < stylesheets.length; i++) {
                try {
                    var href = stylesheets[i].href;
                    if (href) {
                        for (var j = 0; j < criticalCSS.length; j++) {
                            if (href.indexOf(criticalCSS[j]) !== -1) {
                                foundCSS++;
                                break;
                            }
                        }
                    }
                } catch (e) {
                    // Cross-origin stylesheet, skip
                }
            }

            // If all critical CSS loaded, show body
            if (foundCSS >= criticalCSS.length) {
                showContent();
            }
        }

        function showContent() {
            document.documentElement.classList.add('css-loaded');

            // Stop checking
            if (checkInterval) {
                clearInterval(checkInterval);
            }

            // Remove anti-flicker CSS after transition completes
            setTimeout(function() {
                var antiFlicker = document.getElementById('nexus-critical-antiflicker');
                if (antiFlicker) {
                    antiFlicker.remove();
                }
            }, 200);
        }

        // Check immediately
        checkCSSLoaded();

        // Check every 50ms until loaded
        checkInterval = setInterval(checkCSSLoaded, 50);

        // Failsafe: Show content after 2.5 seconds no matter what (increased for 19 CSS files)
        setTimeout(function() {
            if (!document.documentElement.classList.contains('css-loaded')) {
                console.warn('[Nexus] CSS load timeout - showing content anyway');
                console.warn('[Nexus] Loaded CSS files:', foundCSS, '/', criticalCSS.length);
                console.warn('[Nexus] Missing files - check Network tab for failed CSS loads');
                showContent();
            }
        }, 2500);

        // Also check when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkCSSLoaded);
        }
    })();
    </script>

    <?php if (file_exists(__DIR__ . '/inline-scripts.php')) require __DIR__ . '/inline-scripts.php'; ?>
</head>

<body>
    <!-- Skip Link for Accessibility (WCAG 2.4.1) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
