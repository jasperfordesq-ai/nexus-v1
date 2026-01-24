/**
 * Volunteer License JavaScript  
 * CivicOne Theme
 */

// Button Touch Feedback - using classList for GOV.UK compliance
document.querySelectorAll('.holo-btn-print').forEach(btn => {
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
