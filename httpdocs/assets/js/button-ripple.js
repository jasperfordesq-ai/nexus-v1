/**
 * Button Ripple Effects - Material Design Style
 * Touch/click feedback with expanding ripple animation
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Auto-applies to buttons with .ripple class or data-ripple="true"
 *   // Or manually:
 *   ButtonRipple.attach(element);
 *   ButtonRipple.attachAll('.my-buttons');
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        duration: 600,
        autoAttach: true,
        selectors: [
            '.ripple',
            '.btn',
            '.glass-btn',
            '.glass-btn-primary',
            '.glass-btn-secondary',
            '.glass-btn-outline',
            '.nexus-smart-btn',
            '.nav-link',
            '.tab-item',
            '.dropdown-item',
            '.icon-btn',
            '.nexus-header-icon-btn',
            '.fab',
            '[data-ripple="true"]'
        ]
    };

    // Check for reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Track attached elements to prevent double-binding
    const attachedElements = new WeakSet();

    /**
     * Create ripple container if needed
     */
    function ensureContainer(element) {
        let container = element.querySelector('.ripple-container');
        if (!container) {
            container = document.createElement('span');
            container.className = 'ripple-container';
            container.setAttribute('aria-hidden', 'true');
            element.appendChild(container);
        }
        return container;
    }

    /**
     * Detect if element has dark background
     */
    function isDarkBackground(element) {
        const style = getComputedStyle(element);
        const bg = style.backgroundColor;

        // Parse RGB values
        const match = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!match) {
            // Check for gradient or transparent - use text color as hint
            const color = style.color;
            const colorMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (colorMatch) {
                const r = parseInt(colorMatch[1]);
                const g = parseInt(colorMatch[2]);
                const b = parseInt(colorMatch[3]);
                // If text is light, background is probably dark
                return (r + g + b) / 3 > 128;
            }
            return document.documentElement.getAttribute('data-theme') === 'dark';
        }

        const r = parseInt(match[1]);
        const g = parseInt(match[2]);
        const b = parseInt(match[3]);

        // Calculate luminance
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance < 0.5;
    }

    /**
     * Get ripple color class based on element
     */
    function getRippleClass(element) {
        // Check for explicit ripple color
        if (element.dataset.rippleColor) {
            return `ripple-${element.dataset.rippleColor}`;
        }

        // Primary buttons get light ripple
        if (element.classList.contains('btn-primary') ||
            element.classList.contains('glass-btn-primary') ||
            element.classList.contains('nexus-smart-btn-primary') ||
            element.classList.contains('btn-danger') ||
            element.classList.contains('btn-success')) {
            return 'ripple-light';
        }

        // Auto-detect based on background
        return isDarkBackground(element) ? 'ripple-light' : 'ripple-dark';
    }

    /**
     * Create and animate a ripple
     */
    function createRipple(element, event) {
        const container = ensureContainer(element);
        const rect = element.getBoundingClientRect();

        // Calculate ripple size (should cover entire element)
        const size = Math.max(rect.width, rect.height) * 2;

        // Get click/touch position
        let x, y;
        if (event.type === 'touchstart' && event.touches && event.touches[0]) {
            x = event.touches[0].clientX - rect.left;
            y = event.touches[0].clientY - rect.top;
        } else {
            x = event.clientX - rect.left;
            y = event.clientY - rect.top;
        }

        // Center ripple for icon buttons or if position is invalid
        const isCentered = element.classList.contains('ripple-centered') ||
                          element.classList.contains('icon-btn') ||
                          element.classList.contains('nexus-header-icon-btn') ||
                          isNaN(x) || isNaN(y);

        if (isCentered) {
            x = rect.width / 2;
            y = rect.height / 2;
        }

        // Create ripple element
        const ripple = document.createElement('span');
        ripple.className = `ripple-effect ${getRippleClass(element)}`;

        // Position ripple
        ripple.style.width = `${size}px`;
        ripple.style.height = `${size}px`;
        ripple.style.left = `${x - size / 2}px`;
        ripple.style.top = `${y - size / 2}px`;

        // Add to container
        container.appendChild(ripple);

        // Trigger animation
        requestAnimationFrame(() => {
            ripple.classList.add('ripple-animate');
        });

        // Haptic feedback on touch
        if (event.type === 'touchstart' && navigator.vibrate) {
            navigator.vibrate(5);
        }

        // Remove after animation
        const duration = prefersReducedMotion ? 200 : config.duration;
        setTimeout(() => {
            if (prefersReducedMotion) {
                ripple.classList.add('ripple-fade');
            }
            setTimeout(() => {
                ripple.remove();
            }, prefersReducedMotion ? 200 : 100);
        }, duration);
    }

    /**
     * Handle pointer down event
     */
    function handlePointerDown(event) {
        // Skip if right-click
        if (event.button && event.button !== 0) return;

        // Skip if element is disabled
        const element = event.currentTarget;
        if (element.disabled || element.getAttribute('aria-disabled') === 'true') {
            return;
        }

        createRipple(element, event);
    }

    /**
     * Attach ripple to an element
     */
    function attach(element) {
        if (!element || attachedElements.has(element)) return;

        // Mark as attached
        attachedElements.add(element);

        // Use both mouse and touch for better coverage
        element.addEventListener('mousedown', handlePointerDown, { passive: true });
        element.addEventListener('touchstart', handlePointerDown, { passive: true });

        // Ensure container exists
        ensureContainer(element);
    }

    /**
     * Attach ripple to multiple elements
     */
    function attachAll(selector) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(attach);
        return elements.length;
    }

    /**
     * Detach ripple from an element
     */
    function detach(element) {
        if (!element || !attachedElements.has(element)) return;

        element.removeEventListener('mousedown', handlePointerDown);
        element.removeEventListener('touchstart', handlePointerDown);
        attachedElements.delete(element);

        // Remove container
        const container = element.querySelector('.ripple-container');
        if (container) {
            container.remove();
        }
    }

    /**
     * Initialize auto-attachment
     */
    function init() {
        if (!config.autoAttach) return;

        // Attach to existing elements
        const selector = config.selectors.join(', ');
        const count = attachAll(selector);
        console.log(`[ButtonRipple] Attached to ${count} elements`);

        // Watch for new elements
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return; // Skip non-elements

                    // Check if node matches
                    if (node.matches && config.selectors.some(s => node.matches(s))) {
                        attach(node);
                    }

                    // Check children
                    if (node.querySelectorAll) {
                        config.selectors.forEach(s => {
                            node.querySelectorAll(s).forEach(attach);
                        });
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Trigger ripple programmatically
     */
    function trigger(element, x, y) {
        if (!element) return;

        const rect = element.getBoundingClientRect();
        const fakeEvent = {
            type: 'mousedown',
            clientX: x !== undefined ? x : rect.left + rect.width / 2,
            clientY: y !== undefined ? y : rect.top + rect.height / 2,
            currentTarget: element
        };

        createRipple(element, fakeEvent);
    }

    // Public API
    window.ButtonRipple = {
        attach: attach,
        attachAll: attachAll,
        detach: detach,
        trigger: trigger,
        init: init,
        config: (newConfig) => Object.assign(config, newConfig)
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Small delay to ensure CSS is loaded
        setTimeout(init, 50);
    }

})();
