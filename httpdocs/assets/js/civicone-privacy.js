/**
 * CivicOne Privacy Policy
 * Smooth scroll and button interactions
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // ============================================
    // Smooth Scroll for Anchor Links
    // ============================================
    function initSmoothScroll() {
        const navButtons = document.querySelectorAll('#privacy-wrapper .privacy-nav-btn');

        navButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href.startsWith('#')) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    }

    // ============================================
    // Button Press States
    // ============================================
    function initButtonStates() {
        const buttons = document.querySelectorAll('#privacy-wrapper .privacy-nav-btn, #privacy-wrapper .privacy-cta-btn');

        buttons.forEach(btn => {
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
    }

    // ============================================
    // Initialize All Features
    // ============================================
    function init() {
        initSmoothScroll();
        initButtonStates();
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
