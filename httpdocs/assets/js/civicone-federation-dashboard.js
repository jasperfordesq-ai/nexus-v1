/**
 * CivicOne Federation Dashboard
 * Offline indicator for federation dashboard
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // Offline indicator
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));

    if (!navigator.onLine) {
        banner.classList.add('civic-fed-offline-banner--visible');
    }
})();
