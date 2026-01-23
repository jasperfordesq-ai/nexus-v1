/**
 * Admin Sidebar Navigation
 * Handles collapse/expand, mobile drawer, and keyboard navigation
 */
(function() {
    'use strict';

    var STORAGE_KEY_COLLAPSED = 'admin_sidebar_collapsed';
    var STORAGE_KEY_EXPANDED_SECTIONS = 'admin_sidebar_expanded_sections';
    var MOBILE_BREAKPOINT = 1024;

    var AdminSidebar = {
        sidebar: null,
        backdrop: null,
        layout: null,
        toggleBtn: null,
        mobileBtn: null,
        isCollapsed: false,
        isMobileOpen: false,
        expandedSections: [],

        /**
         * Initialize the sidebar
         */
        init: function() {
            this.sidebar = document.querySelector('.admin-sidebar');
            this.backdrop = document.querySelector('.admin-sidebar-backdrop');
            this.layout = document.querySelector('.admin-layout');
            this.toggleBtn = document.querySelector('.admin-sidebar-toggle');
            this.mobileBtn = document.getElementById('adminMobileBtn');

            if (!this.sidebar || !this.layout) {
                return;
            }

            this.loadState();
            this.applyState();
            this.bindEvents();
            this.setupKeyboard();
            this.setupNavigationLoading();
            this.handleResize();

            // Mark as initialized
            this.layout.classList.add('sidebar-initialized');
        },

        /**
         * Load state from localStorage
         */
        loadState: function() {
            try {
                // Default to expanded (not collapsed)
                var storedCollapsed = localStorage.getItem(STORAGE_KEY_COLLAPSED);
                this.isCollapsed = storedCollapsed === 'true' ? true : false;
                var savedSections = localStorage.getItem(STORAGE_KEY_EXPANDED_SECTIONS);
                this.expandedSections = savedSections ? JSON.parse(savedSections) : [];
            } catch (e) {
                this.isCollapsed = false;
                this.expandedSections = [];
            }
        },

        /**
         * Save state to localStorage
         */
        saveState: function() {
            try {
                localStorage.setItem(STORAGE_KEY_COLLAPSED, this.isCollapsed);
                localStorage.setItem(STORAGE_KEY_EXPANDED_SECTIONS, JSON.stringify(this.expandedSections));
            } catch (e) {
                // localStorage not available
            }
        },

        /**
         * Apply current state to DOM
         */
        applyState: function() {
            // Apply collapsed state
            if (this.isCollapsed) {
                this.layout.classList.add('sidebar-collapsed');
            } else {
                this.layout.classList.remove('sidebar-collapsed');
            }

            // Update footer button label
            var footerLabel = this.sidebar.querySelector('.admin-sidebar-footer-label');
            if (footerLabel) {
                footerLabel.textContent = this.isCollapsed ? 'Expand' : 'Collapse';
            }

            // Apply expanded sections
            var sections = this.sidebar.querySelectorAll('.admin-sidebar-section');
            var self = this;
            sections.forEach(function(section) {
                var key = section.getAttribute('data-section');
                var header = section.querySelector('.admin-sidebar-section-header');
                if (self.expandedSections.indexOf(key) !== -1) {
                    section.classList.add('expanded');
                    if (header) header.setAttribute('aria-expanded', 'true');
                } else {
                    section.classList.remove('expanded');
                    if (header) header.setAttribute('aria-expanded', 'false');
                }
            });
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            var self = this;

            // Toggle button (collapse/expand)
            if (this.toggleBtn) {
                this.toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggle();
                });
            }

            // Mobile menu button
            if (this.mobileBtn) {
                this.mobileBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleMobile();
                });
            }

            // Backdrop click (close mobile)
            if (this.backdrop) {
                this.backdrop.addEventListener('click', function() {
                    self.closeMobile();
                });
            }

            // Section headers (expand/collapse)
            var sectionHeaders = this.sidebar.querySelectorAll('.admin-sidebar-section-header');
            sectionHeaders.forEach(function(header) {
                header.addEventListener('click', function(e) {
                    // On touch devices in collapsed mode, toggle flyout visibility
                    if (window.innerWidth > MOBILE_BREAKPOINT && self.isCollapsed) {
                        if (self.isTouchDevice()) {
                            e.preventDefault();
                            e.stopPropagation();
                            self.toggleFlyout(header.closest('.admin-sidebar-section'));
                            return;
                        }
                        return; // Let CSS hover handle it on non-touch
                    }
                    e.preventDefault();
                    var section = header.closest('.admin-sidebar-section');
                    self.toggleSection(section);
                });
            });

            // Close mobile on link click
            var links = this.sidebar.querySelectorAll('a');
            links.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= MOBILE_BREAKPOINT) {
                        self.closeMobile();
                    }
                });
            });

            // Window resize
            window.addEventListener('resize', function() {
                self.handleResize();
            });

            // Close flyouts when clicking outside (for touch devices)
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.admin-sidebar-section')) {
                    self.closeAllFlyouts();
                }
            });

            // Close flyouts when navigating via flyout links
            var flyoutLinks = this.sidebar.querySelectorAll('.admin-sidebar-flyout a');
            flyoutLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    self.closeAllFlyouts();
                });
            });
        },

        /**
         * Toggle sidebar collapsed state
         */
        toggle: function() {
            this.isCollapsed = !this.isCollapsed;
            this.applyState();
            this.saveState();
        },

        /**
         * Toggle mobile drawer
         */
        toggleMobile: function() {
            if (this.isMobileOpen) {
                this.closeMobile();
            } else {
                this.openMobile();
            }
        },

        /**
         * Open mobile drawer
         */
        openMobile: function() {
            this.isMobileOpen = true;
            this.sidebar.classList.add('mobile-open');
            if (this.backdrop) {
                this.backdrop.classList.add('visible');
            }
            document.body.style.overflow = 'hidden';

            // Update hamburger icon
            if (this.mobileBtn) {
                var icon = this.mobileBtn.querySelector('i');
                if (icon) {
                    icon.className = 'fa-solid fa-times';
                }
            }
        },

        /**
         * Close mobile drawer
         */
        closeMobile: function() {
            this.isMobileOpen = false;
            this.sidebar.classList.remove('mobile-open');
            if (this.backdrop) {
                this.backdrop.classList.remove('visible');
            }
            document.body.style.overflow = '';

            // Update hamburger icon
            if (this.mobileBtn) {
                var icon = this.mobileBtn.querySelector('i');
                if (icon) {
                    icon.className = 'fa-solid fa-bars';
                }
            }
        },

        /**
         * Toggle a section's expanded state
         */
        toggleSection: function(section) {
            var key = section.getAttribute('data-section');
            var isExpanded = section.classList.contains('expanded');
            var header = section.querySelector('.admin-sidebar-section-header');

            if (isExpanded) {
                section.classList.remove('expanded');
                if (header) header.setAttribute('aria-expanded', 'false');
                var idx = this.expandedSections.indexOf(key);
                if (idx !== -1) {
                    this.expandedSections.splice(idx, 1);
                }
            } else {
                section.classList.add('expanded');
                if (header) header.setAttribute('aria-expanded', 'true');
                if (this.expandedSections.indexOf(key) === -1) {
                    this.expandedSections.push(key);
                }
            }

            this.saveState();
        },

        /**
         * Check if device supports touch
         */
        isTouchDevice: function() {
            return ('ontouchstart' in window) ||
                   (navigator.maxTouchPoints > 0) ||
                   (navigator.msMaxTouchPoints > 0);
        },

        /**
         * Toggle flyout visibility (for touch devices)
         */
        toggleFlyout: function(section) {
            var isOpen = section.classList.contains('flyout-open');

            // Close all other flyouts first
            var allSections = this.sidebar.querySelectorAll('.admin-sidebar-section.flyout-open');
            allSections.forEach(function(s) {
                s.classList.remove('flyout-open');
            });

            // Toggle this one
            if (!isOpen) {
                section.classList.add('flyout-open');
            }
        },

        /**
         * Close all flyouts
         */
        closeAllFlyouts: function() {
            var allSections = this.sidebar.querySelectorAll('.admin-sidebar-section.flyout-open');
            allSections.forEach(function(s) {
                s.classList.remove('flyout-open');
            });
        },

        /**
         * Expand a section by key
         */
        expandSection: function(key) {
            var section = this.sidebar.querySelector('[data-section="' + key + '"]');
            if (section && !section.classList.contains('expanded')) {
                this.toggleSection(section);
            }
        },

        /**
         * Collapse a section by key
         */
        collapseSection: function(key) {
            var section = this.sidebar.querySelector('[data-section="' + key + '"]');
            if (section && section.classList.contains('expanded')) {
                this.toggleSection(section);
            }
        },

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboard: function() {
            var self = this;

            document.addEventListener('keydown', function(e) {
                // Don't trigger in input fields
                if (e.target.matches('input, textarea, select, [contenteditable]')) {
                    return;
                }

                // [ key to toggle sidebar
                if (e.key === '[' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    e.preventDefault();
                    if (window.innerWidth > MOBILE_BREAKPOINT) {
                        self.toggle();
                    } else {
                        self.toggleMobile();
                    }
                }

                // Escape to close mobile
                if (e.key === 'Escape' && self.isMobileOpen) {
                    self.closeMobile();
                }

                // Arrow key navigation within sidebar
                if (e.target.closest('.admin-sidebar')) {
                    self.handleArrowNavigation(e);
                }
            });
        },

        /**
         * Handle arrow key navigation within sidebar
         */
        handleArrowNavigation: function(e) {
            var focusableItems = this.sidebar.querySelectorAll(
                '.admin-sidebar-single, .admin-sidebar-section-header, .admin-sidebar-item, .admin-sidebar-super-admin, .admin-sidebar-logo'
            );
            var focusable = Array.from(focusableItems).filter(function(el) {
                return el.offsetParent !== null; // Only visible elements
            });

            var currentIndex = focusable.indexOf(document.activeElement);
            if (currentIndex === -1) return;

            var handled = false;

            if (e.key === 'ArrowDown') {
                if (currentIndex < focusable.length - 1) {
                    focusable[currentIndex + 1].focus();
                    handled = true;
                }
            } else if (e.key === 'ArrowUp') {
                if (currentIndex > 0) {
                    focusable[currentIndex - 1].focus();
                    handled = true;
                }
            } else if (e.key === 'ArrowRight') {
                // Expand section if on section header
                var section = document.activeElement.closest('.admin-sidebar-section');
                if (section && !section.classList.contains('expanded')) {
                    this.toggleSection(section);
                    handled = true;
                }
            } else if (e.key === 'ArrowLeft') {
                // Collapse section if on section header
                const sectionLeft = document.activeElement.closest('.admin-sidebar-section');
                if (sectionLeft && sectionLeft.classList.contains('expanded')) {
                    this.toggleSection(sectionLeft);
                    handled = true;
                }
            } else if (e.key === 'Enter' || e.key === ' ') {
                // Toggle section or follow link
                if (document.activeElement.classList.contains('admin-sidebar-section-header')) {
                    const sectionEnter = document.activeElement.closest('.admin-sidebar-section');
                    if (sectionEnter) {
                        this.toggleSection(sectionEnter);
                        handled = true;
                    }
                }
            }

            if (handled) {
                e.preventDefault();
            }
        },

        /**
         * Handle window resize
         */
        handleResize: function() {
            var isMobile = window.innerWidth <= MOBILE_BREAKPOINT;

            if (isMobile && this.isMobileOpen) {
                // Keep mobile state
            } else if (isMobile) {
                // Close mobile drawer on resize to mobile
                this.closeMobile();
            } else {
                // Desktop - ensure mobile drawer is closed
                this.sidebar.classList.remove('mobile-open');
                if (this.backdrop) {
                    this.backdrop.classList.remove('visible');
                }
                document.body.style.overflow = '';
            }
        },

        /**
         * Show loading state during navigation
         */
        showNavigating: function() {
            if (this.layout) {
                this.layout.classList.add('navigating');
            }
        },

        /**
         * Hide loading state
         */
        hideNavigating: function() {
            if (this.layout) {
                this.layout.classList.remove('navigating');
            }
        },

        /**
         * Setup navigation loading indicators
         */
        setupNavigationLoading: function() {
            var self = this;

            // Add loading state when clicking sidebar links
            var navLinks = this.sidebar.querySelectorAll('a[href]:not([href^="#"]):not([href^="javascript"])');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    // Don't show loading for external links or same-page
                    var href = link.getAttribute('href');
                    if (href && !href.startsWith('#') && !link.target) {
                        self.showNavigating();
                    }
                });
            });

            // Hide loading when page is fully loaded (for back/forward navigation)
            window.addEventListener('pageshow', function() {
                self.hideNavigating();
            });
        },

        /**
         * Expand section containing active item and scroll into view
         */
        expandActiveSection: function() {
            var self = this;
            var activeItem = this.sidebar.querySelector('.admin-sidebar-item.active');

            if (activeItem) {
                var section = activeItem.closest('.admin-sidebar-section');
                if (section) {
                    var key = section.getAttribute('data-section');
                    // Force expand even if not in saved state (current page takes priority)
                    if (!section.classList.contains('expanded')) {
                        section.classList.add('expanded');
                        var header = section.querySelector('.admin-sidebar-section-header');
                        if (header) header.setAttribute('aria-expanded', 'true');
                        // Add to expanded sections if not already there
                        if (this.expandedSections.indexOf(key) === -1) {
                            this.expandedSections.push(key);
                            this.saveState();
                        }
                    }
                }

                // Scroll active item into view after a brief delay (allow expansion animation)
                setTimeout(function() {
                    var nav = self.sidebar.querySelector('.admin-sidebar-nav');
                    if (nav && activeItem) {
                        var itemRect = activeItem.getBoundingClientRect();
                        var navRect = nav.getBoundingClientRect();

                        // Check if item is outside visible area
                        if (itemRect.top < navRect.top || itemRect.bottom > navRect.bottom) {
                            activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                }, 100);
            }
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            AdminSidebar.init();
            AdminSidebar.expandActiveSection();
        });
    } else {
        AdminSidebar.init();
        AdminSidebar.expandActiveSection();
    }

    // Expose globally
    window.AdminSidebar = AdminSidebar;
})();
