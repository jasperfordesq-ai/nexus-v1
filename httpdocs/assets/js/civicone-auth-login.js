/**
 * Authentication Login JavaScript
 * Biometric/WebAuthn support check
 * CivicOne Theme
 */

// Check for biometric/WebAuthn support and show the option
async function checkBiometricSupport() {
    const container = document.getElementById('biometric-login-container');
    const promo = document.getElementById('biometric-promo');

    // Check if WebAuthn is supported
    if (!window.PublicKeyCredential) return;

    try {
        // Check for platform authenticator (biometric)
        const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        if (!available) return;

        // Check if any users have registered biometrics on this device
        // by trying to get credentials without specifying allowCredentials
        try {
            const response = await fetch('/api/webauthn/auth-challenge', {
                method: 'POST',
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                // If we got allowCredentials, user has set up biometrics
                if (data.allowCredentials && data.allowCredentials.length > 0) {
                    if (container) container.classList.remove('hidden');
                    if (promo) promo.classList.add('hidden');
                } else {
                    // No credentials - show promo instead
                    if (container) container.classList.add('hidden');
                    if (promo) promo.classList.remove('hidden');
                }
            } else {
                // No session/credentials - show promo to advertise the feature
                if (promo) promo.classList.remove('hidden');
            }
        } catch (e) {
            // Show promo as fallback
            if (promo) promo.classList.remove('hidden');
        }
    } catch (e) {
        console.warn('[WebAuthn] Biometric check failed:', e);
    }
}

async function attemptBiometricLogin() {
    const btn = document.getElementById('biometric-login-btn');
    const originalContent = btn.innerHTML;

    try {
        btn.disabled = true;
        btn.innerHTML = '<span>Authenticating...</span>';

        // Use NexusPWA Biometric if available
        if (window.NexusPWA && NexusPWA.Biometric) {
            const result = await NexusPWA.Biometric.authenticate();
            if (result && result.redirect) {
                window.location.href = result.redirect;
            } else if (result && result.success) {
                window.location.reload();
            }
        } else {
            // Fallback: Direct WebAuthn call
            const response = await fetch('/api/webauthn/auth-challenge', {
                method: 'POST',
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('No biometric credentials found. Please login with password first.');
            }

            const options = await response.json();

            // Convert base64 to ArrayBuffer
            options.challenge = base64ToArrayBuffer(options.challenge);
            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map(cred => ({
                    ...cred,
                    id: base64ToArrayBuffer(cred.id)
                }));
            }

            const credential = await navigator.credentials.get({ publicKey: options });

            // Verify with server
            const verifyResponse = await fetch('/api/webauthn/auth-verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: credential.id,
                    rawId: arrayBufferToBase64(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                        authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
                        signature: arrayBufferToBase64(credential.response.signature),
                        userHandle: credential.response.userHandle ?
                            arrayBufferToBase64(credential.response.userHandle) : null
                    }
                }),
                credentials: 'include'
            });

            if (verifyResponse.ok) {
                const result = await verifyResponse.json();
                window.location.href = result.redirect || '/dashboard';
            } else {
                throw new Error('Biometric verification failed');
            }
        }
    } catch (error) {
        console.error('[Biometric]', error);
        btn.innerHTML = originalContent;
        btn.disabled = false;

        if (error.name !== 'NotAllowedError') {
            alert(error.message || 'Biometric login failed. Please use password.');
        }
    }
}

function base64ToArrayBuffer(base64) {
    const binary = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Check on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkBiometricSupport);
} else {
    checkBiometricSupport();
}
