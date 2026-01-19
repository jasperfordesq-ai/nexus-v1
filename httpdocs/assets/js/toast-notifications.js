/**
 * Toast Notifications - Polished System
 * Slide-in animations, stacking behavior, auto-dismiss
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   Toast.success('Title', 'Message');
 *   Toast.error('Title', 'Message');
 *   Toast.warning('Title', 'Message');
 *   Toast.info('Title', 'Message');
 *
 * With options:
 *   Toast.success('Title', 'Message', {
 *     duration: 5000,      // Auto-dismiss time (0 = no auto-dismiss)
 *     position: 'top-right', // top-right, top-left, top-center, bottom-right, bottom-left, bottom-center
 *     showProgress: true,  // Show countdown progress bar
 *     closable: true,      // Show close button
 *     action: { label: 'Undo', onClick: () => {} }  // Optional action button
 *   });
 */

(function() {
    'use strict';

    // Default configuration
    const defaults = {
        duration: 5000,
        position: 'top-right',
        showProgress: true,
        closable: true,
        maxToasts: 5,
        gap: 12
    };

    // Icon mapping
    const icons = {
        success: '<i class="fa-solid fa-check"></i>',
        error: '<i class="fa-solid fa-xmark"></i>',
        warning: '<i class="fa-solid fa-exclamation"></i>',
        info: '<i class="fa-solid fa-info"></i>'
    };

    // Container cache by position
    const containers = {};

    // Active toasts tracking
    const activeToasts = new Map();

    /**
     * Get or create container for a position
     */
    function getContainer(position) {
        if (containers[position]) {
            return containers[position];
        }

        const container = document.createElement('div');
        container.className = 'toast-container';
        container.setAttribute('data-position', position);
        container.setAttribute('role', 'alert');
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
        containers[position] = container;

        return container;
    }

    /**
     * Get slide animation direction based on position
     */
    function getSlideDirection(position) {
        if (position.includes('right')) return 'right';
        if (position.includes('left')) return 'left';
        if (position.startsWith('top')) return 'top';
        if (position.startsWith('bottom')) return 'bottom';
        return 'right';
    }

    /**
     * Create a toast element
     */
    function createToast(type, title, message, options) {
        const toast = document.createElement('div');
        const id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        toast.id = id;
        toast.className = `toast toast-${type}`;

        const direction = getSlideDirection(options.position);
        toast.classList.add(`toast-slide-${direction}-enter`);

        // Set duration as CSS variable for progress bar
        if (options.duration > 0) {
            toast.style.setProperty('--toast-duration', options.duration + 'ms');
        }

        // Build HTML
        let html = `
            <div class="toast-icon">${icons[type]}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${escapeHtml(title)}</div>` : ''}
                ${message ? `<div class="toast-message">${escapeHtml(message)}</div>` : ''}
        `;

        // Add action button if provided
        if (options.action && options.action.label) {
            html += `<button class="toast-action" data-action="true">${escapeHtml(options.action.label)}</button>`;
        }

        html += '</div>';

        // Add close button
        if (options.closable) {
            html += '<button class="toast-close" aria-label="Close notification"><i class="fa-solid fa-times"></i></button>';
        }

        // Add progress bar
        if (options.showProgress && options.duration > 0) {
            html += '<div class="toast-progress animate"></div>';
        }

        toast.innerHTML = html;

        return { element: toast, id };
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Remove a toast with exit animation
     */
    function removeToast(id, position) {
        const toast = document.getElementById(id);
        if (!toast) return;

        const direction = getSlideDirection(position);
        toast.classList.remove(`toast-slide-${direction}-enter`);
        toast.classList.add(`toast-slide-${direction}-exit`);

        // Clear any existing timeout
        const data = activeToasts.get(id);
        if (data && data.timeout) {
            clearTimeout(data.timeout);
        }
        activeToasts.delete(id);

        // Remove after animation
        toast.addEventListener('animationend', function() {
            toast.remove();

            // Clean up empty containers
            const container = containers[position];
            if (container && container.children.length === 0) {
                // Keep container in DOM but hidden for reuse
            }
        }, { once: true });

        // Fallback removal for reduced motion
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 250);
    }

    /**
     * Enforce max toasts limit
     */
    function enforceMaxToasts(position) {
        const container = containers[position];
        if (!container) return;

        const toasts = container.querySelectorAll('.toast');
        const excess = toasts.length - defaults.maxToasts;

        if (excess > 0) {
            // Remove oldest toasts
            for (let i = 0; i < excess; i++) {
                const oldest = toasts[i];
                if (oldest && oldest.id) {
                    removeToast(oldest.id, position);
                }
            }
        }
    }

    /**
     * Show a toast notification
     */
    function show(type, title, message, userOptions = {}) {
        const options = { ...defaults, ...userOptions };
        const container = getContainer(options.position);

        // Create toast
        const { element: toast, id } = createToast(type, title, message, options);

        // Add to container
        if (options.position.startsWith('bottom')) {
            // For bottom positions, prepend so newest is at bottom
            container.insertBefore(toast, container.firstChild);
        } else {
            // For top positions, append so newest is at bottom of stack
            container.appendChild(toast);
        }

        // Enforce max toasts
        enforceMaxToasts(options.position);

        // Setup event listeners
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => removeToast(id, options.position));
        }

        const actionBtn = toast.querySelector('.toast-action');
        if (actionBtn && options.action && options.action.onClick) {
            actionBtn.addEventListener('click', () => {
                options.action.onClick();
                removeToast(id, options.position);
            });
        }

        // Setup auto-dismiss
        let timeout = null;
        if (options.duration > 0) {
            timeout = setTimeout(() => {
                removeToast(id, options.position);
            }, options.duration);

            // Pause on hover
            toast.addEventListener('mouseenter', () => {
                clearTimeout(timeout);
                const progress = toast.querySelector('.toast-progress');
                if (progress) {
                    progress.style.animationPlayState = 'paused';
                }
            });

            toast.addEventListener('mouseleave', () => {
                const progress = toast.querySelector('.toast-progress');
                const remaining = progress ?
                    options.duration * (1 - getProgressWidth(progress)) :
                    options.duration / 2;

                timeout = setTimeout(() => {
                    removeToast(id, options.position);
                }, remaining);

                if (progress) {
                    progress.style.animationPlayState = 'running';
                }
            });
        }

        // Track active toast
        activeToasts.set(id, { element: toast, timeout, position: options.position });

        // Haptic feedback on mobile
        if (navigator.vibrate && (type === 'error' || type === 'warning')) {
            navigator.vibrate(type === 'error' ? [50, 30, 50] : 50);
        }

        return id;
    }

    /**
     * Get progress bar width ratio
     */
    function getProgressWidth(progress) {
        const style = getComputedStyle(progress);
        const transform = style.transform;
        if (transform && transform !== 'none') {
            const match = transform.match(/scaleX\(([\d.]+)\)/);
            if (match) {
                return parseFloat(match[1]);
            }
        }
        return 0;
    }

    /**
     * Dismiss a toast by ID
     */
    function dismiss(id) {
        const data = activeToasts.get(id);
        if (data) {
            removeToast(id, data.position);
        }
    }

    /**
     * Dismiss all toasts
     */
    function dismissAll() {
        activeToasts.forEach((data, id) => {
            removeToast(id, data.position);
        });
    }

    // Public API
    window.Toast = {
        success: (title, message, options) => show('success', title, message, options),
        error: (title, message, options) => show('error', title, message, options),
        warning: (title, message, options) => show('warning', title, message, options),
        info: (title, message, options) => show('info', title, message, options),
        show: show,
        dismiss: dismiss,
        dismissAll: dismissAll,
        config: (newDefaults) => Object.assign(defaults, newDefaults)
    };

    // Also expose as window.showToast for backward compatibility
    window.showToast = function(message, type = 'info', duration = 3000) {
        return show(type, '', message, { duration });
    };

})();
