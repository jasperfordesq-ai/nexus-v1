/**
 * Goals Edit JavaScript  
 * CivicOne Theme
 */

// Offline Detection
(function() {
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

    if (!navigator.onLine) handleOffline();
})();

// Form Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
        }
    });
});

// Button Touch Feedback - using classList for GOV.UK compliance
document.querySelectorAll('.holo-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.classList.add('btn-pressed-sm');
    });
    btn.addEventListener('pointerup', function() {
        this.classList.remove('btn-pressed-sm');
    });
    btn.addEventListener('pointerleave', function() {
        this.classList.remove('btn-pressed-sm');
    });
});
