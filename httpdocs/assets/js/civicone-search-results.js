/**
 * CivicOne Search Results - Filter Functionality
 * GOV.UK Design System v5.14.0 - WCAG 2.1 AA Compliant
 * Client-side filtering of search results by type
 * Progressive enhancement with event listeners
 */

(function() {
    'use strict';

    /**
     * Filter search results by type
     * @param {string} type - Filter type: 'all', 'user', 'group', 'listing', 'page'
     */
    function filterSearch(type) {
        // 1. Update Tabs ARIA and active state
        const tabs = document.querySelectorAll('.civicone-search-tab');
        tabs.forEach(tab => {
            const isActive = tab.getAttribute('data-filter') === type;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        // 2. Filter Items using class toggles (no inline styles)
        const items = document.querySelectorAll('.civicone-search-result-item');
        const emptyMsg = document.getElementById('no-filter-results');
        let visibleCount = 0;

        items.forEach(item => {
            const itemType = item.getAttribute('data-type');
            const shouldShow = type === 'all' || itemType === type;

            item.classList.toggle('civicone-search-result-item--hidden', !shouldShow);

            if (shouldShow) {
                visibleCount++;
            }
        });

        // 3. Toggle Empty Message
        if (emptyMsg) {
            if (visibleCount === 0) {
                emptyMsg.style.display = 'block';
                const msgText = emptyMsg.querySelector('p');
                if (msgText) {
                    const typeLabel = {
                        'all': 'results',
                        'user': 'people',
                        'group': 'hubs',
                        'listing': 'listings',
                        'page': 'pages'
                    }[type] || 'results';
                    msgText.textContent = `No ${typeLabel} found matching your search.`;
                }
            } else {
                emptyMsg.style.display = 'none';
            }
        }

        // 4. Update visible count display
        updateVisibleCount(visibleCount);

        // Announce filter change to screen readers
        announceFilterChange(type, visibleCount);
    }

    /**
     * Update visible results count display
     * @param {number} count - Number of visible results
     */
    function updateVisibleCount(count) {
        const visibleCountEl = document.getElementById('visible-count');
        if (visibleCountEl) {
            visibleCountEl.textContent = count;
        }
    }

    /**
     * Sort search results
     * @param {string} sortBy - Sort method: 'relevance', 'recent', 'name'
     */
    function sortResults(sortBy) {
        const resultsList = document.getElementById('search-results-list');
        if (!resultsList) return;

        const items = Array.from(resultsList.querySelectorAll('.civicone-search-result-item'));

        items.sort((a, b) => {
            switch (sortBy) {
                case 'name':
                    const titleA = a.querySelector('.civicone-search-result-item__title')?.textContent?.trim() || '';
                    const titleB = b.querySelector('.civicone-search-result-item__title')?.textContent?.trim() || '';
                    return titleA.localeCompare(titleB);

                case 'recent':
                    // If items have data-date attribute, sort by date
                    const dateA = a.getAttribute('data-date') || '0';
                    const dateB = b.getAttribute('data-date') || '0';
                    return dateB.localeCompare(dateA); // Descending (newest first)

                case 'relevance':
                default:
                    // Keep original order (server already sorted by relevance)
                    return 0;
            }
        });

        // Re-append items in sorted order
        items.forEach(item => resultsList.appendChild(item));
    }

    /**
     * Announce filter change to screen readers
     * @param {string} type - Filter type
     * @param {number} count - Number of visible results
     */
    function announceFilterChange(type, count) {
        const liveRegion = document.getElementById('search-results-list');
        if (liveRegion) {
            const typeLabel = {
                'all': 'All results',
                'user': 'People',
                'group': 'Hubs',
                'listing': 'Listings',
                'page': 'Pages'
            }[type] || 'Results';

            // Temporarily set aria-live for announcement
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'false');

            // Announce to screen readers (won't be visible)
            const announcement = `${typeLabel} filter applied. Showing ${count} ${count === 1 ? 'result' : 'results'}.`;

            // Store current aria-label
            const currentLabel = liveRegion.getAttribute('aria-label');
            liveRegion.setAttribute('aria-label', announcement);

            // Reset after announcement
            setTimeout(() => {
                if (currentLabel) {
                    liveRegion.setAttribute('aria-label', currentLabel);
                }
                liveRegion.removeAttribute('aria-live');
                liveRegion.removeAttribute('aria-atomic');
            }, 1000);
        }
    }

    /**
     * Initialize search results functionality
     */
    function init() {
        // Attach click listeners to all tab buttons
        const tabs = document.querySelectorAll('.civicone-search-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const filterType = this.getAttribute('data-filter');
                filterSearch(filterType);
            });

            // Keyboard support (Enter and Space)
            tab.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const filterType = this.getAttribute('data-filter');
                    filterSearch(filterType);
                }
            });
        });

        // Attach change listener to sort dropdown
        const sortDropdown = document.getElementById('sort-by');
        if (sortDropdown) {
            sortDropdown.addEventListener('change', function() {
                sortResults(this.value);
            });
        }
    }

    // Auto-initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }

    // Export for backwards compatibility (if needed)
    window.CivicSearchResults = {
        filterSearch: filterSearch,
        sortResults: sortResults
    };

})();
