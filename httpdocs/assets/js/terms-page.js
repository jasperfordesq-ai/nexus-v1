/**
 * Terms Page JavaScript
 * Smooth scrolling and button interactions for the Terms of Service page
 */

(function() {
    'use strict';

    // Smooth scroll for anchor links
    const navButtons = document.querySelectorAll('#terms-glass-wrapper .terms-nav-btn');
    navButtons.forEach(btn => {
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
    const pressButtons = document.querySelectorAll('#terms-glass-wrapper .terms-nav-btn, #terms-glass-wrapper .terms-cta-btn');
    pressButtons.forEach(btn => {
        btn.addEventListener('pointerdown', function() {
            this.classList.add('pressed');
        });
        btn.addEventListener('pointerup', function() {
            this.classList.remove('pressed');
        });
        btn.addEventListener('pointerleave', function() {
            this.classList.remove('pressed');
        });
    });
})();
