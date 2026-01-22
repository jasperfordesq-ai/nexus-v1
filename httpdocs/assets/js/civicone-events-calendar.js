/**
 * CivicOne Events Calendar - Interactivity
 * WCAG 2.1 AA Compliant
 * Offline detection, touch feedback, dynamic theme color
 */

(function() {
    'use strict';

    // Offline Indicator
    (function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        function handleOffline() {
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }

        function handleOnline() {
            banner.classList.remove('visible');
        }

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        if (!navigator.onLine) {
            handleOffline();
        }
    })();

    // Touch feedback
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.holo-nav-btn, .holo-cal-event').forEach(el => {
            el.addEventListener('pointerdown', () => el.style.transform = 'scale(0.97)');
            el.addEventListener('pointerup', () => el.style.transform = '');
            el.addEventListener('pointerleave', () => el.style.transform = '');
        });
    });

    // Dynamic Theme Color
    (function initDynamicThemeColor() {
        let metaTheme = document.querySelector('meta[name="theme-color"]');
        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            metaTheme.setAttribute('content', isDark ? '#0f172a' : '#f97316');
        }

        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        updateThemeColor();
    })();

})();
