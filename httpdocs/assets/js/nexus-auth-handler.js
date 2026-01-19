/**
 * NEXUS Auth Handler
 * Handles session keep-alive and token management for mobile apps
 *
 * This module helps prevent unexpected logouts on mobile apps by:
 * 1. Providing a session heartbeat to keep sessions alive
 * 2. Supporting token-based authentication for mobile apps
 * 3. Automatic token refresh before expiration
 * 4. Fetch interceptor for automatic Bearer token injection and 401 handling
 * 5. Persistent storage using Capacitor Preferences for Android reliability
 * 6. Recovery from app termination and background suspension
 */
(function() {
    'use strict';

    window.NexusAuth = {
        // Configuration
        // Mobile devices get longer intervals and thresholds for battery efficiency
        // and "install and forget" experience (like Facebook/Instagram)
        config: {
            // Heartbeat: Mobile = 10 min, Desktop = 2 min
            heartbeatInterval: 2 * 60 * 1000, // Will be adjusted in init() based on platform
            // Token refresh check: Mobile = 10 min, Desktop = 2 min
            tokenRefreshInterval: 2 * 60 * 1000, // Will be adjusted in init()
            // Refresh threshold: Mobile = 1 day before expiry, Desktop = 10 min before expiry
            tokenRefreshThreshold: 600, // Will be adjusted in init()
            heartbeatEndpoint: '/api/auth/heartbeat',
            checkSessionEndpoint: '/api/auth/check-session',
            refreshTokenEndpoint: '/api/auth/refresh-token',
            tokenStorageKey: 'nexus_auth_token',
            tokenExpiryKey: 'nexus_token_expiry',
            userStorageKey: 'nexus_user_data',
            refreshTokenStorageKey: 'nexus_refresh_token',
            platformStorageKey: 'nexus_platform'
        },

        // State
        heartbeatTimer: null,
        tokenRefreshTimer: null,
        isRefreshing: false,
        refreshPromise: null,
        lastActivity: Date.now(),
        storageReady: false,
        useCapacitorStorage: false,
        capacitorPreferences: null,
        isRecovering: false,
        networkConnected: true,
        // Note: Automatic logout on heartbeat failures has been DISABLED
        // The heartbeat only keeps sessions alive - it does not force logouts
        // This prevents random unexpected logouts that were frustrating users

        /**
         * Initialize the auth handler
         * Call this early in your app initialization
         */
        init: async function() {
            // Initialize storage first (critical for Android)
            await this.initStorage();

            // Detect platform and adjust config for mobile "install and forget" experience
            this.detectAndConfigurePlatform();

            // Attempt to recover auth state if we're on Android and localStorage was cleared
            await this.recoverAuthState();

            // Only initialize for logged-in users
            if (!this.isLoggedIn()) {
                console.log('[NexusAuth] Not logged in, skipping initialization');
                return;
            }

            // For mobile devices, ensure the PHP session is in sync with our tokens
            // This bridges the gap between token auth and PHP session-based pages
            if (this.isMobileDevice()) {
                await this.ensureSessionSync();
            }

            this.setupActivityTracking();
            this.startHeartbeat();
            this.startTokenRefreshTimer();
            this.setupVisibilityHandler();
            this.setupFetchInterceptor();
            this.setupNetworkHandler();

            const platform = this.isMobileDevice() ? 'mobile' : 'desktop';
            console.log('[NexusAuth] Auth handler initialized for ' + platform + ' platform');
        },

        /**
         * Ensure PHP session is in sync with our tokens
         * Calls the restore-session endpoint to create/restore PHP session from Bearer token
         * This is critical for mobile apps where session cookies may not work reliably
         */
        ensureSessionSync: async function() {
            const token = this.getToken();
            if (!token) {
                console.log('[NexusAuth] No token for session sync');
                return;
            }

            // Check if we already synced this session (avoid reload loops)
            const lastSync = sessionStorage.getItem('nexus_session_synced');
            const syncTime = lastSync ? parseInt(lastSync, 10) : 0;
            const fiveMinutesAgo = Date.now() - (5 * 60 * 1000);

            // Check if page shows us as not logged in (look for login links or missing user elements)
            const pageShowsLoggedOut = !document.body.classList.contains('logged-in') &&
                                       !document.querySelector('[data-user-id]') &&
                                       (document.querySelector('a[href*="/login"]') || document.querySelector('.login-btn'));

            // Only sync if page shows logged out OR we haven't synced recently
            if (!pageShowsLoggedOut && syncTime > fiveMinutesAgo) {
                console.log('[NexusAuth] Session sync not needed (recently synced or page shows logged in)');
                return;
            }

            try {
                console.log('[NexusAuth] Syncing PHP session with token...');

                const headers = {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token,
                    'X-Requested-With': 'XMLHttpRequest'
                };

                if (this.isNativeApp()) {
                    headers['X-Capacitor-App'] = 'true';
                }
                if (this.isMobileDevice()) {
                    headers['X-Nexus-Mobile'] = 'true';
                }

                const response = await fetch('/api/auth/restore-session', {
                    method: 'POST',
                    credentials: 'include',
                    headers: headers
                });

                if (response.ok) {
                    const data = await response.json();
                    console.log('[NexusAuth] Session synced successfully:', data);

                    // Mark that we synced
                    sessionStorage.setItem('nexus_session_synced', Date.now().toString());

                    // If page was showing logged out, reload to get logged-in view
                    if (pageShowsLoggedOut) {
                        console.log('[NexusAuth] Page showed logged out, reloading to refresh...');
                        window.location.reload();
                    }
                } else {
                    console.log('[NexusAuth] Session sync failed:', response.status);
                    // If token is invalid, try to refresh it
                    if (response.status === 401) {
                        const refreshed = await this.refreshAccessToken();
                        if (refreshed) {
                            // Retry session sync with new token (but mark to prevent loop)
                            sessionStorage.setItem('nexus_session_synced', Date.now().toString());
                            await this.ensureSessionSync();
                        }
                    }
                }
            } catch (e) {
                console.error('[NexusAuth] Session sync error:', e);
            }
        },

        /**
         * Detect if running on mobile and configure for persistent login
         * Mobile gets longer intervals for battery efficiency and longer token thresholds
         */
        detectAndConfigurePlatform: function() {
            const isMobile = this.isMobileDevice();

            if (isMobile) {
                // Mobile configuration: "Install and forget" like Facebook/Instagram
                this.config.heartbeatInterval = 10 * 60 * 1000;      // 10 minutes (battery friendly)
                this.config.tokenRefreshInterval = 10 * 60 * 1000;   // 10 minutes
                this.config.tokenRefreshThreshold = 86400;           // Refresh when < 1 day remaining

                console.log('[NexusAuth] Mobile platform detected - using persistent login settings');
            } else {
                // Desktop configuration: More frequent checks for security
                this.config.heartbeatInterval = 2 * 60 * 1000;       // 2 minutes
                this.config.tokenRefreshInterval = 2 * 60 * 1000;    // 2 minutes
                this.config.tokenRefreshThreshold = 600;             // Refresh when < 10 min remaining

                console.log('[NexusAuth] Desktop platform detected - using standard settings');
            }

            // Store platform preference
            try {
                localStorage.setItem(this.config.platformStorageKey, isMobile ? 'mobile' : 'web');
            } catch (e) {}
        },

        /**
         * Check if running on a mobile device
         */
        isMobileDevice: function() {
            // Check for Capacitor/native app
            if (this.isNativeApp()) return true;

            // Check user agent for mobile indicators
            const ua = navigator.userAgent || '';
            return /Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua);
        },

        /**
         * Initialize storage - prefer Capacitor Preferences on native apps
         * Capacitor Preferences is persistent and survives WebView data clears
         */
        initStorage: async function() {
            if (this.storageReady) return;

            try {
                if (this.isNativeApp() && typeof Capacitor !== 'undefined' && Capacitor.Plugins.Preferences) {
                    this.capacitorPreferences = Capacitor.Plugins.Preferences;
                    this.useCapacitorStorage = true;
                    console.log('[NexusAuth] Using Capacitor Preferences for persistent storage');
                } else {
                    this.useCapacitorStorage = false;
                    console.log('[NexusAuth] Using localStorage (web mode)');
                }
            } catch (e) {
                this.useCapacitorStorage = false;
                console.log('[NexusAuth] Capacitor Preferences not available, using localStorage');
            }

            this.storageReady = true;
        },

        /**
         * Recover auth state after app restart or WebView data clear
         * This handles Android killing the app and clearing localStorage
         * Also handles case where localStorage has stale data but Capacitor has fresh data
         */
        recoverAuthState: async function() {
            if (!this.useCapacitorStorage || this.isRecovering) return;

            this.isRecovering = true;

            try {
                // Check if localStorage has tokens
                const localToken = localStorage.getItem(this.config.tokenStorageKey);
                const localExpiry = localStorage.getItem(this.config.tokenExpiryKey);

                // Check if Capacitor Preferences has tokens
                const { value: prefToken } = await this.capacitorPreferences.get({ key: this.config.tokenStorageKey });
                const { value: prefRefresh } = await this.capacitorPreferences.get({ key: this.config.refreshTokenStorageKey });
                const { value: prefExpiry } = await this.capacitorPreferences.get({ key: this.config.tokenExpiryKey });

                // Determine which storage has fresher data
                let shouldRecover = false;
                let reason = '';

                if (!localToken && prefToken) {
                    // Case 1: localStorage empty, Capacitor has data
                    shouldRecover = true;
                    reason = 'localStorage empty';
                } else if (localToken && prefToken && prefExpiry && localExpiry) {
                    // Case 2: Both have data - use the one with later expiry (fresher token)
                    const localExpiryTime = parseInt(localExpiry, 10);
                    const prefExpiryTime = parseInt(prefExpiry, 10);

                    if (prefExpiryTime > localExpiryTime) {
                        shouldRecover = true;
                        reason = 'Capacitor has fresher token';
                    }
                } else if (localToken && prefToken && !localExpiry && prefExpiry) {
                    // Case 3: localStorage missing expiry but Capacitor has it
                    shouldRecover = true;
                    reason = 'localStorage missing expiry';
                }

                if (shouldRecover && prefToken) {
                    console.log('[NexusAuth] Recovering tokens from Capacitor Preferences (' + reason + ')...');
                    localStorage.setItem(this.config.tokenStorageKey, prefToken);

                    if (prefRefresh) {
                        localStorage.setItem(this.config.tokenStorageKey + '_refresh', prefRefresh);
                    }

                    // Recover expiry and user data too
                    const { value: prefUser } = await this.capacitorPreferences.get({ key: this.config.userStorageKey });

                    if (prefExpiry) {
                        localStorage.setItem(this.config.tokenExpiryKey, prefExpiry);
                    }
                    if (prefUser) {
                        localStorage.setItem(this.config.userStorageKey, prefUser);
                    }

                    console.log('[NexusAuth] Auth state recovered from persistent storage');
                }

                // Always validate tokens after recovery (even if no recovery was needed)
                // This catches expired tokens that weren't properly cleared
                const currentToken = this.getToken();
                const currentRefresh = this.getRefreshToken();
                const remaining = this.getTokenTimeRemaining();

                console.log('[NexusAuth] Post-recovery token state:', {
                    hasToken: !!currentToken,
                    hasRefresh: !!currentRefresh,
                    remaining: remaining,
                    needsRefresh: this.tokenNeedsRefresh()
                });

                if (currentToken) {
                    if (remaining <= 0) {
                        // Token is expired - try to refresh
                        console.log('[NexusAuth] Token expired, attempting refresh...');
                        const refreshed = await this.refreshAccessToken();

                        if (!refreshed) {
                            // Refresh failed - but DON'T clear tokens if we still have a refresh token
                            // The user might just be offline temporarily
                            if (!this.getRefreshToken()) {
                                console.log('[NexusAuth] Token refresh failed and no refresh token, clearing tokens');
                                await this.clearTokens();
                            } else {
                                console.log('[NexusAuth] Token refresh failed but keeping refresh token for later retry');
                            }
                        }
                    } else if (this.tokenNeedsRefresh()) {
                        // Token is close to expiry - refresh proactively
                        console.log('[NexusAuth] Token needs refresh, refreshing...');
                        await this.refreshAccessToken();
                    }
                } else if (currentRefresh) {
                    // No access token but have refresh token - try to get new access token
                    console.log('[NexusAuth] No access token but have refresh token, attempting refresh...');
                    await this.refreshAccessToken();
                }
            } catch (e) {
                console.error('[NexusAuth] Error recovering auth state:', e);
            } finally {
                this.isRecovering = false;
            }
        },

        /**
         * Setup network connectivity monitoring
         * Prevents auth calls when offline and syncs when back online
         */
        setupNetworkHandler: function() {
            const self = this;

            if (this.isNativeApp() && typeof Capacitor !== 'undefined' && Capacitor.Plugins.Network) {
                try {
                    const Network = Capacitor.Plugins.Network;

                    // Get initial status
                    Network.getStatus().then(status => {
                        self.networkConnected = status.connected;
                        console.log('[NexusAuth] Initial network status:', status.connected ? 'online' : 'offline');
                    });

                    // Listen for changes
                    Network.addListener('networkStatusChange', (status) => {
                        const wasOffline = !self.networkConnected;
                        self.networkConnected = status.connected;

                        console.log('[NexusAuth] Network status changed:', status.connected ? 'online' : 'offline');

                        // If we just came back online, wait briefly then refresh auth state
                        // The delay helps ensure the network is actually stable
                        if (wasOffline && status.connected) {
                            console.log('[NexusAuth] Back online, waiting for stable connection...');
                            setTimeout(() => {
                                if (self.networkConnected) {
                                    console.log('[NexusAuth] Connection stable, refreshing auth...');
                                    self.checkAndRefreshToken();
                                    self.sendHeartbeat();
                                }
                            }, 1000); // 1 second delay for network stabilization
                        }
                    });
                } catch (e) {
                    console.log('[NexusAuth] Network plugin not available');
                }
            }

            // Also use browser online/offline events as fallback
            window.addEventListener('online', () => {
                const wasOffline = !self.networkConnected;
                self.networkConnected = true;
                console.log('[NexusAuth] Browser online event');

                // Only trigger auth refresh if we were actually offline
                if (wasOffline) {
                    // Wait briefly for network to stabilize
                    setTimeout(() => {
                        if (self.networkConnected) {
                            console.log('[NexusAuth] Network stable, refreshing auth...');
                            self.checkAndRefreshToken();
                            self.sendHeartbeat();
                        }
                    }, 1000);
                }
            });

            window.addEventListener('offline', () => {
                self.networkConnected = false;
                console.log('[NexusAuth] Browser offline');
            });
        },

        /**
         * Check if network is actually connected (real-time check)
         * More reliable than just checking the networkConnected flag
         */
        isNetworkAvailable: async function() {
            // First check the flag
            if (!this.networkConnected) return false;

            // For native apps, do a real-time check
            if (this.isNativeApp() && typeof Capacitor !== 'undefined' && Capacitor.Plugins.Network) {
                try {
                    const Network = Capacitor.Plugins.Network;
                    const status = await Network.getStatus();
                    this.networkConnected = status.connected;
                    return status.connected;
                } catch (e) {
                    // Fall through to browser check
                }
            }

            // Browser fallback
            return navigator.onLine;
        },

        /**
         * Start session heartbeat to keep session alive
         */
        startHeartbeat: function() {
            const self = this;

            // Clear any existing heartbeat
            this.stopHeartbeat();

            // Don't start heartbeat if not logged in
            if (!this.isLoggedIn()) {
                console.log('[NexusAuth] Not logged in, skipping heartbeat');
                return;
            }

            // Send heartbeat at configured interval
            this.heartbeatTimer = setInterval(() => {
                self.sendHeartbeat();
            }, this.config.heartbeatInterval);

            // Also send an immediate heartbeat
            this.sendHeartbeat();

            console.log('[NexusAuth] Heartbeat started');
        },

        /**
         * Stop session heartbeat
         */
        stopHeartbeat: function() {
            if (this.heartbeatTimer) {
                clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
                console.log('[NexusAuth] Heartbeat stopped');
            }
        },

        /**
         * Send a heartbeat to keep the session alive
         * Also includes Bearer token for token status check
         */
        sendHeartbeat: async function() {
            // Guard: prevent concurrent heartbeats (can cause race conditions)
            if (this._heartbeatInProgress) {
                console.log('[NexusAuth] Heartbeat already in progress, skipping');
                return;
            }
            this._heartbeatInProgress = true;

            // Skip heartbeat if offline (do real-time check for reliability)
            const isOnline = await this.isNetworkAvailable();
            if (!isOnline) {
                console.log('[NexusAuth] Skipping heartbeat - offline');
                this._heartbeatInProgress = false;
                return;
            }

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                // Add mobile indicators for proper platform detection
                if (this.isNativeApp()) {
                    headers['X-Capacitor-App'] = 'true';
                }
                if (this.isMobileDevice()) {
                    headers['X-Nexus-Mobile'] = 'true';
                }

                // Include Bearer token if available for token status check
                const token = this.getToken();
                if (token) {
                    headers['Authorization'] = 'Bearer ' + token;
                }

                const response = await fetch(this.config.heartbeatEndpoint, {
                    method: 'POST',
                    credentials: 'include',
                    headers: headers
                });

                if (response.ok) {
                    const data = await response.json();
                    console.log('[NexusAuth] Heartbeat OK, session valid');

                    // Reset ALL failure counts on success
                    this.heartbeatFailCount = 0;
                    this.networkFailCount = 0;

                    // Update session expiry info if provided
                    if (data.expires_at) {
                        this.sessionExpiresAt = new Date(data.expires_at);
                    }

                    // Check token status from heartbeat response
                    if (data.token) {
                        if (data.token.needs_refresh) {
                            console.log('[NexusAuth] Heartbeat indicates token needs refresh');
                            this.refreshAccessToken();
                        } else if (data.token.time_remaining) {
                            // Update local token expiry based on server response
                            this.setTokenExpiry(data.token.time_remaining);
                        }
                    }
                } else if (response.status === 401) {
                    // Log for debugging but DO NOT force logout
                    // The heartbeat's job is to keep sessions alive, not to enforce logouts
                    // If the session is truly invalid, API requests will fail and the user
                    // can decide to login again. This prevents random unexpected logouts.
                    console.log('[NexusAuth] Heartbeat 401 - session may have expired');
                    console.log('[NexusAuth] Has token:', !!this.getToken(), 'Has refresh:', !!this.getRefreshToken());

                    // Try token refresh (silently, don't count failures)
                    const refreshToken = this.getRefreshToken();
                    if (refreshToken) {
                        console.log('[NexusAuth] Attempting silent token refresh...');
                        await this.refreshAccessToken();
                    }

                    // IMPORTANT: Do NOT automatically logout or redirect
                    // Let the user continue browsing - they'll be prompted to login
                    // when they try to do something that requires authentication
                } else {
                    // Other error (500, etc.) - just log it, don't take any action
                    console.log('[NexusAuth] Heartbeat server error: ' + response.status);
                }
            } catch (error) {
                // Network error - just log it, don't take any action
                console.log('[NexusAuth] Heartbeat network error:', error.message || error);
            } finally {
                // Always clear the in-progress flag
                this._heartbeatInProgress = false;
            }
        },

        /**
         * Track user activity to optimize heartbeat
         */
        setupActivityTracking: function() {
            const self = this;
            const events = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart'];

            events.forEach(event => {
                document.addEventListener(event, () => {
                    self.lastActivity = Date.now();
                }, { passive: true, once: false });
            });
        },

        /**
         * Handle visibility changes (app goes to background/foreground)
         */
        setupVisibilityHandler: function() {
            const self = this;

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    // App came to foreground - send heartbeat AND check/refresh token
                    console.log('[NexusAuth] App visible, checking auth status...');
                    self.sendHeartbeat();
                    self.checkAndRefreshToken();
                }
            });

            // For Capacitor apps, also listen to app state changes
            if (this.isNativeApp() && typeof Capacitor !== 'undefined') {
                try {
                    const App = Capacitor.Plugins.App;
                    if (App) {
                        App.addListener('appStateChange', (state) => {
                            if (state.isActive) {
                                console.log('[NexusAuth] Native app active, checking auth status...');
                                self.sendHeartbeat();
                                self.checkAndRefreshToken();
                            }
                        });
                    }
                } catch (e) {
                    console.log('[NexusAuth] Could not setup Capacitor app state listener');
                }
            }
        },

        /**
         * Check if user appears to be logged in
         * Only returns true if we have actual proof of auth (tokens or explicit session marker)
         * DOM element fallbacks removed - they caused false positives and unnecessary 401s
         */
        isLoggedIn: function() {
            // PRIMARY: Check for valid tokens in localStorage (most reliable)
            const token = this.getToken();
            const refreshToken = this.getRefreshToken();
            const remaining = this.getTokenTimeRemaining();

            console.log('[NexusAuth] isLoggedIn check:', {
                hasToken: !!token,
                hasRefreshToken: !!refreshToken,
                tokenTimeRemaining: remaining
            });

            // If we have a refresh token, we can always get a new access token
            if (refreshToken) {
                return true;
            }

            // If we have a valid access token, we're logged in
            if (token && remaining > 0) {
                return true;
            }

            // Safety check: ensure document.body exists before checking DOM
            if (!document.body) {
                return false;
            }

            // SECONDARY: Check explicit session marker (set by server on login)
            // This is more reliable than checking for UI elements like .user-menu
            if (document.body.classList.contains('logged-in')) return true;
            if (document.body.dataset && document.body.dataset.sessionActive === 'true') return true;

            // STRICT: Only check for data-user-id with actual value (not just presence)
            const userIdEl = document.querySelector('[data-user-id]');
            if (userIdEl && userIdEl.dataset && userIdEl.dataset.userId && userIdEl.dataset.userId !== '') {
                return true;
            }

            // NOTE: Removed generic .user-menu/.user-avatar checks - these elements exist
            // in many layouts regardless of login state and caused false positives

            return false;
        },

        /**
         * Check if current page requires authentication
         * Public pages should not redirect on session expiry
         */
        isAuthRequiredPage: function() {
            const path = window.location.pathname;

            // List of public page path segments (check if path ends with these)
            const publicPages = [
                'login', 'register', 'forgot-password', 'reset-password',
                'listings', 'groups', 'events', 'leaderboard', 'leaderboards',
                'directory', 'about', 'contact', 'terms', 'privacy',
                'faq', 'help', 'search', 'blog', 'news', 'feed', 'home'
            ];

            // Check for data attribute that explicitly marks page as public
            if (document.body && document.body.hasAttribute('data-public-page')) {
                return false;
            }

            // Home page is public
            if (path === '/' || path.match(/^\/[a-z-]+\/?$/i) && path.split('/').filter(Boolean).length <= 1) {
                // Could be home or a tenant base path
                const segments = path.split('/').filter(Boolean);
                if (segments.length === 0) return false; // Root home
            }

            // Get path segments (with safety check)
            if (!path || typeof path !== 'string') {
                console.warn('[NexusAuth] Path is undefined or invalid:', path);
                return false; // Assume public if path is invalid
            }
            const segments = path.toLowerCase().split('/').filter(Boolean);

            // Check if any segment matches a public page
            for (const segment of segments) {
                if (publicPages.includes(segment)) {
                    return false; // Public page, auth not required
                }
            }

            // Check for profile pages (public when viewing others)
            if (segments.includes('profile') && segments.length >= 2) {
                const profileIndex = segments.indexOf('profile');
                const profileId = segments[profileIndex + 1];
                // Profile pages are public (viewing other users)
                if (profileId && profileId !== 'edit' && profileId !== 'settings') {
                    return false;
                }
            }

            // Check for /page/ custom pages
            if (segments.includes('page')) {
                return false;
            }

            // Default: assume auth is required (dashboard, settings, wallet, messages, etc.)
            return true;
        },

        /**
         * Check if running in native Capacitor app
         */
        isNativeApp: function() {
            return typeof Capacitor !== 'undefined' &&
                   Capacitor.isNativePlatform &&
                   Capacitor.isNativePlatform();
        },

        // ============================================
        // Token Management (JWT-based authentication)
        // ============================================

        /**
         * Store access token (writes to both localStorage and Capacitor Preferences)
         */
        setToken: function(token) {
            try {
                localStorage.setItem(this.config.tokenStorageKey, token);

                // Also persist to Capacitor Preferences for Android reliability
                if (this.useCapacitorStorage && this.capacitorPreferences) {
                    this.capacitorPreferences.set({ key: this.config.tokenStorageKey, value: token })
                        .catch(e => console.error('[NexusAuth] Capacitor storage error:', e));
                }
            } catch (e) {
                console.error('[NexusAuth] Could not store token:', e);
            }
        },

        /**
         * Get stored access token
         */
        getToken: function() {
            try {
                return localStorage.getItem(this.config.tokenStorageKey);
            } catch (e) {
                return null;
            }
        },

        /**
         * Store refresh token (writes to both localStorage and Capacitor Preferences)
         */
        setRefreshToken: function(token) {
            try {
                localStorage.setItem(this.config.tokenStorageKey + '_refresh', token);

                // Also persist to Capacitor Preferences for Android reliability
                if (this.useCapacitorStorage && this.capacitorPreferences) {
                    this.capacitorPreferences.set({ key: this.config.refreshTokenStorageKey, value: token })
                        .catch(e => console.error('[NexusAuth] Capacitor storage error:', e));
                }
            } catch (e) {
                console.error('[NexusAuth] Could not store refresh token:', e);
            }
        },

        /**
         * Get stored refresh token
         */
        getRefreshToken: function() {
            try {
                return localStorage.getItem(this.config.tokenStorageKey + '_refresh');
            } catch (e) {
                return null;
            }
        },

        /**
         * Store user data (writes to both localStorage and Capacitor Preferences)
         */
        setUser: function(userData) {
            try {
                const userJson = JSON.stringify(userData);
                localStorage.setItem(this.config.userStorageKey, userJson);

                // Also persist to Capacitor Preferences for Android reliability
                if (this.useCapacitorStorage && this.capacitorPreferences) {
                    this.capacitorPreferences.set({ key: this.config.userStorageKey, value: userJson })
                        .catch(e => console.error('[NexusAuth] Capacitor storage error:', e));
                }
            } catch (e) {
                console.error('[NexusAuth] Could not store user data:', e);
            }
        },

        /**
         * Get stored user data
         */
        getUser: function() {
            try {
                const data = localStorage.getItem(this.config.userStorageKey);
                return data ? JSON.parse(data) : null;
            } catch (e) {
                return null;
            }
        },

        /**
         * Clear all auth data (client-side AND Capacitor Preferences)
         */
        clearAuth: function() {
            try {
                localStorage.removeItem(this.config.tokenStorageKey);
                localStorage.removeItem(this.config.tokenStorageKey + '_refresh');
                localStorage.removeItem(this.config.tokenExpiryKey);
                localStorage.removeItem(this.config.userStorageKey);

                // Also clear Capacitor Preferences
                if (this.useCapacitorStorage && this.capacitorPreferences) {
                    this.capacitorPreferences.remove({ key: this.config.tokenStorageKey }).catch(() => {});
                    this.capacitorPreferences.remove({ key: this.config.refreshTokenStorageKey }).catch(() => {});
                    this.capacitorPreferences.remove({ key: this.config.tokenExpiryKey }).catch(() => {});
                    this.capacitorPreferences.remove({ key: this.config.userStorageKey }).catch(() => {});
                }
            } catch (e) {
                console.error('[NexusAuth] Could not clear auth data:', e);
            }
        },

        /**
         * Clear only tokens (not user data) - used when tokens expire but session might still be valid
         * This allows the heartbeat to recover the session without losing user data
         */
        clearTokens: async function() {
            try {
                localStorage.removeItem(this.config.tokenStorageKey);
                localStorage.removeItem(this.config.tokenStorageKey + '_refresh');
                localStorage.removeItem(this.config.tokenExpiryKey);

                // Also clear Capacitor Preferences tokens only
                if (this.useCapacitorStorage && this.capacitorPreferences) {
                    await this.capacitorPreferences.remove({ key: this.config.tokenStorageKey }).catch(() => {});
                    await this.capacitorPreferences.remove({ key: this.config.refreshTokenStorageKey }).catch(() => {});
                    await this.capacitorPreferences.remove({ key: this.config.tokenExpiryKey }).catch(() => {});
                }
                console.log('[NexusAuth] Tokens cleared (user data preserved)');
            } catch (e) {
                console.error('[NexusAuth] Could not clear tokens:', e);
            }
        },

        /**
         * Full logout - clears client auth AND invalidates server session
         */
        logout: async function() {
            // Stop background processes first
            this.stopHeartbeat();
            this.stopTokenRefreshTimer();

            // Clear client-side auth data
            this.clearAuth();

            // Notify server to invalidate session
            try {
                await fetch('/api/auth/logout', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                console.log('[NexusAuth] Server logout successful');
            } catch (e) {
                console.log('[NexusAuth] Server logout failed (may already be logged out)');
            }
        },

        /**
         * Store token expiry time (writes to both localStorage and Capacitor Preferences)
         */
        setTokenExpiry: function(expiresIn) {
            try {
                const expiryTime = Date.now() + (expiresIn * 1000);
                const expiryString = expiryTime.toString();
                localStorage.setItem(this.config.tokenExpiryKey, expiryString);

                // Also persist to Capacitor Preferences for Android reliability
                if (this.useCapacitorStorage && this.capacitorPreferences) {
                    this.capacitorPreferences.set({ key: this.config.tokenExpiryKey, value: expiryString })
                        .catch(e => console.error('[NexusAuth] Capacitor storage error:', e));
                }
            } catch (e) {
                console.error('[NexusAuth] Could not store token expiry:', e);
            }
        },

        /**
         * Get token expiry time
         */
        getTokenExpiry: function() {
            try {
                const expiry = localStorage.getItem(this.config.tokenExpiryKey);
                return expiry ? parseInt(expiry, 10) : null;
            } catch (e) {
                return null;
            }
        },

        /**
         * Get remaining time until token expires (in seconds)
         */
        getTokenTimeRemaining: function() {
            const expiry = this.getTokenExpiry();
            if (!expiry) return -1;
            return Math.floor((expiry - Date.now()) / 1000);
        },

        /**
         * Check if token needs refresh
         */
        tokenNeedsRefresh: function() {
            const remaining = this.getTokenTimeRemaining();
            // Needs refresh if expired or less than threshold remaining
            return remaining < this.config.tokenRefreshThreshold;
        },

        // ============================================
        // Token Refresh Logic
        // ============================================

        /**
         * Start the token refresh timer
         */
        startTokenRefreshTimer: function() {
            const self = this;

            // Clear any existing timer
            this.stopTokenRefreshTimer();

            // Check token periodically
            this.tokenRefreshTimer = setInterval(() => {
                self.checkAndRefreshToken();
            }, this.config.tokenRefreshInterval);

            // Also check immediately
            this.checkAndRefreshToken();

            console.log('[NexusAuth] Token refresh timer started');
        },

        /**
         * Stop the token refresh timer
         */
        stopTokenRefreshTimer: function() {
            if (this.tokenRefreshTimer) {
                clearInterval(this.tokenRefreshTimer);
                this.tokenRefreshTimer = null;
            }
        },

        /**
         * Check if token needs refresh and refresh if necessary
         */
        checkAndRefreshToken: async function() {
            const token = this.getToken();
            const refreshToken = this.getRefreshToken();

            // No tokens - nothing to refresh
            if (!token || !refreshToken) {
                return;
            }

            // Check if token needs refresh
            if (this.tokenNeedsRefresh()) {
                console.log('[NexusAuth] Token needs refresh, refreshing...');
                await this.refreshAccessToken();
            }
        },

        /**
         * Refresh the access token using the refresh token
         * Returns a promise that resolves when refresh is complete
         */
        refreshAccessToken: async function() {
            // Prevent concurrent refresh attempts
            if (this.isRefreshing) {
                console.log('[NexusAuth] Refresh already in progress, waiting...');
                return this.refreshPromise;
            }

            const refreshToken = this.getRefreshToken();
            if (!refreshToken) {
                console.log('[NexusAuth] No refresh token available');
                return false;
            }

            this.isRefreshing = true;

            this.refreshPromise = (async () => {
                try {
                    // Build headers with mobile indicators for correct token expiry
                    const headers = {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    };

                    // Add mobile indicators so server returns correct expiry
                    if (this.isNativeApp()) {
                        headers['X-Capacitor-App'] = 'true';
                    }
                    if (this.isMobileDevice()) {
                        headers['X-Nexus-Mobile'] = 'true';
                    }

                    const response = await fetch(this.config.refreshTokenEndpoint, {
                        method: 'POST',
                        credentials: 'include',
                        headers: headers,
                        body: JSON.stringify({ refresh_token: refreshToken })
                    });

                    if (response.ok) {
                        const data = await response.json();

                        if (data.access_token) {
                            this.setToken(data.access_token);
                            // Use server-provided expiry, with platform-appropriate fallback
                            const defaultExpiry = this.isMobileDevice() ? 31536000 : 7200;
                            this.setTokenExpiry(data.expires_in || defaultExpiry);
                            console.log('[NexusAuth] Access token refreshed, expires_in:', data.expires_in || defaultExpiry);
                        }

                        // Update refresh token if a new one was provided
                        if (data.refresh_token) {
                            this.setRefreshToken(data.refresh_token);
                            console.log('[NexusAuth] Refresh token updated');
                        }

                        return true;
                    } else if (response.status === 401) {
                        // Refresh token is invalid/expired
                        // Log detailed info for debugging
                        console.log('[NexusAuth] Refresh token rejected (401)');
                        try {
                            const errorData = await response.json();
                            console.log('[NexusAuth] Refresh error details:', errorData);
                        } catch (e) {}

                        // DON'T clear tokens immediately - the user might need to re-login
                        // but we shouldn't force logout silently
                        console.log('[NexusAuth] NOT clearing tokens - user may need to re-login manually');
                        return false;
                    } else {
                        console.log('[NexusAuth] Token refresh failed:', response.status);
                        return false;
                    }
                } catch (error) {
                    console.error('[NexusAuth] Token refresh error:', error);
                    return false;
                } finally {
                    this.isRefreshing = false;
                    this.refreshPromise = null;
                }
            })();

            return this.refreshPromise;
        },

        // ============================================
        // Fetch Interceptor
        // ============================================

        /**
         * Setup fetch interceptor to automatically add Bearer tokens
         * and handle 401 responses with token refresh
         */
        setupFetchInterceptor: function() {
            const self = this;
            const originalFetch = window.fetch;

            window.fetch = async function(input, init = {}) {
                const url = typeof input === 'string' ? input : input.url;

                // Only intercept API requests (not external URLs)
                const isApiRequest = url.startsWith('/api/') ||
                                    url.startsWith(window.location.origin + '/api/');

                // Skip interception for auth endpoints to avoid infinite loops
                const isAuthEndpoint = url.includes('/api/auth/refresh-token') ||
                                      url.includes('/api/auth/login') ||
                                      url.includes('/api/auth/heartbeat');

                if (isApiRequest && !isAuthEndpoint) {
                    // Check if token needs refresh before making request
                    if (self.tokenNeedsRefresh() && self.getRefreshToken()) {
                        console.log('[NexusAuth] Pre-flight token refresh...');
                        const refreshed = await self.refreshAccessToken();
                        if (!refreshed) {
                            console.log('[NexusAuth] Pre-flight refresh failed, proceeding without token');
                            // Don't block the request - let server decide what to do
                        }
                    }

                    init.headers = init.headers || {};

                    // Add mobile app header for Capacitor apps (helps server set correct cookie policy)
                    if (self.isNativeApp()) {
                        if (init.headers instanceof Headers) {
                            init.headers.set('X-Capacitor-App', 'true');
                        } else {
                            init.headers['X-Capacitor-App'] = 'true';
                        }
                    }

                    // Add Bearer token to request (if we have one)
                    const token = self.getToken();
                    if (token) {
                        if (init.headers instanceof Headers) {
                            init.headers.set('Authorization', 'Bearer ' + token);
                        } else {
                            init.headers['Authorization'] = 'Bearer ' + token;
                        }
                    }
                }

                // Make the request
                let response = await originalFetch.call(window, input, init);

                // Handle 401 responses for API requests
                if (response.status === 401 && isApiRequest && !isAuthEndpoint) {
                    console.log('[NexusAuth] Got 401, attempting token refresh...');

                    // Try to refresh the token
                    const refreshed = await self.refreshAccessToken();

                    if (refreshed) {
                        // Retry the original request with new token
                        const newToken = self.getToken();
                        if (newToken) {
                            init.headers = init.headers || {};
                            if (init.headers instanceof Headers) {
                                init.headers.set('Authorization', 'Bearer ' + newToken);
                            } else {
                                init.headers['Authorization'] = 'Bearer ' + newToken;
                            }
                        }

                        console.log('[NexusAuth] Retrying request with new token...');
                        response = await originalFetch.call(window, input, init);
                    }
                }

                return response;
            };

            console.log('[NexusAuth] Fetch interceptor installed');
        },

        /**
         * Handle successful login - store tokens and user data
         */
        onLoginSuccess: async function(response) {
            // Ensure storage is initialized (critical for login before full init)
            await this.initStorage();

            // Detect and configure platform settings
            this.detectAndConfigurePlatform();

            if (response.access_token) {
                this.setToken(response.access_token);
            } else if (response.token) {
                this.setToken(response.token);
            }

            if (response.refresh_token) {
                this.setRefreshToken(response.refresh_token);
            }

            // Store token expiry time
            // Server sends platform-appropriate expiry (1 year mobile, 2 hours desktop)
            if (response.expires_in) {
                this.setTokenExpiry(response.expires_in);
                console.log('[NexusAuth] Token expiry set from server:', response.expires_in, 'seconds (~' + Math.round(response.expires_in / 86400) + ' days)');
            } else {
                // Default based on platform if not specified
                const defaultExpiry = this.isMobileDevice() ? 31536000 : 7200; // 1 year or 2 hours
                this.setTokenExpiry(defaultExpiry);
                console.log('[NexusAuth] Token expiry set to default:', defaultExpiry, 'seconds');
            }

            if (response.user) {
                this.setUser(response.user);
            }

            // Start all the auth maintenance processes
            this.setupFetchInterceptor();
            this.setupNetworkHandler();
            this.startHeartbeat();
            this.startTokenRefreshTimer();

            const platform = this.isMobileDevice() ? 'mobile' : 'desktop';
            console.log('[NexusAuth] Login successful on ' + platform + ', auth handler activated');
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NexusAuth.init());
    } else {
        NexusAuth.init();
    }

})();
