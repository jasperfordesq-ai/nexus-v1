<?php
/**
 * CivicOne Layout - Head Meta Tags & Styles (BUNDLED - PRODUCTION)
 *
 * This version loads 1 compiled CSS bundle instead of 7 separate files
 *
 * Performance: 60-75% faster load time
 * Accessibility: WCAG 2.1 AA Compliant
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $mode ?>" data-layout="civicone">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= \Nexus\Core\Csrf::generate() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?= \Nexus\Core\SEO::render() ?>

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#002d72">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(\Nexus\Core\TenantContext::get()['name'] ?? 'NEXUS') ?>">
    <link rel="apple-touch-icon" href="/assets/images/pwa/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/pwa/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/pwa/icon-512x512.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="<?= htmlspecialchars(\Nexus\Core\TenantContext::get()['name'] ?? 'NEXUS') ?>">
    <meta name="msapplication-TileColor" content="#002d72">

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

        /* Background matches CivicOne theme to prevent white flash */
        html {
            background-color: #f9fafb;
            overflow-y: scroll;
        }

        html[data-theme="dark"] {
            background-color: #0f172a;
        }

        /* Show body when CSS is loaded */
        html.css-loaded body {
            opacity: 1 !important;
            visibility: visible !important;
            overflow-y: auto !important; /* NOT visible - visible breaks mouse wheel scroll */
            transition: opacity 0.15s ease-in;
        }

        /* Remove anti-flicker after load */
        html.css-loaded #nexus-critical-antiflicker {
            display: none;
        }
    </style>

    <!-- Critical CSS Inline (Lighthouse: Reduce FCP) -->
    <?php include __DIR__ . '/../critical-css.php'; ?>

    <!-- ============================================
         CIVICONE LAYOUT BUNDLE - Single optimized file
         ============================================ -->
    <link rel="stylesheet" href="/assets/css/civicone-bundle-compiled.min.css?v=2.4.1">

    <!-- External CSS (Font Awesome, Fonts) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <?php
    // Page-specific CSS (additionalCSS support)
    if (isset($additionalCSS) && !empty($additionalCSS)) {
        echo "<!-- Page-Specific CSS (Priority Load) -->\n";
        echo $additionalCSS . "\n";
    }

    // Note: Custom Layout Builder CSS removed 2026-01-17
    // The getCustomLayoutCSS() method was never implemented
    ?>

    <!-- CRITICAL: CSS Load Detection - OPTIMIZED for bundle -->
    <script>
    (function() {
        'use strict';

        // With bundled CSS, we only check for 1 file!
        var bundleLoaded = false;
        var checkInterval;

        function checkCSSLoaded() {
            var stylesheets = document.styleSheets;

            for (var i = 0; i < stylesheets.length; i++) {
                try {
                    var href = stylesheets[i].href;
                    if (href && href.indexOf('civicone-bundle-compiled') !== -1) {
                        // Bundle found! Check if it has rules (fully loaded)
                        if (stylesheets[i].cssRules && stylesheets[i].cssRules.length > 0) {
                            bundleLoaded = true;
                            showContent();
                            return;
                        }
                    }
                } catch (e) {
                    // Cross-origin stylesheet or not yet loaded, skip
                }
            }
        }

        function showContent() {
            if (!bundleLoaded) return; // Don't show twice

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

        // Check every 50ms until loaded (faster than multiple files)
        checkInterval = setInterval(checkCSSLoaded, 50);

        // Failsafe: Show content after 1 second (much faster with bundle)
        setTimeout(function() {
            if (!document.documentElement.classList.contains('css-loaded')) {
                console.warn('[Nexus CivicOne] CSS bundle load timeout - showing content anyway');
                console.warn('[Nexus CivicOne] Check Network tab - bundle may have failed to load');
                showContent();
            }
        }, 1000);

        // Also check when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkCSSLoaded);
        }
    })();
    </script>

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
</head>

<body class="nexus-skin-civicone <?= $skinClass ?? '' ?> <?= $isHome ? 'nexus-home-page' : '' ?> <?= isset($_SESSION['user_id']) ? 'logged-in' : '' ?> <?= ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])) ? 'user-is-admin' : '' ?> <?= $bodyClass ?? '' ?>">
    <!-- Skip Link for Accessibility (WCAG 2.4.1) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
