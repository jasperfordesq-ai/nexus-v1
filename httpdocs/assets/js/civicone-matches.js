/**
 * CivicOne Smart Matches
 * Tab navigation and interaction tracking
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // Get basePath from global or fallback
    const basePath = window.NEXUS_BASE_PATH || '';

    // ============================================
    // Tab Switching with ARIA Support
    // ============================================
    function initTabs() {
        const tabs = document.querySelectorAll('.match-tab');

        tabs.forEach(tab => {
            // Click handler
            tab.addEventListener('click', function() {
                const tabId = this.dataset.tab;

                // Update active tab
                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');

                // Update active section
                document.querySelectorAll('.match-section').forEach(s => {
                    s.classList.remove('active');
                    s.hidden = true;
                });
                const section = document.getElementById('section-' + tabId);
                if (section) {
                    section.classList.add('active');
                    section.hidden = false;
                }
            });

            // Keyboard navigation
            tab.addEventListener('keydown', function(e) {
                const tabsArray = Array.from(tabs);
                const currentIndex = tabsArray.indexOf(this);
                let newIndex;

                if (e.key === 'ArrowRight') {
                    newIndex = (currentIndex + 1) % tabsArray.length;
                } else if (e.key === 'ArrowLeft') {
                    newIndex = (currentIndex - 1 + tabsArray.length) % tabsArray.length;
                } else if (e.key === 'Home') {
                    newIndex = 0;
                } else if (e.key === 'End') {
                    newIndex = tabsArray.length - 1;
                } else {
                    return;
                }

                e.preventDefault();
                tabsArray[newIndex].focus();
                tabsArray[newIndex].click();
            });
        });
    }

    // ============================================
    // Track Match Interactions
    // ============================================
    function trackMatchInteraction(listingId, action, matchScore, distance) {
        fetch(basePath + '/matches/interact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                listing_id: listingId,
                action: action,
                match_score: matchScore,
                distance: distance
            })
        }).catch(console.error);
    }

    // Make globally accessible for onclick handlers
    window.trackMatchInteraction = trackMatchInteraction;

    // ============================================
    // Track Card Views (Intersection Observer)
    // ============================================
    function initViewTracking() {
        // Respect reduced motion preference
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    const listingId = card.dataset.listingId;
                    const matchScore = card.dataset.matchScore;
                    const distance = card.dataset.distance;

                    if (listingId && !card.dataset.viewed) {
                        trackMatchInteraction(listingId, 'viewed', matchScore, distance);
                        card.dataset.viewed = 'true';
                    }
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('.match-card').forEach(card => {
            observer.observe(card);
        });
    }

    // ============================================
    // Preferences Sliders
    // ============================================
    function initPreferencesSliders() {
        var distanceSlider = document.getElementById('distance-slider');
        var distanceValue = document.getElementById('distance-value');
        var scoreSlider = document.getElementById('score-slider');
        var scoreValue = document.getElementById('score-value');

        if (distanceSlider && distanceValue) {
            distanceSlider.addEventListener('input', function() {
                distanceValue.textContent = this.value + ' km';
            });
        }

        if (scoreSlider && scoreValue) {
            scoreSlider.addEventListener('input', function() {
                scoreValue.textContent = this.value + '%';
            });
        }
    }

    // ============================================
    // Initialize All Features
    // ============================================
    function init() {
        initTabs();
        initViewTracking();
        initPreferencesSliders();
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
