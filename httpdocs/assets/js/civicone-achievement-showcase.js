/**
 * Achievement Showcase Component JavaScript
 * Handles tab switching and interactions
 * CivicOne Theme - GOV.UK Design System Compliant
 */

(function() {
    'use strict';

    // Category filter functionality with ARIA support
    document.querySelectorAll('.category-tag').forEach((tag, index) => {
        // Add keyboard support
        tag.setAttribute('role', 'tab');
        tag.setAttribute('tabindex', tag.classList.contains('active') ? '0' : '-1');
        tag.setAttribute('aria-selected', tag.classList.contains('active') ? 'true' : 'false');

        tag.addEventListener('click', function() {
            const category = this.dataset.category;

            // Update active state and ARIA attributes
            document.querySelectorAll('.category-tag').forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
                t.setAttribute('tabindex', '-1');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            this.setAttribute('tabindex', '0');

            // Filter badges based on category
            filterBadgesByCategory(category);
        });

        // Keyboard navigation
        tag.addEventListener('keydown', function(e) {
            const tags = Array.from(document.querySelectorAll('.category-tag'));
            const currentIndex = tags.indexOf(this);
            let newIndex = currentIndex;

            switch (e.key) {
                case 'ArrowRight':
                case 'ArrowDown':
                    newIndex = (currentIndex + 1) % tags.length;
                    e.preventDefault();
                    break;
                case 'ArrowLeft':
                case 'ArrowUp':
                    newIndex = (currentIndex - 1 + tags.length) % tags.length;
                    e.preventDefault();
                    break;
                case 'Home':
                    newIndex = 0;
                    e.preventDefault();
                    break;
                case 'End':
                    newIndex = tags.length - 1;
                    e.preventDefault();
                    break;
            }

            if (newIndex !== currentIndex) {
                tags[newIndex].focus();
                tags[newIndex].click();
            }
        });
    });

    // Filter badges by category
    function filterBadgesByCategory(category) {
        const badges = document.querySelectorAll('[data-badge-category]');
        badges.forEach(badge => {
            if (category === 'all' || badge.dataset.badgeCategory === category) {
                badge.hidden = false;
            } else {
                badge.hidden = true;
            }
        });
    }
})();
