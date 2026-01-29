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

        // Only fix if actually broken - don't interfere with normal scroll
        const computed = getComputedStyle(document.body);

        // Only fix position if it's stuck as fixed
        if (computed.position === 'fixed' && !menuOpen && !notifOpen) {
            // eslint-disable-next-line no-restricted-syntax -- reset position for scroll fix
            document.body.style.position = '';
        }

        // Only fix overflow if it's actually preventing scroll
        if ((computed.overflowY === 'hidden' || computed.overflowY === 'visible') && !menuOpen && !notifOpen) {
            document.body.classList.remove('js-overflow-hidden');
        }
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

    // Initial cleanup only - NO continuous monitoring
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enableScroll);
    } else {
        enableScroll();
    }
})();
