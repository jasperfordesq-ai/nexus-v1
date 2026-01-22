/**
 * Federation Offline Indicator
 * Shows/hides offline banner based on network connectivity
 * CivicOne Federation Module
 */

(function() {
    'use strict';

    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    // Handle online event
    window.addEventListener('online', () => {
        banner.classList.remove('civic-fed-offline-banner--visible');
    });

    // Handle offline event
    window.addEventListener('offline', () => {
        banner.classList.add('civic-fed-offline-banner--visible');
    });

    // Check initial state
    if (!navigator.onLine) {
        banner.classList.add('civic-fed-offline-banner--visible');
    }
})();
