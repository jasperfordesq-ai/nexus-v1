/**
 * Project NEXUS - Capacitor Bridge
 * Progressive enhancement for native features
 * Falls back gracefully to web APIs when not in native wrapper
 */

(function() {
    'use strict';

    // ============================================
    // DETECT ENVIRONMENT
    // ============================================
    const Environment = {
        isCapacitor: () => typeof Capacitor !== 'undefined' && Capacitor.isNativePlatform(),
        isIOS: () => /iPad|iPhone|iPod/.test(navigator.userAgent),
        isAndroid: () => /Android/.test(navigator.userAgent),
        isPWA: () => window.matchMedia('(display-mode: standalone)').matches ||
                     window.navigator.standalone === true,
        isMobile: () => window.matchMedia('(max-width: 768px)').matches,

        getPlatform() {
            if (this.isCapacitor()) {
                return Capacitor.getPlatform(); // 'ios', 'android', 'web'
            }
            if (this.isIOS()) return 'ios-web';
            if (this.isAndroid()) return 'android-web';
            return 'web';
        }
    };

    // ============================================
    // NATIVE HAPTICS (with web fallback)
    // ============================================
    const NativeHaptics = {
        /**
         * Impact feedback - for button taps, selections
         * @param {string} style - 'light', 'medium', 'heavy'
         */
        async impact(style = 'medium') {
            // Try Capacitor Haptics first
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Haptics) {
                const { Haptics, ImpactStyle } = window.Capacitor.Plugins;
                const styles = {
                    light: ImpactStyle?.Light || 'LIGHT',
                    medium: ImpactStyle?.Medium || 'MEDIUM',
                    heavy: ImpactStyle?.Heavy || 'HEAVY'
                };
                try {
                    await Haptics.impact({ style: styles[style] });
                    return true;
                } catch (e) {
                    console.warn('Native haptics failed:', e);
                }
            }

            // Web fallback - Vibration API (only works after user gesture)
            if ('vibrate' in navigator) {
                try {
                    const durations = { light: 10, medium: 20, heavy: 40 };
                    navigator.vibrate(durations[style] || 20);
                    return true;
                } catch (e) {
                    // Blocked by browser - user hasn't interacted yet
                    return false;
                }
            }

            return false;
        },

        /**
         * Notification feedback - for success/warning/error
         * @param {string} type - 'success', 'warning', 'error'
         */
        async notification(type = 'success') {
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Haptics) {
                const { Haptics, NotificationType } = window.Capacitor.Plugins;
                const types = {
                    success: NotificationType?.Success || 'SUCCESS',
                    warning: NotificationType?.Warning || 'WARNING',
                    error: NotificationType?.Error || 'ERROR'
                };
                try {
                    await Haptics.notification({ type: types[type] });
                    return true;
                } catch (e) {
                    console.warn('Native notification haptics failed:', e);
                }
            }

            // Web fallback
            if ('vibrate' in navigator) {
                const patterns = {
                    success: [10, 50, 20, 50, 30],
                    warning: [30, 50, 30],
                    error: [50, 30, 50, 30, 50]
                };
                navigator.vibrate(patterns[type] || patterns.success);
                return true;
            }

            return false;
        },

        /**
         * Selection feedback - for UI selections
         */
        async selection() {
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Haptics) {
                try {
                    await window.Capacitor.Plugins.Haptics.selectionStart();
                    await window.Capacitor.Plugins.Haptics.selectionChanged();
                    await window.Capacitor.Plugins.Haptics.selectionEnd();
                    return true;
                } catch (e) {
                    // Fall through to web
                }
            }

            if ('vibrate' in navigator) {
                navigator.vibrate(5);
                return true;
            }

            return false;
        },

        /**
         * Custom vibration pattern
         * @param {number|number[]} pattern - Duration or pattern array
         */
        async vibrate(pattern) {
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Haptics) {
                try {
                    if (Array.isArray(pattern)) {
                        // Play pattern as sequence of impacts
                        for (let i = 0; i < pattern.length; i++) {
                            if (i % 2 === 0) {
                                await window.Capacitor.Plugins.Haptics.impact({
                                    style: pattern[i] > 30 ? 'HEAVY' : 'MEDIUM'
                                });
                            }
                            await new Promise(r => setTimeout(r, pattern[i]));
                        }
                    } else {
                        await window.Capacitor.Plugins.Haptics.vibrate({ duration: pattern });
                    }
                    return true;
                } catch (e) {
                    // Fall through to web
                }
            }

            if ('vibrate' in navigator) {
                navigator.vibrate(pattern);
                return true;
            }

            return false;
        }
    };

    // ============================================
    // NATIVE STATUS BAR (Capacitor only)
    // ============================================
    const NativeStatusBar = {
        async setStyle(style = 'dark') {
            if (!Environment.isCapacitor() || !window.Capacitor?.Plugins?.StatusBar) {
                return false;
            }

            try {
                const { StatusBar, Style } = window.Capacitor.Plugins;
                await StatusBar.setStyle({
                    style: style === 'light' ? Style.Light : Style.Dark
                });
                return true;
            } catch (e) {
                return false;
            }
        },

        async setBackgroundColor(color) {
            if (!Environment.isCapacitor() || !window.Capacitor?.Plugins?.StatusBar) {
                // Web fallback - update theme-color meta
                const meta = document.querySelector('meta[name="theme-color"]');
                if (meta) meta.setAttribute('content', color);
                return true;
            }

            try {
                await window.Capacitor.Plugins.StatusBar.setBackgroundColor({ color });
                return true;
            } catch (e) {
                return false;
            }
        },

        async hide() {
            if (!Environment.isCapacitor() || !window.Capacitor?.Plugins?.StatusBar) {
                return false;
            }

            try {
                await window.Capacitor.Plugins.StatusBar.hide();
                return true;
            } catch (e) {
                return false;
            }
        },

        async show() {
            if (!Environment.isCapacitor() || !window.Capacitor?.Plugins?.StatusBar) {
                return false;
            }

            try {
                await window.Capacitor.Plugins.StatusBar.show();
                return true;
            } catch (e) {
                return false;
            }
        }
    };

    // ============================================
    // NATIVE KEYBOARD (Capacitor only)
    // ============================================
    const NativeKeyboard = {
        async hide() {
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Keyboard) {
                try {
                    await window.Capacitor.Plugins.Keyboard.hide();
                    return true;
                } catch (e) {
                    // Fall through
                }
            }

            // Web fallback - blur active element
            if (document.activeElement && document.activeElement.blur) {
                document.activeElement.blur();
                return true;
            }

            return false;
        },

        async show() {
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Keyboard) {
                try {
                    await window.Capacitor.Plugins.Keyboard.show();
                    return true;
                } catch (e) {
                    return false;
                }
            }
            return false;
        }
    };

    // ============================================
    // NATIVE SHARE (with Web Share API fallback)
    // ============================================
    const NativeShare = {
        async share(options = {}) {
            const { title, text, url, files } = options;

            // Try Capacitor Share first
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Share) {
                try {
                    await window.Capacitor.Plugins.Share.share({
                        title,
                        text,
                        url,
                        dialogTitle: title
                    });
                    return { success: true, method: 'native' };
                } catch (e) {
                    if (e.message !== 'Share canceled') {
                        console.warn('Native share failed:', e);
                    }
                }
            }

            // Try Web Share API
            if (navigator.share) {
                try {
                    const shareData = { title, text, url };

                    // Add files if supported
                    if (files && navigator.canShare && navigator.canShare({ files })) {
                        shareData.files = files;
                    }

                    await navigator.share(shareData);
                    return { success: true, method: 'web-share' };
                } catch (e) {
                    if (e.name !== 'AbortError') {
                        console.warn('Web share failed:', e);
                    }
                }
            }

            // Fallback - show custom share sheet
            return { success: false, method: 'fallback', data: options };
        },

        canShare() {
            return Environment.isCapacitor() || !!navigator.share;
        }
    };

    // ============================================
    // NATIVE SPLASH SCREEN (Capacitor only)
    // ============================================
    const NativeSplash = {
        async hide(options = {}) {
            if (!Environment.isCapacitor() || !window.Capacitor?.Plugins?.SplashScreen) {
                return false;
            }

            try {
                await window.Capacitor.Plugins.SplashScreen.hide({
                    fadeOutDuration: options.fadeOutDuration || 300
                });
                return true;
            } catch (e) {
                return false;
            }
        }
    };

    // ============================================
    // NATIVE NETWORK STATE (with web fallback)
    // ============================================
    const NativeNetwork = {
        // Current network status
        _status: {
            connected: navigator.onLine,
            connectionType: 'unknown'
        },

        // Callbacks for status changes
        _listeners: [],

        /**
         * Initialize network monitoring
         * Uses Capacitor Network plugin for accurate detection in native app
         * Falls back to navigator.onLine for web
         */
        async init() {
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Network) {
                try {
                    const { Network } = window.Capacitor.Plugins;

                    // Get initial status
                    const status = await Network.getStatus();
                    this._status = {
                        connected: status.connected,
                        connectionType: status.connectionType || 'unknown'
                    };

                    // Listen for changes
                    Network.addListener('networkStatusChange', (status) => {
                        const wasConnected = this._status.connected;
                        this._status = {
                            connected: status.connected,
                            connectionType: status.connectionType || 'unknown'
                        };

                        console.log('[NEXUS Network] Status changed:', this._status);

                        // Notify all listeners
                        this._listeners.forEach(callback => {
                            try {
                                callback(this._status, wasConnected);
                            } catch (e) {
                                console.warn('[NEXUS Network] Listener error:', e);
                            }
                        });

                        // Dispatch custom event for easy integration
                        window.dispatchEvent(new CustomEvent('nexus:networkChange', {
                            detail: { ...this._status, wasConnected }
                        }));
                    });

                    console.log('[NEXUS Network] Capacitor Network plugin initialized:', this._status);
                    return true;
                } catch (e) {
                    console.warn('[NEXUS Network] Failed to initialize Capacitor Network:', e);
                }
            }

            // Web fallback - use navigator.onLine with online/offline events
            this._status = {
                connected: navigator.onLine,
                connectionType: navigator.onLine ? 'unknown' : 'none'
            };

            const handleOnline = () => {
                const wasConnected = this._status.connected;
                this._status = { connected: true, connectionType: 'unknown' };
                this._notifyListeners(wasConnected);
            };

            const handleOffline = () => {
                const wasConnected = this._status.connected;
                this._status = { connected: false, connectionType: 'none' };
                this._notifyListeners(wasConnected);
            };

            window.addEventListener('online', handleOnline);
            window.addEventListener('offline', handleOffline);

            console.log('[NEXUS Network] Web fallback initialized:', this._status);
            return true;
        },

        _notifyListeners(wasConnected) {
            this._listeners.forEach(callback => {
                try {
                    callback(this._status, wasConnected);
                } catch (e) {
                    console.warn('[NEXUS Network] Listener error:', e);
                }
            });

            window.dispatchEvent(new CustomEvent('nexus:networkChange', {
                detail: { ...this._status, wasConnected }
            }));
        },

        /**
         * Get current network status
         * @returns {Promise<{connected: boolean, connectionType: string}>}
         */
        async getStatus() {
            // Refresh status from Capacitor if available
            if (Environment.isCapacitor() && window.Capacitor?.Plugins?.Network) {
                try {
                    const { Network } = window.Capacitor.Plugins;
                    const status = await Network.getStatus();
                    this._status = {
                        connected: status.connected,
                        connectionType: status.connectionType || 'unknown'
                    };
                } catch (e) {
                    // Use cached status
                }
            }
            return { ...this._status };
        },

        /**
         * Check if currently connected
         * @returns {boolean}
         */
        isConnected() {
            return this._status.connected;
        },

        /**
         * Get connection type (wifi, cellular, none, unknown)
         * @returns {string}
         */
        getConnectionType() {
            return this._status.connectionType;
        },

        /**
         * Add a listener for network status changes
         * @param {Function} callback - Called with (status, wasConnected)
         * @returns {Function} Unsubscribe function
         */
        addListener(callback) {
            if (typeof callback !== 'function') return () => {};

            this._listeners.push(callback);

            // Return unsubscribe function
            return () => {
                const index = this._listeners.indexOf(callback);
                if (index > -1) {
                    this._listeners.splice(index, 1);
                }
            };
        },

        /**
         * Remove all listeners
         */
        removeAllListeners() {
            this._listeners = [];
        }
    };

    // ============================================
    // REPLACE EXISTING HAPTICS
    // ============================================
    function enhanceExistingHaptics() {
        // Replace the basic haptics with native Capacitor haptics
        if (window.NexusNativeNav && window.NexusNativeNav.Haptics) {
            window.NexusNativeNav.Haptics = {
                light: () => NativeHaptics.impact('light'),
                medium: () => NativeHaptics.impact('medium'),
                heavy: () => NativeHaptics.impact('heavy'),
                selection: () => NativeHaptics.selection(),
                success: () => NativeHaptics.notification('success')
            };
        }

        if (window.Nexus10x && window.Nexus10x.Haptics) {
            window.Nexus10x.Haptics = {
                light: () => NativeHaptics.impact('light'),
                medium: () => NativeHaptics.impact('medium'),
                heavy: () => NativeHaptics.impact('heavy'),
                selection: () => NativeHaptics.selection(),
                success: () => NativeHaptics.notification('success'),
                error: () => NativeHaptics.notification('error')
            };
        }
    }

    // ============================================
    // AUTO HAPTIC FEEDBACK ON INTERACTIVE ELEMENTS
    // ============================================
    function initAutoHaptics() {
        // Only enable in native app
        if (!Environment.isCapacitor()) return;

        // Selectors for interactive elements that should have haptic feedback
        const interactiveSelectors = [
            'button',
            '.btn',
            '.glass-btn',
            '[role="button"]',
            '.nexus-bottom-nav-item',
            '.nexus-native-nav-item',
            '.civic-bottom-nav-item',
            '.fab',
            '.nexus-fab',
            '.card-action',
            '.nav-link',
            '.dropdown-item',
            '.list-group-item-action',
            'input[type="submit"]',
            'input[type="button"]',
            '.toggle-switch',
            '.checkbox-label',
            '.radio-label'
        ].join(', ');

        // Use touchstart for immediate feedback (before click fires)
        document.addEventListener('touchstart', (e) => {
            const target = e.target.closest(interactiveSelectors);
            if (!target) return;

            // Skip if element has data-no-haptic attribute
            if (target.hasAttribute('data-no-haptic')) return;

            // Determine haptic type based on element
            let hapticType = 'light';

            // Stronger feedback for primary actions
            if (target.classList.contains('btn-primary') ||
                target.classList.contains('glass-btn-primary') ||
                target.classList.contains('fab') ||
                target.classList.contains('nexus-fab')) {
                hapticType = 'medium';
            }

            // Selection feedback for toggles/checkboxes
            if (target.classList.contains('toggle-switch') ||
                target.classList.contains('checkbox-label') ||
                target.classList.contains('radio-label')) {
                NativeHaptics.selection();
                return;
            }

            // Trigger haptic
            NativeHaptics.impact(hapticType);
        }, { passive: true });

        // Success haptic on form submit
        document.addEventListener('submit', (e) => {
            // Only on successful validation (form will submit)
            if (e.target.checkValidity()) {
                NativeHaptics.notification('success');
            }
        }, { passive: true });

        console.log('[NEXUS] Auto-haptics initialized for interactive elements');
    }

    // ============================================
    // INITIALIZE
    // ============================================
    function init() {
        // Log environment
        console.log('[NEXUS] Platform:', Environment.getPlatform());
        console.log('[NEXUS] Capacitor:', Environment.isCapacitor());
        console.log('[NEXUS] PWA:', Environment.isPWA());

        // Enhance haptics
        enhanceExistingHaptics();

        // Initialize network monitoring
        NativeNetwork.init();

        // Initialize auto-haptics for interactive elements
        initAutoHaptics();

        // Hide splash screen if in native app
        if (Environment.isCapacitor()) {
            // Wait for app to be ready
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => NativeSplash.hide(), 300);
            });

            // Set status bar style based on theme
            const theme = document.documentElement.getAttribute('data-theme');
            NativeStatusBar.setStyle(theme === 'dark' ? 'light' : 'dark');
            NativeStatusBar.setBackgroundColor(theme === 'dark' ? '#0f172a' : '#ffffff');
        }

        // Add platform class to body for CSS targeting
        document.body.classList.add(`platform-${Environment.getPlatform()}`);
        if (Environment.isPWA()) {
            document.body.classList.add('is-pwa');
        }
        if (Environment.isCapacitor()) {
            document.body.classList.add('is-native');
        }
    }

    // ============================================
    // PUBLIC API
    // ============================================
    window.NexusNative = {
        Environment,
        Haptics: NativeHaptics,
        StatusBar: NativeStatusBar,
        Keyboard: NativeKeyboard,
        Share: NativeShare,
        Splash: NativeSplash,
        Network: NativeNetwork,
        init
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
