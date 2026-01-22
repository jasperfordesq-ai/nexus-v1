/**
 * Volunteer License JavaScript  
 * CivicOne Theme
 */

// Button Touch Feedback
document.querySelectorAll('.holo-btn-print').forEach(btn => {
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
