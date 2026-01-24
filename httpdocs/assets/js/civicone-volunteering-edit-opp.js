// Volunteering: Edit Opportunity - Enhanced UX
// WCAG 2.1 AA Compliant

// ============================================
// GOLD STANDARD - Native App Features
// ============================================

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

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to save changes.');
            return;
        }
    });
});

// Button Press States - using classList for GOV.UK compliance
document.querySelectorAll('.htb-btn, button').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.classList.add('btn-pressed');
    });
    btn.addEventListener('pointerup', function() {
        this.classList.remove('btn-pressed');
    });
    btn.addEventListener('pointerleave', function() {
        this.classList.remove('btn-pressed');
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#14b8a6';
        document.head.appendChild(meta);
    }
})();
