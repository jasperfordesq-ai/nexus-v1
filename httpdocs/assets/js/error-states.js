/**
 * Error State Polish
 * Friendly error pages with animations
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Show connection status
 *   ErrorStates.showOffline();
 *   ErrorStates.showOnline();
 *
 *   // Create error page content
 *   ErrorStates.create404(container);
 *   ErrorStates.create500(container);
 *
 *   // Show session expired modal
 *   ErrorStates.showSessionExpired();
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        autoDetectOffline: true,
        showOnlineToast: true,
        onlineToastDuration: 3000,
        sessionCheckInterval: 60000
    };

    let offlineBanner = null;
    let onlineToast = null;

    // Error page templates
    const templates = {
        404: {
            code: '404',
            icon: 'fa-ghost',
            title: 'Page Not Found',
            message: "We couldn't find the page you're looking for. It might have been moved, deleted, or never existed.",
            actions: [
                { label: 'Go Home', href: '/', primary: true },
                { label: 'Go Back', onClick: () => history.back() }
            ]
        },
        500: {
            code: '500',
            icon: 'fa-bug',
            title: 'Something Went Wrong',
            message: "We're experiencing technical difficulties. Our team has been notified and is working on it.",
            actions: [
                { label: 'Try Again', onClick: () => location.reload(), primary: true },
                { label: 'Go Home', href: '/' }
            ]
        },
        403: {
            code: '403',
            icon: 'fa-lock',
            title: 'Access Denied',
            message: "You don't have permission to access this page. Please contact support if you think this is a mistake.",
            actions: [
                { label: 'Go Home', href: '/', primary: true },
                { label: 'Contact Support', href: '/contact' }
            ]
        },
        offline: {
            icon: 'fa-wifi-slash',
            title: "You're Offline",
            message: "Check your internet connection and try again.",
            actions: [
                { label: 'Try Again', onClick: () => location.reload(), primary: true }
            ]
        }
    };

    /**
     * Create error page HTML
     */
    function createErrorPage(type, container) {
        const template = templates[type] || templates[404];

        const html = `
            <div class="error-page">
                <div class="error-page-content">
                    ${template.code ? `
                        <div class="error-code error-code--${template.code}">${template.code}</div>
                    ` : `
                        <div class="error-icon-circle">
                            <i class="fa-solid ${template.icon}"></i>
                        </div>
                    `}
                    <h1 class="error-title">${template.title}</h1>
                    <p class="error-message">${template.message}</p>
                    <div class="error-actions">
                        ${template.actions.map(action => `
                            <${action.href ? 'a' : 'button'}
                                class="error-btn ${action.primary ? 'error-btn--primary' : 'error-btn--secondary'}"
                                ${action.href ? `href="${action.href}"` : 'type="button"'}
                                ${action.onClick ? `data-action="${action.label}"` : ''}
                            >
                                ${action.label}
                            </${action.href ? 'a' : 'button'}>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;

        if (container) {
            container.innerHTML = html;

            // Bind click handlers
            template.actions.forEach(action => {
                if (action.onClick) {
                    const btn = container.querySelector(`[data-action="${action.label}"]`);
                    if (btn) {
                        btn.addEventListener('click', action.onClick);
                    }
                }
            });
        }

        return html;
    }

    /**
     * Create 404 error page
     */
    function create404(container) {
        return createErrorPage('404', container);
    }

    /**
     * Create 500 error page
     */
    function create500(container) {
        return createErrorPage('500', container);
    }

    /**
     * Create 403 error page
     */
    function create403(container) {
        return createErrorPage('403', container);
    }

    /**
     * Create offline error page
     */
    function createOffline(container) {
        return createErrorPage('offline', container);
    }

    /**
     * Show offline banner
     */
    function showOffline() {
        if (offlineBanner) return;

        offlineBanner = document.createElement('div');
        offlineBanner.className = 'offline-banner';
        offlineBanner.innerHTML = `
            <i class="fa-solid fa-wifi-slash"></i>
            <span>You're offline. Some features may be unavailable.</span>
        `;

        document.body.appendChild(offlineBanner);
    }

    /**
     * Hide offline banner
     */
    function hideOffline() {
        if (offlineBanner) {
            offlineBanner.remove();
            offlineBanner = null;
        }
    }

    /**
     * Show online toast
     */
    function showOnline() {
        hideOffline();

        if (!config.showOnlineToast) return;

        onlineToast = document.createElement('div');
        onlineToast.className = 'online-toast';
        onlineToast.innerHTML = `
            <i class="fa-solid fa-wifi"></i>
            <span>You're back online!</span>
        `;

        document.body.appendChild(onlineToast);

        setTimeout(() => {
            if (onlineToast) {
                onlineToast.style.opacity = '0';
                onlineToast.style.transform = 'translateX(-50%) translateY(20px)';
                setTimeout(() => {
                    if (onlineToast) {
                        onlineToast.remove();
                        onlineToast = null;
                    }
                }, 300);
            }
        }, config.onlineToastDuration);
    }

    /**
     * Show session expired modal
     */
    function showSessionExpired(redirectUrl = '/login') {
        const modal = document.createElement('div');
        modal.className = 'session-expired';
        modal.innerHTML = `
            <div class="session-expired-modal">
                <div class="error-icon-circle error-icon-circle--warning">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h2 class="error-title">Session Expired</h2>
                <p class="error-message">Your session has expired. Please log in again to continue.</p>
                <div class="error-actions">
                    <a href="${redirectUrl}" class="error-btn error-btn--primary">Log In</a>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                window.location.href = redirectUrl;
            }
        });

        return modal;
    }

    /**
     * Create empty state
     */
    function createEmptyState(options = {}) {
        const {
            icon = 'fa-inbox',
            title = 'Nothing here yet',
            message = 'Check back later or try a different search.',
            action = null
        } = options;

        const el = document.createElement('div');
        el.className = 'empty-state';
        el.innerHTML = `
            <div class="empty-state-icon">
                <i class="fa-solid ${icon}"></i>
            </div>
            <h3 class="empty-state-title">${title}</h3>
            <p class="empty-state-message">${message}</p>
            ${action ? `
                <button type="button" class="error-btn error-btn--primary empty-state-action">
                    ${action.label}
                </button>
            ` : ''}
        `;

        if (action && action.onClick) {
            el.querySelector('.empty-state-action')?.addEventListener('click', action.onClick);
        }

        return el;
    }

    /**
     * Create load error state with retry
     */
    function createLoadError(options = {}) {
        const {
            message = 'Failed to load content',
            onRetry = null
        } = options;

        const el = document.createElement('div');
        el.className = 'load-error';
        el.innerHTML = `
            <div class="load-error-icon">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <p class="load-error-message">${message}</p>
            ${onRetry ? `
                <button type="button" class="load-error-retry">
                    <i class="fa-solid fa-rotate-right"></i>
                    Try Again
                </button>
            ` : ''}
        `;

        if (onRetry) {
            const retryBtn = el.querySelector('.load-error-retry');
            retryBtn.addEventListener('click', async () => {
                retryBtn.classList.add('loading');
                try {
                    await onRetry();
                } finally {
                    retryBtn.classList.remove('loading');
                }
            });
        }

        return el;
    }

    /**
     * Handle network status changes
     */
    function handleOnline() {
        showOnline();
    }

    function handleOffline() {
        showOffline();
    }

    /**
     * Initialize
     */
    function init() {
        if (!config.autoDetectOffline) return;

        // Listen for online/offline events
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        // Check initial state
        if (!navigator.onLine) {
            showOffline();
        }

        console.warn('[ErrorStates] Initialized');
    }

    // Public API
    window.ErrorStates = {
        create404: create404,
        create500: create500,
        create403: create403,
        createOffline: createOffline,
        createErrorPage: createErrorPage,
        createEmptyState: createEmptyState,
        createLoadError: createLoadError,
        showOffline: showOffline,
        hideOffline: hideOffline,
        showOnline: showOnline,
        showSessionExpired: showSessionExpired,
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
