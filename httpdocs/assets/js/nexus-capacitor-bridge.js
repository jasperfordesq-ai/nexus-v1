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
    // INITIALIZE
    // ============================================
    function init() {
        // Log environment
        console.log('[NEXUS] Platform:', Environment.getPlatform());
        console.log('[NEXUS] Capacitor:', Environment.isCapacitor());
        console.log('[NEXUS] PWA:', Environment.isPWA());

        // Enhance haptics
        enhanceExistingHaptics();

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
        init
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
