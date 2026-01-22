/**
 * CivicOne Federation Help Page
 * FAQ Accordion interactions and offline indicator
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // FAQ Accordion
    document.querySelectorAll('.civic-fed-faq-question').forEach(function(button) {
        button.addEventListener('click', function() {
            const item = this.closest('.civic-fed-faq-item');
            const isOpen = item.classList.contains('civic-fed-faq-item--open');

            // Close all other items
            document.querySelectorAll('.civic-fed-faq-item--open').forEach(function(openItem) {
                if (openItem !== item) {
                    openItem.classList.remove('civic-fed-faq-item--open');
                    openItem.querySelector('.civic-fed-faq-question').setAttribute('aria-expanded', 'false');
                }
            });

            // Toggle current item
            item.classList.toggle('civic-fed-faq-item--open');
            this.setAttribute('aria-expanded', !isOpen);
        });
    });

    // Smooth scroll for quick links
    document.querySelectorAll('.civic-fed-quick-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    target.focus();
                }
            }
        });
    });

    // Offline indicator
    const banner = document.getElementById('offlineBanner');
    if (banner) {
        window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
        window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
        if (!navigator.onLine) {
            banner.classList.add('civic-fed-offline-banner--visible');
        }
    }
})();
