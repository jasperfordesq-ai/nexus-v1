/**
 * Federation Common - JavaScript
 * Shared functionality for federation pages
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    // Offline indicator
    const offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        window.addEventListener('online', function() {
            offlineBanner.classList.remove('visible');
        });
        window.addEventListener('offline', function() {
            offlineBanner.classList.add('visible');
        });
        if (!navigator.onLine) {
            offlineBanner.classList.add('visible');
        }
    }
})();
