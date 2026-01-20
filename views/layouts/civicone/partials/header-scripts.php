        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Theme Toggle Logic
                var themeToggle = document.getElementById('civic-theme-toggle');
                var body = document.body;

                // Function to apply theme
                function applyTheme(theme) {
                    if (theme === 'dark') {
                        body.classList.add('dark-mode');
                    } else {
                        body.classList.remove('dark-mode');
                    }
                }

                // Check saved preference
                var savedTheme = localStorage.getItem('civic-theme');
                if (savedTheme) {
                    applyTheme(savedTheme);
                }

                // Toggle theme function
                function toggleTheme() {
                    if (body.classList.contains('dark-mode')) {
                        applyTheme('light');
                        localStorage.setItem('civic-theme', 'light');
                    } else {
                        applyTheme('dark');
                        localStorage.setItem('civic-theme', 'dark');
                    }
                }

                if (themeToggle) {
                    themeToggle.addEventListener('click', toggleTheme);
                }

                // Drawer theme toggle (mobile menu)
                var drawerThemeToggle = document.getElementById('civic-drawer-theme-toggle');
                if (drawerThemeToggle) {
                    drawerThemeToggle.addEventListener('click', toggleTheme);
                }

                // ===========================================
                // CURRENT PAGE DETECTION - Highlight active nav
                // ===========================================
                (function() {
                    var currentPath = window.location.pathname;
                    var navLinks = document.querySelectorAll('.civic-nav-link[data-nav-match]');

                    navLinks.forEach(function(link) {
                        var matchPath = link.getAttribute('data-nav-match');

                        // Check for exact match or section match
                        var isActive = false;

                        if (matchPath === '/') {
                            // Home page - exact match only
                            isActive = (currentPath === '/' || currentPath === NEXUS_BASE + '/');
                        } else {
                            // Section match - starts with the path segment
                            var pathSegment = '/' + matchPath;
                            var fullPath = NEXUS_BASE + pathSegment;
                            isActive = currentPath === fullPath || currentPath.startsWith(fullPath + '/');
                        }

                        if (isActive) {
                            link.classList.add('active');
                            link.setAttribute('aria-current', 'page');
                        }
                    });
                })();

                // Mobile Menu Toggle - Now handled by mobile-nav-v2.php
                // The drawer script provides full accessibility support including:
                // - Close button handling
                // - Backdrop click to close
                // - Escape key to close
                // - Focus trapping
                // - Swipe gestures

                // ===========================================
                // MEGA MENU TOGGLE - WCAG 2.1 AA Compliant
                // ===========================================
                var megaMenuBtn = document.getElementById('civic-mega-menu-btn');
                var megaMenu = document.getElementById('civic-mega-menu');

                if (megaMenuBtn && megaMenu) {
                    // Toggle mega menu on click
                    megaMenuBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        if (isExpanded) {
                            closeMegaMenu();
                        } else {
                            openMegaMenu();
                        }
                    });

                    // Open mega menu
                    function openMegaMenu() {
                        megaMenuBtn.setAttribute('aria-expanded', 'true');
                        megaMenuBtn.classList.add('active');
                        megaMenu.classList.add('active');
                        megaMenu.style.display = 'block';

                        // Focus first link in mega menu
                        var firstLink = megaMenu.querySelector('a');
                        if (firstLink) {
                            setTimeout(function() { firstLink.focus(); }, 50);
                        }
                    }

                    // Close mega menu
                    function closeMegaMenu() {
                        megaMenuBtn.setAttribute('aria-expanded', 'false');
                        megaMenuBtn.classList.remove('active');
                        megaMenu.classList.remove('active');
                        megaMenu.style.display = 'none';
                    }

                    // Close on Escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && megaMenu.classList.contains('active')) {
                            closeMegaMenu();
                            megaMenuBtn.focus();
                        }
                    });

                    // Close when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!megaMenu.contains(e.target) && !megaMenuBtn.contains(e.target)) {
                            if (megaMenu.classList.contains('active')) {
                                closeMegaMenu();
                            }
                        }
                    });

                    // Keyboard navigation within mega menu
                    megaMenu.addEventListener('keydown', function(e) {
                        var links = megaMenu.querySelectorAll('a');
                        var currentIndex = Array.from(links).indexOf(document.activeElement);

                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            if (currentIndex < links.length - 1) {
                                links[currentIndex + 1].focus();
                            }
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            if (currentIndex > 0) {
                                links[currentIndex - 1].focus();
                            } else {
                                megaMenuBtn.focus();
                            }
                        } else if (e.key === 'Tab' && e.shiftKey && currentIndex === 0) {
                            // Shift+Tab on first link - close menu and focus button
                            e.preventDefault();
                            closeMegaMenu();
                            megaMenuBtn.focus();
                        }
                    });
                }

                // Mobile Search Toggle
                var mobileSearchToggle = document.getElementById('civic-mobile-search-toggle');
                var mobileSearchBar = document.getElementById('civic-mobile-search-bar');

                if (mobileSearchToggle && mobileSearchBar) {
                    mobileSearchToggle.addEventListener('click', function() {
                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        // Close mobile menu if open
                        if (typeof closeMobileMenu === 'function') {
                            closeMobileMenu();
                        }

                        if (isExpanded) {
                            mobileSearchBar.classList.remove('active');
                            this.setAttribute('aria-expanded', 'false');
                        } else {
                            mobileSearchBar.classList.add('active');
                            this.setAttribute('aria-expanded', 'true');
                            // Focus the search input
                            var searchInput = document.getElementById('mobile-search-input');
                            if (searchInput) searchInput.focus();
                        }
                    });
                }

                // ===========================================
                // Desktop Collapsible Search - Synced with Modern
                // ===========================================
                var searchToggleBtn = document.getElementById('civicSearchToggleBtn');
                var collapsibleSearch = document.getElementById('civicCollapsibleSearch');
                var searchInput = document.getElementById('civicSearchInput');
                var searchCloseBtn = document.getElementById('civicSearchCloseBtn');

                if (searchToggleBtn && collapsibleSearch) {
                    // Open search
                    searchToggleBtn.addEventListener('click', function() {
                        collapsibleSearch.classList.add('active');
                        searchToggleBtn.style.display = 'none';
                        searchToggleBtn.setAttribute('aria-expanded', 'true');
                        if (searchInput) searchInput.focus();
                    });

                    // Close search
                    if (searchCloseBtn) {
                        searchCloseBtn.addEventListener('click', function() {
                            collapsibleSearch.classList.remove('active');
                            searchToggleBtn.style.display = '';
                            searchToggleBtn.setAttribute('aria-expanded', 'false');
                            searchToggleBtn.focus();
                        });
                    }

                    // Close on Escape key
                    if (searchInput) {
                        searchInput.addEventListener('keydown', function(e) {
                            if (e.key === 'Escape') {
                                collapsibleSearch.classList.remove('active');
                                searchToggleBtn.style.display = '';
                                searchToggleBtn.setAttribute('aria-expanded', 'false');
                                searchToggleBtn.focus();
                            }
                        });
                    }
                }

                // ===========================================
                // WCAG 2.1 AA Compliant Dropdown Navigation
                // Supports: Click, Enter, Space, Escape, Arrow Keys
                // ===========================================

                var allDropdowns = document.querySelectorAll('.civic-dropdown button');

                // Helper: Close all dropdowns
                function closeAllDropdowns(exceptTrigger) {
                    allDropdowns.forEach(function(trigger) {
                        if (trigger !== exceptTrigger) {
                            trigger.setAttribute('aria-expanded', 'false');
                            var container = trigger.closest('.civic-dropdown');
                            var content = container ? container.querySelector('.civic-dropdown-content') : null;
                            if (content) content.style.display = 'none';
                        }
                    });
                }

                // Helper: Open dropdown
                function openDropdown(trigger, content) {
                    closeAllDropdowns(trigger);
                    content.style.display = 'block';
                    trigger.setAttribute('aria-expanded', 'true');

                    // Focus first menu item
                    var firstItem = content.querySelector('a, button');
                    if (firstItem) firstItem.focus();
                }

                // Helper: Close dropdown and return focus
                function closeDropdown(trigger, content) {
                    content.style.display = 'none';
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                }

                // Helper: Get focusable items in dropdown
                function getFocusableItems(content) {
                    return content.querySelectorAll('a:not([disabled]), button:not([disabled])');
                }

                allDropdowns.forEach(function(trigger) {
                    var container = trigger.closest('.civic-dropdown');
                    var content = container ? container.querySelector('.civic-dropdown-content') : null;

                    if (!content) return;

                    // Click handler
                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        if (isExpanded) {
                            closeDropdown(trigger, content);
                        } else {
                            openDropdown(trigger, content);
                        }
                    });

                    // Keyboard handler for trigger
                    trigger.addEventListener('keydown', function(e) {
                        var isExpanded = this.getAttribute('aria-expanded') === 'true';

                        switch (e.key) {
                            case 'Enter':
                            case ' ':
                                e.preventDefault();
                                if (isExpanded) {
                                    closeDropdown(trigger, content);
                                } else {
                                    openDropdown(trigger, content);
                                }
                                break;
                            case 'Escape':
                                if (isExpanded) {
                                    e.preventDefault();
                                    closeDropdown(trigger, content);
                                }
                                break;
                            case 'ArrowDown':
                                e.preventDefault();
                                if (!isExpanded) {
                                    openDropdown(trigger, content);
                                } else {
                                    var items = getFocusableItems(content);
                                    if (items.length > 0) items[0].focus();
                                }
                                break;
                            case 'ArrowUp':
                                e.preventDefault();
                                if (isExpanded) {
                                    var items = getFocusableItems(content);
                                    if (items.length > 0) items[items.length - 1].focus();
                                }
                                break;
                        }
                    });

                    // Keyboard navigation within dropdown content
                    content.addEventListener('keydown', function(e) {
                        var items = getFocusableItems(content);
                        var currentIndex = Array.from(items).indexOf(document.activeElement);

                        switch (e.key) {
                            case 'Escape':
                                e.preventDefault();
                                closeDropdown(trigger, content);
                                break;
                            case 'ArrowDown':
                                e.preventDefault();
                                if (currentIndex < items.length - 1) {
                                    items[currentIndex + 1].focus();
                                } else {
                                    items[0].focus(); // Wrap to first
                                }
                                break;
                            case 'ArrowUp':
                                e.preventDefault();
                                if (currentIndex > 0) {
                                    items[currentIndex - 1].focus();
                                } else {
                                    items[items.length - 1].focus(); // Wrap to last
                                }
                                break;
                            case 'Home':
                                e.preventDefault();
                                items[0].focus();
                                break;
                            case 'End':
                                e.preventDefault();
                                items[items.length - 1].focus();
                                break;
                            case 'Tab':
                                // Allow Tab to close dropdown and move focus naturally
                                closeDropdown(trigger, content);
                                break;
                        }
                    });
                });

                // Close dropdowns when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.civic-dropdown')) {
                        closeAllDropdowns(null);
                    }
                });

                // Close dropdowns on Escape anywhere
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeAllDropdowns(null);
                    }
                });
            });
        </script>

        <!-- Notif Scripts (notifications.js now loaded in footer with Pusher) -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <script>
                // Fetch unread notification count for civicone header badge
                (function() {
                    function updateNotifBadge() {
                        fetch(NEXUS_BASE + '/api/notifications/unread-count')
                            .then(r => r.json())
                            .then(data => {
                                const badge = document.getElementById('civic-notif-badge');
                                if (badge && data.count > 0) {
                                    badge.textContent = data.count > 99 ? '99+' : data.count;
                                    badge.style.display = 'block';
                                } else if (badge) {
                                    badge.style.display = 'none';
                                }
                            })
                            .catch(() => {});
                    }
                    // Initial fetch
                    updateNotifBadge();
                    // Refresh every 60 seconds
                    setInterval(updateNotifBadge, 60000);
                })();
            </script>
        <?php endif; ?>
