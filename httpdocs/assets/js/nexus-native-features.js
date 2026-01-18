/**
 * NEXUS Native Features
 * Handles badge count and deep links for the native Android app
 */
(function() {
    'use strict';

    // Only run in native Capacitor environment
    const isNativeApp = typeof Capacitor !== 'undefined' && Capacitor.isNativePlatform && Capacitor.isNativePlatform();

    if (!isNativeApp) {
        console.log('[NativeFeatures] Not in native app, skipping native features');
        return;
    }

    console.log('[NativeFeatures] Initializing native features');

    // ==========================================
    // BADGE COUNT - Show unread count on app icon
    // ==========================================
    const BadgeManager = {
        Badge: null,

        async init() {
            try {
                // Try to get Badge plugin
                this.Badge = Capacitor.Plugins.Badge;

                if (!this.Badge) {
                    console.log('[Badge] Badge plugin not available');
                    return;
                }

                // Check if supported
                const { isSupported } = await this.Badge.isSupported();
                if (!isSupported) {
                    console.log('[Badge] Badge not supported on this device');
                    return;
                }

                // Request permission (Android 13+)
                await this.Badge.requestPermissions();

                // Set initial badge count
                this.updateBadgeFromServer();

                // Listen for notification events to update badge
                this.setupListeners();

                console.log('[Badge] Badge manager initialized');
            } catch (error) {
                console.error('[Badge] Init error:', error);
            }
        },

        setupListeners() {
            // Update badge when page loads or becomes visible
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.updateBadgeFromServer();
                }
            });

            // Listen for custom badge update events
            window.addEventListener('nexus:badge-update', (e) => {
                this.set(e.detail.count);
            });

            // Poll for updates every 60 seconds
            setInterval(() => this.updateBadgeFromServer(), 60000);
        },

        async updateBadgeFromServer() {
            try {
                const response = await fetch('/api/notifications/unread-count', {
                    credentials: 'include'
                });

                if (response.ok) {
                    const data = await response.json();
                    this.set(data.count || 0);
                }
                // Silently ignore 401s - user will be redirected naturally when they navigate
            } catch (error) {
                console.error('[Badge] Failed to fetch unread count:', error);
            }
        },

        async set(count) {
            if (!this.Badge) return;

            try {
                if (count > 0) {
                    await this.Badge.set({ count: count });
                    console.log('[Badge] Set to:', count);
                } else {
                    await this.Badge.clear();
                    console.log('[Badge] Cleared');
                }
            } catch (error) {
                console.error('[Badge] Set error:', error);
            }
        },

        async clear() {
            if (!this.Badge) return;

            try {
                await this.Badge.clear();
                console.log('[Badge] Cleared');
            } catch (error) {
                console.error('[Badge] Clear error:', error);
            }
        },

        async increase(amount = 1) {
            if (!this.Badge) return;

            try {
                await this.Badge.increase({ count: amount });
            } catch (error) {
                console.error('[Badge] Increase error:', error);
            }
        },

        async decrease(amount = 1) {
            if (!this.Badge) return;

            try {
                await this.Badge.decrease({ count: amount });
            } catch (error) {
                console.error('[Badge] Decrease error:', error);
            }
        }
    };

    // ==========================================
    // DEEP LINKS - Handle app links
    // ==========================================
    const DeepLinkManager = {
        App: null,

        async init() {
            try {
                this.App = Capacitor.Plugins.App;

                if (!this.App) {
                    console.log('[DeepLink] App plugin not available');
                    return;
                }

                // Handle app URL open events (deep links)
                this.App.addListener('appUrlOpen', (event) => {
                    console.log('[DeepLink] App URL opened:', event.url);
                    this.handleDeepLink(event.url);
                });

                // Handle app state changes
                this.App.addListener('appStateChange', (state) => {
                    console.log('[DeepLink] App state:', state.isActive ? 'active' : 'background');

                    if (state.isActive) {
                        // App came to foreground - refresh badge
                        BadgeManager.updateBadgeFromServer();
                    }
                });

                // Handle back button
                this.App.addListener('backButton', (event) => {
                    console.log('[DeepLink] Back button pressed, can go back:', event.canGoBack);

                    if (event.canGoBack) {
                        window.history.back();
                    } else {
                        // Optionally minimize app or show exit confirmation
                        this.showExitConfirmation();
                    }
                });

                console.log('[DeepLink] Deep link manager initialized');
            } catch (error) {
                console.error('[DeepLink] Init error:', error);
            }
        },

        handleDeepLink(url) {
            try {
                const urlObj = new URL(url);

                // Only handle URLs for our domain
                if (urlObj.hostname !== 'hour-timebank.ie' && urlObj.hostname !== 'www.hour-timebank.ie') {
                    console.log('[DeepLink] External URL, not handling:', url);
                    return;
                }

                // Get the path
                const path = urlObj.pathname + urlObj.search + urlObj.hash;

                // Navigate to the path
                if (path && path !== '/') {
                    console.log('[DeepLink] Navigating to:', path);
                    window.location.href = path;
                }
            } catch (error) {
                console.error('[DeepLink] Handle error:', error);
            }
        },

        showExitConfirmation() {
            // Simple exit confirmation
            if (confirm('Exit the app?')) {
                this.App.exitApp();
            }
        }
    };

    // ==========================================
    // SHARE TARGET - Receive shared content
    // ==========================================
    const ShareTargetManager = {
        async init() {
            // Check if app was opened with shared content
            const url = new URL(window.location.href);
            const sharedTitle = url.searchParams.get('shared_title');
            const sharedText = url.searchParams.get('shared_text');
            const sharedUrl = url.searchParams.get('shared_url');

            if (sharedTitle || sharedText || sharedUrl) {
                console.log('[ShareTarget] Received shared content:', { sharedTitle, sharedText, sharedUrl });

                // Store in session for use in post creation
                sessionStorage.setItem('nexus_shared_content', JSON.stringify({
                    title: sharedTitle,
                    text: sharedText,
                    url: sharedUrl
                }));

                // Redirect to create post if not already there
                if (!window.location.pathname.includes('/create') && !window.location.pathname.includes('/post')) {
                    window.location.href = '/create?shared=1';
                }
            }
        }
    };

    // ==========================================
    // INITIALIZATION
    // ==========================================
    async function init() {
        await BadgeManager.init();
        await DeepLinkManager.init();
        await ShareTargetManager.init();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose globally for debugging and external access
    window.NexusBadge = BadgeManager;
    window.NexusDeepLink = DeepLinkManager;

})();
