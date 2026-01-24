/**
 * Premium Mega Menu - Accessible Navigation
 * Features: Hover/click, keyboard navigation, focus trap, ARIA support
 * Date: 2026-01-24
 */
(function() {
    'use strict';

    function initMegaMenus() {
        const megaMenuWrappers = document.querySelectorAll('.mega-menu-wrapper');
        if (!megaMenuWrappers.length) return;

        megaMenuWrappers.forEach((wrapper, index) => {
            const trigger = wrapper.querySelector('.mega-menu-trigger');
            const dropdown = wrapper.querySelector('.mega-menu-dropdown');

            if (!trigger || !dropdown) return;

            // Generate unique IDs for ARIA
            const menuId = 'mega-menu-' + index;
            dropdown.id = menuId;
            trigger.setAttribute('aria-controls', menuId);
            trigger.setAttribute('aria-haspopup', 'true');

            // Add role="menu" to dropdown
            dropdown.setAttribute('role', 'menu');

            // Add role="menuitem" to all links
            const menuItems = dropdown.querySelectorAll('a');
            menuItems.forEach((item, itemIndex) => {
                item.setAttribute('role', 'menuitem');
                item.setAttribute('tabindex', '-1');
            });

            let hoverTimeout = null;
            let currentFocusIndex = -1;

            // Open menu
            function openMenu() {
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
                trigger.setAttribute('aria-expanded', 'true');
                wrapper.classList.add('active');
            }

            // Close menu
            function closeMenu(returnFocus) {
                trigger.setAttribute('aria-expanded', 'false');
                wrapper.classList.remove('active');
                currentFocusIndex = -1;
                if (returnFocus) {
                    trigger.focus();
                }
            }

            // Close all other menus
            function closeOtherMenus() {
                megaMenuWrappers.forEach(w => {
                    if (w !== wrapper) {
                        w.classList.remove('active');
                        const t = w.querySelector('.mega-menu-trigger');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // Focus menu item by index
            function focusMenuItem(index) {
                const items = dropdown.querySelectorAll('a[role="menuitem"]');
                if (items.length === 0) return;

                // Wrap around
                if (index < 0) index = items.length - 1;
                if (index >= items.length) index = 0;

                currentFocusIndex = index;
                items[index].focus();
            }

            // Hover to open (immediate)
            wrapper.addEventListener('mouseenter', () => {
                openMenu();
            });

            // Hover to close (with delay)
            wrapper.addEventListener('mouseleave', () => {
                hoverTimeout = setTimeout(() => {
                    closeMenu(false);
                }, 150);
            });

            // Click support for touch devices
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const isActive = wrapper.classList.contains('active');

                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }

                closeOtherMenus();

                if (isActive) {
                    closeMenu(false);
                } else {
                    openMenu();
                    // Focus first item after opening via click
                    setTimeout(() => focusMenuItem(0), 50);
                }
            });

            // Keyboard navigation on trigger
            trigger.addEventListener('keydown', (e) => {
                switch (e.key) {
                    case 'Enter':
                    case ' ':
                    case 'ArrowDown':
                        e.preventDefault();
                        closeOtherMenus();
                        openMenu();
                        setTimeout(() => focusMenuItem(0), 50);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        closeOtherMenus();
                        openMenu();
                        setTimeout(() => {
                            const items = dropdown.querySelectorAll('a[role="menuitem"]');
                            focusMenuItem(items.length - 1);
                        }, 50);
                        break;
                    case 'Escape':
                        closeMenu(true);
                        break;
                }
            });

            // Keyboard navigation within dropdown
            dropdown.addEventListener('keydown', (e) => {
                const items = dropdown.querySelectorAll('a[role="menuitem"]');

                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        focusMenuItem(currentFocusIndex + 1);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        focusMenuItem(currentFocusIndex - 1);
                        break;
                    case 'Home':
                        e.preventDefault();
                        focusMenuItem(0);
                        break;
                    case 'End':
                        e.preventDefault();
                        focusMenuItem(items.length - 1);
                        break;
                    case 'Escape':
                        e.preventDefault();
                        closeMenu(true);
                        break;
                    case 'Tab':
                        // Allow Tab to close menu and move focus naturally
                        closeMenu(false);
                        break;
                }
            });

            // Type-ahead search within menu
            let searchString = '';
            let searchTimeout = null;

            dropdown.addEventListener('keypress', (e) => {
                // Ignore special keys
                if (e.key.length !== 1) return;

                e.preventDefault();
                searchString += e.key.toLowerCase();

                // Clear search after 500ms of no typing
                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchString = '';
                }, 500);

                // Find matching item
                const items = dropdown.querySelectorAll('a[role="menuitem"]');
                for (let i = 0; i < items.length; i++) {
                    const text = items[i].textContent.toLowerCase().trim();
                    if (text.startsWith(searchString)) {
                        focusMenuItem(i);
                        break;
                    }
                }
            });
        });

        // Close mega menus when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.mega-menu-wrapper')) {
                megaMenuWrappers.forEach(wrapper => {
                    wrapper.classList.remove('active');
                    const trigger = wrapper.querySelector('.mega-menu-trigger');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                });
            }
        });

        // Global Escape key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                megaMenuWrappers.forEach(wrapper => {
                    if (wrapper.classList.contains('active')) {
                        wrapper.classList.remove('active');
                        const trigger = wrapper.querySelector('.mega-menu-trigger');
                        if (trigger) {
                            trigger.setAttribute('aria-expanded', 'false');
                            trigger.focus();
                        }
                    }
                });
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMegaMenus);
    } else {
        initMegaMenus();
    }
})();
