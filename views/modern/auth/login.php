<?php
// Consolidated Login View (Single Source of Truth)
// Adapts to Modern, Social, and CivicOne layouts.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<div class="civicone-wrapper" style="padding-top: 40px;">';
} else {
    // Modern Defaults
    $hero_title = "Member Login";
    $hero_subtitle = "Welcome back to the community.";
    $hero_gradient = 'htb-hero-gradient-brand';
    require dirname(__DIR__) . '/../layouts/modern/header.php';
}

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="auth-wrapper">
    <div class="htb-card auth-card">
        <div class="auth-card-body">

            <h2 style="margin-top:0; margin-bottom:25px; font-weight:800; color:#0f172a; text-align:center;">Sign In</h2>

            <?php if (isset($_GET['registered'])): ?>
                <div style="background:#f0fdf4; color:#15803d; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center; font-size:0.9rem; font-weight:600; border:1px solid #bbf7d0;">
                    ✓ Registration successful! Please login.
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div style="background:#fef2f2; color:#b91c1c; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center; font-size:0.9rem; border:1px solid #fecaca;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Biometric Login Option (for users who have set it up) -->
            <div id="biometric-login-container" style="display:none; margin-bottom:25px;">
                <button type="button" id="biometric-login-btn" onclick="attemptBiometricLogin()"
                    style="width:100%; padding:14px; background:linear-gradient(135deg, #6366f1, #8b5cf6); color:white; font-weight:700; border:none; border-radius:8px; font-size:1.05rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                    </svg>
                    Sign In with Biometrics
                </button>
                <div style="text-align:center; margin:15px 0; color:#94a3b8; font-size:0.9rem;">or</div>
            </div>

            <!-- Biometric Feature Promo (for devices that support it but haven't set up) -->
            <div id="biometric-promo" style="display:none; margin-bottom:20px; padding:14px 16px; background:linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.08)); border-radius:10px; border:1px solid rgba(99, 102, 241, 0.2);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:40px; height:40px; background:linear-gradient(135deg, #6366f1, #8b5cf6); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                        </svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:700; font-size:0.9rem; color:#4f46e5; margin-bottom:2px;">Fingerprint & Face ID Available</div>
                        <div style="font-size:0.8rem; color:#64748b;">Sign in faster after your first login</div>
                    </div>
                    <div style="background:rgba(99, 102, 241, 0.1); color:#6366f1; padding:4px 8px; border-radius:12px; font-size:0.7rem; font-weight:700; text-transform:uppercase;">New</div>
                </div>
            </div>

            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" method="POST">
                <?= Nexus\Core\Csrf::input() ?>

                <div style="margin-bottom:20px;">
                    <label for="login-email" style="display:block; font-weight:600; margin-bottom:8px; color:#334155;">Email Address</label>
                    <input type="email" name="email" id="login-email" required placeholder="e.g. you@example.com"
                        autocomplete="email webauthn"
                        style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:8px; font-size:1rem; outline:none; transition:border-color 0.2s;">
                </div>

                <div style="margin-bottom:25px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <label for="login-password" style="font-weight:600; color:#334155;">Password</label>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/forgot" style="font-size:0.85rem; color:#4f46e5; text-decoration:none;">Forgot?</a>
                    </div>
                    <input type="password" name="password" id="login-password" required
                        autocomplete="current-password"
                        style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:8px; font-size:1rem; outline:none; transition:border-color 0.2s;">
                </div>

                <button type="submit"
                    style="width:100%; padding:14px; background:linear-gradient(135deg, #4f46e5, #7c3aed); color:white; font-weight:700; border:none; border-radius:8px; font-size:1.05rem; cursor:pointer; transition:opacity 0.2s;">
                    Sign In
                </button>
            </form>

            <?php
            // Safe Include for Social Login
            $socialPath = __DIR__ . '/../../partials/social_login.php';
            if (file_exists($socialPath)) {
                include $socialPath;
            }
            ?>

            <div style="margin-top:25px; text-align:center; font-size:0.95rem; color:#64748b;">
                Don't have an account? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" style="color:#4f46e5; font-weight:600; text-decoration:none;">Join Now</a>
            </div>

        </div>
    </div>
</div>

<style>
    /* Scoped Refactor Styles (Shared with Register) */
    .auth-wrapper {
        width: 100%;
        max-width: 450px;
        margin: 0 auto;
        padding: 0 15px;
        box-sizing: border-box;
    }

    .auth-card {
        margin-top: 0;
        position: relative;
        z-index: 10;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    /* Reset negative margin for Social/Civic layouts */
    

    .auth-card-body {
        padding: 40px;
    }

    input:focus {
        border-color: #4f46e5 !important;
    }

    button:hover {
        opacity: 0.95;
    }

    /* Desktop spacing for no-hero layout */
    @media (min-width: 601px) {
        .auth-wrapper {
            padding-top: 140px;
        }
    }

    @media (max-width: 600px) {
        .auth-wrapper {
            padding-top: 120px;
            padding-left: 10px;
            padding-right: 10px;
        }

        .auth-card-body {
            padding: 25px !important;
        }
    }

    /* ========================================
       DARK MODE FOR LOGIN PAGE
       ======================================== */

    [data-theme="dark"] .auth-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    }

    /* Sign In heading */
    [data-theme="dark"] .auth-card h2[style*="color:#0f172a"] {
        color: #f1f5f9 !important;
    }

    /* Success message (registered) */
    [data-theme="dark"] .auth-card div[style*="background:#f0fdf4"] {
        background: rgba(22, 163, 74, 0.15) !important;
        border-color: rgba(34, 197, 94, 0.3) !important;
        color: #4ade80 !important;
    }

    /* Error message */
    [data-theme="dark"] .auth-card div[style*="background:#fef2f2"] {
        background: rgba(185, 28, 28, 0.15) !important;
        border-color: rgba(239, 68, 68, 0.3) !important;
        color: #f87171 !important;
    }

    /* Biometric promo box */
    [data-theme="dark"] #biometric-promo {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15)) !important;
        border-color: rgba(99, 102, 241, 0.3) !important;
    }

    [data-theme="dark"] #biometric-promo div[style*="color:#4f46e5"] {
        color: #a5b4fc !important;
    }

    [data-theme="dark"] #biometric-promo div[style*="color:#64748b"] {
        color: #94a3b8 !important;
    }

    /* Divider text ("or") */
    [data-theme="dark"] #biometric-login-container div[style*="color:#94a3b8"] {
        color: #64748b !important;
    }

    /* Form labels */
    [data-theme="dark"] .auth-card label[style*="color:#334155"] {
        color: #e2e8f0 !important;
    }

    /* Form inputs */
    [data-theme="dark"] .auth-card input[type="email"],
    [data-theme="dark"] .auth-card input[type="password"] {
        background: rgba(15, 23, 42, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #f1f5f9 !important;
    }

    [data-theme="dark"] .auth-card input::placeholder {
        color: #64748b !important;
    }

    [data-theme="dark"] .auth-card input:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    /* Forgot password link */
    [data-theme="dark"] .auth-card a[style*="color:#4f46e5"] {
        color: #818cf8 !important;
    }

    /* Don't have account text */
    [data-theme="dark"] .auth-card div[style*="color:#64748b"] {
        color: #94a3b8 !important;
    }

    [data-theme="dark"] .auth-card div[style*="color:#64748b"] a {
        color: #818cf8 !important;
    }
</style>

<script>
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
                    if (container) container.style.display = 'block';
                    if (promo) promo.style.display = 'none';
                } else {
                    // No credentials - show promo instead
                    if (container) container.style.display = 'none';
                    if (promo) promo.style.display = 'block';
                }
            } else {
                // No session/credentials - show promo to advertise the feature
                if (promo) promo.style.display = 'block';
            }
        } catch (e) {
            // Show promo as fallback
            if (promo) promo.style.display = 'block';
        }
    } catch (e) {
        console.log('[WebAuthn] Biometric check failed:', e);
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
</script>

<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/modern/footer.php';
}
?>