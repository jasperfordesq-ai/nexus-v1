/**
 * Global Scroll Fix for Multi-Tab Issues
 *
 * Fixes mouse wheel scrolling when multiple browser tabs are open.
 * Browser tab throttling can cause scroll events to be blocked.
 */

(function() {
    'use strict';

    // Re-enable scroll when tab becomes visible
    function enableScroll() {
        // Remove any stuck scroll-blocking classes
        const menuOpen = document.getElementById('mobileMenu')?.classList.contains('active');
        const notifOpen = document.getElementById('mobileNotifications')?.classList.contains('active');

        if (!menuOpen) {
            document.body.classList.remove('mobile-menu-open');
        }
        if (!notifOpen) {
            document.body.classList.remove('mobile-notifications-open');
        }

        // Force scroll styles - aggressive approach for multi-tab issue
        const computed = getComputedStyle(document.body);

        if (computed.position === 'fixed') {
            document.body.style.position = 'static';
        }
        if (computed.overflow === 'hidden' || computed.overflowY === 'hidden') {
            document.body.style.overflowY = 'auto';
        }
        if (computed.overflow === 'visible' || computed.overflowY === 'visible') {
            document.body.style.overflowY = 'auto';
        }

        // Also check html element
        const htmlComputed = getComputedStyle(document.documentElement);
        if (htmlComputed.overflow === 'hidden' || htmlComputed.overflowY === 'hidden') {
            document.documentElement.style.overflowY = 'scroll';
        }

        console.log('[SCROLL FIX] Scroll re-enabled', {
            bodyOverflow: getComputedStyle(document.body).overflowY,
            bodyPosition: getComputedStyle(document.body).position,
            htmlOverflow: getComputedStyle(document.documentElement).overflowY
        });
    }

    // Run on tab visibility change
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            enableScroll();
        }
    });

    // Run on window focus
    window.addEventListener('focus', enableScroll);

    // Run on page show (when navigating back)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            enableScroll();
        }
    });

    // Initial cleanup
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enableScroll);
    } else {
        enableScroll();
    }

    // Continuous monitor - check every 2 seconds if scroll is still working
    // This catches issues that occur after page load
    setInterval(function() {
        if (!document.hidden) {  // Only when tab is visible
            const bodyOverflow = getComputedStyle(document.body).overflowY;
            const bodyPosition = getComputedStyle(document.body).position;

            // If we detect scroll-blocking state, fix it
            if (bodyOverflow === 'hidden' || bodyOverflow === 'visible' || bodyPosition === 'fixed') {
                console.warn('[SCROLL FIX] Detected scroll-blocking state, fixing...', {
                    bodyOverflow,
                    bodyPosition
                });
                enableScroll();
            }
        }
    }, 2000);
})();
