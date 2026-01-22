// ============================================
// EDIT ORGANIZATION - Enhanced UX
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

// Form Submission Protection
(function initFormProtection() {
    const form = document.getElementById('editOrgForm');
    const submitBtn = document.getElementById('submitBtn');

    if (!form || !submitBtn) return;

    form.addEventListener('submit', function(e) {
        // Offline check
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to save changes.');
            return;
        }

        // Prevent double submission
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    });
})();

// Button Press States
document.querySelectorAll('.edit-org-btn, .edit-org-quick-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.97)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
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
</script>
