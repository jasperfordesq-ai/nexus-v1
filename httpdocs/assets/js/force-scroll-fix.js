/**
 * EMERGENCY SCROLL FIX
 * Forces scrolling to work on all pages
 * Runs immediately on script load
 */

(function() {
    'use strict';
    
    // Run immediately
    function forceScrollEnable() {
        // Remove any classes that block scrolling
        document.body.classList.remove('mobile-menu-open', 'mobile-notifications-open');
        document.documentElement.classList.remove('mobile-menu-open', 'mobile-notifications-open');
        
        // Force scroll styles on body and html
        const forceStyles = {
            overflow: 'visible',
            overflowY: 'auto',
            overflowX: 'hidden',
            position: 'static',
            height: 'auto',
            maxHeight: 'none',
            width: '100%'
        };
        
        Object.assign(document.documentElement.style, forceStyles);
        Object.assign(document.body.style, forceStyles);
        
        console.log('[SCROLL FIX] Scroll enabled forcefully');
    }
    
    // Run immediately
    forceScrollEnable();
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', forceScrollEnable);
    } else {
        forceScrollEnable();
    }
    
    // Run periodically for the first 5 seconds (in case something keeps resetting it)
    // Only run if tab is visible to avoid throttling issues
    let count = 0;
    const interval = setInterval(function() {
        if (!document.hidden) {  // Only run if tab is active
            forceScrollEnable();
        }
        count++;
        if (count >= 10) clearInterval(interval); // Stop after 10 checks (5 seconds)
    }, 500);

    // CRITICAL: Run on visibility change and page focus
    // This fixes the issue where scroll freezes when switching between tabs
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            console.log('[SCROLL FIX] Tab became visible - re-enabling scroll');
            forceScrollEnable();
        }
    });

    window.addEventListener('focus', function() {
        console.log('[SCROLL FIX] Window focused - re-enabling scroll');
        forceScrollEnable();
    });

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            console.log('[SCROLL FIX] Page restored from cache - re-enabling scroll');
            forceScrollEnable();
        }
    });
})();
