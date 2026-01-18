/**
 * Project NEXUS - PWA Features
 * Push Notifications, Install Prompt, and Service Worker Management
 */

(function () {
    'use strict';

    // ============================================
    // CONFIGURATION
    // ============================================
    const CONFIG = {
        vapidPublicKey: window.NEXUS_VAPID_PUBLIC_KEY || null,
        pushApiEndpoint: '/api/push/subscribe',
        pushUnsubscribeEndpoint: '/api/push/unsubscribe',
        installPromptDelay: 30000, // Show install prompt after 30s on mobile
        installPromptMinVisits: 0, // Show on first visit
    };

    // ============================================
    // 1. SERVICE WORKER REGISTRATION
    // ============================================
    const ServiceWorkerManager = {
        registration: null,

        async register() {
            if (!('serviceWorker' in navigator)) {
                console.log('[PWA] Service workers not supported');
                return null;
            }

            try {
                this.registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });

                console.log('[PWA] Service worker registered:', this.registration.scope);

                // Handle updates
                this.registration.addEventListener('updatefound', () => {
                    const newWorker = this.registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New version available
                            this.showUpdatePrompt();
                        }
                    });
                });

                // Listen for controller change (after skipWaiting)
                // DISABLED: Auto-reload on service worker update
                // This was causing unexpected page refreshes and potential logouts
                // Users will see the "Update available" banner instead and can choose when to update
                // To re-enable: uncomment the following lines
                // navigator.serviceWorker.addEventListener('controllerchange', () => {
                //     window.location.reload();
                // });
                console.log('[PWA] Service worker controller change listener disabled - using update banner instead');

                return this.registration;
            } catch (error) {
                console.error('[PWA] Service worker registration failed:', error);
                return null;
            }
        },

        showUpdatePrompt() {
            // Create update banner
            const banner = document.createElement('div');
            banner.className = 'nexus-pwa-update-banner';
            banner.innerHTML = `
                <div class="nexus-pwa-update-content">
                    <i class="fa-solid fa-arrow-rotate-right"></i>
                    <span>A new version is available</span>
                    <button class="nexus-pwa-update-btn">Update Now</button>
                </div>
            `;

            document.body.appendChild(banner);

            // Animate in
            requestAnimationFrame(() => {
                banner.classList.add('visible');
            });

            // Handle update click
            banner.querySelector('.nexus-pwa-update-btn').addEventListener('click', () => {
                if (this.registration.waiting) {
                    this.registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
                banner.remove();
                // Reload the page to apply the update
                // Small delay to allow the service worker to activate
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
        },

        async getRegistration() {
            if (this.registration) return this.registration;
            return navigator.serviceWorker.ready;
        }
    };

    // ============================================
    // 2. PUSH NOTIFICATIONS
    // ============================================
    const PushNotifications = {
        async isSupported() {
            return 'PushManager' in window && 'Notification' in window;
        },

        async getPermission() {
            if (!await this.isSupported()) return 'unsupported';
            return Notification.permission;
        },

        async requestPermission() {
            if (!await this.isSupported()) return 'unsupported';

            const permission = await Notification.requestPermission();

            if (permission === 'granted') {
                await this.subscribe();
            }

            return permission;
        },

        async subscribe() {
            if (!CONFIG.vapidPublicKey) {
                console.warn('[PWA] VAPID public key not configured');
                return null;
            }

            try {
                const registration = await ServiceWorkerManager.getRegistration();

                // Check existing subscription
                let subscription = await registration.pushManager.getSubscription();
                let isNew = false;

                if (subscription) {
                    console.log('[PWA] Found existing push subscription, re-sending to server');
                } else {
                    // Subscribe
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(CONFIG.vapidPublicKey)
                    });
                    isNew = true;
                    console.log('[PWA] Push subscription created');
                }

                // Always send subscription to server (in case previous send failed)
                const sent = await this.sendSubscriptionToServer(subscription);
                if (sent) {
                    console.log('[PWA] Subscription ' + (isNew ? 'created and ' : '') + 'sent to server successfully');
                }

                return subscription;
            } catch (error) {
                console.error('[PWA] Push subscription failed:', error);
                return null;
            }
        },

        async unsubscribe() {
            try {
                const registration = await ServiceWorkerManager.getRegistration();
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    // Get CSRF token from meta tag
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                    const headers = { 'Content-Type': 'application/json' };
                    if (csrfToken) {
                        headers['X-CSRF-Token'] = csrfToken;
                    }

                    // Notify server
                    await fetch(CONFIG.pushUnsubscribeEndpoint, {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify({ endpoint: subscription.endpoint }),
                        credentials: 'include'
                    });

                    // Unsubscribe
                    await subscription.unsubscribe();
                    console.log('[PWA] Unsubscribed from push');
                }

                return true;
            } catch (error) {
                console.error('[PWA] Unsubscribe failed:', error);
                return false;
            }
        },

        async sendSubscriptionToServer(subscription) {
            try {
                // Get CSRF token from meta tag
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                const headers = { 'Content-Type': 'application/json' };
                if (csrfToken) {
                    headers['X-CSRF-Token'] = csrfToken;
                }

                const response = await fetch(CONFIG.pushApiEndpoint, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(subscription),
                    credentials: 'include'
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('[PWA] Server rejected subscription:', response.status, errorText);
                    throw new Error('Server rejected subscription');
                }

                // Also update user's notification preferences to enable push
                try {
                    await fetch('/api/notifications/settings', {
                        method: 'POST',
                        headers: headers,
                        credentials: 'include',
                        body: JSON.stringify({ push_enabled: 1 })
                    });
                } catch (prefError) {
                    console.warn('[PWA] Could not update notification preferences:', prefError);
                }

                console.log('[PWA] Subscription sent to server');
                return true;
            } catch (error) {
                console.error('[PWA] Failed to send subscription to server:', error);
                return false;
            }
        },

        async getSubscription() {
            const registration = await ServiceWorkerManager.getRegistration();
            return registration.pushManager.getSubscription();
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

        // Show notification prompt UI
        showPermissionPrompt() {
            if (Notification.permission !== 'default') return;

            const prompt = document.createElement('div');
            prompt.className = 'nexus-notification-prompt';
            prompt.innerHTML = `
                <div class="nexus-notification-prompt-content">
                    <div class="nexus-notification-prompt-icon">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="nexus-notification-prompt-text">
                        <h4>Stay Updated</h4>
                        <p>Get notified about new messages, transactions, and events</p>
                    </div>
                    <div class="nexus-notification-prompt-actions">
                        <button class="nexus-prompt-btn-secondary" data-action="later">Later</button>
                        <button class="nexus-prompt-btn-primary" data-action="enable">Enable</button>
                    </div>
                </div>
            `;

            document.body.appendChild(prompt);

            requestAnimationFrame(() => {
                prompt.classList.add('visible');
            });

            // Handle actions
            prompt.querySelector('[data-action="enable"]').addEventListener('click', async () => {
                prompt.classList.remove('visible');
                setTimeout(() => prompt.remove(), 300);
                await this.requestPermission();
            });

            prompt.querySelector('[data-action="later"]').addEventListener('click', () => {
                prompt.classList.remove('visible');
                setTimeout(() => prompt.remove(), 300);
                // Remember for later
                sessionStorage.setItem('nexus-notification-prompt-dismissed', 'true');
            });
        }
    };

    // ============================================
    // 3. PWA INSTALL PROMPT
    // ============================================
    const InstallPrompt = {
        deferredPrompt: null,
        isInstalled: false,
        isNativeApp: false,
        isAndroid: /Android/i.test(navigator.userAgent),
        isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent),

        init() {
            // Check if running inside Capacitor native app
            if (typeof Capacitor !== 'undefined' && Capacitor.isNativePlatform()) {
                this.isNativeApp = true;
                document.body.classList.add('is-native-app');
                console.log('[PWA] Running in native app - install prompts disabled');
                return;
            }

            // Check if already installed as PWA
            if (window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true) {
                this.isInstalled = true;
                document.body.classList.add('is-pwa-installed');
                return;
            }

            // Capture the install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                console.log('[PWA] Install prompt captured');

                // Show install button after delay if conditions met
                this.checkAndShowPrompt();
            });

            // Track when app is installed
            window.addEventListener('appinstalled', () => {
                this.isInstalled = true;
                this.deferredPrompt = null;
                document.body.classList.add('is-pwa-installed');
                console.log('[PWA] App installed successfully');

                // Track installation
                if (window.gtag) {
                    gtag('event', 'pwa_install');
                }
            });
        },

        checkAndShowPrompt() {
            // Check visit count
            const visits = parseInt(localStorage.getItem('nexus-visit-count') || '0');
            localStorage.setItem('nexus-visit-count', (visits + 1).toString());

            if (visits < CONFIG.installPromptMinVisits) return;

            // Don't show if dismissed recently
            const dismissed = localStorage.getItem('nexus-install-dismissed');
            if (dismissed) {
                const dismissedTime = parseInt(dismissed);
                const daysSinceDismissed = (Date.now() - dismissedTime) / (1000 * 60 * 60 * 24);
                if (daysSinceDismissed < 7) return;
            }

            // Show after delay
            setTimeout(() => this.showPrompt(), CONFIG.installPromptDelay);
        },

        showPrompt() {
            if (this.isNativeApp || this.isInstalled) return;
            // On Android, always show (for APK option even if no PWA prompt)
            // On iOS, always show (for instructions)
            // On desktop, only show if we have deferredPrompt
            if (!this.isAndroid && !this.isIOS && !this.deferredPrompt) return;

            const prompt = document.createElement('div');
            prompt.className = 'nexus-install-prompt';

            // Different content based on platform
            if (this.isAndroid) {
                // Android: Show both options with clear explanations
                prompt.innerHTML = `
                    <div class="nexus-install-prompt-card nexus-install-android">
                        <button class="nexus-install-close" aria-label="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <div class="nexus-install-icon">
                            <img src="/assets/images/pwa/icon-192x192.png" alt="NEXUS" width="64" height="64">
                        </div>
                        <h3 class="nexus-install-title">Install NEXUS</h3>
                        <p class="nexus-install-description">Two ways to get the app on your device</p>

                        <!-- Option 1: Native APK -->
                        <div class="nexus-install-section nexus-install-section-apk">
                            <div class="nexus-install-section-header">
                                <i class="fa-brands fa-android"></i>
                                <span>Android App (APK)</span>
                                <span class="nexus-install-section-tag nexus-install-section-tag-green">Android Only</span>
                            </div>
                            <div class="nexus-install-section-body">
                                <ul class="nexus-install-benefits">
                                    <li><i class="fa-solid fa-check"></i> Native Android experience</li>
                                    <li><i class="fa-solid fa-check"></i> Works offline</li>
                                </ul>
                                <div class="nexus-install-apk-note">
                                    <i class="fa-solid fa-info-circle"></i>
                                    <span>Not from Play Store - you'll need to allow installation from this source</span>
                                </div>
                                <a href="/mobile-download" class="nexus-install-section-btn nexus-install-section-btn-green">
                                    <i class="fa-solid fa-download"></i> Download APK
                                </a>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="nexus-install-divider"><span>or</span></div>

                        <!-- Option 2: PWA -->
                        <div class="nexus-install-section">
                            <div class="nexus-install-section-header">
                                <i class="fa-solid fa-globe"></i>
                                <span>Web App (PWA)</span>
                            </div>
                            <div class="nexus-install-section-body">
                                <ul class="nexus-install-benefits">
                                    <li><i class="fa-solid fa-check"></i> Works on Android, iPhone & Desktop</li>
                                    <li><i class="fa-solid fa-check"></i> No download required</li>
                                    <li><i class="fa-solid fa-check"></i> Always up to date</li>
                                </ul>
                                <button class="nexus-install-section-btn" data-action="install">
                                    <i class="fa-solid fa-plus"></i> Add to Home Screen
                                </button>
                            </div>
                        </div>

                        <button class="nexus-install-btn-text" data-action="later">Maybe Later</button>
                    </div>
                `;
            } else if (this.isIOS) {
                // iOS: PWA instructions with clear explanation
                prompt.innerHTML = `
                    <div class="nexus-install-prompt-card">
                        <button class="nexus-install-close" aria-label="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <div class="nexus-install-icon">
                            <img src="/assets/images/pwa/icon-192x192.png" alt="NEXUS" width="64" height="64">
                        </div>
                        <h3 class="nexus-install-title">Install NEXUS on iPhone</h3>
                        <p class="nexus-install-description">Add to your home screen for an app-like experience</p>

                        <ul class="nexus-install-benefits" style="margin-bottom: 16px;">
                            <li><i class="fa-solid fa-check"></i> Works just like a native app</li>
                            <li><i class="fa-solid fa-check"></i> Full screen experience</li>
                            <li><i class="fa-solid fa-check"></i> Quick access from home screen</li>
                        </ul>

                        <div class="nexus-install-ios-steps">
                            <div class="nexus-install-ios-step">
                                <span class="nexus-install-ios-step-num">1</span>
                                <span>Tap <i class="fa-solid fa-arrow-up-from-bracket" style="color: #007AFF;"></i> at the bottom of Safari</span>
                            </div>
                            <div class="nexus-install-ios-step">
                                <span class="nexus-install-ios-step-num">2</span>
                                <span>Scroll down and tap <strong>Add to Home Screen</strong></span>
                            </div>
                            <div class="nexus-install-ios-step">
                                <span class="nexus-install-ios-step-num">3</span>
                                <span>Tap <strong>Add</strong> in the top right corner</span>
                            </div>
                        </div>

                        <div class="nexus-install-actions">
                            <button class="nexus-install-btn-secondary" data-action="later" style="flex: 1;">Got it</button>
                        </div>
                    </div>
                `;
            } else {
                // Desktop: Simple PWA install
                prompt.innerHTML = `
                    <div class="nexus-install-prompt-card">
                        <button class="nexus-install-close" aria-label="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <div class="nexus-install-icon">
                            <img src="/assets/images/pwa/icon-192x192.png" alt="NEXUS" width="64" height="64">
                        </div>
                        <h3 class="nexus-install-title">Install NEXUS</h3>
                        <p class="nexus-install-description">
                            Add to your desktop for quick access
                        </p>
                        <div class="nexus-install-features">
                            <div class="nexus-install-feature">
                                <i class="fa-solid fa-bolt"></i>
                                <span>Faster</span>
                            </div>
                            <div class="nexus-install-feature">
                                <i class="fa-solid fa-bell"></i>
                                <span>Notifications</span>
                            </div>
                            <div class="nexus-install-feature">
                                <i class="fa-solid fa-wifi-slash"></i>
                                <span>Offline</span>
                            </div>
                        </div>
                        <div class="nexus-install-actions">
                            <button class="nexus-install-btn-secondary" data-action="later">Not Now</button>
                            <button class="nexus-install-btn-primary" data-action="install">
                                <i class="fa-solid fa-download"></i>
                                Install
                            </button>
                        </div>
                    </div>
                `;
            }

            document.body.appendChild(prompt);

            // Animate in
            requestAnimationFrame(() => {
                prompt.classList.add('visible');
            });

            // Handle dismiss
            const dismiss = () => {
                prompt.classList.remove('visible');
                setTimeout(() => prompt.remove(), 300);
                localStorage.setItem('nexus-install-dismissed', Date.now().toString());
            };

            // Handle install click (if present)
            const installBtn = prompt.querySelector('[data-action="install"]');
            if (installBtn) {
                installBtn.addEventListener('click', async () => {
                    if (this.deferredPrompt) {
                        await this.install();
                    }
                    prompt.classList.remove('visible');
                    setTimeout(() => prompt.remove(), 300);
                });
            }

            // Handle dismiss buttons
            const laterBtn = prompt.querySelector('[data-action="later"]');
            if (laterBtn) {
                laterBtn.addEventListener('click', dismiss);
            }

            const closeBtn = prompt.querySelector('.nexus-install-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', dismiss);
            }
        },

        async install() {
            if (!this.deferredPrompt) return false;

            // Show the install prompt
            this.deferredPrompt.prompt();

            // Wait for user choice
            const { outcome } = await this.deferredPrompt.userChoice;
            console.log('[PWA] Install prompt outcome:', outcome);

            this.deferredPrompt = null;
            return outcome === 'accepted';
        },

        // Manual trigger for install button
        canInstall() {
            return !!this.deferredPrompt && !this.isInstalled;
        }
    };

    // ============================================
    // 4. BIOMETRIC / WEBAUTHN AUTHENTICATION
    // ============================================
    const BiometricAuth = {
        async isSupported() {
            if (!window.PublicKeyCredential) return false;

            // Check for platform authenticator (biometric)
            try {
                return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            } catch (e) {
                return false;
            }
        },

        async register(userId, userName, displayName) {
            if (!await this.isSupported()) {
                throw new Error('Biometric authentication not supported');
            }

            try {
                // Get challenge from server
                const challengeResponse = await fetch('/api/webauthn/register-challenge', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userId }),
                    credentials: 'include'
                });

                if (!challengeResponse.ok) {
                    throw new Error('Failed to get registration challenge');
                }

                const options = await challengeResponse.json();

                // Convert base64 to ArrayBuffer
                options.challenge = this.base64ToArrayBuffer(options.challenge);
                options.user.id = this.base64ToArrayBuffer(options.user.id);
                if (options.excludeCredentials) {
                    options.excludeCredentials = options.excludeCredentials.map(cred => ({
                        ...cred,
                        id: this.base64ToArrayBuffer(cred.id)
                    }));
                }

                // Create credential
                const credential = await navigator.credentials.create({
                    publicKey: options
                });

                // Send credential to server
                const verifyResponse = await fetch('/api/webauthn/register-verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: credential.id,
                        rawId: this.arrayBufferToBase64(credential.rawId),
                        type: credential.type,
                        response: {
                            clientDataJSON: this.arrayBufferToBase64(credential.response.clientDataJSON),
                            attestationObject: this.arrayBufferToBase64(credential.response.attestationObject)
                        }
                    }),
                    credentials: 'include'
                });

                if (!verifyResponse.ok) {
                    throw new Error('Failed to verify registration');
                }

                console.log('[WebAuthn] Biometric registered successfully');
                return true;
            } catch (error) {
                console.error('[WebAuthn] Registration failed:', error);
                throw error;
            }
        },

        async authenticate() {
            if (!await this.isSupported()) {
                throw new Error('Biometric authentication not supported');
            }

            try {
                // Get challenge from server
                const challengeResponse = await fetch('/api/webauthn/auth-challenge', {
                    method: 'POST',
                    credentials: 'include'
                });

                if (!challengeResponse.ok) {
                    throw new Error('Failed to get authentication challenge');
                }

                const options = await challengeResponse.json();

                // Convert base64 to ArrayBuffer
                options.challenge = this.base64ToArrayBuffer(options.challenge);
                if (options.allowCredentials) {
                    options.allowCredentials = options.allowCredentials.map(cred => ({
                        ...cred,
                        id: this.base64ToArrayBuffer(cred.id)
                    }));
                }

                // Get credential
                const credential = await navigator.credentials.get({
                    publicKey: options
                });

                // Verify with server
                const verifyResponse = await fetch('/api/webauthn/auth-verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: credential.id,
                        rawId: this.arrayBufferToBase64(credential.rawId),
                        type: credential.type,
                        response: {
                            clientDataJSON: this.arrayBufferToBase64(credential.response.clientDataJSON),
                            authenticatorData: this.arrayBufferToBase64(credential.response.authenticatorData),
                            signature: this.arrayBufferToBase64(credential.response.signature),
                            userHandle: credential.response.userHandle ?
                                this.arrayBufferToBase64(credential.response.userHandle) : null
                        }
                    }),
                    credentials: 'include'
                });

                if (!verifyResponse.ok) {
                    throw new Error('Authentication failed');
                }

                const result = await verifyResponse.json();
                console.log('[WebAuthn] Biometric authentication successful');
                return result;
            } catch (error) {
                if (error.name === 'NotAllowedError') {
                    console.log('[WebAuthn] User cancelled authentication');
                } else {
                    console.error('[WebAuthn] Authentication failed:', error);
                }
                throw error;
            }
        },

        // Helper functions
        base64ToArrayBuffer(base64) {
            const binary = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return bytes.buffer;
        },

        arrayBufferToBase64(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.length; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return window.btoa(binary)
                .replace(/\+/g, '-')
                .replace(/\//g, '_')
                .replace(/=/g, '');
        },

        // Show biometric setup prompt
        showSetupPrompt() {
            const prompt = document.createElement('div');
            prompt.className = 'nexus-biometric-prompt';
            prompt.innerHTML = `
                <div class="nexus-biometric-card">
                    <div class="nexus-biometric-icon">
                        <i class="fa-solid fa-fingerprint"></i>
                    </div>
                    <h3>Enable Quick Login</h3>
                    <p>Use Face ID, Touch ID, or fingerprint to sign in quickly and securely</p>
                    <div class="nexus-biometric-actions">
                        <button class="nexus-biometric-btn-secondary" data-action="later">Maybe Later</button>
                        <button class="nexus-biometric-btn-primary" data-action="enable">Enable</button>
                    </div>
                </div>
            `;

            document.body.appendChild(prompt);

            requestAnimationFrame(() => {
                prompt.classList.add('visible');
            });

            return new Promise((resolve) => {
                prompt.querySelector('[data-action="enable"]').addEventListener('click', async () => {
                    prompt.classList.remove('visible');
                    setTimeout(() => prompt.remove(), 300);
                    resolve(true);
                });

                prompt.querySelector('[data-action="later"]').addEventListener('click', () => {
                    prompt.classList.remove('visible');
                    setTimeout(() => prompt.remove(), 300);
                    localStorage.setItem('nexus-biometric-dismissed', Date.now().toString());
                    resolve(false);
                });
            });
        }
    };

    // ============================================
    // 5. STYLES (injected)
    // ============================================
    function injectStyles() {
        const styles = document.createElement('style');
        styles.textContent = `
            /* PWA Update Banner */
            .nexus-pwa-update-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: white;
                z-index: 100001;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
            }

            .nexus-pwa-update-banner.visible {
                transform: translateY(0);
            }

            .nexus-pwa-update-content {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                padding: 12px 20px;
                max-width: 600px;
                margin: 0 auto;
            }

            .nexus-pwa-update-btn {
                background: white;
                color: #6366f1;
                border: none;
                padding: 8px 16px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
            }

            /* Notification Permission Prompt */
            .nexus-notification-prompt {
                position: fixed;
                bottom: 100px;
                left: 16px;
                right: 16px;
                z-index: 99999;
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.3s ease;
            }

            .nexus-notification-prompt.visible {
                opacity: 1;
                transform: translateY(0);
            }

            .nexus-notification-prompt-content {
                background: var(--drawer-bg, white);
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 16px;
                max-width: 400px;
                margin: 0 auto;
            }

            .nexus-notification-prompt-icon {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, #f59e0b, #f97316);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.5rem;
            }

            .nexus-notification-prompt-text {
                flex: 1;
                min-width: 150px;
            }

            .nexus-notification-prompt-text h4 {
                margin: 0 0 4px;
                font-size: 1rem;
            }

            .nexus-notification-prompt-text p {
                margin: 0;
                font-size: 0.85rem;
                opacity: 0.8;
            }

            .nexus-notification-prompt-actions {
                display: flex;
                gap: 8px;
                width: 100%;
            }

            .nexus-prompt-btn-secondary,
            .nexus-prompt-btn-primary {
                flex: 1;
                padding: 10px 16px;
                border-radius: 10px;
                font-weight: 600;
                cursor: pointer;
                border: none;
            }

            .nexus-prompt-btn-secondary {
                background: rgba(0, 0, 0, 0.05);
                color: inherit;
            }

            .nexus-prompt-btn-primary {
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: white;
            }

            /* Install Prompt */
            .nexus-install-prompt {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .nexus-install-prompt.visible {
                opacity: 1;
                visibility: visible;
            }

            .nexus-install-prompt-card {
                background: var(--drawer-bg, white);
                border-radius: 24px;
                padding: 32px 24px;
                max-width: 340px;
                width: 100%;
                text-align: center;
                position: relative;
                transform: scale(0.9);
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            }

            .nexus-install-prompt.visible .nexus-install-prompt-card {
                transform: scale(1);
            }

            .nexus-install-close {
                position: absolute;
                top: 16px;
                right: 16px;
                width: 32px;
                height: 32px;
                background: rgba(0, 0, 0, 0.05);
                border: none;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .nexus-install-icon {
                margin-bottom: 16px;
            }

            .nexus-install-icon img {
                width: 72px;
                height: 72px;
                border-radius: 16px;
                box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
            }

            .nexus-install-title {
                margin: 0 0 8px;
                font-size: 1.5rem;
            }

            .nexus-install-description {
                margin: 0 0 20px;
                font-size: 0.95rem;
                opacity: 0.8;
            }

            .nexus-install-features {
                display: flex;
                justify-content: center;
                gap: 16px;
                margin-bottom: 24px;
                flex-wrap: wrap;
            }

            .nexus-install-feature {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 6px;
                font-size: 0.75rem;
                opacity: 0.8;
            }

            .nexus-install-feature i {
                width: 36px;
                height: 36px;
                background: rgba(99, 102, 241, 0.1);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6366f1;
                font-size: 1rem;
            }

            .nexus-install-actions {
                display: flex;
                gap: 12px;
            }

            .nexus-install-btn-secondary,
            .nexus-install-btn-primary {
                flex: 1;
                padding: 14px 20px;
                border-radius: 12px;
                font-weight: 600;
                cursor: pointer;
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .nexus-install-btn-secondary {
                background: rgba(0, 0, 0, 0.05);
                color: inherit;
            }

            .nexus-install-btn-primary {
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: white;
            }

            /* Android Install Options */
            .nexus-install-option {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px;
                background: rgba(0, 0, 0, 0.03);
                border-radius: 12px;
                margin-bottom: 10px;
                position: relative;
                text-align: left;
            }

            .nexus-install-option-badge {
                position: absolute;
                top: -8px;
                left: 12px;
                background: #6366f1;
                color: white;
                font-size: 0.65rem;
                font-weight: 700;
                padding: 3px 8px;
                border-radius: 6px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .nexus-install-option-icon {
                width: 44px;
                height: 44px;
                background: rgba(99, 102, 241, 0.1);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6366f1;
                font-size: 1.25rem;
                flex-shrink: 0;
            }

            .nexus-install-option-info {
                flex: 1;
                min-width: 0;
            }

            .nexus-install-option-info strong {
                display: block;
                font-size: 0.95rem;
                margin-bottom: 2px;
            }

            .nexus-install-option-info span {
                font-size: 0.75rem;
                opacity: 0.7;
            }

            .nexus-install-option-btn {
                padding: 10px 16px;
                background: #6366f1;
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 0.85rem;
                cursor: pointer;
                text-decoration: none;
                flex-shrink: 0;
            }

            .nexus-install-option-btn-green {
                background: #10b981;
            }

            .nexus-install-btn-text {
                background: none;
                border: none;
                color: inherit;
                opacity: 0.6;
                font-size: 0.85rem;
                cursor: pointer;
                padding: 12px;
                width: 100%;
                margin-top: 4px;
            }

            .nexus-install-btn-text:hover {
                opacity: 1;
            }

            /* iOS Install Steps */
            .nexus-install-ios-steps {
                text-align: left;
                margin-bottom: 16px;
            }

            .nexus-install-ios-step {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 0;
                border-bottom: 1px solid rgba(0, 0, 0, 0.06);
                font-size: 0.9rem;
            }

            .nexus-install-ios-step:last-child {
                border-bottom: none;
            }

            .nexus-install-ios-step-num {
                width: 28px;
                height: 28px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 0.8rem;
                flex-shrink: 0;
            }

            .nexus-install-ios-step i {
                color: #6366f1;
            }

            /* Benefits list */
            .nexus-install-benefits {
                list-style: none;
                padding: 0;
                margin: 0 0 12px 0;
                text-align: left;
            }

            .nexus-install-benefits li {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 6px 0;
                font-size: 0.85rem;
            }

            .nexus-install-benefits li i {
                color: #10b981;
                font-size: 0.75rem;
                width: 16px;
                flex-shrink: 0;
            }

            /* Install Sections (new design) */
            .nexus-install-section {
                background: rgba(0, 0, 0, 0.02);
                border: 1px solid rgba(0, 0, 0, 0.06);
                border-radius: 12px;
                overflow: hidden;
                text-align: left;
            }

            .nexus-install-section-header {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px 14px;
                background: rgba(99, 102, 241, 0.08);
                font-weight: 600;
                font-size: 0.9rem;
            }

            .nexus-install-section-header i {
                color: #6366f1;
            }

            .nexus-install-section-apk .nexus-install-section-header {
                background: rgba(16, 185, 129, 0.08);
            }

            .nexus-install-section-apk .nexus-install-section-header i {
                color: #10b981;
            }

            .nexus-install-section-tag {
                margin-left: auto;
                font-size: 0.65rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding: 3px 8px;
                border-radius: 4px;
                background: #6366f1;
                color: white;
            }

            .nexus-install-section-tag-green {
                background: #10b981;
            }

            .nexus-install-section-body {
                padding: 14px;
            }

            .nexus-install-section-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                padding: 12px 16px;
                background: #6366f1;
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 0.9rem;
                cursor: pointer;
                text-decoration: none;
            }

            .nexus-install-section-btn-green {
                background: #10b981;
            }

            .nexus-install-apk-note {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                padding: 10px 12px;
                background: rgba(245, 158, 11, 0.1);
                border-radius: 8px;
                font-size: 0.75rem;
                color: #b45309;
                margin-bottom: 12px;
            }

            .nexus-install-apk-note i {
                flex-shrink: 0;
                margin-top: 2px;
            }

            .nexus-install-divider {
                display: flex;
                align-items: center;
                gap: 12px;
                margin: 16px 0;
                opacity: 0.5;
                font-size: 0.8rem;
            }

            .nexus-install-divider::before,
            .nexus-install-divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: currentColor;
                opacity: 0.3;
            }

            /* Dark mode */
            [data-theme="dark"] .nexus-install-option {
                background: rgba(255, 255, 255, 0.05);
            }

            [data-theme="dark"] .nexus-install-ios-step {
                border-bottom-color: rgba(255, 255, 255, 0.1);
            }

            [data-theme="dark"] .nexus-install-section {
                background: rgba(255, 255, 255, 0.03);
                border-color: rgba(255, 255, 255, 0.08);
            }

            [data-theme="dark"] .nexus-install-section-header {
                background: rgba(99, 102, 241, 0.15);
            }

            [data-theme="dark"] .nexus-install-section-apk .nexus-install-section-header {
                background: rgba(16, 185, 129, 0.15);
            }

            [data-theme="dark"] .nexus-install-apk-note {
                background: rgba(245, 158, 11, 0.15);
                color: #fbbf24;
            }

            /* Biometric Prompt */
            .nexus-biometric-prompt {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .nexus-biometric-prompt.visible {
                opacity: 1;
                visibility: visible;
            }

            .nexus-biometric-card {
                background: var(--drawer-bg, white);
                border-radius: 24px;
                padding: 32px 24px;
                max-width: 320px;
                width: 100%;
                text-align: center;
            }

            .nexus-biometric-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                border-radius: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                font-size: 2.5rem;
                color: white;
            }

            .nexus-biometric-card h3 {
                margin: 0 0 8px;
                font-size: 1.3rem;
            }

            .nexus-biometric-card p {
                margin: 0 0 24px;
                font-size: 0.9rem;
                opacity: 0.8;
            }

            .nexus-biometric-actions {
                display: flex;
                gap: 12px;
            }

            .nexus-biometric-btn-secondary,
            .nexus-biometric-btn-primary {
                flex: 1;
                padding: 14px 20px;
                border-radius: 12px;
                font-weight: 600;
                cursor: pointer;
                border: none;
            }

            .nexus-biometric-btn-secondary {
                background: rgba(0, 0, 0, 0.05);
                color: inherit;
            }

            .nexus-biometric-btn-primary {
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: white;
            }

            /* Dark mode adjustments */
            [data-theme="dark"] .nexus-prompt-btn-secondary,
            [data-theme="dark"] .nexus-install-btn-secondary,
            [data-theme="dark"] .nexus-biometric-btn-secondary,
            [data-theme="dark"] .nexus-install-close {
                background: rgba(255, 255, 255, 0.1);
            }
        `;
        document.head.appendChild(styles);
    }

    // ============================================
    // 6. INITIALIZE
    // ============================================
    async function init() {
        // Inject styles
        injectStyles();

        // Register service worker
        await ServiceWorkerManager.register();

        // Initialize install prompt
        InstallPrompt.init();

        // Auto-subscribe if permission granted but not yet subscribed
        // This handles cases where previous subscription failed (CSRF, network, etc.)
        if (document.body.classList.contains('logged-in') &&
            Notification.permission === 'granted' &&
            CONFIG.vapidPublicKey) {
            try {
                const registration = await ServiceWorkerManager.getRegistration();
                if (registration) {
                    const subscription = await registration.pushManager.getSubscription();
                    if (subscription) {
                        // Subscription exists in browser, ensure it's saved to server
                        console.log('[PWA] Permission granted, ensuring subscription is saved...');
                        await PushNotifications.sendSubscriptionToServer(subscription);
                    } else {
                        // Permission granted but no subscription - create one
                        console.log('[PWA] Permission granted but no subscription, subscribing...');
                        await PushNotifications.subscribe();
                    }
                }
            } catch (e) {
                console.warn('[PWA] Auto-subscribe check failed:', e);
            }
        }

        // Show notification prompt if appropriate
        if (document.body.classList.contains('logged-in') &&
            Notification.permission === 'default' &&
            !sessionStorage.getItem('nexus-notification-prompt-dismissed')) {
            setTimeout(() => {
                PushNotifications.showPermissionPrompt();
            }, 5000);
        }

        console.log('[PWA] Features initialized');
    }

    // ============================================
    // PUBLIC API
    // ============================================
    window.NexusPWA = {
        ServiceWorker: ServiceWorkerManager,
        Push: PushNotifications,
        Install: InstallPrompt,
        Biometric: BiometricAuth,
        init
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
