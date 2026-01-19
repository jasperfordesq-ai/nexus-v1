/**
 * Floating Action Button Polish
 * Bounce-in animation, expanded state, speed dial
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Create basic FAB
 *   FAB.create({ icon: 'plus', onClick: () => {} });
 *
 *   // Create speed dial FAB
 *   FAB.createSpeedDial({
 *       icon: 'plus',
 *       actions: [
 *           { icon: 'camera', label: 'Photo', onClick: () => {} },
 *           { icon: 'video', label: 'Video', onClick: () => {} }
 *       ]
 *   });
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        animateOnCreate: true,
        hideOnScroll: false,
        scrollThreshold: 100,
        hapticFeedback: true
    };

    let fabInstances = [];
    let lastScrollY = 0;

    /**
     * Create a simple FAB
     */
    function create(options = {}) {
        const {
            icon = 'plus',
            label = null,
            color = 'primary',
            size = 'md',
            position = 'bottom-right',
            badge = null,
            pulse = false,
            onClick = null,
            container = document.body
        } = options;

        const fab = document.createElement('button');
        fab.className = `fab fab--${size} fab--${color} fab--${position}`;

        if (pulse) fab.classList.add('fab--pulse');
        if (config.animateOnCreate) fab.classList.add('fab-animate-in');

        // Icon
        fab.innerHTML = `<i class="fa-solid fa-${icon}"></i>`;

        // Extended with label
        if (label) {
            fab.classList.add('fab--extended');
            fab.innerHTML += `<span class="fab-label">${label}</span>`;
        }

        // Badge
        if (badge !== null) {
            fab.innerHTML += `<span class="fab-badge">${badge}</span>`;
        }

        // Click handler
        if (onClick) {
            fab.addEventListener('click', (e) => {
                triggerHaptic();
                onClick(e);
            });
        }

        // Accessibility
        fab.setAttribute('type', 'button');
        fab.setAttribute('aria-label', label || `${icon} action`);

        container.appendChild(fab);
        fabInstances.push(fab);

        return fab;
    }

    /**
     * Create speed dial FAB
     */
    function createSpeedDial(options = {}) {
        const {
            icon = 'plus',
            color = 'primary',
            position = 'bottom-right',
            actions = [],
            overlay = true,
            container = document.body
        } = options;

        // Container
        const fabContainer = document.createElement('div');
        fabContainer.className = `fab-container fab--${position}`;

        // Overlay
        if (overlay) {
            const overlayEl = document.createElement('div');
            overlayEl.className = 'fab-overlay';
            overlayEl.addEventListener('click', () => collapse(fabContainer));
            fabContainer.appendChild(overlayEl);
        }

        // Actions
        const actionsContainer = document.createElement('div');
        actionsContainer.className = 'fab-actions';

        actions.forEach((action, index) => {
            const actionEl = document.createElement('div');
            actionEl.className = 'fab-action';

            const btn = document.createElement('button');
            btn.className = 'fab-action-btn';
            btn.innerHTML = `<i class="fa-solid fa-${action.icon}"></i>`;
            btn.setAttribute('type', 'button');
            btn.setAttribute('aria-label', action.label || action.icon);

            btn.addEventListener('click', (e) => {
                triggerHaptic();
                collapse(fabContainer);
                if (action.onClick) action.onClick(e);
            });

            actionEl.appendChild(btn);

            if (action.label) {
                const label = document.createElement('span');
                label.className = 'fab-action-label';
                label.textContent = action.label;
                actionEl.appendChild(label);
            }

            actionsContainer.appendChild(actionEl);
        });

        fabContainer.appendChild(actionsContainer);

        // Main FAB
        const mainFab = document.createElement('button');
        mainFab.className = `fab fab-main fab--${color}`;
        mainFab.innerHTML = `<i class="fa-solid fa-${icon}"></i>`;
        mainFab.setAttribute('type', 'button');
        mainFab.setAttribute('aria-label', 'Toggle actions menu');
        mainFab.setAttribute('aria-expanded', 'false');

        if (config.animateOnCreate) {
            mainFab.classList.add('fab-animate-in');
        }

        mainFab.addEventListener('click', () => {
            triggerHaptic();
            toggle(fabContainer);
        });

        fabContainer.appendChild(mainFab);
        container.appendChild(fabContainer);
        fabInstances.push(fabContainer);

        // Close on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && fabContainer.classList.contains('fab-expanded')) {
                collapse(fabContainer);
            }
        });

        return fabContainer;
    }

    /**
     * Toggle speed dial
     */
    function toggle(fabContainer) {
        if (fabContainer.classList.contains('fab-expanded')) {
            collapse(fabContainer);
        } else {
            expand(fabContainer);
        }
    }

    /**
     * Expand speed dial
     */
    function expand(fabContainer) {
        fabContainer.classList.add('fab-expanded');
        const mainFab = fabContainer.querySelector('.fab-main');
        if (mainFab) {
            mainFab.setAttribute('aria-expanded', 'true');
        }
    }

    /**
     * Collapse speed dial
     */
    function collapse(fabContainer) {
        fabContainer.classList.remove('fab-expanded');
        const mainFab = fabContainer.querySelector('.fab-main');
        if (mainFab) {
            mainFab.setAttribute('aria-expanded', 'false');
        }
    }

    /**
     * Show FAB with animation
     */
    function show(fab) {
        fab.classList.remove('fab-animate-out');
        fab.classList.add('fab-animate-in');
        fab.style.display = '';
    }

    /**
     * Hide FAB with animation
     */
    function hide(fab) {
        fab.classList.remove('fab-animate-in');
        fab.classList.add('fab-animate-out');
        setTimeout(() => {
            fab.style.display = 'none';
        }, 300);
    }

    /**
     * Update FAB badge
     */
    function updateBadge(fab, count) {
        let badge = fab.querySelector('.fab-badge');

        if (count === null || count === 0) {
            if (badge) badge.remove();
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'fab-badge';
            fab.appendChild(badge);
        }

        badge.textContent = count > 99 ? '99+' : count;

        // Animate badge update
        badge.style.transform = 'scale(1.2)';
        setTimeout(() => {
            badge.style.transform = '';
        }, 200);
    }

    /**
     * Set FAB pulse state
     */
    function setPulse(fab, enabled) {
        if (enabled) {
            fab.classList.add('fab--pulse');
        } else {
            fab.classList.remove('fab--pulse');
        }
    }

    /**
     * Trigger haptic feedback
     */
    function triggerHaptic() {
        if (config.hapticFeedback && navigator.vibrate) {
            navigator.vibrate(10);
        }
    }

    /**
     * Handle scroll for hide on scroll
     */
    function handleScroll() {
        if (!config.hideOnScroll) return;

        const currentScrollY = window.pageYOffset;
        const isScrollingDown = currentScrollY > lastScrollY;
        const scrollDelta = Math.abs(currentScrollY - lastScrollY);

        if (scrollDelta < 10) return;

        fabInstances.forEach(fab => {
            if (isScrollingDown && currentScrollY > config.scrollThreshold) {
                fab.style.transform = 'translateY(100px)';
                fab.style.opacity = '0';
            } else {
                fab.style.transform = '';
                fab.style.opacity = '';
            }
        });

        lastScrollY = currentScrollY;
    }

    /**
     * Destroy a FAB
     */
    function destroy(fab) {
        const index = fabInstances.indexOf(fab);
        if (index > -1) {
            fabInstances.splice(index, 1);
        }
        fab.remove();
    }

    /**
     * Destroy all FABs
     */
    function destroyAll() {
        fabInstances.forEach(fab => fab.remove());
        fabInstances = [];
    }

    /**
     * Initialize existing FABs
     */
    function init() {
        // Find existing FABs and add animation class
        const existingFabs = document.querySelectorAll('.fab, .floating-action-button');
        existingFabs.forEach(fab => {
            if (config.animateOnCreate && !fab.classList.contains('fab-animate-in')) {
                fab.classList.add('fab-animate-in');
            }
            fabInstances.push(fab);
        });

        // Set up speed dial containers
        const speedDials = document.querySelectorAll('.fab-container');
        speedDials.forEach(container => {
            const mainFab = container.querySelector('.fab-main, .fab');
            const overlay = container.querySelector('.fab-overlay');

            if (mainFab) {
                mainFab.addEventListener('click', () => {
                    triggerHaptic();
                    toggle(container);
                });
            }

            if (overlay) {
                overlay.addEventListener('click', () => collapse(container));
            }
        });

        // Hide on scroll
        if (config.hideOnScroll) {
            window.addEventListener('scroll', handleScroll, { passive: true });
        }

        console.log(`[FAB] Initialized ${fabInstances.length} floating action buttons`);
    }

    // Public API
    window.FAB = {
        create: create,
        createSpeedDial: createSpeedDial,
        show: show,
        hide: hide,
        expand: expand,
        collapse: collapse,
        toggle: toggle,
        updateBadge: updateBadge,
        setPulse: setPulse,
        destroy: destroy,
        destroyAll: destroyAll,
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
