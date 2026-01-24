/**
 * Polls Edit JavaScript  
 * CivicOne Theme
 */

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

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editPollForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to save changes.');
                return;
            }

            // Only show loading for save (not delete)
            if (e.submitter === submitBtn) {
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
            }
        });
    }

    // Touch feedback - using classList for GOV.UK compliance
    document.querySelectorAll('.holo-btn').forEach(el => {
        el.addEventListener('pointerdown', () => el.classList.add('btn-pressed-sm'));
        el.addEventListener('pointerup', () => el.classList.remove('btn-pressed-sm'));
        el.addEventListener('pointerleave', () => el.classList.remove('btn-pressed-sm'));
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
        metaTheme.setAttribute('content', isDark ? '#0f172a' : '#8b5cf6');
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
