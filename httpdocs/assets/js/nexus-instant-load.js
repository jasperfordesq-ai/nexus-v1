/**
 * Nexus Instant Load - Enhanced
 * CRITICAL: Prevents ALL visual glitches during page load
 * Must be inline in <head> before any other scripts
 */

(function() {
    'use strict';

    // ============================================
    // DEBUG STAMP - TEMPORARY (remove after confirming fix)
    // ============================================
    var INSTANT_LOAD_VERSION = 'v2026-02-01-scrollfix';
    console.log('[NEXUS_INSTANT_LOAD] ' + INSTANT_LOAD_VERSION);

    // Show debug badge if ?debug_instant=1
    if (window.location.search.indexOf('debug_instant=1') !== -1) {
        var badge = document.createElement('div');
        badge.id = 'nexus-instant-load-debug-badge';
        badge.textContent = 'instant-load ' + INSTANT_LOAD_VERSION;
        badge.style.cssText = 'position:fixed;bottom:8px;left:8px;z-index:999999;background:#000;color:#0f0;font:10px monospace;padding:4px 8px;border-radius:4px;opacity:0.9;pointer-events:none;';
        if (document.body) {
            document.body.appendChild(badge);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.appendChild(badge);
            });
        }
    }

    // ============================================
    // 1. HIDE EVERYTHING IMMEDIATELY
    // ============================================

    // Add styles to head IMMEDIATELY (before body even exists)
    const criticalHideStyles = document.createElement('style');
    criticalHideStyles.id = 'nexus-critical-hide';
    criticalHideStyles.textContent = `
        /* CRITICAL: Hide body until fully styled */
        body {
            opacity: 0;
            visibility: hidden;
        }

        /* Prevent layout shift and scrollbar jump */
        html {
            overflow-y: scroll;
            background-color: #0f172a;
            scroll-behavior: auto;
        }

        html[data-theme="light"] {
            background-color: #ffffff;
        }

        /* Prevent content flash */
        main, .main-content, .page-content {
            opacity: 0;
        }
    `;
    document.head.appendChild(criticalHideStyles);

    // ============================================
    // 2. TRACK CSS LOADING
    // ============================================

    const cssLoaded = 0;
    const requiredCSS = [
        'core'  // Combined bundle (matches core.min.css)
    ];

    // Page-specific CSS that must also load
    const pageSpecificCSS = [
        'nexus-groups',
        'nexus-home',
        'nexus-achievements',
        'nexus-profile'
    ];

    function checkCSSLoaded() {
        const stylesheets = document.styleSheets;
        const foundCSS = new Set();
        let pageSpecificFound = false;
        let pageSpecificLoaded = false;

        for (let i = 0; i < stylesheets.length; i++) {
            try {
                const href = stylesheets[i].href;
                if (href) {
                    // Check core CSS (use Set to prevent duplicates)
                    requiredCSS.forEach(function(cssFile) {
                        if (href.includes(cssFile)) {
                            foundCSS.add(cssFile);
                        }
                    });

                    // Check if page has page-specific CSS
                    pageSpecificCSS.forEach(function(cssFile) {
                        if (href.includes(cssFile)) {
                            pageSpecificFound = true;
                            pageSpecificLoaded = true;
                        }
                    });
                }
            } catch (e) {
                // CORS error, ignore
            }
        }

        const coreLoaded = foundCSS.size >= requiredCSS.length;

        // If page has page-specific CSS, wait for it too
        if (pageSpecificFound) {
            return coreLoaded && pageSpecificLoaded;
        }

        // Otherwise just wait for core CSS
        return coreLoaded;
    }

    // ============================================
    // 3. SHOW CONTENT WHEN READY
    // ============================================

    function showContent() {
        // Remove critical hide styles
        const hideStyles = document.getElementById('nexus-critical-hide');
        if (hideStyles) {
            hideStyles.remove();
        }

        // Fade in body smoothly
        if (document.body) {
            // eslint-disable-next-line no-restricted-syntax -- critical page load timing
            document.body.style.opacity = '0';
            // eslint-disable-next-line no-restricted-syntax -- critical page load timing
            document.body.style.visibility = 'visible';
            document.body.classList.remove('js-overflow-hidden');

            // Instant fade in with fast timing (reduced from 0.4s to 0.25s)
            requestAnimationFrame(function() {
                // eslint-disable-next-line no-restricted-syntax -- critical page load timing
                document.body.style.transition = 'opacity 0.25s cubic-bezier(0.4, 0.0, 0.2, 1)';
                // eslint-disable-next-line no-restricted-syntax -- critical page load timing
                document.body.style.opacity = '1';

                // Fade in main content areas
                const mainContent = document.querySelectorAll('main, .main-content, .page-content');
                mainContent.forEach(function(element) {
                    // eslint-disable-next-line no-restricted-syntax -- critical page load timing
                    element.style.transition = 'opacity 0.25s cubic-bezier(0.4, 0.0, 0.2, 1)';
                    // eslint-disable-next-line no-restricted-syntax -- critical page load timing
                    element.style.opacity = '1';
                });
            });
        }

        // Mark as loaded
        document.documentElement.classList.add('content-loaded');
        document.documentElement.classList.remove('loading');

        // Lock layout to prevent JavaScript from causing shifts
        document.documentElement.setAttribute('data-layout-stable', 'true');

        // Enable smooth scroll after load
        setTimeout(function() {
            document.documentElement.style.scrollBehavior = 'smooth';

            // Unlock layout after animations complete
            setTimeout(function() {
                document.documentElement.removeAttribute('data-layout-stable');
            }, 300);
        }, 500);

        console.warn('✅ Content loaded and visible');
    }

    // ============================================
    // 4. WAIT FOR EVERYTHING
    // ============================================

    let checkInterval;
    let attempts = 0;
    const maxAttempts = 100; // 5 seconds max (50ms * 100)

    function waitForCSS() {
        checkInterval = setInterval(function() {
            attempts++;

            if (checkCSSLoaded() || attempts >= maxAttempts) {
                clearInterval(checkInterval);

                // Wait a tiny bit for CSS to fully parse and apply
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        // Also wait for fonts (but don't delay)
                        if (document.fonts && document.fonts.ready) {
                            document.fonts.ready.then(showContent);
                        } else {
                            // Show immediately without font wait
                            showContent();
                        }
                    });
                });
            }
        }, 50); // Check every 50ms instead of 100ms for faster response
    }

    // Start checking when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForCSS);
    } else {
        waitForCSS();
    }

    // Fallback: Force show after 1.5 seconds (reduced for faster perception)
    setTimeout(function() {
        if (!document.documentElement.classList.contains('content-loaded')) {
            console.warn('⚠️ Force showing content after timeout');
            showContent();
        }
    }, 1500);

    // ============================================
    // 5. GUARANTEED SCROLL RESTORATION
    // ============================================

    // Absolute failsafe: Always ensure scrolling works after page load
    window.addEventListener('load', function() {
        setTimeout(function() {
            // Remove any lingering critical hide styles
            const hideStyles = document.getElementById('nexus-critical-hide');
            if (hideStyles) {
                hideStyles.remove();
            }

            // CRITICAL: Clear inline styles that may block scroll
            // DO NOT set height/overflow on html - let CSS handle it
            // Only clear problematic inline styles, don't add new ones
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.documentElement.style.removeProperty('overflow');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.documentElement.style.removeProperty('overflow-y');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.documentElement.style.removeProperty('overflow-x');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.documentElement.style.removeProperty('height');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.body.style.removeProperty('overflow');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.body.style.removeProperty('overflow-y');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.body.style.removeProperty('overflow-x');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.body.style.removeProperty('height');
            // eslint-disable-next-line no-restricted-syntax -- critical scroll fix
            document.body.style.removeProperty('position');

            // Remove any scroll-blocking event listeners
            document.body.onwheel = null;
            document.body.ontouchmove = null;
            document.onwheel = null;
            document.ontouchmove = null;

            // Remove any stuck modal/drawer classes
            document.body.classList.remove('drawer-open', 'modal-open', 'fds-sheet-open', 'menu-open', 'mobile-menu-open', 'keyboard-open', 'js-overflow-hidden');

            // Mark as loaded if not already
            if (!document.documentElement.classList.contains('content-loaded')) {
                document.documentElement.classList.add('content-loaded');
                // eslint-disable-next-line no-restricted-syntax -- critical page load visibility
                document.body.style.opacity = '1';
                // eslint-disable-next-line no-restricted-syntax -- critical page load visibility
                document.body.style.visibility = 'visible';
            }
        }, 100);
    });

})();
