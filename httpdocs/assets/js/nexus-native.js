/**
 * Project NEXUS - Native Device APIs
 * Web Share API, Camera Capture, Geolocation, Haptics
 */

(function() {
    'use strict';

    window.NexusNative = {

        // ============================================
        // 1. WEB SHARE API
        // ============================================

        canShare: function() {
            return navigator.share !== undefined;
        },

        async share(data) {
            const shareData = {
                title: data.title || document.title,
                text: data.text || '',
                url: data.url || window.location.href
            };

            // Haptic feedback
            this.haptic('medium');

            if (this.canShare()) {
                try {
                    await navigator.share(shareData);
                    return { success: true };
                } catch (err) {
                    if (err.name === 'AbortError') {
                        return { success: false, reason: 'cancelled' };
                    }
                    // Fall back to custom share sheet
                    return this.showFallbackShare(shareData);
                }
            } else {
                return this.showFallbackShare(shareData);
            }
        },

        showFallbackShare: function(data) {
            return new Promise((resolve) => {
                // Create share sheet
                const sheet = document.createElement('div');
                sheet.className = 'nexus-share-sheet';
                sheet.innerHTML = `
                    <div class="nexus-share-sheet-backdrop"></div>
                    <div class="nexus-share-sheet-content">
                        <div class="nexus-share-sheet-header">
                            <h3>Share</h3>
                            <button class="nexus-share-close">&times;</button>
                        </div>
                        <div class="nexus-share-sheet-body">
                            <div class="nexus-share-options">
                                <button class="nexus-share-option" data-action="copy">
                                    <i class="fa-solid fa-link"></i>
                                    <span>Copy Link</span>
                                </button>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(data.url)}" target="_blank" class="nexus-share-option">
                                    <i class="fa-brands fa-facebook"></i>
                                    <span>Facebook</span>
                                </a>
                                <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(data.text)}&url=${encodeURIComponent(data.url)}" target="_blank" class="nexus-share-option">
                                    <i class="fa-brands fa-x-twitter"></i>
                                    <span>X / Twitter</span>
                                </a>
                                <a href="https://api.whatsapp.com/send?text=${encodeURIComponent(data.text + ' ' + data.url)}" target="_blank" class="nexus-share-option">
                                    <i class="fa-brands fa-whatsapp"></i>
                                    <span>WhatsApp</span>
                                </a>
                                <a href="mailto:?subject=${encodeURIComponent(data.title)}&body=${encodeURIComponent(data.text + '\n\n' + data.url)}" class="nexus-share-option">
                                    <i class="fa-solid fa-envelope"></i>
                                    <span>Email</span>
                                </a>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(sheet);
                requestAnimationFrame(() => sheet.classList.add('active'));

                // Handle copy
                sheet.querySelector('[data-action="copy"]').addEventListener('click', () => {
                    navigator.clipboard.writeText(data.url);
                    if (window.NexusMobile) {
                        NexusMobile.showToast('Link copied!', 'success');
                    }
                    closeSheet();
                });

                // Handle close
                const closeSheet = () => {
                    sheet.classList.remove('active');
                    setTimeout(() => sheet.remove(), 300);
                    resolve({ success: true });
                };

                sheet.querySelector('.nexus-share-close').addEventListener('click', closeSheet);
                sheet.querySelector('.nexus-share-sheet-backdrop').addEventListener('click', closeSheet);

                // Close on any share option click
                sheet.querySelectorAll('a.nexus-share-option').forEach(link => {
                    link.addEventListener('click', () => {
                        setTimeout(closeSheet, 500);
                    });
                });
            });
        },


        // ============================================
        // 2. CAMERA CAPTURE
        // ============================================

        canCapture: function() {
            return 'mediaDevices' in navigator && 'getUserMedia' in navigator.mediaDevices;
        },

        async capturePhoto(options = {}) {
            const config = {
                facingMode: options.facingMode || 'user', // 'user' or 'environment'
                width: options.width || 1280,
                height: options.height || 720,
                quality: options.quality || 0.85
            };

            this.haptic('medium');

            return new Promise((resolve, reject) => {
                // Create camera UI
                const cameraUI = document.createElement('div');
                cameraUI.className = 'nexus-camera-ui';
                cameraUI.innerHTML = `
                    <div class="nexus-camera-container">
                        <video class="nexus-camera-video" autoplay playsinline></video>
                        <canvas class="nexus-camera-canvas" style="display:none;"></canvas>
                        <div class="nexus-camera-controls">
                            <button class="nexus-camera-btn nexus-camera-close">
                                <i class="fa-solid fa-times"></i>
                            </button>
                            <button class="nexus-camera-btn nexus-camera-capture">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                            <button class="nexus-camera-btn nexus-camera-flip">
                                <i class="fa-solid fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="nexus-camera-preview" style="display:none;">
                            <img class="nexus-camera-preview-img">
                            <div class="nexus-camera-preview-actions">
                                <button class="nexus-camera-btn nexus-camera-retake">
                                    <i class="fa-solid fa-redo"></i> Retake
                                </button>
                                <button class="nexus-camera-btn nexus-camera-use primary">
                                    <i class="fa-solid fa-check"></i> Use Photo
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(cameraUI);
                requestAnimationFrame(() => cameraUI.classList.add('active'));

                const video = cameraUI.querySelector('.nexus-camera-video');
                const canvas = cameraUI.querySelector('.nexus-camera-canvas');
                const preview = cameraUI.querySelector('.nexus-camera-preview');
                const previewImg = cameraUI.querySelector('.nexus-camera-preview-img');
                let stream = null;
                let currentFacing = config.facingMode;
                let capturedBlob = null;

                const startCamera = async (facing) => {
                    // Stop existing stream
                    if (stream) {
                        stream.getTracks().forEach(t => t.stop());
                    }

                    try {
                        stream = await navigator.mediaDevices.getUserMedia({
                            video: {
                                facingMode: facing,
                                width: { ideal: config.width },
                                height: { ideal: config.height }
                            },
                            audio: false
                        });
                        video.srcObject = stream;
                    } catch (err) {
                        cleanup();
                        reject(new Error('Camera access denied'));
                    }
                };

                const cleanup = () => {
                    if (stream) {
                        stream.getTracks().forEach(t => t.stop());
                    }
                    cameraUI.classList.remove('active');
                    setTimeout(() => cameraUI.remove(), 300);
                };

                // Start camera
                startCamera(currentFacing);

                // Capture photo
                cameraUI.querySelector('.nexus-camera-capture').addEventListener('click', () => {
                    this.haptic('heavy');

                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);

                    canvas.toBlob((blob) => {
                        capturedBlob = blob;
                        previewImg.src = URL.createObjectURL(blob);
                        preview.style.display = 'flex';
                        video.style.display = 'none';
                    }, 'image/jpeg', config.quality);
                });

                // Flip camera
                cameraUI.querySelector('.nexus-camera-flip').addEventListener('click', () => {
                    currentFacing = currentFacing === 'user' ? 'environment' : 'user';
                    startCamera(currentFacing);
                    this.haptic('light');
                });

                // Retake
                cameraUI.querySelector('.nexus-camera-retake').addEventListener('click', () => {
                    preview.style.display = 'none';
                    video.style.display = 'block';
                    capturedBlob = null;
                    this.haptic('light');
                });

                // Use photo
                cameraUI.querySelector('.nexus-camera-use').addEventListener('click', () => {
                    this.haptic('success');
                    cleanup();
                    resolve({
                        success: true,
                        blob: capturedBlob,
                        dataUrl: previewImg.src
                    });
                });

                // Close
                cameraUI.querySelector('.nexus-camera-close').addEventListener('click', () => {
                    cleanup();
                    resolve({ success: false, reason: 'cancelled' });
                });
            });
        },

        // Simple file input with camera option
        selectPhoto: function(inputElement) {
            if (!inputElement) return;

            // On mobile, this will show camera option
            inputElement.setAttribute('accept', 'image/*');
            inputElement.setAttribute('capture', 'environment');
            inputElement.click();
        },


        // ============================================
        // 3. GEOLOCATION
        // ============================================

        async getLocation(options = {}) {
            const config = {
                enableHighAccuracy: options.highAccuracy !== false,
                timeout: options.timeout || 10000,
                maximumAge: options.maxAge || 60000
            };

            if (!navigator.geolocation) {
                return { success: false, error: 'Geolocation not supported' };
            }

            return new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        resolve({
                            success: true,
                            coords: {
                                latitude: position.coords.latitude,
                                longitude: position.coords.longitude,
                                accuracy: position.coords.accuracy
                            }
                        });
                    },
                    (error) => {
                        resolve({
                            success: false,
                            error: error.message
                        });
                    },
                    config
                );
            });
        },


        // ============================================
        // 4. ENHANCED HAPTICS
        // ============================================

        haptic: function(type = 'light') {
            if (!navigator.vibrate) return;

            const patterns = {
                // Simple patterns
                light: [10],
                medium: [20],
                heavy: [40],

                // Feedback patterns
                success: [10, 30, 10, 30, 20],
                error: [50, 30, 50, 30, 50],
                warning: [30, 20, 30],

                // UI patterns
                tap: [8],
                doubleTap: [8, 50, 8],
                longPress: [5, 5, 5, 5, 5, 5, 30],

                // Navigation
                selection: [5],
                impact: [15, 10, 25],

                // Notifications
                notification: [20, 100, 20, 100, 40],
                reminder: [30, 50, 30]
            };

            const pattern = patterns[type] || patterns.light;
            navigator.vibrate(pattern);
        },


        // ============================================
        // 5. OFFLINE FORM QUEUE
        // ============================================

        formQueue: [],

        queueForm: function(formData, action, method) {
            const entry = {
                id: Date.now(),
                action: action,
                method: method,
                data: Object.fromEntries(formData),
                timestamp: new Date().toISOString()
            };

            this.formQueue.push(entry);
            localStorage.setItem('nexus_form_queue', JSON.stringify(this.formQueue));

            // Register for background sync
            if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.sync.register('sync-forms');
                });
            }

            return entry.id;
        },

        async processQueue() {
            const queue = JSON.parse(localStorage.getItem('nexus_form_queue') || '[]');
            const remaining = [];

            for (const entry of queue) {
                try {
                    const formData = new FormData();
                    Object.entries(entry.data).forEach(([k, v]) => formData.append(k, v));

                    const response = await fetch(entry.action, {
                        method: entry.method,
                        body: formData
                    });

                    if (!response.ok) {
                        remaining.push(entry);
                    } else {
                        if (window.NexusMobile) {
                            NexusMobile.showToast('Queued form submitted', 'success');
                        }
                    }
                } catch (e) {
                    remaining.push(entry);
                }
            }

            localStorage.setItem('nexus_form_queue', JSON.stringify(remaining));
            this.formQueue = remaining;
        },

        getQueuedForms: function() {
            return JSON.parse(localStorage.getItem('nexus_form_queue') || '[]');
        },


        // ============================================
        // 6. SWIPE-BACK GESTURE NAVIGATION
        // ============================================

        swipeBackEnabled: true,
        swipeStartX: 0,
        swipeStartY: 0,
        swipeThreshold: 80,
        edgeWidth: 30,

        initSwipeBack: function() {
            if (!this.swipeBackEnabled) return;

            // Create swipe indicator
            const indicator = document.createElement('div');
            indicator.className = 'nexus-swipe-back-indicator';
            indicator.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
            document.body.appendChild(indicator);

            let startX = 0;
            let startY = 0;
            let currentX = 0;
            let isSwipeBack = false;

            document.addEventListener('touchstart', (e) => {
                const touch = e.touches[0];
                startX = touch.clientX;
                startY = touch.clientY;

                // Only activate from left edge
                if (startX < this.edgeWidth && window.history.length > 1) {
                    isSwipeBack = true;
                }
            }, { passive: true });

            document.addEventListener('touchmove', (e) => {
                if (!isSwipeBack) return;

                const touch = e.touches[0];
                currentX = touch.clientX;
                const deltaX = currentX - startX;
                const deltaY = Math.abs(touch.clientY - startY);

                // Cancel if vertical movement is too high
                if (deltaY > 50) {
                    isSwipeBack = false;
                    indicator.classList.remove('visible');
                    return;
                }

                if (deltaX > 20) {
                    indicator.classList.add('visible');
                    const progress = Math.min(deltaX / this.swipeThreshold, 1);
                    indicator.style.opacity = progress;
                    indicator.querySelector('i').style.transform = `rotate(${-180 * (1 - progress)}deg)`;
                }
            }, { passive: true });

            document.addEventListener('touchend', () => {
                if (!isSwipeBack) return;

                const deltaX = currentX - startX;
                indicator.classList.remove('visible');

                if (deltaX >= this.swipeThreshold) {
                    this.haptic('impact');
                    window.history.back();
                }

                isSwipeBack = false;
                currentX = 0;
            }, { passive: true });

            document.addEventListener('touchcancel', () => {
                isSwipeBack = false;
                indicator.classList.remove('visible');
            }, { passive: true });
        },


        // ============================================
        // 7. PULL-TO-REFRESH - REMOVED
        // ============================================
        // Pull-to-refresh feature has been permanently removed due to conflicts with scrolling

        ptrEnabled: false,
        ptrThreshold: 80,
        ptrRefreshing: false,

        initPullToRefresh: function() {
            // Pull-to-refresh feature removed
            console.log('[NexusNative] Pull-to-refresh has been permanently removed');
        },


        // ============================================
        // 8. HAPTIC FEEDBACK FOR ELEMENTS
        // ============================================

        initHapticElements: function() {
            // Add haptic feedback to elements with .nexus-haptic class
            document.addEventListener('touchstart', (e) => {
                const hapticEl = e.target.closest('.nexus-haptic');
                if (hapticEl) {
                    this.haptic('light');
                }
            }, { passive: true });

            // Add haptic feedback on successful form submissions
            document.addEventListener('submit', (e) => {
                this.haptic('medium');
            }, { passive: true });
        },


        // ============================================
        // 9. PUSH NOTIFICATIONS
        // ============================================

        pushSupported: false,
        pushSubscription: null,

        async initPushNotifications() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                console.log('[NexusNative] Push notifications not supported');
                return;
            }

            this.pushSupported = true;

            try {
                const registration = await navigator.serviceWorker.ready;
                this.pushSubscription = await registration.pushManager.getSubscription();

                if (this.pushSubscription) {
                    console.log('[NexusNative] Already subscribed to push');
                }
            } catch (err) {
                console.warn('[NexusNative] Push init error:', err);
            }
        },

        async subscribeToPush() {
            if (!this.pushSupported) {
                return { success: false, error: 'Push not supported' };
            }

            try {
                const registration = await navigator.serviceWorker.ready;

                // Request permission
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    return { success: false, error: 'Permission denied' };
                }

                // Get VAPID public key from server
                const vapidResponse = await fetch('/api/push/vapid-key');
                const { publicKey } = await vapidResponse.json();

                // Subscribe
                this.pushSubscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(publicKey)
                });

                // Send subscription to server
                await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.pushSubscription)
                });

                this.haptic('success');
                return { success: true, subscription: this.pushSubscription };

            } catch (err) {
                console.error('[NexusNative] Push subscribe error:', err);
                return { success: false, error: err.message };
            }
        },

        async unsubscribeFromPush() {
            if (!this.pushSubscription) {
                return { success: false, error: 'Not subscribed' };
            }

            try {
                await this.pushSubscription.unsubscribe();

                // Notify server
                await fetch('/api/push/unsubscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: this.pushSubscription.endpoint })
                });

                this.pushSubscription = null;
                return { success: true };

            } catch (err) {
                return { success: false, error: err.message };
            }
        },

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },

        isPushSubscribed() {
            return !!this.pushSubscription;
        },


        // ============================================
        // 10. PWA INSTALL PROMPT
        // ============================================
        // DISABLED: Install prompt is now handled by nexus-pwa.js which has
        // a more polished modal implementation. Having two install prompts
        // caused the unstyled banner to appear unexpectedly.

        deferredInstallPrompt: null,
        isInstalled: false,

        initInstallPrompt() {
            // DISABLED - nexus-pwa.js handles this with better UI
            // Check if already installed (still useful for other code to check)
            if (window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true) {
                this.isInstalled = true;
                console.log('[NexusNative] App is installed');
            }
            console.log('[NexusNative] Install prompt disabled - handled by nexus-pwa.js');
        },

        showInstallBanner() {
            // DISABLED - nexus-pwa.js handles install prompts
            console.log('[NexusNative] showInstallBanner disabled - use NexusPWA.Install instead');
        },

        async triggerInstall() {
            // Delegate to nexus-pwa.js if available
            if (window.NexusPWA && window.NexusPWA.Install) {
                return window.NexusPWA.Install.install();
            }
            console.log('[NexusNative] triggerInstall - NexusPWA not available');
        },

        dismissInstall() {
            // Delegate to nexus-pwa.js localStorage key for consistency
            localStorage.setItem('nexus-install-dismissed', Date.now().toString());
        },

        canInstall() {
            // Delegate to nexus-pwa.js if available
            if (window.NexusPWA && window.NexusPWA.Install) {
                return window.NexusPWA.Install.canInstall();
            }
            return false;
        },


        // ============================================
        // 11. WEBAUTHN BIOMETRIC AUTH
        // ============================================

        webAuthnSupported: false,

        async initWebAuthn() {
            this.webAuthnSupported = window.PublicKeyCredential !== undefined &&
                typeof window.PublicKeyCredential === 'function';

            if (this.webAuthnSupported) {
                // Check platform authenticator availability
                try {
                    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                    this.webAuthnSupported = available;
                    console.log('[NexusNative] WebAuthn platform authenticator:', available ? 'available' : 'not available');
                } catch (e) {
                    this.webAuthnSupported = false;
                }
            }
        },

        async registerBiometric(userId, userName) {
            if (!this.webAuthnSupported) {
                return { success: false, error: 'WebAuthn not supported' };
            }

            try {
                // Get challenge from server
                const challengeResponse = await fetch('/api/webauthn/register-challenge', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userId, userName })
                });
                const options = await challengeResponse.json();

                // Decode base64 values
                options.challenge = this.base64ToArrayBuffer(options.challenge);
                options.user.id = this.base64ToArrayBuffer(options.user.id);

                // Create credential
                const credential = await navigator.credentials.create({
                    publicKey: options
                });

                // Send to server
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
                    })
                });

                const result = await verifyResponse.json();
                if (result.success) {
                    this.haptic('success');
                    localStorage.setItem('nexus_biometric_enabled', 'true');
                }

                return result;

            } catch (err) {
                console.error('[NexusNative] Biometric registration error:', err);
                return { success: false, error: err.message };
            }
        },

        async authenticateWithBiometric() {
            if (!this.webAuthnSupported) {
                return { success: false, error: 'WebAuthn not supported' };
            }

            try {
                // Get challenge from server
                const challengeResponse = await fetch('/api/webauthn/auth-challenge', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const options = await challengeResponse.json();

                // Decode base64 values
                options.challenge = this.base64ToArrayBuffer(options.challenge);
                if (options.allowCredentials) {
                    options.allowCredentials = options.allowCredentials.map(cred => ({
                        ...cred,
                        id: this.base64ToArrayBuffer(cred.id)
                    }));
                }

                this.haptic('medium');

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
                    })
                });

                const result = await verifyResponse.json();
                if (result.success) {
                    this.haptic('success');
                }

                return result;

            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    return { success: false, error: 'Authentication cancelled' };
                }
                console.error('[NexusNative] Biometric auth error:', err);
                return { success: false, error: err.message };
            }
        },

        isBiometricEnabled() {
            return localStorage.getItem('nexus_biometric_enabled') === 'true';
        },

        disableBiometric() {
            localStorage.removeItem('nexus_biometric_enabled');
            // Also remove from server
            fetch('/api/webauthn/remove', { method: 'POST' });
        },

        base64ToArrayBuffer(base64) {
            const binaryString = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
            const bytes = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            return bytes.buffer;
        },

        arrayBufferToBase64(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.byteLength; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        },


        // ============================================
        // 12. INITIALIZATION
        // ============================================

        init: function() {
            // Load queued forms
            this.formQueue = this.getQueuedForms();

            // Process queue when online
            window.addEventListener('online', () => {
                if (this.formQueue.length > 0) {
                    this.processQueue();
                }
            });

            // Add share button handler
            document.addEventListener('click', (e) => {
                const shareBtn = e.target.closest('[data-share]');
                if (shareBtn) {
                    e.preventDefault();
                    this.share({
                        title: shareBtn.dataset.shareTitle || document.title,
                        text: shareBtn.dataset.shareText || '',
                        url: shareBtn.dataset.shareUrl || window.location.href
                    });
                }
            });

            // Add camera button handler
            document.addEventListener('click', (e) => {
                const cameraBtn = e.target.closest('[data-camera]');
                if (cameraBtn) {
                    e.preventDefault();
                    const targetInput = document.querySelector(cameraBtn.dataset.camera);
                    if (targetInput) {
                        this.capturePhoto().then(result => {
                            if (result.success) {
                                // Create file from blob
                                const file = new File([result.blob], 'photo.jpg', { type: 'image/jpeg' });
                                const dt = new DataTransfer();
                                dt.items.add(file);
                                targetInput.files = dt.files;

                                // Trigger change event
                                targetInput.dispatchEvent(new Event('change', { bubbles: true }));

                                // Show preview if there's an img target
                                const previewTarget = cameraBtn.dataset.cameraPreview;
                                if (previewTarget) {
                                    document.querySelector(previewTarget).src = result.dataUrl;
                                }
                            }
                        });
                    }
                }
            });

            // Initialize mobile-specific features
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                // DISABLED: Swipe-back was causing navigation issues and conflicts with scroll gestures
                // this.initSwipeBack();
                // DISABLED: Pull-to-refresh was interfering with normal scrolling
                // this.initPullToRefresh();
                this.initHapticElements();
            }

            // Register service worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => {
                        console.log('[NexusNative] SW registered');
                        // Initialize push after SW is ready
                        this.initPushNotifications();
                    })
                    .catch(err => console.warn('[NexusNative] SW registration failed:', err));
            }

            // Initialize PWA install prompt
            this.initInstallPrompt();

            // Initialize WebAuthn
            this.initWebAuthn();

            console.log('[NexusNative] Initialized with native features');
        }
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NexusNative.init());
    } else {
        NexusNative.init();
    }

})();
