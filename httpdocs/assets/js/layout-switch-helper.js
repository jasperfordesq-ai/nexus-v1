/**
 * Layout Switch Helper v2.0 - Clean Implementation
 * Session-based layout switching without URL pollution
 * Zero parameters, pure session handling
 */

(function() {
    'use strict';

    /**
     * Layout Switcher Class
     */
    class LayoutSwitcher {
        constructor() {
            this.currentLayout = document.documentElement.getAttribute('data-layout') || 'modern';
            this.switching = false;
            this.endpoint = '/api/layout-switch';
            this.init();
        }

        init() {
            // Listen for layout switch buttons
            document.addEventListener('click', (e) => {
                const switcher = e.target.closest('[data-layout-switcher]');
                if (switcher) {
                    e.preventDefault();
                    const targetLayout = switcher.dataset.layoutSwitcher;
                    this.switchLayout(targetLayout);
                }
            });

            // Listen for custom events
            document.addEventListener('nexus:switchLayout', (e) => {
                this.switchLayout(e.detail.layout);
            });

            // Clean URL on page load (remove legacy parameters)
            this.cleanUrlParams();

            // Ensure layout attribute is set
            this.ensureLayoutAttribute();

            // Add transition guard
            this.addTransitionGuard();
        }

        async switchLayout(targetLayout) {
            if (this.switching || targetLayout === this.currentLayout) {
                return;
            }

            this.switching = true;
            this.showLoadingState();

            try {
                // Get CSRF token from meta tag
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                // Send POST request to backend (session-based)
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        layout: targetLayout,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Layout saved to session
                    this.currentLayout = targetLayout;

                    // Set flag so next page load knows it's a layout switch
                    sessionStorage.setItem('nexus_layout_switching', 'true');

                    // Emit event
                    document.dispatchEvent(new CustomEvent('nexus:layoutChanged', {
                        detail: { layout: targetLayout }
                    }));

                    // Small delay to ensure overlay is fully visible before redirect
                    // This prevents flash of old layout during transition
                    setTimeout(() => {
                        window.location.replace(this.cleanUrl(window.location.href));
                    }, 200);
                } else {
                    console.error('Layout switch failed:', data.message);
                    this.showError(data.message || 'Failed to switch layout');
                }
            } catch (error) {
                console.error('Layout switch error:', error);
                this.showError('Failed to switch layout. Please try again.');
            } finally {
                this.switching = false;
            }
        }

        /**
         * Clean URL - Remove all layout-related parameters
         */
        cleanUrl(url) {
            const urlObj = new URL(url);
            urlObj.searchParams.delete('layout');
            urlObj.searchParams.delete('_refresh');
            return urlObj.toString();
        }

        /**
         * Clean URL parameters on page load
         */
        cleanUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);

            // Remove legacy parameters
            if (urlParams.has('layout') || urlParams.has('_refresh')) {
                urlParams.delete('layout');
                urlParams.delete('_refresh');

                const newUrl = window.location.pathname +
                    (urlParams.toString() ? '?' + urlParams.toString() : '') +
                    window.location.hash;

                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', newUrl);
                }
            }
        }

        /**
         * Show loading overlay
         */
        showLoadingState() {
            document.body.classList.add('layout-switching');

            const overlay = document.createElement('div');
            overlay.className = 'layout-switch-overlay';
            overlay.innerHTML = `
                <div class="layout-switch-spinner">
                    <div class="spinner-ring"></div>
                    <p>Switching layout...</p>
                </div>
            `;
            document.body.appendChild(overlay);

            requestAnimationFrame(() => {
                overlay.classList.add('visible');
            });
        }

        /**
         * Show error message
         */
        showError(message) {
            document.body.classList.remove('layout-switching');

            const overlay = document.querySelector('.layout-switch-overlay');
            if (overlay) {
                overlay.remove();
            }

            // Show toast if available, otherwise alert
            if (window.toast && typeof window.toast.error === 'function') {
                window.toast.error(message);
            } else {
                alert(message);
            }
        }

        /**
         * Ensure layout attribute is set
         */
        ensureLayoutAttribute() {
            if (!document.documentElement.getAttribute('data-layout')) {
                document.documentElement.setAttribute('data-layout', 'modern');
            }
        }

        /**
         * Add smooth transition guard - ONLY during layout switches
         * Removed from normal page loads to prevent flash on navigation
         */
        addTransitionGuard() {
            // Only apply transition guard if we're coming FROM a layout switch
            // Check for a flag set by the switching process
            const isLayoutSwitch = sessionStorage.getItem('nexus_layout_switching');

            if (isLayoutSwitch) {
                // Clear the flag
                sessionStorage.removeItem('nexus_layout_switching');

                // Apply guard only during switch
                document.body.classList.add('layout-transition-guard');

                const markLoaded = () => {
                    // Longer delay to ensure new CSS is fully applied
                    setTimeout(() => {
                        document.body.classList.add('loaded');
                    }, 150);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', markLoaded);
                } else {
                    markLoaded();
                }
            }
            // If not a layout switch, don't add any opacity guard - let page render normally
        }

        getCurrentLayout() {
            return this.currentLayout;
        }
    }

    /**
     * Inject minimal CSS for loading state
     */
    const injectStyles = () => {
        const style = document.createElement('style');
        style.textContent = `
            .layout-switch-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(8px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .layout-switch-overlay.visible {
                opacity: 1;
            }

            .layout-switch-spinner {
                text-align: center;
                color: white;
            }

            .spinner-ring {
                width: 48px;
                height: 48px;
                margin: 0 auto 16px;
                border: 3px solid rgba(255, 255, 255, 0.2);
                border-top-color: white;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .layout-switch-spinner p {
                font-size: 14px;
                font-weight: 500;
                margin: 0;
            }

            body.layout-switching {
                overflow: hidden;
                pointer-events: none;
            }

            /* Smooth transition guard */
            .layout-transition-guard {
                opacity: 0;
                transition: opacity 0.2s ease-out;
            }

            .layout-transition-guard.loaded {
                opacity: 1;
            }
        `;
        document.head.appendChild(style);
    };

    /**
     * Handle scroll to adjust utility bar position
     */
    const handleBannerScroll = () => {
        const banner = document.querySelector('.modern-experimental-banner');
        if (!banner) return;

        let ticking = false;

        const checkBannerVisibility = () => {
            const bannerRect = banner.getBoundingClientRect();
            const html = document.documentElement;

            // If banner has scrolled out of view (top is negative and bottom is <= 0)
            if (bannerRect.bottom <= 0) {
                html.classList.add('banner-scrolled');
            } else {
                html.classList.remove('banner-scrolled');
            }

            ticking = false;
        };

        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(checkBannerVisibility);
                ticking = true;
            }
        }, { passive: true });

        // Check initial state
        checkBannerVisibility();
    };

    /**
     * Initialize
     */
    const init = () => {
        injectStyles();

        // Check for modern browser support
        if ('fetch' in window && 'Promise' in window) {
            window.layoutSwitcher = new LayoutSwitcher();
        } else {
            console.warn('LayoutSwitcher requires modern browser with fetch API');
        }

        // Setup scroll handler for banner
        handleBannerScroll();
    };

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export for module usage
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = LayoutSwitcher;
    }
})();
