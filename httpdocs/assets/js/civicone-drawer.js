/**
 * CivicOne Native Mobile Drawer
 * WCAG 2.1 AA Accessible Navigation Drawer
 * Features: Focus trapping, keyboard navigation, touch gestures
 */

(function() {
    'use strict';

    // =========================================
    // DRAWER STATE & ELEMENTS
    // =========================================

    const state = {
        isOpen: false,
        scrollPosition: 0,
        touchStartX: 0,
        touchStartY: 0
    };

    let elements = {};

    // =========================================
    // INITIALIZATION
    // =========================================

    function init() {
        // Get elements
        elements = {
            toggle: document.getElementById('civic-menu-toggle'),
            nav: document.getElementById('civic-main-nav'),
            backdrop: document.getElementById('civic-drawer-backdrop'),
            closeBtn: document.getElementById('civic-drawer-close'),
            focusTrap: document.querySelector('.civic-drawer-focus-trap'),
            body: document.body
        };

        // Exit if essential elements don't exist
        if (!elements.toggle || !elements.nav) return;

        // Set up event listeners
        setupToggleButton();
        setupCloseButton();
        setupBackdrop();
        setupKeyboardNavigation();
        setupSwipeGestures();
        setupFocusTrap();
        setupBottomNavAnimations();
    }

    // =========================================
    // TOGGLE BUTTON
    // =========================================

    function setupToggleButton() {
        elements.toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (state.isOpen) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    // =========================================
    // CLOSE BUTTON
    // =========================================

    function setupCloseButton() {
        if (!elements.closeBtn) return;

        elements.closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeDrawer();
        });
    }

    // =========================================
    // BACKDROP
    // =========================================

    function setupBackdrop() {
        if (!elements.backdrop) return;

        elements.backdrop.addEventListener('click', function(e) {
            e.preventDefault();
            closeDrawer();
        });

        // Prevent scrolling on backdrop
        elements.backdrop.addEventListener('touchmove', function(e) {
            e.preventDefault();
        }, { passive: false });
    }

    // =========================================
    // OPEN DRAWER
    // =========================================

    function openDrawer() {
        if (state.isOpen) return;

        // Close mobile search bar if open
        var mobileSearchBar = document.getElementById('civic-mobile-search-bar');
        var mobileSearchToggle = document.getElementById('civic-mobile-search-toggle');
        if (mobileSearchBar && mobileSearchBar.classList.contains('active')) {
            mobileSearchBar.classList.remove('active');
            if (mobileSearchToggle) mobileSearchToggle.setAttribute('aria-expanded', 'false');
        }

        // Save scroll position before locking
        state.scrollPosition = window.scrollY;

        // Update state
        state.isOpen = true;

        // Update ARIA
        elements.toggle.setAttribute('aria-expanded', 'true');
        elements.nav.setAttribute('aria-hidden', 'false');
        if (elements.backdrop) {
            elements.backdrop.setAttribute('aria-hidden', 'false');
        }

        // Add active classes
        elements.nav.classList.add('active');
        if (elements.backdrop) {
            elements.backdrop.classList.add('active');
        }
        elements.body.classList.add('drawer-open');

        // Restore scroll position for fixed body
        elements.body.style.top = `-${state.scrollPosition}px`;

        // Focus first focusable element in drawer (close button)
        requestAnimationFrame(() => {
            const firstFocusable = elements.closeBtn || elements.nav.querySelector('a, button');
            if (firstFocusable) {
                firstFocusable.focus();
            }
        });

        // Haptic feedback
        triggerHaptic('light');

        // Announce to screen readers
        announceToScreenReader('Navigation menu opened');
    }

    // =========================================
    // CLOSE DRAWER
    // =========================================

    function closeDrawer() {
        if (!state.isOpen) return;

        // Update state
        state.isOpen = false;

        // Update ARIA
        elements.toggle.setAttribute('aria-expanded', 'false');
        elements.nav.setAttribute('aria-hidden', 'true');
        if (elements.backdrop) {
            elements.backdrop.setAttribute('aria-hidden', 'true');
        }

        // Remove active classes
        elements.nav.classList.remove('active');
        if (elements.backdrop) {
            elements.backdrop.classList.remove('active');
        }
        elements.body.classList.remove('drawer-open');

        // Restore scroll position
        elements.body.style.top = '';
        window.scrollTo(0, state.scrollPosition);

        // Return focus to toggle button
        elements.toggle.focus();

        // Haptic feedback
        triggerHaptic('light');

        // Announce to screen readers
        announceToScreenReader('Navigation menu closed');
    }

    // =========================================
    // KEYBOARD NAVIGATION
    // =========================================

    function setupKeyboardNavigation() {
        document.addEventListener('keydown', function(e) {
            if (!state.isOpen) return;

            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    closeDrawer();
                    break;

                case 'Tab':
                    handleTabKey(e);
                    break;
            }
        });

        // Arrow key navigation within drawer
        elements.nav.addEventListener('keydown', function(e) {
            if (!state.isOpen) return;

            const focusableItems = getFocusableElements();
            const currentIndex = Array.from(focusableItems).indexOf(document.activeElement);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (currentIndex < focusableItems.length - 1) {
                        focusableItems[currentIndex + 1].focus();
                    } else {
                        focusableItems[0].focus();
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (currentIndex > 0) {
                        focusableItems[currentIndex - 1].focus();
                    } else {
                        focusableItems[focusableItems.length - 1].focus();
                    }
                    break;

                case 'Home':
                    e.preventDefault();
                    focusableItems[0].focus();
                    break;

                case 'End':
                    e.preventDefault();
                    focusableItems[focusableItems.length - 1].focus();
                    break;
            }
        });
    }

    function handleTabKey(e) {
        const focusableItems = getFocusableElements();
        const firstItem = focusableItems[0];
        const lastItem = focusableItems[focusableItems.length - 1];

        if (e.shiftKey) {
            // Shift + Tab
            if (document.activeElement === firstItem) {
                e.preventDefault();
                lastItem.focus();
            }
        } else {
            // Tab
            if (document.activeElement === lastItem) {
                e.preventDefault();
                firstItem.focus();
            }
        }
    }

    function getFocusableElements() {
        return elements.nav.querySelectorAll(
            'a[href]:not([disabled]):not([tabindex="-1"]), ' +
            'button:not([disabled]):not([tabindex="-1"]), ' +
            'input:not([disabled]):not([tabindex="-1"]), ' +
            '[tabindex]:not([tabindex="-1"]):not(.civic-drawer-focus-trap)'
        );
    }

    // =========================================
    // FOCUS TRAP
    // =========================================

    function setupFocusTrap() {
        if (!elements.focusTrap) return;

        elements.focusTrap.addEventListener('focus', function() {
            // When focus reaches the trap, loop back to close button
            if (elements.closeBtn) {
                elements.closeBtn.focus();
            }
        });
    }

    // =========================================
    // SWIPE GESTURES
    // =========================================

    function setupSwipeGestures() {
        // Only on touch devices
        if (!('ontouchstart' in window)) return;

        const threshold = 50; // Minimum swipe distance
        const allowedTime = 300; // Maximum time for swipe

        let startTime;

        elements.nav.addEventListener('touchstart', function(e) {
            const touch = e.changedTouches[0];
            state.touchStartX = touch.pageX;
            state.touchStartY = touch.pageY;
            startTime = Date.now();
        }, { passive: true });

        elements.nav.addEventListener('touchend', function(e) {
            const touch = e.changedTouches[0];
            const distX = touch.pageX - state.touchStartX;
            const distY = touch.pageY - state.touchStartY;
            const elapsedTime = Date.now() - startTime;

            // Check for horizontal swipe to the right (close gesture)
            if (elapsedTime <= allowedTime &&
                distX > threshold &&
                Math.abs(distY) < threshold) {
                closeDrawer();
            }
        }, { passive: true });

        // DISABLED: Edge swipe to open (from right edge)
        // This was causing conflicts with normal scrolling and accidental drawer opens
        // Users can still open drawer via menu button
    }

    // =========================================
    // BOTTOM NAV ANIMATIONS
    // =========================================

    function setupBottomNavAnimations() {
        const navItems = document.querySelectorAll('.civic-bottom-nav-item');

        navItems.forEach(item => {
            // Touch start - add tap class
            item.addEventListener('touchstart', function() {
                this.classList.add('tap');
            }, { passive: true });

            // Touch end - remove tap, add bounce and ripple
            item.addEventListener('touchend', function() {
                this.classList.remove('tap');
                this.classList.add('bounce', 'ripple');

                // Haptic feedback
                triggerHaptic('light');

                // Remove animation classes after animation completes
                setTimeout(() => {
                    this.classList.remove('bounce', 'ripple');
                }, 500);
            }, { passive: true });

            // Mouse click fallback for non-touch
            item.addEventListener('click', function() {
                if (!('ontouchstart' in window)) {
                    this.classList.add('bounce', 'ripple');
                    setTimeout(() => {
                        this.classList.remove('bounce', 'ripple');
                    }, 500);
                }
            });

            // Cancel animations if touch moves away
            item.addEventListener('touchcancel', function() {
                this.classList.remove('tap');
            }, { passive: true });
        });
    }

    // =========================================
    // HAPTIC FEEDBACK
    // =========================================

    function triggerHaptic(style = 'light') {
        // Check for reduced motion preference
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        // Vibration API
        if ('vibrate' in navigator) {
            switch (style) {
                case 'light':
                    navigator.vibrate(10);
                    break;
                case 'medium':
                    navigator.vibrate(20);
                    break;
                case 'heavy':
                    navigator.vibrate([30, 10, 30]);
                    break;
            }
        }
    }

    // =========================================
    // SCREEN READER ANNOUNCEMENTS
    // =========================================

    function announceToScreenReader(message) {
        let announcer = document.getElementById('civic-sr-announcer');

        if (!announcer) {
            announcer = document.createElement('div');
            announcer.id = 'civic-sr-announcer';
            announcer.setAttribute('role', 'status');
            announcer.setAttribute('aria-live', 'polite');
            announcer.setAttribute('aria-atomic', 'true');
            announcer.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
            document.body.appendChild(announcer);
        }

        // Clear and set new message
        announcer.textContent = '';
        requestAnimationFrame(() => {
            announcer.textContent = message;
        });
    }

    // =========================================
    // INITIALIZE ON DOM READY
    // =========================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize on Turbo navigation (if using Turbo)
    document.addEventListener('turbo:load', init);

})();
