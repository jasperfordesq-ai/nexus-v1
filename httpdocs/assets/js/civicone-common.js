/**
 * CivicOne Common JavaScript
 * Shared functionality for GOV.UK Design System pages
 *
 * Extracted from inline scripts 2026-01-25
 * Per CLAUDE.md: No inline script blocks
 */

(function() {
    'use strict';

    /**
     * Offline Banner Handler
     * Shows/hides the offline notification banner based on network status
     * Used by: Federation pages, Volunteering pages
     */
    function initOfflineBanner() {
        var banner = document.getElementById('offlineBanner');
        if (!banner) return;

        function updateOffline(offline) {
            banner.classList.toggle('govuk-!-display-none', !offline);
            // Haptic feedback on mobile when going offline
            if (offline && navigator.vibrate) {
                navigator.vibrate(100);
            }
        }

        window.addEventListener('online', function() { updateOffline(false); });
        window.addEventListener('offline', function() { updateOffline(true); });

        // Check initial state
        if (!navigator.onLine) {
            updateOffline(true);
        }
    }

    /**
     * Form Offline Protection
     * Prevents form submission when offline
     * Used by: Volunteering edit forms
     */
    function initFormOfflineProtection() {
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!navigator.onLine) {
                    e.preventDefault();
                    alert('You are offline. Please connect to the internet to save changes.');
                }
            });
        });
    }

    /**
     * Scroll to Bottom
     * Scrolls a container to the bottom (for chat/message views)
     * Used by: Messages thread, Federation messages
     */
    function initScrollToBottom() {
        var chatBox = document.getElementById('chat-messages');
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }

    /**
     * Auto-dismiss Notification Banners
     * Fades out success banners after a delay
     * Used by: Various pages with flash messages
     */
    function initAutoDismissBanners() {
        var successBanners = document.querySelectorAll('.govuk-notification-banner--success[data-auto-dismiss]');
        successBanners.forEach(function(banner) {
            var delay = parseInt(banner.getAttribute('data-auto-dismiss'), 10) || 5000;
            setTimeout(function() {
                // Use CSS classes instead of inline styles
                banner.classList.add('js-transition-opacity-slow', 'js-banner-fade-out');
                setTimeout(function() {
                    banner.remove();
                }, 500);
            }, delay);
        });
    }

    /**
     * Confirm Delete Actions
     * Adds confirmation dialog to delete buttons/forms
     * Used by: Various delete actions
     */
    function initConfirmDelete() {
        document.querySelectorAll('[data-confirm-delete]').forEach(function(el) {
            el.addEventListener('click', function(e) {
                var message = el.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Character Counter
     * Shows remaining characters for textareas with maxlength
     * Used by: Post composition, message forms
     */
    function initCharacterCounters() {
        document.querySelectorAll('textarea[maxlength]').forEach(function(textarea) {
            var maxLength = parseInt(textarea.getAttribute('maxlength'), 10);
            var counterId = textarea.id + '-counter';
            var counter = document.getElementById(counterId);

            if (!counter) return;

            function updateCounter() {
                var remaining = maxLength - textarea.value.length;
                counter.textContent = remaining + ' characters remaining';
                counter.classList.toggle('govuk-hint', remaining > 20);
                counter.classList.toggle('govuk-error-message', remaining <= 20);
            }

            textarea.addEventListener('input', updateCounter);
            updateCounter();
        });
    }

    /**
     * Smooth Scroll for Anchor Links
     * Enables smooth scrolling for all in-page anchor links
     * Used by: Federation Help, FAQ pages
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                if (href.length > 1) {
                    var target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        target.focus();
                    }
                }
            });
        });
    }

    /**
     * GOV.UK Tabs Component
     * Full implementation based on GOV.UK Frontend v5.x
     * Source: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/tabs/tabs.mjs
     * Used by: Groups show, Profile pages, any page with .govuk-tabs
     */
    function initGovukTabs() {
        var tabContainers = document.querySelectorAll('[data-module="govuk-tabs"], .govuk-tabs');

        tabContainers.forEach(function(container) {
            var tabs = container.querySelectorAll('.govuk-tabs__tab');
            var panels = container.querySelectorAll('.govuk-tabs__panel');

            if (!tabs.length || !panels.length) return;

            // Set up ARIA attributes
            var tabList = container.querySelector('.govuk-tabs__list');
            if (tabList) {
                tabList.setAttribute('role', 'tablist');
            }

            tabs.forEach(function(tab, index) {
                var panelId = tab.getAttribute('href');
                if (!panelId || panelId.charAt(0) !== '#') return;

                var panel = container.querySelector(panelId);
                if (!panel) return;

                // Set up tab attributes
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-controls', panelId.substring(1));
                tab.setAttribute('tabindex', index === 0 ? '0' : '-1');

                // Set up panel attributes
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', tab.id || 'tab-' + panelId.substring(1));

                // Give tab an ID if it doesn't have one
                if (!tab.id) {
                    tab.id = 'tab-' + panelId.substring(1);
                }

                // Set initial state
                var listItem = tab.closest('.govuk-tabs__list-item');
                var isSelected = listItem && listItem.classList.contains('govuk-tabs__list-item--selected');

                tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');

                if (!isSelected) {
                    panel.classList.add('govuk-tabs__panel--hidden');
                } else {
                    panel.classList.remove('govuk-tabs__panel--hidden');
                }
            });

            // Handle tab clicks
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    activateTab(container, tab);
                });

                // Keyboard navigation
                tab.addEventListener('keydown', function(e) {
                    var tabArray = Array.from(tabs);
                    var currentIndex = tabArray.indexOf(tab);
                    var newIndex;

                    switch (e.key) {
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            e.preventDefault();
                            newIndex = currentIndex - 1;
                            if (newIndex < 0) newIndex = tabArray.length - 1;
                            tabArray[newIndex].focus();
                            activateTab(container, tabArray[newIndex]);
                            break;
                        case 'ArrowRight':
                        case 'ArrowDown':
                            e.preventDefault();
                            newIndex = currentIndex + 1;
                            if (newIndex >= tabArray.length) newIndex = 0;
                            tabArray[newIndex].focus();
                            activateTab(container, tabArray[newIndex]);
                            break;
                        case 'Home':
                            e.preventDefault();
                            tabArray[0].focus();
                            activateTab(container, tabArray[0]);
                            break;
                        case 'End':
                            e.preventDefault();
                            tabArray[tabArray.length - 1].focus();
                            activateTab(container, tabArray[tabArray.length - 1]);
                            break;
                    }
                });
            });

            // Check for hash in URL to activate specific tab
            if (window.location.hash) {
                var hashTab = container.querySelector('.govuk-tabs__tab[href="' + window.location.hash + '"]');
                if (hashTab) {
                    activateTab(container, hashTab);
                }
            }
        });

        function activateTab(container, selectedTab) {
            var tabs = container.querySelectorAll('.govuk-tabs__tab');
            var panels = container.querySelectorAll('.govuk-tabs__panel');

            // Deactivate all tabs
            tabs.forEach(function(tab) {
                var listItem = tab.closest('.govuk-tabs__list-item');
                if (listItem) {
                    listItem.classList.remove('govuk-tabs__list-item--selected');
                }
                tab.setAttribute('aria-selected', 'false');
                tab.setAttribute('tabindex', '-1');
            });

            // Hide all panels
            panels.forEach(function(panel) {
                panel.classList.add('govuk-tabs__panel--hidden');
            });

            // Activate selected tab
            var selectedListItem = selectedTab.closest('.govuk-tabs__list-item');
            if (selectedListItem) {
                selectedListItem.classList.add('govuk-tabs__list-item--selected');
            }
            selectedTab.setAttribute('aria-selected', 'true');
            selectedTab.setAttribute('tabindex', '0');

            // Show selected panel
            var panelId = selectedTab.getAttribute('href');
            if (panelId && panelId.charAt(0) === '#') {
                var panel = container.querySelector(panelId);
                if (panel) {
                    panel.classList.remove('govuk-tabs__panel--hidden');
                }
            }

            // Update URL hash without scrolling
            if (panelId && history.pushState) {
                history.pushState(null, null, panelId);
            }
        }
    }

    /**
     * Filter Tabs Handler
     * Handles GOV.UK-style filter tabs with data-filter attribute
     * Used by: Activity page, listings filter
     */
    function initFilterTabs() {
        var filterTabs = document.querySelectorAll('.govuk-tabs__tab[data-filter]');
        if (!filterTabs.length) return;

        var listId = document.querySelector('[data-filter-list]');
        var filterItems = listId ? listId.querySelectorAll('[data-type]') : document.querySelectorAll('#activity-list li[data-type]');

        filterTabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();

                // Update active tab styling
                document.querySelectorAll('.govuk-tabs__list-item').forEach(function(item) {
                    item.classList.remove('govuk-tabs__list-item--selected');
                });
                this.closest('.govuk-tabs__list-item').classList.add('govuk-tabs__list-item--selected');

                // Update ARIA
                filterTabs.forEach(function(t) {
                    t.setAttribute('aria-selected', 'false');
                });
                this.setAttribute('aria-selected', 'true');

                // Filter items
                var filter = this.dataset.filter;
                filterItems.forEach(function(item) {
                    if (filter === 'all' || item.dataset.type === filter) {
                        item.classList.remove('hidden');
                    } else {
                        item.classList.add('hidden');
                    }
                });
            });
        });
    }

    /**
     * Initialize all common functionality
     * Called on DOMContentLoaded
     */
    function init() {
        initOfflineBanner();
        initScrollToBottom();
        initAutoDismissBanners();
        initConfirmDelete();
        initCharacterCounters();
        initSmoothScroll();
        initGovukTabs();
        initFilterTabs();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose functions globally for pages that need specific initialization
    window.CivicOne = window.CivicOne || {};
    window.CivicOne.initOfflineBanner = initOfflineBanner;
    window.CivicOne.initFormOfflineProtection = initFormOfflineProtection;
    window.CivicOne.initScrollToBottom = initScrollToBottom;
    window.CivicOne.initAutoDismissBanners = initAutoDismissBanners;
    window.CivicOne.initConfirmDelete = initConfirmDelete;
    window.CivicOne.initCharacterCounters = initCharacterCounters;
    window.CivicOne.initSmoothScroll = initSmoothScroll;
    window.CivicOne.initGovukTabs = initGovukTabs;
    window.CivicOne.initFilterTabs = initFilterTabs;

})();
