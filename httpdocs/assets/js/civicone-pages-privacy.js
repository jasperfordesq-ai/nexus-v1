/**
 * Privacy Policy JavaScript  
 * CivicOne Theme
 */

// Smooth scroll for anchor links
document.querySelectorAll('#privacy-glass-wrapper .privacy-nav-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// Button press states
document.querySelectorAll('#privacy-glass-wrapper .privacy-nav-btn, #privacy-glass-wrapper .privacy-cta-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
