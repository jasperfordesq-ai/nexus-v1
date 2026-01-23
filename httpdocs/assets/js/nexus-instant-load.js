/**
 * Nexus Instant Load - Enhanced
 * CRITICAL: Prevents ALL visual glitches during page load
 * Must be inline in <head> before any other scripts
 */

(function() {
    'use strict';

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
            document.body.style.opacity = '0';
            document.body.style.visibility = 'visible';
            document.body.style.overflow = '';

            // Instant fade in with fast timing (reduced from 0.4s to 0.25s)
            requestAnimationFrame(function() {
                document.body.style.transition = 'opacity 0.25s cubic-bezier(0.4, 0.0, 0.2, 1)';
                document.body.style.opacity = '1';

                // Fade in main content areas
                const mainContent = document.querySelectorAll('main, .main-content, .page-content');
                mainContent.forEach(function(element) {
                    element.style.transition = 'opacity 0.25s cubic-bezier(0.4, 0.0, 0.2, 1)';
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

        console.log('✅ Content loaded and visible');
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

            // CRITICAL: Force clear any scroll-blocking styles AND enable scrolling
            document.documentElement.style.cssText = 'overflow-y: scroll !important; overflow-x: hidden !important; height: 100% !important;';
            document.body.style.cssText = 'overflow-y: auto !important; overflow-x: hidden !important; height: auto !important; position: static !important;';

            // Remove any scroll-blocking event listeners
            document.body.onwheel = null;
            document.body.ontouchmove = null;
            document.onwheel = null;
            document.ontouchmove = null;

            // Remove any stuck modal/drawer classes
            document.body.classList.remove('drawer-open', 'modal-open', 'fds-sheet-open', 'menu-open', 'mobile-menu-open', 'keyboard-open');

            // Mark as loaded if not already
            if (!document.documentElement.classList.contains('content-loaded')) {
                document.documentElement.classList.add('content-loaded');
                document.body.style.opacity = '1';
                document.body.style.visibility = 'visible';
            }
        }, 100);
    });

})();
