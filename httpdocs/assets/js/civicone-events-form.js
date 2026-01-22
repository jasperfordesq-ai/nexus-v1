/**
 * CivicOne Events Form - Interactive Elements
 * WCAG 2.1 AA Compliant
 * SDG checkbox card selection interactivity
 */

(function() {
    'use strict';

    // SDG checkbox card selection
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.civicone-sdg-card').forEach(function(card) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            if (!checkbox) return;

            card.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    e.preventDefault();
                    checkbox.checked = !checkbox.checked;
                    card.classList.toggle('selected', checkbox.checked);
                }
            });

            checkbox.addEventListener('change', function() {
                card.classList.toggle('selected', this.checked);
            });
        });
    });

})();
