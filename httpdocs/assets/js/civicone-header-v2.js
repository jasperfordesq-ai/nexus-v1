/**
 * CivicOne Header v2.1 JavaScript
 *
 * COMPLETE REBUILD - Pure GOV.UK structure (no utility bar)
 *
 * Based on GOV.UK Frontend service-navigation.mjs
 * https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/service-navigation/service-navigation.mjs
 *
 * Includes:
 * 1. Service Navigation (mobile toggle)
 * 2. More Dropdown (categorized nav)
 */

(function() {
    'use strict';

    var TABLET_BREAKPOINT = '641px';

    /* ============================================
       1. SERVICE NAVIGATION (Mobile Toggle)
       ============================================ */
    function ServiceNavigation(element) {
        this.element = element;
        this.menuButton = element.querySelector('.govuk-service-navigation__toggle');

        if (!this.menuButton) {
            return;
        }

        var menuId = this.menuButton.getAttribute('aria-controls');
        this.menu = document.getElementById(menuId);

        if (!this.menu) {
            return;
        }

        this.menuIsOpen = false;
        this.mql = null;

        this.setupResponsiveChecks();
        this.menuButton.addEventListener('click', this.handleMenuButtonClick.bind(this));
    }

    ServiceNavigation.prototype.setupResponsiveChecks = function() {
        this.mql = window.matchMedia('(min-width: ' + TABLET_BREAKPOINT + ')');

        if ('addEventListener' in this.mql) {
            this.mql.addEventListener('change', this.checkMode.bind(this));
        } else {
            this.mql.addListener(this.checkMode.bind(this));
        }

        this.checkMode();
    };

    ServiceNavigation.prototype.checkMode = function() {
        if (this.mql.matches) {
            // Desktop: show menu, hide button
            this.menu.removeAttribute('hidden');
            this.menuButton.setAttribute('hidden', '');
            this.menuButton.setAttribute('aria-hidden', 'true');
        } else {
            // Mobile: show button, toggle menu
            this.menuButton.removeAttribute('hidden');
            this.menuButton.removeAttribute('aria-hidden');
            this.menuButton.setAttribute('aria-expanded', this.menuIsOpen.toString());

            if (this.menuIsOpen) {
                this.menu.removeAttribute('hidden');
            } else {
                this.menu.setAttribute('hidden', '');
            }
        }
    };

    ServiceNavigation.prototype.handleMenuButtonClick = function() {
        this.menuIsOpen = !this.menuIsOpen;
        this.checkMode();
    };

    /* ============================================
       2. MORE DROPDOWN (Categorized Nav)
       ============================================ */
    function MoreDropdown() {
        this.container = document.querySelector('.civicone-nav-more');

        if (!this.container) {
            return;
        }

        this.trigger = this.container.querySelector('.civicone-nav-more__btn');
        this.panel = this.container.querySelector('.civicone-nav-dropdown');

        if (!this.trigger || !this.panel) {
            return;
        }

        this.init();
    }

    MoreDropdown.prototype.init = function() {
        var self = this;

        this.trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.toggle();
        });

        this.trigger.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                self.toggle();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                self.open();
            } else if (e.key === 'Escape') {
                self.close(true);
            }
        });

        this.panel.addEventListener('keydown', function(e) {
            self.handlePanelKeydown(e);
        });

        document.addEventListener('click', function(e) {
            if (!self.container.contains(e.target)) {
                self.close(false);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                self.close(true);
            }
        });
    };

    MoreDropdown.prototype.toggle = function() {
        var isOpen = this.trigger.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
            this.close(true);
        } else {
            this.open();
        }
    };

    MoreDropdown.prototype.open = function() {
        this.trigger.setAttribute('aria-expanded', 'true');
        this.panel.removeAttribute('hidden');

        var firstLink = this.panel.querySelector('a');
        if (firstLink) {
            firstLink.focus();
        }
    };

    MoreDropdown.prototype.close = function(returnFocus) {
        this.trigger.setAttribute('aria-expanded', 'false');
        this.panel.setAttribute('hidden', '');

        if (returnFocus) {
            this.trigger.focus();
        }
    };

    MoreDropdown.prototype.handlePanelKeydown = function(e) {
        var links = this.panel.querySelectorAll('a');
        var currentIndex = Array.from(links).indexOf(document.activeElement);

        switch (e.key) {
            case 'Escape':
                e.preventDefault();
                this.close(true);
                break;
            case 'ArrowDown':
                e.preventDefault();
                if (currentIndex < links.length - 1) {
                    links[currentIndex + 1].focus();
                } else {
                    links[0].focus();
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (currentIndex > 0) {
                    links[currentIndex - 1].focus();
                } else {
                    links[links.length - 1].focus();
                }
                break;
            case 'Home':
                e.preventDefault();
                links[0].focus();
                break;
            case 'End':
                e.preventDefault();
                links[links.length - 1].focus();
                break;
            case 'Tab':
                this.close(false);
                break;
        }
    };

    /* ============================================
       INITIALIZATION
       ============================================ */
    function init() {
        // Service Navigation
        var navElements = document.querySelectorAll('[data-module="govuk-service-navigation"]');
        navElements.forEach(function(el) {
            new ServiceNavigation(el);
        });

        // More Dropdown
        new MoreDropdown();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export for testing
    window.CivicOneHeader = {
        ServiceNavigation: ServiceNavigation,
        MoreDropdown: MoreDropdown
    };
})();
