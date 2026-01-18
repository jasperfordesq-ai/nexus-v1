/**
 * CivicOne WebAuthn Biometric Authentication
 * Fingerprint/Face ID login support
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    window.CivicOneWebAuthn = {

        // State
        isSupported: false,
        isRegistered: false,
        credential: null,

        // =========================================
        // 1. INITIALIZATION
        // =========================================

        init: function() {
            this.checkSupport();

            if (!this.isSupported) {
                console.log('[CivicOne WebAuthn] Not supported in this browser');
                return;
            }

            this.checkRegistration();
            this.initEventListeners();

            console.log('[CivicOne WebAuthn] Initialized');
        },

        // =========================================
        // 2. BROWSER SUPPORT CHECK
        // =========================================

        checkSupport: function() {
            this.isSupported = !!(
                window.PublicKeyCredential &&
                typeof window.PublicKeyCredential === 'function'
            );

            if (this.isSupported) {
                document.body.classList.add('webauthn-supported');
            }

            // Check platform authenticator (biometrics) availability
            if (this.isSupported && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
                PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
                    .then(available => {
                        if (available) {
                            document.body.classList.add('biometrics-available');
                        }
                    });
            }
        },

        // =========================================
        // 3. REGISTRATION STATUS CHECK
        // =========================================

        async checkRegistration() {
            try {
                const response = await fetch('/api/webauthn/status', {
                    credentials: 'include'
                });

                if (!response.ok) return;

                const data = await response.json();
                this.isRegistered = data.registered || false;

                if (this.isRegistered) {
                    document.body.classList.add('webauthn-registered');
                }
            } catch (e) {
                console.error('[CivicOne WebAuthn] Error checking registration:', e);
            }
        },

        // =========================================
        // 4. EVENT LISTENERS
        // =========================================

        initEventListeners: function() {
            // Biometric login button
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-webauthn-login]')) {
                    e.preventDefault();
                    this.authenticate();
                }

                if (e.target.closest('[data-webauthn-register]')) {
                    e.preventDefault();
                    this.register();
                }

                if (e.target.closest('[data-webauthn-remove]')) {
                    e.preventDefault();
                    this.removeCredential();
                }
            });
        },

        // =========================================
        // 5. REGISTRATION (Enroll Biometrics)
        // =========================================

        async register() {
            if (!this.isSupported) {
                this.showMessage('Biometric authentication is not supported on this device.', 'error');
                return;
            }

            try {
                // Show loading state
                this.setButtonLoading('[data-webauthn-register]', true);

                // Get registration options from server
                const optionsResponse = await fetch('/api/webauthn/register/options', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (!optionsResponse.ok) {
                    throw new Error('Failed to get registration options');
                }

                const options = await optionsResponse.json();

                // Decode base64url values
                options.challenge = this.base64UrlDecode(options.challenge);
                options.user.id = this.base64UrlDecode(options.user.id);

                if (options.excludeCredentials) {
                    options.excludeCredentials = options.excludeCredentials.map(cred => ({
                        ...cred,
                        id: this.base64UrlDecode(cred.id)
                    }));
                }

                // Create credential (triggers biometric prompt)
                const credential = await navigator.credentials.create({
                    publicKey: options
                });

                // Prepare response for server
                const credentialResponse = {
                    id: credential.id,
                    rawId: this.base64UrlEncode(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: this.base64UrlEncode(credential.response.clientDataJSON),
                        attestationObject: this.base64UrlEncode(credential.response.attestationObject)
                    }
                };

                // Verify with server
                const verifyResponse = await fetch('/api/webauthn/register/verify', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(credentialResponse)
                });

                if (!verifyResponse.ok) {
                    throw new Error('Failed to verify registration');
                }

                const result = await verifyResponse.json();

                if (result.success) {
                    this.isRegistered = true;
                    document.body.classList.add('webauthn-registered');
                    this.showMessage('Biometric login enabled successfully!', 'success');

                    // Update UI
                    this.updateSecuritySettings();
                } else {
                    throw new Error(result.error || 'Registration failed');
                }

            } catch (e) {
                console.error('[CivicOne WebAuthn] Registration error:', e);

                if (e.name === 'NotAllowedError') {
                    this.showMessage('Biometric registration was cancelled.', 'warning');
                } else if (e.name === 'InvalidStateError') {
                    this.showMessage('A biometric credential already exists for this account.', 'warning');
                } else {
                    this.showMessage('Failed to enable biometric login. Please try again.', 'error');
                }
            } finally {
                this.setButtonLoading('[data-webauthn-register]', false);
            }
        },

        // =========================================
        // 6. AUTHENTICATION (Login with Biometrics)
        // =========================================

        async authenticate() {
            if (!this.isSupported) {
                this.showMessage('Biometric authentication is not supported on this device.', 'error');
                return;
            }

            try {
                // Show loading state
                this.setButtonLoading('[data-webauthn-login]', true);

                // Get authentication options from server
                const optionsResponse = await fetch('/api/webauthn/login/options', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (!optionsResponse.ok) {
                    throw new Error('Failed to get authentication options');
                }

                const options = await optionsResponse.json();

                // Decode base64url values
                options.challenge = this.base64UrlDecode(options.challenge);

                if (options.allowCredentials) {
                    options.allowCredentials = options.allowCredentials.map(cred => ({
                        ...cred,
                        id: this.base64UrlDecode(cred.id)
                    }));
                }

                // Get credential (triggers biometric prompt)
                const credential = await navigator.credentials.get({
                    publicKey: options
                });

                // Prepare response for server
                const credentialResponse = {
                    id: credential.id,
                    rawId: this.base64UrlEncode(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: this.base64UrlEncode(credential.response.clientDataJSON),
                        authenticatorData: this.base64UrlEncode(credential.response.authenticatorData),
                        signature: this.base64UrlEncode(credential.response.signature),
                        userHandle: credential.response.userHandle
                            ? this.base64UrlEncode(credential.response.userHandle)
                            : null
                    }
                };

                // Verify with server
                const verifyResponse = await fetch('/api/webauthn/login/verify', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(credentialResponse)
                });

                if (!verifyResponse.ok) {
                    throw new Error('Authentication failed');
                }

                const result = await verifyResponse.json();

                if (result.success) {
                    this.showMessage('Login successful!', 'success');

                    // Redirect to dashboard or intended page
                    const redirectUrl = result.redirect || '/dashboard';
                    window.location.href = redirectUrl;
                } else {
                    throw new Error(result.error || 'Authentication failed');
                }

            } catch (e) {
                console.error('[CivicOne WebAuthn] Authentication error:', e);

                if (e.name === 'NotAllowedError') {
                    this.showMessage('Biometric login was cancelled.', 'warning');
                } else if (e.name === 'SecurityError') {
                    this.showMessage('Please use HTTPS for biometric authentication.', 'error');
                } else {
                    this.showMessage('Biometric login failed. Please try password login.', 'error');
                }
            } finally {
                this.setButtonLoading('[data-webauthn-login]', false);
            }
        },

        // =========================================
        // 7. REMOVE CREDENTIAL
        // =========================================

        async removeCredential() {
            if (!confirm('Remove biometric login from this account?')) {
                return;
            }

            try {
                this.setButtonLoading('[data-webauthn-remove]', true);

                const response = await fetch('/api/webauthn/remove', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (!response.ok) {
                    throw new Error('Failed to remove credential');
                }

                const result = await response.json();

                if (result.success) {
                    this.isRegistered = false;
                    document.body.classList.remove('webauthn-registered');
                    this.showMessage('Biometric login has been removed.', 'success');
                    this.updateSecuritySettings();
                } else {
                    throw new Error(result.error || 'Removal failed');
                }

            } catch (e) {
                console.error('[CivicOne WebAuthn] Remove error:', e);
                this.showMessage('Failed to remove biometric login.', 'error');
            } finally {
                this.setButtonLoading('[data-webauthn-remove]', false);
            }
        },

        // =========================================
        // 8. UTILITY FUNCTIONS
        // =========================================

        base64UrlDecode: function(base64url) {
            const padding = '='.repeat((4 - base64url.length % 4) % 4);
            const base64 = (base64url + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray.buffer;
        },

        base64UrlEncode: function(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.byteLength; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return window.btoa(binary)
                .replace(/\+/g, '-')
                .replace(/\//g, '_')
                .replace(/=/g, '');
        },

        setButtonLoading: function(selector, loading) {
            const btn = document.querySelector(selector);
            if (!btn) return;

            if (loading) {
                btn.disabled = true;
                btn.dataset.originalText = btn.textContent;
                btn.innerHTML = '<span class="civic-spinner"></span> Please wait...';
                btn.setAttribute('aria-busy', 'true');
            } else {
                btn.disabled = false;
                btn.textContent = btn.dataset.originalText || btn.textContent;
                btn.removeAttribute('aria-busy');
            }
        },

        showMessage: function(message, type) {
            // Use CivicOneMobile toast if available
            if (window.CivicOneMobile && CivicOneMobile.showToast) {
                CivicOneMobile.showToast(message, type);
                return;
            }

            // Fallback: create accessible alert
            const alert = document.createElement('div');
            alert.className = `civic-webauthn-alert civic-webauthn-alert--${type}`;
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'assertive');
            alert.textContent = message;

            document.body.appendChild(alert);

            requestAnimationFrame(() => {
                alert.classList.add('visible');
            });

            setTimeout(() => {
                alert.classList.remove('visible');
                setTimeout(() => alert.remove(), 300);
            }, 4000);
        },

        updateSecuritySettings: function() {
            // Update security settings UI if on settings page
            const registerBtn = document.querySelector('[data-webauthn-register]');
            const removeBtn = document.querySelector('[data-webauthn-remove]');
            const status = document.querySelector('[data-webauthn-status]');

            if (this.isRegistered) {
                if (registerBtn) registerBtn.style.display = 'none';
                if (removeBtn) removeBtn.style.display = '';
                if (status) {
                    status.textContent = 'Enabled';
                    status.className = 'civic-badge civic-badge--success';
                }
            } else {
                if (registerBtn) registerBtn.style.display = '';
                if (removeBtn) removeBtn.style.display = 'none';
                if (status) {
                    status.textContent = 'Disabled';
                    status.className = 'civic-badge civic-badge--neutral';
                }
            }
        },

        // =========================================
        // 9. CONDITIONAL UI (Auto-show biometric prompt)
        // =========================================

        autoPromptOnLogin: async function() {
            // Only auto-prompt if:
            // 1. WebAuthn is supported
            // 2. User has registered biometrics
            // 3. We're on a login page
            // 4. User hasn't dismissed recently

            if (!this.isSupported) return;

            const isLoginPage = document.querySelector('[data-login-form]');
            if (!isLoginPage) return;

            // Check if user has credential stored
            const hasCredential = localStorage.getItem('webauthn-enabled') === 'true';
            if (!hasCredential) return;

            // Check dismissal
            const dismissed = localStorage.getItem('webauthn-prompt-dismissed');
            if (dismissed && Date.now() - parseInt(dismissed) < 24 * 60 * 60 * 1000) {
                return; // Don't show if dismissed within 24 hours
            }

            // Show biometric prompt
            this.showBiometricLoginPrompt();
        },

        showBiometricLoginPrompt: function() {
            // Create accessible modal for biometric login option
            const modal = document.createElement('div');
            modal.id = 'civic-webauthn-modal';
            modal.className = 'civic-webauthn-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-labelledby', 'webauthn-modal-title');
            modal.setAttribute('aria-modal', 'true');

            modal.innerHTML = `
                <div class="civic-webauthn-modal-backdrop" data-webauthn-dismiss></div>
                <div class="civic-webauthn-modal-content">
                    <div class="civic-webauthn-modal-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 1c-1.1 0-2 .9-2 2v2C6.69 5 4 7.69 4 11v5l-2 2v1h20v-1l-2-2v-5c0-3.31-2.69-6-6-6V3c0-1.1-.9-2-2-2zm0 5c2.76 0 5 2.24 5 5v6H7v-6c0-2.76 2.24-5 5-5zm0 8c-.83 0-1.5.67-1.5 1.5S11.17 17 12 17s1.5-.67 1.5-1.5S12.83 14 12 14z"/>
                        </svg>
                    </div>
                    <h2 id="webauthn-modal-title">Use Biometric Login?</h2>
                    <p>Sign in quickly using your fingerprint or face recognition.</p>
                    <div class="civic-webauthn-modal-actions">
                        <button type="button" class="civic-btn civic-btn--primary" data-webauthn-login>
                            Use Biometrics
                        </button>
                        <button type="button" class="civic-btn civic-btn--secondary" data-webauthn-dismiss>
                            Use Password
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            requestAnimationFrame(() => {
                modal.classList.add('visible');
            });

            // Handle dismissal
            modal.querySelectorAll('[data-webauthn-dismiss]').forEach(el => {
                el.addEventListener('click', () => {
                    modal.classList.remove('visible');
                    setTimeout(() => modal.remove(), 300);
                    localStorage.setItem('webauthn-prompt-dismissed', Date.now());
                });
            });

            // Focus first button
            setTimeout(() => {
                modal.querySelector('[data-webauthn-login]').focus();
            }, 100);

            // Trap focus
            this.trapFocus(modal);
        },

        trapFocus: function(element) {
            const focusableElements = element.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            element.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey && document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    } else if (!e.shiftKey && document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }

                if (e.key === 'Escape') {
                    const dismissBtn = element.querySelector('[data-webauthn-dismiss]');
                    if (dismissBtn) dismissBtn.click();
                }
            });
        }
    };

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            CivicOneWebAuthn.init();
            CivicOneWebAuthn.autoPromptOnLogin();
        });
    } else {
        CivicOneWebAuthn.init();
        CivicOneWebAuthn.autoPromptOnLogin();
    }

})();
