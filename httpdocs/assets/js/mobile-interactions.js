/**
 * Mobile Interactions Controller
 * Brings CSS micro-interactions to life with JavaScript
 * Version: 1.0 - 2026-01-21
 *
 * Features:
 * - Material Design ripple effects
 * - Haptic feedback coordination
 * - Loading state management
 * - Swipe gesture handlers
 * - Toggle/checkbox animations
 */

(function() {
    'use strict';

    // ============================================
    // HAPTIC FEEDBACK
    // ============================================

    const HapticFeedback = {
        // Check if device supports haptic feedback
        isSupported: () => {
            return 'vibrate' in navigator || (window.Capacitor && window.Capacitor.Plugins.Haptics);
        },

        // Trigger haptic feedback with specific pattern
        trigger: async (type = 'light') => {
            if (!HapticFeedback.isSupported()) return;

            // Capacitor Haptics (preferred for native apps)
            if (window.Capacitor && window.Capacitor.Plugins.Haptics) {
                try {
                    const { Haptics, ImpactStyle } = window.Capacitor.Plugins;

                    switch(type) {
                        case 'light':
                            await Haptics.impact({ style: ImpactStyle.Light });
                            break;
                        case 'medium':
                            await Haptics.impact({ style: ImpactStyle.Medium });
                            break;
                        case 'heavy':
                            await Haptics.impact({ style: ImpactStyle.Heavy });
                            break;
                        case 'success':
                            await Haptics.notification({ type: 'SUCCESS' });
                            break;
                        case 'error':
                            await Haptics.notification({ type: 'ERROR' });
                            break;
                        case 'warning':
                            await Haptics.notification({ type: 'WARNING' });
                            break;
                        default:
                            await Haptics.impact({ style: ImpactStyle.Light });
                    }
                } catch (error) {
                    console.warn('Haptics failed:', error);
                }
            }
            // Web Vibration API fallback
            else if ('vibrate' in navigator) {
                const patterns = {
                    light: [10],
                    medium: [20],
                    heavy: [30],
                    success: [10, 50, 10],
                    error: [10, 50, 10, 50, 10],
                    warning: [10, 50, 10]
                };

                navigator.vibrate(patterns[type] || patterns.light);
            }
        },

        // Add haptic feedback to element with CSS class
        addToElement: (element, type = 'light') => {
            element.addEventListener('click', () => {
                HapticFeedback.trigger(type);

                // Add CSS animation class
                const cssClass = `mobile-haptic-${type}`;
                element.classList.add(cssClass);
                setTimeout(() => element.classList.remove(cssClass), 600);
            }, { passive: true });
        }
    };

    // ============================================
    // RIPPLE EFFECTS
    // ============================================

    const RippleEffect = {
        // Create ripple on click
        create: (event, element) => {
            const ripple = document.createElement('span');
            ripple.classList.add('mobile-ripple');

            // Calculate ripple position
            const rect = element.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;

            // Apply styles - dynamic values based on click position
            // eslint-disable-next-line no-restricted-syntax -- dynamic size calculation
            ripple.style.width = ripple.style.height = `${size}px`;
            // eslint-disable-next-line no-restricted-syntax -- dynamic position calculation
            ripple.style.left = `${x}px`;
            // eslint-disable-next-line no-restricted-syntax -- dynamic position calculation
            ripple.style.top = `${y}px`;

            // Add variant class if present
            if (element.classList.contains('btn-primary')) {
                ripple.classList.add('primary');
            } else if (element.classList.contains('btn-secondary')) {
                ripple.classList.add('secondary');
            } else if (element.dataset.rippleVariant) {
                ripple.classList.add(element.dataset.rippleVariant);
            }

            // Add to element and remove after animation
            element.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        },

        // Initialize ripple on elements
        init: () => {
            document.addEventListener('click', (event) => {
                const target = event.target.closest('.mobile-ripple-container, [data-ripple="true"]');

                if (target && !target.disabled) {
                    // Ensure element is positioned
                    if (getComputedStyle(target).position === 'static') {
                        target.style.position = 'relative';
                    }

                    RippleEffect.create(event, target);
                }
            }, { passive: true });
        }
    };

    // ============================================
    // BUTTON PRESS ANIMATIONS
    // ============================================

    const ButtonPress = {
        init: () => {
            // Add press animation to interactive elements
            const selectors = [
                '.mobile-interactive-press',
                '.mobile-icon-press',
                '.mobile-fab-press',
                '.mobile-list-item-press',
                '.mobile-card-press'
            ];

            document.addEventListener('touchstart', (event) => {
                const target = event.target.closest(selectors.join(','));
                if (target && !target.disabled) {
                    HapticFeedback.trigger('light');
                }
            }, { passive: true });
        }
    };

    // ============================================
    // SWIPE GESTURE HANDLERS
    // ============================================

    const SwipeGesture = {
        touchStart: null,
        threshold: 80, // Minimum swipe distance in pixels

        handleTouchStart: (event) => {
            const target = event.target.closest('.mobile-swipe-container');
            if (!target) return;

            SwipeGesture.touchStart = {
                x: event.touches[0].clientX,
                y: event.touches[0].clientY,
                element: target,
                time: Date.now()
            };
        },

        handleTouchMove: (event) => {
            if (!SwipeGesture.touchStart) return;

            const deltaX = event.touches[0].clientX - SwipeGesture.touchStart.x;
            const deltaY = event.touches[0].clientY - SwipeGesture.touchStart.y;

            // Prevent vertical scroll if horizontal swipe detected
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10) {
                event.preventDefault();
            }

            const { element } = SwipeGesture.touchStart;

            // Apply transform during swipe
            if (Math.abs(deltaX) > 10) {
                const maxSwipe = 80;
                const clampedDelta = Math.max(-maxSwipe, Math.min(maxSwipe, deltaX));
                element.style.transform = `translateX(${clampedDelta}px)`;
            }
        },

        handleTouchEnd: (event) => {
            if (!SwipeGesture.touchStart) return;

            const deltaX = event.changedTouches[0].clientX - SwipeGesture.touchStart.x;
            const deltaY = event.changedTouches[0].clientY - SwipeGesture.touchStart.y;
            const deltaTime = Date.now() - SwipeGesture.touchStart.time;
            const { element } = SwipeGesture.touchStart;

            // Reset transform
            element.style.transform = '';

            // Check if swipe threshold met
            if (Math.abs(deltaX) > SwipeGesture.threshold && Math.abs(deltaX) > Math.abs(deltaY) && deltaTime < 300) {
                if (deltaX < 0) {
                    // Swipe left
                    element.setAttribute('data-swipe', 'left');
                    HapticFeedback.trigger('medium');

                    // Dispatch custom event
                    element.dispatchEvent(new CustomEvent('swipeleft', { bubbles: true }));
                } else {
                    // Swipe right
                    element.setAttribute('data-swipe', 'right');
                    HapticFeedback.trigger('medium');

                    // Dispatch custom event
                    element.dispatchEvent(new CustomEvent('swiperight', { bubbles: true }));
                }

                // Auto-reset after 2s
                setTimeout(() => {
                    element.removeAttribute('data-swipe');
                }, 2000);
            }

            SwipeGesture.touchStart = null;
        },

        init: () => {
            document.addEventListener('touchstart', SwipeGesture.handleTouchStart, { passive: true });
            document.addEventListener('touchmove', SwipeGesture.handleTouchMove, { passive: false });
            document.addEventListener('touchend', SwipeGesture.handleTouchEnd, { passive: true });
        }
    };

    // ============================================
    // TOGGLE SWITCH ANIMATIONS
    // ============================================

    const ToggleSwitch = {
        toggle: (element) => {
            const isActive = element.classList.toggle('active');
            HapticFeedback.trigger('medium');

            // Dispatch change event
            element.dispatchEvent(new CustomEvent('toggle', {
                detail: { active: isActive },
                bubbles: true
            }));
        },

        init: () => {
            document.addEventListener('click', (event) => {
                const target = event.target.closest('.mobile-toggle-switch');
                if (target && !target.disabled) {
                    ToggleSwitch.toggle(target);
                }
            });
        }
    };

    // ============================================
    // CHECKBOX/RADIO ANIMATIONS
    // ============================================

    const CheckboxRadio = {
        toggle: (element) => {
            // Handle radio button groups
            if (element.classList.contains('mobile-radio')) {
                const group = element.dataset.group;
                if (group) {
                    // Uncheck others in group
                    document.querySelectorAll(`.mobile-radio[data-group="${group}"]`).forEach(radio => {
                        radio.classList.remove('checked');
                    });
                }
            }

            const isChecked = element.classList.toggle('checked');
            HapticFeedback.trigger('light');

            // Dispatch change event
            element.dispatchEvent(new CustomEvent('change', {
                detail: { checked: isChecked },
                bubbles: true
            }));
        },

        init: () => {
            document.addEventListener('click', (event) => {
                const target = event.target.closest('.mobile-checkbox, .mobile-radio');
                if (target && !target.disabled) {
                    CheckboxRadio.toggle(target);
                }
            });
        }
    };

    // ============================================
    // LOADING STATES
    // ============================================

    const LoadingState = {
        // Show loading state on button
        showButton: (button) => {
            if (!button || button.classList.contains('mobile-btn-loading')) return;

            button.classList.add('mobile-btn-loading');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        },

        // Hide loading state on button
        hideButton: (button) => {
            if (!button) return;

            button.classList.remove('mobile-btn-loading');
            button.disabled = false;
            button.removeAttribute('aria-busy');
        },

        // Show loading state on form
        showForm: (form) => {
            if (!form) return;

            form.classList.add('mobile-form-loading');
            form.setAttribute('aria-busy', 'true');

            // Disable all form controls
            form.querySelectorAll('input, textarea, select, button').forEach(control => {
                control.disabled = true;
            });
        },

        // Hide loading state on form
        hideForm: (form) => {
            if (!form) return;

            form.classList.remove('mobile-form-loading');
            form.removeAttribute('aria-busy');

            // Re-enable all form controls
            form.querySelectorAll('input, textarea, select, button').forEach(control => {
                control.disabled = false;
            });
        },

        // Show input loading state
        showInput: (input) => {
            if (!input) return;

            const wrapper = input.closest('.mobile-input-wrapper') || input.parentElement;
            wrapper.classList.add('mobile-input-loading');
        },

        // Hide input loading state
        hideInput: (input) => {
            if (!input) return;

            const wrapper = input.closest('.mobile-input-wrapper') || input.parentElement;
            wrapper.classList.remove('mobile-input-loading');
        }
    };

    // ============================================
    // PAGE LOADING BAR
    // ============================================

    const PageLoadingBar = {
        bar: null,

        show: () => {
            if (PageLoadingBar.bar) return;

            PageLoadingBar.bar = document.createElement('div');
            PageLoadingBar.bar.className = 'mobile-page-loading-bar';
            PageLoadingBar.bar.setAttribute('role', 'progressbar');
            PageLoadingBar.bar.setAttribute('aria-label', 'Page loading');

            document.body.appendChild(PageLoadingBar.bar);
        },

        hide: () => {
            if (!PageLoadingBar.bar) return;

            PageLoadingBar.bar.remove();
            PageLoadingBar.bar = null;
        },

        // Auto-show on navigation
        init: () => {
            // Show on page unload
            window.addEventListener('beforeunload', PageLoadingBar.show);

            // Hide when page loads
            window.addEventListener('load', PageLoadingBar.hide);

            // Show on link clicks (for multi-page apps)
            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');
                if (link && !link.hasAttribute('download') && !link.target && link.href.startsWith(window.location.origin)) {
                    PageLoadingBar.show();
                }
            });
        }
    };

    // ============================================
    // BADGE ANIMATIONS
    // ============================================

    const BadgeAnimation = {
        // Animate badge count change
        updateCount: (badge, newCount) => {
            if (!badge) return;

            const oldCount = parseInt(badge.textContent) || 0;

            if (newCount > oldCount) {
                badge.classList.add('mobile-badge-count-up');
                setTimeout(() => badge.classList.remove('mobile-badge-count-up'), 300);

                HapticFeedback.trigger('light');
            }

            badge.textContent = newCount;

            // Wiggle if count increased significantly
            if (newCount > oldCount + 5) {
                badge.classList.add('mobile-badge-wiggle');
                setTimeout(() => badge.classList.remove('mobile-badge-wiggle'), 1500);
            }
        },

        // Show new badge
        show: (badge) => {
            if (!badge) return;

            badge.classList.add('mobile-badge-pop');
            setTimeout(() => badge.classList.remove('mobile-badge-pop'), 400);

            HapticFeedback.trigger('light');
        }
    };

    // ============================================
    // SNACKBAR/TOAST NOTIFICATIONS
    // ============================================

    const Snackbar = {
        show: (message, type = 'default', duration = 3000) => {
            const snackbar = document.createElement('div');
            snackbar.className = `mobile-snackbar ${type}`;
            snackbar.textContent = message;
            snackbar.setAttribute('role', 'status');
            snackbar.setAttribute('aria-live', 'polite');

            document.body.appendChild(snackbar);

            // Trigger haptic based on type
            if (type === 'success') {
                HapticFeedback.trigger('success');
            } else if (type === 'error') {
                HapticFeedback.trigger('error');
            } else if (type === 'warning') {
                HapticFeedback.trigger('warning');
            }

            // Auto-remove after duration
            setTimeout(() => {
                snackbar.style.opacity = '0';
                snackbar.style.transform = 'translateX(-100%)';
                setTimeout(() => snackbar.remove(), 300);
            }, duration);
        }
    };

    // ============================================
    // PUBLIC API
    // ============================================

    window.MobileInteractions = {
        haptic: HapticFeedback,
        ripple: RippleEffect,
        loading: LoadingState,
        badge: BadgeAnimation,
        snackbar: Snackbar,

        // Initialize all interactions
        init: () => {
            RippleEffect.init();
            ButtonPress.init();
            SwipeGesture.init();
            ToggleSwitch.init();
            CheckboxRadio.init();
            PageLoadingBar.init();

            console.warn('✅ Mobile Interactions initialized');
        }
    };

    // ============================================
    // AUTO-INITIALIZE
    // ============================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.MobileInteractions.init);
    } else {
        window.MobileInteractions.init();
    }

})();
