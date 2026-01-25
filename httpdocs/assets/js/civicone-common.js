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
                banner.style.transition = 'opacity 0.5s ease';
                banner.style.opacity = '0';
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
            tab.addEventListener('click', function() {
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
    window.CivicOne.initFilterTabs = initFilterTabs;

})();
