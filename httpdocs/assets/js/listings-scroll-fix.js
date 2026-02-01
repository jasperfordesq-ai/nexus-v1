/**
 * Listings Page Mobile Scroll Safety Fix
 * Route-specific: Only runs on /listings
 * Added: 2026-02-01 for mobile scroll issues
 *
 * Purpose:
 * - Prevents stuck scroll-lock classes from breaking page scroll
 * - Cleans up after overlays/menus that may not properly release lock
 * - Only acts when overlay/menu is NOT actually open
 */

(function() {
    'use strict';

    // Only run on /listings index page
    const path = window.location.pathname;
    if (!path.includes('/listings') || path.match(/\/listings\/\d+/)) {
        return; // Skip detail pages like /listings/123
    }

    /**
     * Check if an overlay/menu is actually open
     */
    function isOverlayOpen() {
        const selectors = [
            '#mobileMenu.active',
            '#mobileSearchOverlay.active',
            '#mobileNotifications.active',
            '.mobile-sheet.active',
            '.modal.show',
            '.modal[aria-hidden="false"]',
            '[data-modal-open="true"]'
        ];
        return selectors.some(sel => document.querySelector(sel));
    }

    /**
     * Force cleanup of scroll-blocking classes if no overlay is open
     */
    function cleanupScrollLock() {
        if (isOverlayOpen()) {
            return; // Don't touch - overlay is legitimately open
        }

        // Remove scroll-blocking classes
        const blockingClasses = ['js-overflow-hidden', 'mobile-menu-open', 'mobile-notifications-open'];
        blockingClasses.forEach(cls => {
            if (document.body.classList.contains(cls)) {
                document.body.classList.remove(cls);
            }
        });

        // Reset any stuck body styles that block scroll
        const computed = getComputedStyle(document.body);
        if (computed.position === 'fixed' || computed.overflow === 'hidden') {
            // Only reset if we're not in an overlay state
            // eslint-disable-next-line no-restricted-syntax -- emergency scroll fix
            document.body.style.position = '';
            // eslint-disable-next-line no-restricted-syntax -- emergency scroll fix
            document.body.style.overflow = '';
            // eslint-disable-next-line no-restricted-syntax -- emergency scroll fix
            document.body.style.top = '';
        }
    }

    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cleanupScrollLock);
    } else {
        cleanupScrollLock();
    }

    // Run on pageshow (bfcache navigation)
    window.addEventListener('pageshow', function(e) {
        // Small delay to let any menu close animations complete
        setTimeout(cleanupScrollLock, 100);
    });

    // Run on visibility change (tab switch back)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            setTimeout(cleanupScrollLock, 50);
        }
    });

    // Run on window focus
    window.addEventListener('focus', function() {
        setTimeout(cleanupScrollLock, 50);
    });

})();
