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

// Button press states - using classList for GOV.UK compliance
document.querySelectorAll('#privacy-glass-wrapper .privacy-nav-btn, #privacy-glass-wrapper .privacy-cta-btn').forEach(btn => {
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
