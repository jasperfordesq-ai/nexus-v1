/**
 * CivicOne Members Directory - Enhanced with GOV.UK/MOJ v1.6.0 Components
 * Handles tabs, MOJ filter mobile toggle, and AJAX search
 * Following GOV.UK Design System and MOJ Design Patterns
 *
 * NEW in v1.6.0:
 * - Bottom sheet filter on mobile
 * - Backdrop overlay for mobile filter
 * - Synchronized search inputs (main + filter)
 * - Search bar always visible
 * - Tabs at top with counts
 *
 * @version 1.6.0
 * @since 2026-01-22
 */

(function () {
    'use strict';

    // Get tenant base path for API calls
    const basePath = (typeof NEXUS_BASE !== 'undefined') ? NEXUS_BASE : '';

    // ==================================================
    // MOJ Filter Mobile Toggle (v1.6.0 - Bottom Sheet)
    // ==================================================

    function initializeMobileFilter() {
        const toggleButtons = document.querySelectorAll('[data-filter-toggle]');
        const closeButtons = document.querySelectorAll('[data-filter-close]');
        const backdrop = document.querySelector('[data-filter-backdrop]');

        toggleButtons.forEach(button => {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('aria-controls');
                const filterPanel = document.getElementById(targetId);

                if (filterPanel) {
                    const isExpanded = button.getAttribute('aria-expanded') === 'true';

                    // Toggle ARIA state
                    button.setAttribute('aria-expanded', !isExpanded);

                    // Toggle visibility class
                    filterPanel.classList.toggle('moj-filter--visible');

                    // Toggle backdrop
                    if (backdrop) {
                        backdrop.setAttribute('aria-hidden', isExpanded ? 'true' : 'false');
                    }

                    // Update button text (only text, not icon)
                    const buttonText = button.querySelector('.members-filter-toggle__text');
                    if (buttonText) {
                        buttonText.textContent = isExpanded ? 'Filters' : 'Close';
                    }

                    // Body scroll lock on mobile only
                    if (window.innerWidth < 641) {
                        document.body.classList.toggle('moj-filter--open', !isExpanded);
                    }

                    // Announce to screen readers
                    announceToScreenReader(isExpanded ? 'Filter menu closed' : 'Filter menu opened');
                }
            });
        });

        closeButtons.forEach(button => {
            button.addEventListener('click', function () {
                const filterPanel = button.closest('.moj-filter');
                const toggleButton = document.querySelector(`[aria-controls="${filterPanel.id}"]`);

                if (filterPanel && toggleButton) {
                    filterPanel.classList.remove('moj-filter--visible');
                    toggleButton.setAttribute('aria-expanded', 'false');

                    const buttonText = toggleButton.querySelector('.members-filter-toggle__text');
                    if (buttonText) {
                        buttonText.textContent = 'Filters';
                    }

                    if (backdrop) {
                        backdrop.setAttribute('aria-hidden', 'true');
                    }

                    document.body.classList.remove('moj-filter--open');

                    announceToScreenReader('Filter menu closed');

                    // Return focus to toggle button
                    toggleButton.focus();
                }
            });
        });

        // Close filter on escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const visibleFilter = document.querySelector('.moj-filter--visible');
                if (visibleFilter && window.innerWidth < 641) {
                    const closeButton = visibleFilter.querySelector('[data-filter-close]');
                    if (closeButton) closeButton.click();
                }
            }
        });

        // Close filter when clicking backdrop
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                const visibleFilter = document.querySelector('.moj-filter--visible');
                if (visibleFilter) {
                    const closeButton = visibleFilter.querySelector('[data-filter-close]');
                    if (closeButton) closeButton.click();
                }
            });
        }
    }

    // ==================================================
    // Search Input (v1.6.0)
    // ==================================================

    function initializeSearch() {
        const mainSearch = document.getElementById('member-search-main');

        if (!mainSearch) return;

        // Single search input at top
        mainSearch.addEventListener('input', function () {
            triggerSearch(mainSearch.value);
        });
    }

    let searchTimeout = null;

    function triggerSearch(query) {
        clearTimeout(searchTimeout);

        const spinner = document.querySelector('.civicone-spinner');

        if (query.length === 0) {
            // Reload page to show all members
            window.location.href = window.location.pathname;
            return;
        }

        if (query.length < 2) {
            return;
        }

        // Show loading spinner
        if (spinner) {
            spinner.classList.remove('civicone-spinner--hidden');
        }

        searchTimeout = setTimeout(function () {
            // Update URL with search query
            const url = new URL(window.location);
            url.searchParams.set('q', query);
            window.history.pushState({}, '', url);

            // Perform search
            performSearch(query, spinner);
        }, 300);
    }

    function performSearch(query, spinner) {
        const activeTab = document.querySelector('.members-tabs__item--selected .members-tabs__link');
        const isActiveTab = activeTab && activeTab.getAttribute('href') === '#active-members';
        const currentPanel = document.querySelector('.members-tabs__panel:not(.members-tabs__panel--hidden)');
        const resultsList = currentPanel ? currentPanel.querySelector('.civicone-results-list') : null;
        const emptyState = currentPanel ? currentPanel.querySelector('.civicone-empty-state') : null;
        const resultsCount = currentPanel ? currentPanel.querySelector('.moj-action-bar__filter .govuk-body') : null;

        if (!resultsList) {
            console.error('Results list not found');
            return;
        }

        // Build API URL
        const params = new URLSearchParams();
        if (query) params.append('q', query);
        if (isActiveTab) params.append('active', 'true');
        params.append('limit', '30');

        fetch(`${basePath}/api/members?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Search request failed');
                }
                return response.json();
            })
            .then(data => {
                if (data.data) {
                    updateResults(data, resultsList, emptyState, resultsCount);
                } else {
                    console.error('Invalid response format:', data);
                    showErrorMessage(resultsList, 'Invalid response from server');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                showErrorMessage(resultsList, 'Unable to complete search. Please try again.');
            })
            .finally(() => {
                if (spinner) spinner.classList.add('civicone-spinner--hidden');
            });
    }

    function updateResults(data, resultsList, emptyState, resultsCount) {
        const members = data.data || [];
        const total = data.meta?.total || members.length;
        const showing = members.length;

        // Update results count
        if (resultsCount) {
            resultsCount.innerHTML = `Showing <strong>${showing}</strong> of <strong>${total}</strong> members`;
        }

        // Update results list
        if (members.length > 0) {
            resultsList.innerHTML = members.map(renderMemberItem).join('');
            if (emptyState) emptyState.classList.add('civicone-empty-state--hidden');
        } else {
            resultsList.innerHTML = '';
            if (emptyState) emptyState.classList.remove('civicone-empty-state--hidden');
        }

        // Announce to screen readers
        announceToScreenReader(`Found ${showing} members`);
    }

    function renderMemberItem(member) {
        const basePath = window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const hasAvatar = member.avatar_url && member.avatar_url.trim() !== '';
        const lastActive = member.last_active_at ? new Date(member.last_active_at) : null;
        const fiveMinutesAgo = new Date(Date.now() - 5 * 60 * 1000);
        const isOnline = lastActive && lastActive > fiveMinutesAgo;
        const displayName = member.display_name || member.name || member.username || 'Member';
        const location = member.location || '';

        return `
            <li class="civicone-member-item">
                <div class="civicone-member-item__avatar">
                    ${hasAvatar
                        ? `<img src="${member.avatar_url}" alt="" class="civicone-avatar">`
                        : `<div class="civicone-avatar civicone-avatar--placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>`
                    }
                    ${isOnline ? '<span class="civicone-status-indicator civicone-status-indicator--online" title="Active now" aria-label="Currently online"></span>' : ''}
                </div>
                <div class="civicone-member-item__content">
                    <h3 class="civicone-member-item__name">
                        <a href="${basePath}/profile/${member.id}" class="civicone-link">
                            ${displayName}
                        </a>
                    </h3>
                    ${location ? `<p class="civicone-member-item__meta">
                        <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        ${location}
                    </p>` : ''}
                </div>
                <div class="civicone-member-item__actions">
                    <a href="${basePath}/profile/${member.id}" class="civicone-button civicone-button--secondary">
                        View profile
                    </a>
                </div>
            </li>
        `;
    }

    function showErrorMessage(resultsList, message) {
        resultsList.innerHTML = `
            <li class="civicone-member-item" style="text-align: center; padding: 2rem;">
                <p style="color: var(--color-error, #d4351c); margin: 0;">${message}</p>
            </li>
        `;
    }

    // ==================================================
    // Tabs Component (v1.6.0 - Updated for new structure)
    // ==================================================

    function initializeTabs() {
        const tabsContainer = document.querySelector('.members-tabs');
        if (!tabsContainer) return;

        const tabs = tabsContainer.querySelectorAll('.members-tabs__link');
        const panels = document.querySelectorAll('.members-tabs__panel');

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', function (e) {
                e.preventDefault();

                // Update URL without reload
                const href = tab.getAttribute('href');
                const tabId = href.replace('#', '');
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId.replace('-members', ''));
                window.history.pushState({}, '', url);

                // Update tabs
                tabs.forEach(t => {
                    t.setAttribute('aria-selected', 'false');
                    t.setAttribute('tabindex', '-1');
                    t.parentElement.classList.remove('members-tabs__item--selected');
                });

                tab.setAttribute('aria-selected', 'true');
                tab.removeAttribute('tabindex');
                tab.parentElement.classList.add('members-tabs__item--selected');

                // Update panels
                panels.forEach(panel => {
                    panel.classList.add('members-tabs__panel--hidden');
                });

                const targetPanel = document.querySelector(href);
                if (targetPanel) {
                    targetPanel.classList.remove('members-tabs__panel--hidden');
                }

                // Focus the tab for accessibility
                tab.focus();
            });

            // Keyboard navigation
            tab.addEventListener('keydown', function (e) {
                let newIndex = index;

                switch (e.key) {
                    case 'ArrowLeft':
                        newIndex = index > 0 ? index - 1 : tabs.length - 1;
                        break;
                    case 'ArrowRight':
                        newIndex = index < tabs.length - 1 ? index + 1 : 0;
                        break;
                    case 'Home':
                        newIndex = 0;
                        break;
                    case 'End':
                        newIndex = tabs.length - 1;
                        break;
                    default:
                        return;
                }

                e.preventDefault();
                tabs[newIndex].click();
            });
        });
    }

    // ==================================================
    // View Toggle (List / Grid)
    // ==================================================

    function initializeViewToggle() {
        const viewToggles = document.querySelectorAll('.civicone-view-toggle');

        viewToggles.forEach(toggleGroup => {
            const buttons = toggleGroup.querySelectorAll('.civicone-view-toggle__button');
            const resultsList = document.querySelector('.members-tabs__panel:not(.members-tabs__panel--hidden) .civicone-results-list');

            if (!resultsList) return;

            // Restore saved view preference
            const savedView = localStorage.getItem('civicone-members-view') || 'list';

            if (savedView === 'grid') {
                resultsList.classList.add('civicone-results-list--grid');
                buttons.forEach(btn => {
                    const isActive = btn.dataset.view === 'grid';
                    btn.classList.toggle('civicone-view-toggle__button--active', isActive);
                    btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
                });
            }

            buttons.forEach(button => {
                button.addEventListener('click', function () {
                    const view = button.dataset.view;

                    // Update buttons
                    buttons.forEach(btn => {
                        const isActive = btn.dataset.view === view;
                        btn.classList.toggle('civicone-view-toggle__button--active', isActive);
                        btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
                    });

                    // Update all results lists in all tab panels
                    document.querySelectorAll('.civicone-results-list').forEach(list => {
                        if (view === 'grid') {
                            list.classList.add('civicone-results-list--grid');
                        } else {
                            list.classList.remove('civicone-results-list--grid');
                        }
                    });

                    // Save preference
                    localStorage.setItem('civicone-members-view', view);

                    // Announce to screen readers
                    announceToScreenReader(`Switched to ${view} view`);
                });
            });
        });
    }

    // ==================================================
    // Accessibility: Screen Reader Announcements
    // ==================================================

    function announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('role', 'status');
        announcement.setAttribute('aria-live', 'polite');
        announcement.classList.add('govuk-visually-hidden');
        announcement.textContent = message;

        document.body.appendChild(announcement);

        // Remove after announcement
        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }

    // ==================================================
    // Initialize on DOM Ready
    // ==================================================

    function init() {
        initializeMobileFilter(); // MOJ Filter mobile toggle with bottom sheet
        initializeSearch(); // Single search input
        initializeTabs(); // Tabs with new structure
        initializeViewToggle(); // List/Grid view toggle
        console.warn('CivicOne Members Directory v1.6.0 initialized (GOV.UK/MOJ compliant)');
    }

    // ==================================================
    // Clear Button Functionality
    // ==================================================

    function initializeClearButton() {
        const searchInput = document.getElementById('member-search-main');
        const clearButton = document.querySelector('.members-search-bar__clear');

        if (!searchInput || !clearButton) return;

        // Show/hide clear button based on input value
        function toggleClearButton() {
            if (searchInput.value.trim().length > 0) {
                clearButton.classList.remove('hidden');
            } else {
                clearButton.classList.add('hidden');
            }
        }

        // Handle clear button click
        clearButton.addEventListener('click', function () {
            searchInput.value = '';
            searchInput.focus();
            toggleClearButton();

            // Trigger search to refresh results
            const event = new Event('input', { bubbles: true });
            searchInput.dispatchEvent(event);
        });

        // Toggle visibility on input
        searchInput.addEventListener('input', toggleClearButton);

        // Initial state
        toggleClearButton();
    }

    // ==================================================
    // Skeleton Screens for Loading States
    // ==================================================

    function showSkeletonScreens() {
        const skeleton = document.querySelector('.members-skeleton');
        const resultsList = document.querySelector('.civicone-results-list');

        if (skeleton) {
            skeleton.classList.remove('hidden');
        }
        if (resultsList) {
            resultsList.classList.add('loading');
        }
    }

    function hideSkeletonScreens() {
        const skeleton = document.querySelector('.members-skeleton');
        const resultsList = document.querySelector('.civicone-results-list');

        if (skeleton) {
            skeleton.classList.add('hidden');
        }
        if (resultsList) {
            resultsList.classList.remove('loading');
        }
    }

    // Override existing search function to use skeleton screens
    const originalSearchInput = document.getElementById('member-search-main');
    if (originalSearchInput) {
        let searchTimeout;
        originalSearchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            showSkeletonScreens();

            searchTimeout = setTimeout(function () {
                hideSkeletonScreens();
            }, 500);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Initialize new features
    function initNewFeatures() {
        initializeClearButton();
        // Other initialization code...
    }

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function () {
        const url = new URL(window.location);
        const tab = url.searchParams.get('tab') || 'all';
        const tabLink = document.querySelector(`.members-tabs__link[href="#${tab}-members"]`);
        if (tabLink) {
            tabLink.click();
        }
    });

})();
