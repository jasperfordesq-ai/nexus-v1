/**
 * NEXUS Native Push Notifications
 * Handles FCM push notifications for the Capacitor Android app
 * Works alongside PWA web push - does NOT conflict
 */
(function() {
    'use strict';

    // Only run in native Capacitor environment
    const isNativeApp = typeof Capacitor !== 'undefined' && Capacitor.isNativePlatform && Capacitor.isNativePlatform();

    if (!isNativeApp) {
        console.log('[NativePush] Not in native app, skipping native push setup');
        return;
    }

    console.log('[NativePush] Initializing native push notifications');

    // Import Capacitor Push Notifications
    const { PushNotifications } = Capacitor.Plugins;

    if (!PushNotifications) {
        console.error('[NativePush] PushNotifications plugin not available');
        return;
    }

    const NativePush = {
        token: null,
        isRegistered: false,

        async init() {
            try {
                // Check current permission status
                const permStatus = await PushNotifications.checkPermissions();
                console.log('[NativePush] Permission status:', permStatus.receive);

                if (permStatus.receive === 'prompt') {
                    // Request permission
                    const result = await PushNotifications.requestPermissions();
                    if (result.receive !== 'granted') {
                        console.log('[NativePush] Permission denied');
                        return;
                    }
                } else if (permStatus.receive !== 'granted') {
                    console.log('[NativePush] Permission not granted:', permStatus.receive);
                    return;
                }

                // Register for push notifications
                await this.register();

            } catch (error) {
                console.error('[NativePush] Init error:', error);
            }
        },

        async register() {
            // Set up listeners BEFORE registering
            this.setupListeners();

            // Register with FCM
            await PushNotifications.register();
            console.log('[NativePush] Registration initiated');
        },

        setupListeners() {
            // Registration success - we get the FCM token
            PushNotifications.addListener('registration', async (token) => {
                console.log('[NativePush] FCM Token received:', token.value.substring(0, 20) + '...');
                this.token = token.value;
                this.isRegistered = true;

                // Send token to our server
                await this.sendTokenToServer(token.value);
            });

            // Registration error
            PushNotifications.addListener('registrationError', (error) => {
                console.error('[NativePush] Registration error:', error);
            });

            // Notification received while app is in foreground
            PushNotifications.addListener('pushNotificationReceived', (notification) => {
                console.log('[NativePush] Notification received in foreground:', notification);

                // Show in-app notification (since foreground notifications don't show in system tray)
                this.showInAppNotification(notification);
            });

            // Notification action performed (user tapped notification)
            PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
                console.log('[NativePush] Notification action:', action);

                const data = action.notification.data;

                // Handle navigation based on notification data
                if (data && data.url) {
                    window.location.href = data.url;
                } else if (data && data.type) {
                    this.handleNotificationType(data);
                }
            });
        },

        async sendTokenToServer(token) {
            try {
                // Get user ID if logged in
                const userId = window.NEXUS?.userId || null;

                const response = await fetch('/api/push/register-device', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        token: token,
                        platform: 'android',
                        type: 'fcm',
                        user_id: userId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    console.log('[NativePush] Token registered with server');
                } else {
                    console.error('[NativePush] Server registration failed:', result.error);
                }
            } catch (error) {
                console.error('[NativePush] Failed to send token to server:', error);
            }
        },

        showInAppNotification(notification) {
            // Create a toast-style notification for foreground messages
            const toast = document.createElement('div');
            toast.className = 'nexus-native-toast';
            toast.innerHTML = `
                <div class="nexus-native-toast-content">
                    <div class="nexus-native-toast-icon">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="nexus-native-toast-text">
                        <strong>${this.escapeHtml(notification.title || 'Notification')}</strong>
                        <span>${this.escapeHtml(notification.body || '')}</span>
                    </div>
                    <button class="nexus-native-toast-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            `;

            document.body.appendChild(toast);

            // Animate in
            requestAnimationFrame(() => {
                toast.classList.add('visible');
            });

            // Handle click on toast
            toast.querySelector('.nexus-native-toast-content').addEventListener('click', (e) => {
                if (!e.target.closest('.nexus-native-toast-close')) {
                    // Handle tap on notification
                    if (notification.data && notification.data.url) {
                        window.location.href = notification.data.url;
                    }
                    this.dismissToast(toast);
                }
            });

            // Handle close button
            toast.querySelector('.nexus-native-toast-close').addEventListener('click', () => {
                this.dismissToast(toast);
            });

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                this.dismissToast(toast);
            }, 5000);
        },

        dismissToast(toast) {
            if (!toast.parentNode) return;
            toast.classList.remove('visible');
            setTimeout(() => toast.remove(), 300);
        },

        handleNotificationType(data) {
            // Handle different notification types
            switch (data.type) {
                case 'message':
                    window.location.href = '/messages';
                    break;
                case 'exchange':
                    window.location.href = data.exchange_id ? `/exchanges/${data.exchange_id}` : '/exchanges';
                    break;
                case 'request':
                    window.location.href = data.request_id ? `/requests/${data.request_id}` : '/requests';
                    break;
                case 'offer':
                    window.location.href = data.offer_id ? `/offers/${data.offer_id}` : '/offers';
                    break;
                case 'profile':
                    window.location.href = data.user_id ? `/profile/${data.user_id}` : '/profile';
                    break;
                default:
                    // Default: go to notifications page or home
                    window.location.href = '/notifications';
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Public method to get current token
        getToken() {
            return this.token;
        },

        // Public method to check if registered
        isReady() {
            return this.isRegistered;
        }
    };

    // Add styles for in-app toast
    const style = document.createElement('style');
    style.textContent = `
        .nexus-native-toast {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100001;
            padding: 12px;
            transform: translateY(-100%);
            transition: transform 0.3s ease;
            pointer-events: none;
        }

        .nexus-native-toast.visible {
            transform: translateY(0);
        }

        .nexus-native-toast-content {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--htb-card-bg, white);
            border-radius: 12px;
            padding: 12px 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--htb-border, #e5e7eb);
            pointer-events: auto;
            cursor: pointer;
            max-width: 400px;
            margin: 0 auto;
        }

        .nexus-native-toast-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .nexus-native-toast-text {
            flex: 1;
            min-width: 0;
        }

        .nexus-native-toast-text strong {
            display: block;
            font-size: 0.95rem;
            color: var(--htb-text-main, #1f2937);
            margin-bottom: 2px;
        }

        .nexus-native-toast-text span {
            display: block;
            font-size: 0.85rem;
            color: var(--htb-text-muted, #6b7280);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nexus-native-toast-close {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: var(--htb-text-muted, #9ca3af);
            cursor: pointer;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .nexus-native-toast-close:hover {
            background: var(--htb-bg-secondary, #f3f4f6);
            color: var(--htb-text-main, #374151);
        }

        /* Safe area padding for notched phones */
        @supports (padding-top: env(safe-area-inset-top)) {
            .nexus-native-toast {
                padding-top: calc(12px + env(safe-area-inset-top));
            }
        }

        /* Dark mode */
        [data-theme="dark"] .nexus-native-toast-content {
            background: var(--htb-card-bg, #1e293b);
            border-color: var(--htb-border, #334155);
        }
    `;
    document.head.appendChild(style);

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NativePush.init());
    } else {
        NativePush.init();
    }

    // Expose globally for debugging and external access
    window.NexusNativePush = NativePush;

})();
