/**
 * CivicOne PWA Enhancements
 * Push notifications, install prompt, and app lifecycle
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    window.CivicOnePWA = {

        // State
        deferredPrompt: null,
        isInstalled: false,
        pushSubscription: null,

        // =========================================
        // 1. INITIALIZATION
        // =========================================

        init: function() {
            this.checkInstallState();
            this.initInstallPrompt();
            this.initPushNotifications();
            this.initAppLifecycle();

            console.warn('[CivicOne PWA] Initialized');
        },

        // =========================================
        // 2. INSTALL STATE CHECK
        // =========================================

        checkInstallState: function() {
            // Check if running in standalone mode (installed)
            if (window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true) {
                this.isInstalled = true;
                document.body.classList.add('pwa-installed');
            }

            // Listen for display mode changes
            window.matchMedia('(display-mode: standalone)').addEventListener('change', (e) => {
                this.isInstalled = e.matches;
                document.body.classList.toggle('pwa-installed', e.matches);
            });
        },

        // =========================================
        // 3. INSTALL PROMPT
        // =========================================

        initInstallPrompt: function() {
            // Capture the install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;

                // Show install button after delay (don't be too aggressive)
                setTimeout(() => {
                    this.showInstallBanner();
                }, 30000); // 30 seconds delay

                console.warn('[CivicOne PWA] Install prompt captured');
            });

            // Track successful installation
            window.addEventListener('appinstalled', () => {
                this.isInstalled = true;
                this.deferredPrompt = null;
                this.hideInstallBanner();
                document.body.classList.add('pwa-installed');

                // Show thank you message
                if (window.CivicOneMobile) {
                    CivicOneMobile.showToast('App installed successfully!', 'success');
                }

                console.warn('[CivicOne PWA] App installed');
            });

            // Set up install button click handlers
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-pwa-install]')) {
                    e.preventDefault();
                    this.promptInstall();
                }
                if (e.target.closest('[data-pwa-dismiss]')) {
                    e.preventDefault();
                    this.hideInstallBanner();
                    // Remember dismissal
                    localStorage.setItem('pwa-banner-dismissed', Date.now());
                }
            });
        },

        showInstallBanner: function() {
            // Don't show if already installed or dismissed recently
            if (this.isInstalled) return;

            const dismissed = localStorage.getItem('pwa-banner-dismissed');
            if (dismissed && Date.now() - parseInt(dismissed) < 7 * 24 * 60 * 60 * 1000) {
                return; // Don't show if dismissed within 7 days
            }

            // Check if banner already exists
            if (document.getElementById('civic-pwa-banner')) return;

            // Create install banner
            const banner = document.createElement('div');
            banner.id = 'civic-pwa-banner';
            banner.className = 'civic-pwa-banner';
            banner.setAttribute('role', 'alertdialog');
            banner.setAttribute('aria-labelledby', 'pwa-banner-title');
            banner.setAttribute('aria-describedby', 'pwa-banner-desc');

            banner.innerHTML = `
                <div class="civic-pwa-banner-content">
                    <div class="civic-pwa-banner-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                    </div>
                    <div class="civic-pwa-banner-text">
                        <strong id="pwa-banner-title">Install App</strong>
                        <p id="pwa-banner-desc">Add to your home screen for the best experience</p>
                    </div>
                    <div class="civic-pwa-banner-actions">
                        <button type="button" class="civic-pwa-btn-install" data-pwa-install aria-label="Install app">
                            Install
                        </button>
                        <button type="button" class="civic-pwa-btn-dismiss" data-pwa-dismiss aria-label="Dismiss install prompt">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(banner);

            // Animate in
            requestAnimationFrame(() => {
                banner.classList.add('visible');
            });

            // Focus the install button for accessibility
            setTimeout(() => {
                const installBtn = banner.querySelector('[data-pwa-install]');
                if (installBtn) installBtn.focus();
            }, 300);
        },

        hideInstallBanner: function() {
            const banner = document.getElementById('civic-pwa-banner');
            if (!banner) return;

            banner.classList.remove('visible');
            setTimeout(() => banner.remove(), 300);
        },

        async promptInstall() {
            if (!this.deferredPrompt) {
                console.warn('[CivicOne PWA] No install prompt available');
                return;
            }

            // Show the install prompt
            this.deferredPrompt.prompt();

            // Wait for user choice
            const { outcome } = await this.deferredPrompt.userChoice;
            console.warn('[CivicOne PWA] User choice:', outcome);

            // Clear the prompt
            this.deferredPrompt = null;
            this.hideInstallBanner();
        },

        // =========================================
        // 4. PUSH NOTIFICATIONS
        // =========================================

        initPushNotifications: function() {
            // Check support (including Notification API for mobile browsers)
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                console.warn('[CivicOne PWA] Push notifications not supported');
                return;
            }

            // Set up notification permission button handlers
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-push-enable]')) {
                    e.preventDefault();
                    this.requestPushPermission();
                }
            });

            // Check existing subscription
            this.checkPushSubscription();
        },

        async checkPushSubscription() {
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    this.pushSubscription = subscription;
                    document.body.classList.add('push-enabled');
                    console.warn('[CivicOne PWA] Existing push subscription found');
                }
            } catch (e) {
                console.error('[CivicOne PWA] Error checking push subscription:', e);
            }
        },

        async requestPushPermission() {
            try {
                // Request permission
                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    console.warn('[CivicOne PWA] Push permission denied');
                    if (window.CivicOneMobile) {
                        CivicOneMobile.showToast('Notification permission denied', 'warning');
                    }
                    return;
                }

                // Subscribe to push
                await this.subscribeToPush();

            } catch (e) {
                console.error('[CivicOne PWA] Error requesting push permission:', e);
            }
        },

        async subscribeToPush() {
            try {
                const registration = await navigator.serviceWorker.ready;

                // Get VAPID public key from server
                const response = await fetch('/api/push/vapid-public-key');
                const { publicKey } = await response.json();

                // Subscribe
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(publicKey)
                });

                // Send subscription to server
                await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(subscription)
                });

                // Also update user's notification preferences to enable push
                try {
                    await fetch('/api/notifications/settings', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ push_enabled: 1 })
                    });
                } catch (prefError) {
                    console.warn('[CivicOne PWA] Could not update notification preferences:', prefError);
                }

                this.pushSubscription = subscription;
                document.body.classList.add('push-enabled');

                if (window.CivicOneMobile) {
                    CivicOneMobile.showToast('Notifications enabled!', 'success');
                }

                console.warn('[CivicOne PWA] Push subscription successful');

            } catch (e) {
                console.error('[CivicOne PWA] Error subscribing to push:', e);
                if (window.CivicOneMobile) {
                    CivicOneMobile.showToast('Failed to enable notifications', 'error');
                }
            }
        },

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },

        // =========================================
        // 5. APP LIFECYCLE
        // =========================================

        initAppLifecycle: function() {
            // Handle app becoming visible after being in background
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.onAppResume();
                } else {
                    this.onAppPause();
                }
            });

            // Handle page show (back/forward cache)
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) {
                    this.onAppResume();
                }
            });

            // Update check on resume
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(registration => {
                    // Check for updates periodically
                    setInterval(() => {
                        registration.update();
                    }, 60 * 60 * 1000); // Every hour
                });
            }
        },

        onAppResume: function() {
            console.warn('[CivicOne PWA] App resumed');

            // Refresh badges
            if (window.updateBottomNavBadges) {
                updateBottomNavBadges();
            }

            // Check for updates
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(registration => {
                    registration.update();
                });
            }
        },

        onAppPause: function() {
            console.warn('[CivicOne PWA] App paused');
        },

        // =========================================
        // 6. UPDATE PROMPT
        // =========================================

        showUpdatePrompt: function() {
            const banner = document.createElement('div');
            banner.id = 'civic-update-banner';
            banner.className = 'civic-update-banner';
            banner.setAttribute('role', 'alertdialog');
            banner.setAttribute('aria-label', 'App update available');

            banner.innerHTML = `
                <div class="civic-update-banner-content">
                    <span>A new version is available</span>
                    <button type="button" class="civic-update-btn" onclick="window.location.reload()">
                        Update Now
                    </button>
                </div>
            `;

            document.body.appendChild(banner);

            requestAnimationFrame(() => {
                banner.classList.add('visible');
            });
        }
    };

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => CivicOnePWA.init());
    } else {
        CivicOnePWA.init();
    }

    // Re-initialize on Turbo navigation
    document.addEventListener('turbo:load', () => {
        CivicOnePWA.checkInstallState();
    });

})();
