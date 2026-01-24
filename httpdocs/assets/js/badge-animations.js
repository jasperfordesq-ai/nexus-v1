/**
 * Badge/Counter Animations
 * Pop effect when count changes, pulse for attention
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Update badge with animation
 *   BadgeAnimation.update(badgeEl, 5);
 *
 *   // Trigger specific animation
 *   BadgeAnimation.pop(badgeEl);
 *   BadgeAnimation.pulse(badgeEl);
 *
 *   // Create animated counter
 *   BadgeAnimation.createCounter(container, initialValue);
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        animationDuration: 300,
        pulseOnIncrease: true,
        shakeOnDecrease: false,
        hapticFeedback: true,
        maxDisplay: 99
    };

    // Track badge values for comparison
    const badgeValues = new WeakMap();

    /**
     * Trigger pop animation
     */
    function pop(badge) {
        badge.classList.remove('badge-pop');
        void badge.offsetWidth; // Force reflow
        badge.classList.add('badge-pop');

        setTimeout(() => {
            badge.classList.remove('badge-pop');
        }, 300);
    }

    /**
     * Trigger bounce animation
     */
    function bounce(badge) {
        badge.classList.remove('badge-bounce');
        void badge.offsetWidth;
        badge.classList.add('badge-bounce');

        setTimeout(() => {
            badge.classList.remove('badge-bounce');
        }, 500);
    }

    /**
     * Trigger pulse animation
     */
    function pulse(badge, continuous = false) {
        if (continuous) {
            badge.classList.add('badge-pulse');
        } else {
            badge.classList.add('badge-pulse');
            setTimeout(() => {
                badge.classList.remove('badge-pulse');
            }, 1500);
        }
    }

    /**
     * Stop pulse animation
     */
    function stopPulse(badge) {
        badge.classList.remove('badge-pulse');
    }

    /**
     * Trigger shake animation
     */
    function shake(badge) {
        badge.classList.remove('badge-shake');
        void badge.offsetWidth;
        badge.classList.add('badge-shake');

        setTimeout(() => {
            badge.classList.remove('badge-shake');
        }, 400);
    }

    /**
     * Trigger fade in animation
     */
    function fadeIn(badge) {
        badge.classList.add('badge-fade-in');
        setTimeout(() => {
            badge.classList.remove('badge-fade-in');
        }, 300);
    }

    /**
     * Format count for display
     */
    function formatCount(count) {
        if (count > config.maxDisplay) {
            return `${config.maxDisplay}+`;
        }
        return count.toString();
    }

    /**
     * Update badge value with animation
     */
    function update(badge, newValue, options = {}) {
        const previousValue = badgeValues.get(badge) || 0;
        const value = parseInt(newValue) || 0;

        // Store new value
        badgeValues.set(badge, value);

        // Handle zero - hide badge
        if (value === 0) {
            badge.classList.add('hidden');
            return;
        }

        // Show badge if was hidden
        if (badge.classList.contains('hidden')) {
            badge.classList.remove('hidden');
            fadeIn(badge);
        }

        // Update display
        badge.textContent = formatCount(value);
        badge.setAttribute('data-count', value);

        // Determine animation
        if (value > previousValue) {
            // Count increased
            if (config.pulseOnIncrease || options.pulse) {
                pop(badge);
            }
            if (config.hapticFeedback && navigator.vibrate) {
                navigator.vibrate(10);
            }
        } else if (value < previousValue && config.shakeOnDecrease) {
            // Count decreased
            shake(badge);
        }
    }

    /**
     * Increment badge by amount
     */
    function increment(badge, amount = 1) {
        const currentValue = badgeValues.get(badge) || 0;
        update(badge, currentValue + amount);
    }

    /**
     * Decrement badge by amount
     */
    function decrement(badge, amount = 1) {
        const currentValue = badgeValues.get(badge) || 0;
        update(badge, Math.max(0, currentValue - amount));
    }

    /**
     * Create slot-machine style counter
     */
    function createCounter(container, initialValue = 0) {
        container.classList.add('counter-slot');
        container.innerHTML = '';

        const digits = formatCount(initialValue).split('');

        digits.forEach(digit => {
            const digitEl = document.createElement('span');
            digitEl.className = 'counter-slot-digit';
            digitEl.textContent = digit;
            container.appendChild(digitEl);
        });

        // Store value
        badgeValues.set(container, initialValue);

        return container;
    }

    /**
     * Update slot-machine counter
     */
    function updateCounter(container, newValue) {
        const previousValue = badgeValues.get(container) || 0;
        const value = parseInt(newValue) || 0;
        const isIncreasing = value > previousValue;

        badgeValues.set(container, value);

        const newDigits = formatCount(value).split('');
        const existingDigits = container.querySelectorAll('.counter-slot-digit');

        // Add or remove digit elements as needed
        while (existingDigits.length < newDigits.length) {
            const digitEl = document.createElement('span');
            digitEl.className = 'counter-slot-digit';
            container.appendChild(digitEl);
        }

        while (container.children.length > newDigits.length) {
            container.lastChild.remove();
        }

        // Update each digit with animation
        const digitEls = container.querySelectorAll('.counter-slot-digit');
        digitEls.forEach((digitEl, index) => {
            const newDigit = newDigits[index];

            if (digitEl.textContent !== newDigit) {
                digitEl.classList.remove('count-up', 'count-down');
                void digitEl.offsetWidth;

                digitEl.classList.add(isIncreasing ? 'count-up' : 'count-down');
                digitEl.textContent = newDigit;

                setTimeout(() => {
                    digitEl.classList.remove('count-up', 'count-down');
                }, 300);
            }
        });

        // Haptic feedback
        if (config.hapticFeedback && navigator.vibrate && value > previousValue) {
            navigator.vibrate(10);
        }
    }

    /**
     * Create a badge element
     */
    function createBadge(options = {}) {
        const {
            count = 0,
            color = 'primary',
            size = 'md',
            dot = false,
            outline = false,
            soft = false,
            position = null
        } = options;

        const badge = document.createElement('span');
        badge.className = 'badge';

        if (dot) badge.classList.add('badge--dot');
        if (outline) badge.classList.add('badge--outline');
        if (soft) badge.classList.add('badge--soft');
        if (size !== 'md') badge.classList.add(`badge--${size}`);
        if (color !== 'primary') badge.classList.add(`badge--${color}`);
        if (position) badge.classList.add(`badge--${position}`);

        if (!dot && count > 0) {
            badge.textContent = formatCount(count);
            badge.setAttribute('data-count', count);
        }

        badgeValues.set(badge, count);

        // Hide if zero
        if (count === 0 && !dot) {
            badge.classList.add('hidden');
        }

        return badge;
    }

    /**
     * Create badge container (for positioning on icons/avatars)
     */
    function wrapWithBadge(element, badgeOptions = {}) {
        const container = document.createElement('span');
        container.className = 'badge-container';

        element.parentNode.insertBefore(container, element);
        container.appendChild(element);

        const badge = createBadge(badgeOptions);
        container.appendChild(badge);

        return { container, badge };
    }

    /**
     * Initialize existing badges
     */
    function init() {
        // Find all badges and store their initial values
        const badges = document.querySelectorAll('.badge, .notification-badge, .unread-count');

        badges.forEach(badge => {
            const count = parseInt(badge.textContent) || parseInt(badge.dataset.count) || 0;
            badgeValues.set(badge, count);
        });

        console.warn(`[BadgeAnimation] Initialized ${badges.length} badges`);
    }

    // Public API
    window.BadgeAnimation = {
        pop: pop,
        bounce: bounce,
        pulse: pulse,
        stopPulse: stopPulse,
        shake: shake,
        fadeIn: fadeIn,
        update: update,
        increment: increment,
        decrement: decrement,
        createBadge: createBadge,
        createCounter: createCounter,
        updateCounter: updateCounter,
        wrapWithBadge: wrapWithBadge,
        init: init,
        config: (newConfig) => Object.assign(config, newConfig)
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 50);
    }

})();
