/**
 * CivicOne Federation Hub Page
 * Offline indicator functionality
 * WCAG 2.1 AA Compliant
 */

(function initFederationHub() {
    'use strict';

    // Offline Indicator
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('civic-fed-offline-banner--visible');
    }

    function handleOnline() {
        banner.classList.remove('civic-fed-offline-banner--visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();
