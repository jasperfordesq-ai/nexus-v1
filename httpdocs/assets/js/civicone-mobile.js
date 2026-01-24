/**
 * CivicOne Mobile Enhancements
 * WCAG 2.1 AA Compliant Mobile Experience
 * Includes: Skeleton loading, PWA install prompt, offline detection, pull-to-refresh
 */

(function() {
    'use strict';

    window.CivicOneMobile = {
        // ============================================
        // 1. INITIALIZATION
        // ============================================

        init: function() {
            this.initBottomNav();
            this.initPWAInstallPrompt();
            this.initOfflineDetection();
            // DISABLED: Pull-to-refresh was causing page reload issues and interfering with scrolling
            // this.initPullToRefresh();
            this.initSkeletonLoading();
            this.initTouchEnhancements();

            console.warn('[CivicOne] Mobile enhancements initialized');
        },

        // ============================================
        // 2. BOTTOM NAVIGATION
        // ============================================

        initBottomNav: function() {
            const nav = document.querySelector('.civic-bottom-nav');
            if (!nav) return;

            const currentPath = window.location.pathname;
            const items = nav.querySelectorAll('.civic-bottom-nav-item');

            items.forEach(item => {
                const href = item.getAttribute('href');
                if (!href) return;

                // Normalize paths for comparison
                const itemPath = new URL(href, window.location.origin).pathname;

                // Check if active (exact match or starts with for nested pages)
                const isHome = itemPath === '/' || itemPath.endsWith('/');
                const isActive = isHome
                    ? currentPath === itemPath || currentPath === itemPath.slice(0, -1)
                    : currentPath.startsWith(itemPath);

                if (isActive && !item.classList.contains('civic-bottom-nav-item--create')) {
                    item.classList.add('active');
                    item.setAttribute('aria-current', 'page');
                }
            });

            // Add body padding
            document.body.classList.add('has-civic-bottom-nav');
        },

        // ============================================
        // 3. PWA INSTALL PROMPT
        // ============================================

        deferredPrompt: null,

        initPWAInstallPrompt: function() {
            const self = this;

            // Capture the install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                self.deferredPrompt = e;

                // Check if user has dismissed before
                if (localStorage.getItem('civic-pwa-dismissed')) return;

                // Show install banner after a delay
                setTimeout(() => {
                    self.showPWABanner();
                }, 5000);
            });

            // Track successful install
            window.addEventListener('appinstalled', () => {
                self.hidePWABanner();
                localStorage.setItem('civic-pwa-installed', 'true');
            });
        },

        showPWABanner: function() {
            // Don't show if already installed or on desktop
            if (window.matchMedia('(display-mode: standalone)').matches) return;
            if (window.innerWidth > 768) return;

            let banner = document.querySelector('.civic-pwa-banner');

            if (!banner) {
                banner = document.createElement('div');
                banner.className = 'civic-pwa-banner';
                banner.setAttribute('role', 'dialog');
                banner.setAttribute('aria-labelledby', 'pwa-banner-title');
                banner.innerHTML = `
                    <div class="civic-pwa-banner-content">
                        <div class="civic-pwa-banner-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                            </svg>
                        </div>
                        <div class="civic-pwa-banner-text">
                            <div class="civic-pwa-banner-title" id="pwa-banner-title">Install App</div>
                            <div class="civic-pwa-banner-desc">Add to your home screen for a better experience</div>
                        </div>
                    </div>
                    <div class="civic-pwa-banner-actions">
                        <button class="civic-pwa-banner-dismiss" type="button">Not now</button>
                        <button class="civic-pwa-banner-install" type="button">Install</button>
                    </div>
                `;
                document.body.appendChild(banner);

                // Event handlers
                banner.querySelector('.civic-pwa-banner-dismiss').addEventListener('click', () => {
                    this.hidePWABanner();
                    localStorage.setItem('civic-pwa-dismissed', Date.now());
                });

                banner.querySelector('.civic-pwa-banner-install').addEventListener('click', () => {
                    this.installPWA();
                });
            }

            requestAnimationFrame(() => {
                banner.classList.add('visible');
            });
        },

        hidePWABanner: function() {
            const banner = document.querySelector('.civic-pwa-banner');
            if (banner) {
                banner.classList.remove('visible');
                setTimeout(() => banner.remove(), 300);
            }
        },

        installPWA: async function() {
            if (!this.deferredPrompt) return;

            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;

            if (outcome === 'accepted') {
                this.hidePWABanner();
            }

            this.deferredPrompt = null;
        },

        // ============================================
        // 4. OFFLINE DETECTION
        // ============================================

        networkUnsubscribe: null,

        initOfflineDetection: function() {
            // Only enable offline detection on mobile devices
            // Desktop browsers have unreliable navigator.onLine detection (unless using Capacitor)
            const isNative = window.NexusNative?.Environment?.isCapacitor?.();
            if (window.innerWidth > 768 && !isNative) {
                return;
            }

            // Create offline bar
            let offlineBar = document.querySelector('.civic-offline-bar');
            if (!offlineBar) {
                offlineBar = document.createElement('div');
                offlineBar.className = 'civic-offline-bar';
                offlineBar.setAttribute('role', 'alert');
                offlineBar.setAttribute('aria-live', 'polite');
                offlineBar.textContent = 'You are currently offline. Some features may be unavailable.';
                document.body.insertBefore(offlineBar, document.body.firstChild);
            }

            function showOffline() {
                document.body.classList.add('is-offline');
                offlineBar.classList.add('visible');
            }

            function hideOffline() {
                document.body.classList.remove('is-offline');
                offlineBar.classList.remove('visible');
            }

            // Use NexusNative.Network if available (Capacitor plugin - more reliable)
            if (window.NexusNative?.Network) {
                console.warn('[CivicOneMobile] Using Capacitor Network plugin for offline detection');

                // Listen for network changes via the Capacitor bridge
                this.networkUnsubscribe = window.NexusNative.Network.addListener((status, wasConnected) => {
                    if (status.connected && !wasConnected) {
                        hideOffline();
                    } else if (!status.connected && wasConnected) {
                        showOffline();
                    }
                });

                // Check initial state
                window.NexusNative.Network.getStatus().then(status => {
                    if (!status.connected) {
                        showOffline();
                    }
                });
            } else {
                // Fallback to browser events (less reliable on desktop)
                window.addEventListener('online', hideOffline);
                window.addEventListener('offline', showOffline);

                // Check initial state after a short delay to avoid false positives
                setTimeout(function() {
                    if (!navigator.onLine) {
                        showOffline();
                    }
                }, 1000);
            }
        },

        // ============================================
        // 5. PULL-TO-REFRESH - REMOVED
        // ============================================
        // Pull-to-refresh feature has been permanently removed due to conflicts with scrolling

        initPullToRefresh: function() {
            // Removed - no longer in use
        },

        resetPullIndicator: function(indicator) {
            // Removed - no longer in use
        },

        // ============================================
        // 6. SKELETON LOADING
        // ============================================

        initSkeletonLoading: function() {
            // Replace placeholder containers with skeletons while loading
            const containers = document.querySelectorAll('[data-skeleton]');

            containers.forEach(container => {
                const type = container.dataset.skeleton || 'card';
                const count = parseInt(container.dataset.skeletonCount) || 3;

                if (container.children.length === 0) {
                    this.showSkeletons(container, count, type);
                }
            });
        },

        showSkeletons: function(container, count = 3, type = 'card') {
            if (!container) return;

            container.setAttribute('aria-busy', 'true');
            container.setAttribute('aria-label', 'Loading content');

            for (let i = 0; i < count; i++) {
                const skeleton = this.createSkeleton(type);
                container.appendChild(skeleton);
            }
        },

        createSkeleton: function(type) {
            const skeleton = document.createElement('div');
            skeleton.className = 'civic-card civic-skeleton-card';
            skeleton.setAttribute('aria-hidden', 'true');
            skeleton.setAttribute('role', 'presentation');

            switch (type) {
                case 'listing':
                    skeleton.innerHTML = `
                        <div class="civic-skeleton-card-header">
                            <div class="civic-skeleton civic-skeleton-avatar"></div>
                            <div class="civic-skeleton-card-meta">
                                <div class="civic-skeleton civic-skeleton-text" style="width: 60%;"></div>
                                <div class="civic-skeleton civic-skeleton-text" style="width: 40%;"></div>
                            </div>
                        </div>
                        <div class="civic-skeleton-card-body">
                            <div class="civic-skeleton civic-skeleton-title"></div>
                            <div class="civic-skeleton civic-skeleton-text"></div>
                            <div class="civic-skeleton civic-skeleton-text" style="width: 80%;"></div>
                        </div>
                    `;
                    break;

                case 'member':
                    skeleton.className = 'civic-member-card civic-skeleton-member';
                    skeleton.innerHTML = `
                        <div class="civic-skeleton civic-skeleton-avatar" style="width: 80px; height: 80px; margin: 0 auto 12px;"></div>
                        <div class="civic-skeleton civic-skeleton-title" style="width: 70%; margin: 0 auto;"></div>
                        <div class="civic-skeleton civic-skeleton-text" style="width: 50%; margin: 8px auto 0;"></div>
                    `;
                    break;

                default:
                    skeleton.innerHTML = `
                        <div class="civic-skeleton civic-skeleton-title"></div>
                        <div class="civic-skeleton civic-skeleton-text"></div>
                        <div class="civic-skeleton civic-skeleton-text" style="width: 80%;"></div>
                    `;
            }

            return skeleton;
        },

        hideSkeletons: function(container) {
            if (!container) return;

            container.removeAttribute('aria-busy');
            container.removeAttribute('aria-label');

            const skeletons = container.querySelectorAll('.civic-skeleton-card, .civic-skeleton-member');
            skeletons.forEach(s => s.remove());
        },

        // ============================================
        // 7. TOUCH ENHANCEMENTS
        // ============================================

        initTouchEnhancements: function() {
            // Only on touch devices
            if (!('ontouchstart' in window)) return;

            // Add active state feedback to buttons
            const buttons = document.querySelectorAll('.civic-btn, .civic-bottom-nav-item, .civic-card a');

            buttons.forEach(btn => {
                btn.addEventListener('touchstart', function() {
                    this.classList.add('touch-active');
                }, { passive: true });

                btn.addEventListener('touchend', function() {
                    this.classList.remove('touch-active');
                }, { passive: true });

                btn.addEventListener('touchcancel', function() {
                    this.classList.remove('touch-active');
                }, { passive: true });
            });

            // Prevent double-tap zoom on buttons (accessibility feature)
            document.addEventListener('touchend', function(e) {
                const target = e.target;
                if (target.matches('button, a, [role="button"]')) {
                    e.preventDefault();
                    target.click();
                }
            }, { passive: false });
        },

        // ============================================
        // 8. TOAST NOTIFICATIONS
        // ============================================

        showToast: function(message, type = 'default', duration = 3000) {
            let container = document.querySelector('.civic-toast-container');

            if (!container) {
                container = document.createElement('div');
                container.className = 'civic-toast-container';
                container.setAttribute('aria-live', 'polite');
                container.setAttribute('aria-atomic', 'true');
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `civic-toast ${type}`;
            toast.setAttribute('role', 'status');

            const icons = {
                success: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>',
                error: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>',
                warning: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
                info: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
                default: ''
            };

            toast.innerHTML = `${icons[type] || ''}<span>${message}</span>`;
            container.appendChild(toast);

            // Auto remove
            setTimeout(() => {
                toast.classList.add('exit');
                setTimeout(() => toast.remove(), 200);
            }, duration);

            return toast;
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => CivicOneMobile.init());
    } else {
        CivicOneMobile.init();
    }

})();
