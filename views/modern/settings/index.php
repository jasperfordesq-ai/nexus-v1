<?php
// ============================================
// HOLOGRAPHIC GLASSMORPHISM SETTINGS HUB
// Premium UI/UX - Modern Layout Compatible
// ============================================

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

// Session Check
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . TenantContext::getBasePath() . '/login');
    exit;
}

// Get TinyMCE API key from .env
$tinymceApiKey = 'no-api-key';
$envPath = dirname(__DIR__, 3) . '/.env';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, 'TINYMCE_API_KEY=') === 0) {
            $tinymceApiKey = trim(substr($line, 16), '"\'');
            break;
        }
    }
}

// Current Section
$section = $_GET['section'] ?? 'profile';

// Validate section - only allow known sections
$validSections = ['profile', 'account', 'organizations', 'security', 'privacy', 'data_privacy', 'notifications', 'appearance', 'federation'];
if (!in_array($section, $validSections)) {
    // Redirect to profile if invalid section
    header('Location: ' . TenantContext::getBasePath() . '/settings?section=profile');
    exit;
}

// Flash Messages
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// Hide hero section
$hideHero = true;
$pageTitle = 'Settings';

// FOUC Prevention: Hide settings container until CSS loads
// The full CSS in scattered-singles.css sets opacity:1 and proper layout
// This prevents the unstyled flash while keeping CSS loading in normal position
$additionalCSS = '<style id="settings-fouc-fix">.settings-container,.settings-ambient-bg{opacity:0;transition:opacity .15s}.settings-container.ready,.settings-ambient-bg.ready{opacity:1}</style>';

require dirname(__DIR__, 2) . '/layouts/header.php';
?>

<!-- HOLOGRAPHIC GLASSMORPHISM SETTINGS -->

<!-- Ambient Background -->
<div class="settings-ambient-bg"></div>

<div class="settings-container">
    <!-- SIDEBAR -->
    <aside class="settings-sidebar">
        <div class="settings-sidebar-header">
            <h1 class="settings-sidebar-title">
                <i class="fa-solid fa-gear icon-mr"></i>
                Settings
            </h1>
        </div>

        <nav role="navigation" aria-label="Main navigation" class="settings-nav">
            <?php
            $menu = [
                'profile' => ['icon' => 'fa-user', 'label' => 'Profile'],
                'account' => ['icon' => 'fa-id-card', 'label' => 'Account'],
                'organizations' => ['icon' => 'fa-building', 'label' => 'Organizations'],
                'security' => ['icon' => 'fa-shield-halved', 'label' => 'Security'],
                'privacy' => ['icon' => 'fa-lock', 'label' => 'Privacy'],
                'data_privacy' => ['icon' => 'fa-database', 'label' => 'Data & Privacy'],
                'notifications' => ['icon' => 'fa-bell', 'label' => 'Notifications'],
                'appearance' => ['icon' => 'fa-palette', 'label' => 'Appearance'],
                'federation' => ['icon' => 'fa-network-wired', 'label' => 'Federation'],
            ];
            ?>
            <?php foreach ($menu as $key => $item): ?>
                <?php $isActive = ($section === $key); ?>
                <a href="<?= TenantContext::getBasePath() ?>/settings?section=<?= $key ?>" class="settings-nav-item <?= $isActive ? 'active' : '' ?>">
                    <div class="settings-nav-icon">
                        <i class="fa-solid <?= $item['icon'] ?>"></i>
                    </div>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- CONTENT -->
    <main class="settings-content">
        <?php if ($success): ?>
            <div class="settings-alert settings-alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <?php
                $successMessages = [
                    'profile_updated' => 'Your profile has been updated successfully.',
                    'password_updated' => 'Your password has been changed.',
                    'privacy_updated' => 'Privacy settings saved.',
                ];
                echo $successMessages[$success] ?? 'Changes saved successfully.';
                ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="settings-alert settings-alert-error">
                <i class="fa-solid fa-exclamation-circle"></i>
                <?php
                $errorMessages = [
                    'missing_fields' => 'Please fill in all required fields.',
                    'mismatch' => 'New passwords do not match.',
                    'invalid_current' => 'Current password is incorrect.',
                ];
                echo $errorMessages[$error] ?? 'An error occurred.';
                ?>
            </div>
        <?php endif; ?>

        <?php if ($section === 'profile'): ?>
            <!-- PROFILE SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-user"></i>
                    Profile Settings
                </h2>
                <p class="settings-section-desc">Update your personal information and profile photo.</p>
            </div>

            <form action="<?= TenantContext::getBasePath() ?>/settings/profile" method="POST" enctype="multipart/form-data">
                <?= Csrf::input() ?>

                <!-- Avatar Upload -->
                <div class="settings-avatar-section">
                    <img id="avatar-preview"
                         src="<?= htmlspecialchars($user['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>" loading="lazy"
                         alt="Profile Photo"
                         class="settings-avatar-preview"
                         onerror="this.src='/assets/img/defaults/default_avatar.webp'">
                    <div class="settings-avatar-info">
                        <h4>Profile Photo</h4>
                        <p>JPG, PNG or WebP. Max 5MB.</p>
                        <label for="avatar_upload" class="settings-avatar-btn">
                            <i class="fa-solid fa-camera"></i>
                            Change Photo
                        </label>
                        <input type="file" name="avatar" id="avatar_upload" accept="image/*" class="hidden"
                               onchange="document.getElementById('avatar-preview').src = window.URL.createObjectURL(this.files[0])">
                    </div>
                </div>

                <div class="settings-grid">
                    <div class="settings-form-group">
                        <label class="settings-label">First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                               class="settings-input" placeholder="Enter your first name">
                    </div>

                    <div class="settings-form-group">
                        <label class="settings-label">Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                               class="settings-input" placeholder="Enter your last name">
                    </div>
                </div>

                <div class="settings-form-group">
                    <label class="settings-label">Display Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                           class="settings-input" placeholder="How you want to be known">
                    <p class="settings-hint">This is the name that will be displayed to other members.</p>
                </div>

                <div class="settings-form-group">
                    <label class="settings-label">Bio</label>
                    <textarea name="bio" id="bio-editor" class="settings-input settings-textarea"
                              placeholder="Tell us about yourself, your skills, interests..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <p class="settings-hint">A brief description that appears on your profile.</p>
                </div>

                <!-- TinyMCE for Bio -->
                <script src="https://cdn.tiny.cloud/1/<?= htmlspecialchars($tinymceApiKey) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
                <script>
                tinymce.init({
                    selector: '#bio-editor',
                    height: 200,
                    menubar: false,
                    statusbar: false,
                    plugins: ['link', 'lists', 'emoticons'],
                    toolbar: 'bold italic | bullist numlist | link emoticons',
                    content_style: `
                        body {
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            font-size: 15px;
                            line-height: 1.6;
                            color: #374151;
                            padding: 10px;
                        }
                    `,
                    placeholder: 'Tell us about yourself, your skills, interests...',
                    branding: false,
                    promotion: false,
                    setup: function(editor) {
                        // Sync content to textarea before form submit
                        editor.on('submit', function() {
                            editor.save();
                        });
                        // Also sync on form submit event
                        var form = document.querySelector('form');
                        if (form) {
                            form.addEventListener('submit', function() {
                                tinymce.triggerSave();
                            });
                        }
                    }
                });
                </script>

                <div class="settings-grid">
                    <div class="settings-form-group">
                        <label class="settings-label">Location</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>"
                               class="settings-input" placeholder="City, Country">
                    </div>

                    <div class="settings-form-group">
                        <label class="settings-label">Phone (Optional)</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                               class="settings-input" placeholder="+1 234 567 8900">
                    </div>
                </div>

                <!-- Profile Type Selection -->
                <div class="settings-form-group">
                    <label class="settings-label">Account Type</label>
                    <select name="profile_type" id="profile_type_select" class="settings-input" onchange="toggleOrgNameField()">
                        <option value="individual" <?= ($user['profile_type'] ?? 'individual') === 'individual' ? 'selected' : '' ?>>Individual</option>
                        <option value="organisation" <?= ($user['profile_type'] ?? '') === 'organisation' ? 'selected' : '' ?>>Organisation</option>
                    </select>
                    <p class="settings-hint">Select "Organisation" if this account represents a business, charity, or group.</p>
                </div>

                <div class="settings-form-group <?= ($user['profile_type'] ?? 'individual') !== 'organisation' ? 'hidden' : '' ?>" id="org_name_container">
                    <label class="settings-label">Organisation Name</label>
                    <input type="text" name="organization_name" value="<?= htmlspecialchars($user['organization_name'] ?? '') ?>"
                           class="settings-input" placeholder="e.g. hOUR Timebank, Community Garden Co-op">
                    <p class="settings-hint">This name will be displayed instead of your personal name on posts and listings.</p>
                </div>

                <script>
                function toggleOrgNameField() {
                    const select = document.getElementById('profile_type_select');
                    const container = document.getElementById('org_name_container');
                    container.classList.toggle('hidden', select.value !== 'organisation');
                }
                </script>

                <div class="settings-submit-section">
                    <button type="submit" class="settings-btn settings-btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                </div>
            </form>

        <?php elseif ($section === 'account'): ?>
            <!-- ACCOUNT SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-id-card"></i>
                    Account Settings
                </h2>
                <p class="settings-section-desc">Manage your account details and preferences.</p>
            </div>

            <div class="settings-form-group">
                <label class="settings-label">Email Address</label>
                <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                       class="settings-input" disabled>
                <p class="settings-hint">Contact support to update your email address.</p>
            </div>

            <div class="settings-form-group">
                <label class="settings-label">Member Since</label>
                <input type="text" value="<?= date('F j, Y', strtotime($user['created_at'])) ?>"
                       class="settings-input" disabled>
            </div>

            <div class="settings-form-group">
                <label class="settings-label">Account Type</label>
                <div class="account-type-row">
                    <input type="text" value="<?= ucfirst($user['profile_type'] ?? 'Individual') ?><?= ($user['profile_type'] ?? '') === 'organisation' && !empty($user['organization_name']) ? ' (' . htmlspecialchars($user['organization_name']) . ')' : '' ?>"
                           class="settings-input account-type-input" disabled>
                    <a href="<?= TenantContext::getBasePath() ?>/settings?section=profile#profile_type_select" class="settings-btn settings-btn-secondary account-type-btn">
                        <i class="fa-solid fa-pen"></i> Change
                    </a>
                </div>
                <p class="settings-hint">You can change your account type in the Profile section.</p>
            </div>

            <div class="settings-divider"></div>

            <div class="settings-section-header settings-danger-zone-header">
                <h3 class="settings-heading-danger">
                    <i class="fa-solid fa-triangle-exclamation icon-mr"></i>
                    Danger Zone
                </h3>
            </div>

            <div class="settings-toggle-row danger-zone-row">
                <div class="settings-toggle-info">
                    <h4 class="danger-zone-title">Delete Account</h4>
                    <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                </div>
                <button type="button" class="settings-btn settings-btn-danger" onclick="alert('Please contact support to delete your account.')">
                    <i class="fa-solid fa-trash"></i>
                    Delete
                </button>
            </div>

        <?php elseif ($section === 'security'): ?>
            <!-- SECURITY SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-shield-halved"></i>
                    Security Settings
                </h2>
                <p class="settings-section-desc">Keep your account secure with biometric login and password management.</p>
            </div>

            <!-- Biometric Authentication Section -->
            <div id="biometric-section" class="biometric-section">
                <div class="biometric-section-inner">
                    <div class="biometric-icon-wrapper">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                        </svg>
                    </div>
                    <div class="biometric-content">
                        <h3 class="biometric-title">Biometric Login</h3>
                        <p class="biometric-desc">Use your fingerprint or Face ID to sign in quickly and securely. Each device needs to be registered separately.</p>

                        <div id="biometric-status" class="biometric-status">
                            <span id="biometric-status-text" class="biometric-status-badge">
                                <i class="fas fa-circle-notch fa-spin"></i> Checking...
                            </span>
                        </div>

                        <div id="biometric-actions" class="biometric-actions">
                            <button type="button" id="btn-enroll-biometric" onclick="enrollBiometric()" class="biometric-btn">
                                <i class="fas fa-fingerprint icon-mr"></i> Set Up Biometric Login
                            </button>
                            <button type="button" id="btn-add-device" onclick="enrollBiometric()" class="biometric-btn">
                                <i class="fas fa-plus icon-mr"></i> Register This Device
                            </button>
                            <button type="button" id="btn-remove-biometric" onclick="removeBiometric()" class="biometric-btn biometric-btn-danger">
                                <i class="fas fa-trash icon-mr"></i> Remove All Devices
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="biometric-not-supported" class="biometric-not-supported">
                <i class="fas fa-info-circle icon-mr-md"></i>
                Biometric login is not available on this device or browser.
            </div>

            <script>
            // Detect if running in native Capacitor app
            function isNativeApp() {
                return window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform();
            }

            // Get the native biometric plugin
            function getNativeBiometric() {
                if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.NativeBiometric) {
                    return window.Capacitor.Plugins.NativeBiometric;
                }
                return null;
            }

            async function checkBiometricStatus() {
                const section = document.getElementById('biometric-section');
                const notSupported = document.getElementById('biometric-not-supported');
                const statusText = document.getElementById('biometric-status-text');
                const btnEnroll = document.getElementById('btn-enroll-biometric');
                const btnAddDevice = document.getElementById('btn-add-device');
                const btnRemove = document.getElementById('btn-remove-biometric');

                // Check if running in native Capacitor app
                if (isNativeApp()) {
                    const NativeBiometric = getNativeBiometric();
                    if (!NativeBiometric) {
                        notSupported.classList.add('active');
                        return;
                    }

                    try {
                        const result = await NativeBiometric.isAvailable();
                        if (!result.isAvailable) {
                            notSupported.classList.add('active');
                            return;
                        }

                        section.classList.add('active');

                        // Check if credentials are stored for this user
                        const userEmail = window.NEXUS?.userEmail || '<?= addslashes($_SESSION['user_email'] ?? '') ?>';
                        let hasCredentials = false;

                        try {
                            const creds = await NativeBiometric.getCredentials({ server: 'hour-timebank.ie' });
                            hasCredentials = creds && creds.username === userEmail;
                        } catch (e) {
                            hasCredentials = false;
                        }

                        if (hasCredentials) {
                            statusText.innerHTML = '<i class="fas fa-check-circle"></i> Enabled on this device';
                            statusText.classList.add('active');
                            btnEnroll.classList.remove('active');
                            btnAddDevice.classList.remove('active');
                            btnRemove.classList.add('active');
                        } else {
                            statusText.innerHTML = '<i class="fas fa-circle"></i> Not set up';
                            statusText.classList.remove('active');
                            btnEnroll.classList.add('active');
                            btnAddDevice.classList.remove('active');
                            btnRemove.classList.remove('active');
                        }
                    } catch (e) {
                        console.error('Native biometric check error:', e);
                        notSupported.classList.add('active');
                    }
                    return;
                }

                // Browser WebAuthn flow (original code)
                if (!window.PublicKeyCredential) {
                    notSupported.classList.add('active');
                    return;
                }

                try {
                    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                    if (!available) {
                        notSupported.classList.add('active');
                        return;
                    }

                    section.classList.add('active');

                    const response = await fetch('/api/webauthn/status', { credentials: 'include' });
                    const data = await response.json();

                    if (data.registered && data.count > 0) {
                        const deviceWord = data.count === 1 ? 'device' : 'devices';
                        statusText.innerHTML = '<i class="fas fa-check-circle"></i> Enabled on ' + data.count + ' ' + deviceWord;
                        statusText.classList.add('active');
                        btnEnroll.classList.remove('active');
                        btnAddDevice.classList.add('active');
                        btnRemove.classList.add('active');
                    } else {
                        statusText.innerHTML = '<i class="fas fa-circle"></i> Not set up';
                        statusText.classList.remove('active');
                        btnEnroll.classList.add('active');
                        btnAddDevice.classList.remove('active');
                        btnRemove.classList.remove('active');
                    }
                } catch (e) {
                    console.error('Biometric check error:', e);
                    statusText.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error checking status';
                }
            }

            async function enrollBiometric() {
                const btnEnroll = document.getElementById('btn-enroll-biometric');
                const btnAddDevice = document.getElementById('btn-add-device');
                const btn = btnEnroll.classList.contains('active') ? btnEnroll : btnAddDevice;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Setting up...';

                // Native app biometric enrollment
                if (isNativeApp()) {
                    const NativeBiometric = getNativeBiometric();
                    if (!NativeBiometric) {
                        alert('Biometric is not available on this device.');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        return;
                    }

                    try {
                        // First verify the user's identity with biometric
                        await NativeBiometric.verifyIdentity({
                            reason: 'Confirm your identity to enable biometric login',
                            title: 'Enable Biometric Login',
                            subtitle: 'Use your fingerprint or face',
                            description: 'This will allow you to sign in without entering your password'
                        });

                        // Get user credentials to store
                        const userEmail = window.NEXUS?.userEmail || '<?= addslashes($_SESSION['user_email'] ?? '') ?>';

                        // Prompt for password to store
                        const password = prompt('Enter your password to enable biometric login:');
                        if (!password) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            return;
                        }

                        // Store credentials securely
                        await NativeBiometric.setCredentials({
                            username: userEmail,
                            password: password,
                            server: 'hour-timebank.ie'
                        });

                        alert('Biometric login has been enabled! You can now sign in using your fingerprint or face.');
                        checkBiometricStatus();
                    } catch (e) {
                        console.error('Native biometric enrollment error:', e);
                        if (e.message !== 'User cancelled' && e.code !== 'BIOMETRIC_DISMISSED') {
                            alert('Failed to set up biometric login: ' + (e.message || 'Unknown error'));
                        }
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                    return;
                }

                // Browser WebAuthn enrollment (original code)
                try {
                    const challengeResponse = await fetch('/api/webauthn/register-challenge', {
                        method: 'POST',
                        credentials: 'include'
                    });

                    if (!challengeResponse.ok) throw new Error('Failed to get registration challenge');

                    const options = await challengeResponse.json();
                    options.challenge = base64UrlToBuffer(options.challenge);
                    options.user.id = base64UrlToBuffer(options.user.id);
                    if (options.excludeCredentials) {
                        options.excludeCredentials = options.excludeCredentials.map(cred => ({
                            ...cred,
                            id: base64UrlToBuffer(cred.id)
                        }));
                    }

                    const credential = await navigator.credentials.create({ publicKey: options });

                    const verifyResponse = await fetch('/api/webauthn/register-verify', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({
                            id: credential.id,
                            rawId: bufferToBase64Url(credential.rawId),
                            type: credential.type,
                            response: {
                                clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                                attestationObject: bufferToBase64Url(credential.response.attestationObject)
                            }
                        })
                    });

                    if (verifyResponse.ok) {
                        alert('This device has been registered for biometric login!');
                        checkBiometricStatus();
                    } else {
                        const error = await verifyResponse.json();
                        throw new Error(error.error || 'Registration failed');
                    }
                } catch (e) {
                    console.error('Biometric enrollment error:', e);
                    if (e.name !== 'NotAllowedError') {
                        alert('Failed to set up biometric login: ' + e.message);
                    }
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }

            async function removeBiometric() {
                if (!confirm('Are you sure you want to remove biometric login? You will need to use your password to sign in.')) {
                    return;
                }

                // Native app biometric removal
                if (isNativeApp()) {
                    const NativeBiometric = getNativeBiometric();
                    if (NativeBiometric) {
                        try {
                            await NativeBiometric.deleteCredentials({ server: 'hour-timebank.ie' });
                            alert('Biometric login has been removed.');
                            checkBiometricStatus();
                        } catch (e) {
                            console.error('Failed to remove biometric:', e);
                            alert('Failed to remove biometric login.');
                        }
                    }
                    return;
                }

                // Browser WebAuthn removal (original code)
                try {
                    const response = await fetch('/api/webauthn/remove', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({})
                    });

                    if (response.ok) {
                        alert('Biometric login has been removed from all devices.');
                        checkBiometricStatus();
                    } else {
                        throw new Error('Failed to remove biometric');
                    }
                } catch (e) {
                    alert('Error removing biometric: ' + e.message);
                }
            }

            function base64UrlToBuffer(base64url) {
                const padding = '='.repeat((4 - base64url.length % 4) % 4);
                const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = atob(base64);
                const buffer = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; i++) {
                    buffer[i] = rawData.charCodeAt(i);
                }
                return buffer.buffer;
            }

            function bufferToBase64Url(buffer) {
                const bytes = new Uint8Array(buffer);
                let binary = '';
                for (let i = 0; i < bytes.length; i++) {
                    binary += String.fromCharCode(bytes[i]);
                }
                return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            }

            document.addEventListener('DOMContentLoaded', checkBiometricStatus);
            </script>

            <div class="settings-divider"></div>

            <h4 class="settings-subheading">
                <i class="fa-solid fa-key settings-subheading-icon"></i>
                Change Password
            </h4>

            <form action="<?= TenantContext::getBasePath() ?>/settings/password" method="POST">
                <?= Csrf::input() ?>

                <div class="settings-form-group">
                    <label class="settings-label">Current Password</label>
                    <input type="password" name="current_password" required
                           class="settings-input" placeholder="Enter your current password">
                </div>

                <div class="settings-divider"></div>

                <div class="settings-form-group">
                    <label class="settings-label">New Password</label>
                    <input type="password" name="new_password" id="new_password" required
                           class="settings-input" placeholder="Enter a new password"
                           oninput="checkPasswordStrength(this.value)">
                    <div class="password-strength">
                        <div id="password-strength-bar" class="password-strength-bar"></div>
                    </div>
                    <p class="settings-hint">Use 8+ characters with a mix of letters, numbers & symbols.</p>
                </div>

                <div class="settings-form-group">
                    <label class="settings-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" required
                           class="settings-input" placeholder="Re-enter your new password">
                </div>

                <div class="settings-submit-section">
                    <button type="submit" class="settings-btn settings-btn-primary">
                        <i class="fa-solid fa-key"></i>
                        Update Password
                    </button>
                </div>
            </form>

            <script>
                function checkPasswordStrength(password) {
                    const bar = document.getElementById('password-strength-bar');
                    let strength = 0;

                    if (password.length >= 8) strength++;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                    if (password.match(/[0-9]/)) strength++;
                    if (password.match(/[^a-zA-Z0-9]/)) strength++;

                    bar.className = 'password-strength-bar';
                    if (strength >= 4) bar.classList.add('strong');
                    else if (strength >= 2) bar.classList.add('medium');
                    else if (strength >= 1) bar.classList.add('weak');
                }
            </script>

        <?php elseif ($section === 'privacy'): ?>
            <!-- PRIVACY SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-lock"></i>
                    Privacy Settings
                </h2>
                <p class="settings-section-desc">Control who can see your profile and contact you.</p>
            </div>

            <form action="<?= TenantContext::getBasePath() ?>/settings/privacy" method="POST">
                <?= Csrf::input() ?>

                <div class="settings-form-group">
                    <label class="settings-label">Profile Visibility</label>
                    <select name="privacy_profile" class="settings-input settings-select">
                        <option value="public" <?= ($user['privacy_profile'] ?? 'public') === 'public' ? 'selected' : '' ?>>Public - Anyone can view</option>
                        <option value="members" <?= ($user['privacy_profile'] ?? '') === 'members' ? 'selected' : '' ?>>Members Only - Logged-in users</option>
                        <option value="connections" <?= ($user['privacy_profile'] ?? '') === 'connections' ? 'selected' : '' ?>>Connections Only - Your connections</option>
                    </select>
                    <p class="settings-hint">Choose who can see your full profile information.</p>
                </div>

                <div class="settings-divider"></div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Appear in Search Results</h4>
                        <p>Allow other members to find you when searching.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="privacy_search" value="1"
                               <?= ($user['privacy_search'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Allow Direct Contact</h4>
                        <p>Let members send you messages directly.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="privacy_contact" value="yes"
                               <?= ($user['privacy_contact'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Show Online Status</h4>
                        <p>Display when you are currently online.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="show_online" value="1" checked>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-submit-section">
                    <button type="submit" class="settings-btn settings-btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Save Privacy Settings
                    </button>
                </div>
            </form>

        <?php elseif ($section === 'data_privacy'): ?>
            <!-- DATA & PRIVACY (GDPR) SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-database"></i>
                    Data & Privacy
                </h2>
                <p class="settings-section-desc">Manage your data, consents, and exercise your privacy rights under GDPR.</p>
            </div>

            <?php
            // Fetch user's consent records and GDPR requests
            $userConsents = [];
            $gdprRequests = [];
            $consentTypes = [];
            try {
                $gdprService = new \Nexus\Services\Enterprise\GdprService();
                $userConsents = $gdprService->getUserConsents($_SESSION['user_id']);
                $gdprRequests = $gdprService->getUserRequests($_SESSION['user_id']);
                $consentTypes = $gdprService->getActiveConsentTypes();
            } catch (\Exception $e) {
                // Service may not be available
            }

            // Index consents by type slug for easy lookup
            $consentsByType = [];
            foreach ($userConsents as $consent) {
                $consentsByType[$consent['consent_type_slug']] = $consent;
            }

            // For marketing_email, check newsletter subscription as fallback if no consent record exists
            if (!isset($consentsByType['marketing_email'])) {
                try {
                    $user = \Nexus\Models\User::findById($_SESSION['user_id']);
                    if ($user) {
                        $newsletterSub = \Nexus\Models\NewsletterSubscriber::findByEmail($user['email']);
                        if ($newsletterSub && $newsletterSub['status'] === 'active') {
                            // User is subscribed to newsletter, so default marketing_email consent to ON
                            $consentsByType['marketing_email'] = ['consent_given' => true];
                        }
                    }
                } catch (\Exception $e) {
                    // Silently fail
                }
            }
            ?>

            <!-- Your Consents -->
            <div class="consents-section">
                <h4 class="settings-subheading">
                    <i class="fa-solid fa-file-contract settings-subheading-icon"></i>
                    Your Consents
                </h4>
                <p class="text-muted text-sm mb-lg">
                    Review and manage how we use your data. You can withdraw consent at any time.
                </p>

                <?php if (empty($consentTypes)): ?>
                    <div class="consents-empty">
                        <i class="fa-solid fa-check-circle consents-empty-icon"></i>
                        <p class="consents-empty-text">No consent preferences available at this time.</p>
                    </div>
                <?php else: ?>
                    <form id="consentsForm">
                        <?php foreach ($consentTypes as $type): ?>
                            <?php
                            $hasConsent = isset($consentsByType[$type['slug']]) && $consentsByType[$type['slug']]['consent_given'];
                            $isRequired = $type['is_required'] ?? false;
                            ?>
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-info">
                                    <h4 class="flex items-center gap-sm">
                                        <?= htmlspecialchars($type['name']) ?>
                                        <?php if ($isRequired): ?>
                                            <span class="consent-required-badge">REQUIRED</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p><?= htmlspecialchars($type['description'] ?? '') ?></p>
                                    <?php if (!empty($type['current_version'])): ?>
                                        <small class="consent-version">Version <?= htmlspecialchars($type['current_version']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <label class="settings-toggle">
                                    <input type="checkbox"
                                           name="consent_<?= htmlspecialchars($type['slug']) ?>"
                                           value="1"
                                           data-consent-slug="<?= htmlspecialchars($type['slug']) ?>"
                                           <?= $hasConsent ? 'checked' : '' ?>
                                           <?= $isRequired ? 'disabled' : '' ?>
                                           onchange="updateConsent('<?= htmlspecialchars($type['slug']) ?>', this.checked)">
                                    <span class="settings-toggle-slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
            </div>

            <div class="settings-divider"></div>

            <!-- Data Rights -->
            <div class="consents-section">
                <h4 class="settings-subheading">
                    <i class="fa-solid fa-scale-balanced settings-subheading-icon"></i>
                    Your Data Rights
                </h4>
                <p class="text-muted text-sm mb-lg">
                    Under GDPR, you have the right to access, export, and request deletion of your personal data.
                </p>

                <div class="data-rights-grid">
                    <!-- Export Data -->
                    <div class="data-right-card data-right-card-export">
                        <div class="data-right-header">
                            <div class="data-right-icon data-right-icon-export">
                                <i class="fa-solid fa-download"></i>
                            </div>
                            <h5 class="data-right-title">Export My Data</h5>
                        </div>
                        <p class="data-right-desc">
                            Request a copy of all your personal data in a machine-readable format (JSON).
                        </p>
                        <button type="button" onclick="requestDataExport()" class="settings-btn settings-btn-primary data-right-btn">
                            <i class="fa-solid fa-file-export"></i>
                            Request Data Export
                        </button>
                    </div>

                    <!-- Data Portability -->
                    <div class="data-right-card data-right-card-portability">
                        <div class="data-right-header">
                            <div class="data-right-icon data-right-icon-portability">
                                <i class="fa-solid fa-right-left"></i>
                            </div>
                            <h5 class="data-right-title">Data Portability</h5>
                        </div>
                        <p class="data-right-desc">
                            Transfer your data to another service in a standard format.
                        </p>
                        <button type="button" onclick="requestDataPortability()" class="settings-btn data-right-btn data-right-btn-portability">
                            <i class="fa-solid fa-cloud-arrow-down"></i>
                            Request Portability Export
                        </button>
                    </div>

                    <!-- Delete Account -->
                    <div class="data-right-card data-right-card-delete">
                        <div class="data-right-header">
                            <div class="data-right-icon data-right-icon-delete">
                                <i class="fa-solid fa-trash-can"></i>
                            </div>
                            <h5 class="data-right-title">Delete My Account</h5>
                        </div>
                        <p class="data-right-desc">
                            Permanently delete your account and all associated data. This cannot be undone.
                        </p>
                        <button type="button" onclick="requestAccountDeletion()" class="settings-btn data-right-btn data-right-btn-delete">
                            <i class="fa-solid fa-user-slash"></i>
                            Request Account Deletion
                        </button>
                    </div>
                </div>
            </div>

            <div class="settings-divider"></div>

            <!-- My Requests -->
            <div>
                <h4 class="settings-subheading">
                    <i class="fa-solid fa-clipboard-list settings-subheading-icon"></i>
                    My Data Requests
                </h4>

                <?php if (empty($gdprRequests)): ?>
                    <div class="requests-empty">
                        <i class="fa-solid fa-inbox requests-empty-icon"></i>
                        <p class="consents-empty-text">You haven't submitted any data requests yet.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-list">
                        <?php foreach ($gdprRequests as $request): ?>
                            <?php
                            $statusIcons = [
                                'pending' => 'fa-clock',
                                'in_progress' => 'fa-spinner fa-spin',
                                'completed' => 'fa-check-circle',
                                'rejected' => 'fa-times-circle',
                            ];
                            $statusIcon = $statusIcons[$request['status']] ?? 'fa-clock';
                            $statusClass = 'status-' . ($request['status'] ?? 'pending');
                            $typeLabels = [
                                'access' => 'Data Export',
                                'erasure' => 'Account Deletion',
                                'portability' => 'Data Portability',
                                'rectification' => 'Data Correction',
                                'restriction' => 'Processing Restriction',
                                'objection' => 'Processing Objection',
                            ];
                            ?>
                            <div class="request-item">
                                <div class="request-item-left">
                                    <div class="request-status-icon <?= $statusClass ?>">
                                        <i class="fa-solid <?= $statusIcon ?>"></i>
                                    </div>
                                    <div>
                                        <div class="request-info-title">
                                            <?= $typeLabels[$request['request_type']] ?? ucfirst($request['request_type']) ?>
                                        </div>
                                        <div class="request-info-date">
                                            Submitted <?= date('M j, Y', strtotime($request['requested_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="request-item-right">
                                    <span class="request-status-badge <?= $statusClass ?>">
                                        <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                    </span>
                                    <?php if ($request['status'] === 'completed' && !empty($request['download_url'])): ?>
                                        <a href="<?= htmlspecialchars($request['download_url']) ?>" class="request-download-link">
                                            <i class="fa-solid fa-download"></i> Download
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- GDPR Request Modal -->
            <div id="gdprModal" class="gdpr-modal">
                <div class="gdpr-modal-content">
                    <div id="modalContent"></div>
                </div>
            </div>

            <script>
            const gdprBasePath = '<?= TenantContext::getBasePath() ?>';

            function showModal(content) {
                document.getElementById('modalContent').innerHTML = content;
                document.getElementById('gdprModal').classList.add('active');
            }

            function hideModal() {
                document.getElementById('gdprModal').classList.remove('active');
            }

            document.getElementById('gdprModal').addEventListener('click', function(e) {
                if (e.target === this) hideModal();
            });

            async function updateConsent(slug, given) {
                try {
                    const response = await fetch(gdprBasePath + '/settings/consent', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ slug, given })
                    });
                    const data = await response.json();
                    if (!data.success) {
                        alert(data.error || 'Failed to update consent');
                        // Revert toggle
                        const toggle = document.querySelector(`[data-consent-slug="${slug}"]`);
                        if (toggle) toggle.checked = !given;
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                }
            }

            function requestDataExport() {
                showModal(`
                    <div class="gdpr-modal-header">
                        <div class="gdpr-modal-icon gdpr-modal-icon-export">
                            <i class="fa-solid fa-file-export"></i>
                        </div>
                        <h3 class="gdpr-modal-title">Request Data Export</h3>
                        <p class="gdpr-modal-desc">We'll prepare a copy of all your personal data. This usually takes 1-3 business days.</p>
                    </div>
                    <div class="gdpr-modal-buttons">
                        <button onclick="hideModal()" class="gdpr-modal-btn gdpr-modal-btn-cancel">Cancel</button>
                        <button onclick="submitGdprRequest('access')" class="gdpr-modal-btn gdpr-modal-btn-export">Confirm Request</button>
                    </div>
                `);
            }

            function requestDataPortability() {
                showModal(`
                    <div class="gdpr-modal-header">
                        <div class="gdpr-modal-icon gdpr-modal-icon-portability">
                            <i class="fa-solid fa-right-left"></i>
                        </div>
                        <h3 class="gdpr-modal-title">Request Data Portability</h3>
                        <p class="gdpr-modal-desc">We'll prepare your data in a standard format that can be transferred to another service.</p>
                    </div>
                    <div class="gdpr-modal-buttons">
                        <button onclick="hideModal()" class="gdpr-modal-btn gdpr-modal-btn-cancel">Cancel</button>
                        <button onclick="submitGdprRequest('portability')" class="gdpr-modal-btn gdpr-modal-btn-portability">Confirm Request</button>
                    </div>
                `);
            }

            function requestAccountDeletion() {
                showModal(`
                    <div class="gdpr-modal-header">
                        <div class="gdpr-modal-icon gdpr-modal-icon-delete">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="gdpr-modal-title">Delete Your Account?</h3>
                        <p class="gdpr-modal-desc">This action is <strong>permanent and irreversible</strong>. All your data, including profile, credits, transactions, and history will be permanently deleted.</p>
                    </div>
                    <div class="gdpr-modal-warning">
                        <p>
                            <i class="fa-solid fa-info-circle"></i>
                            Type <strong>DELETE</strong> to confirm
                        </p>
                    </div>
                    <input type="text" id="deleteConfirmation" placeholder="Type DELETE to confirm" class="gdpr-modal-input">
                    <div class="gdpr-modal-buttons">
                        <button onclick="hideModal()" class="gdpr-modal-btn gdpr-modal-btn-cancel">Cancel</button>
                        <button onclick="confirmDeletion()" class="gdpr-modal-btn gdpr-modal-btn-delete">Delete Account</button>
                    </div>
                `);
            }

            function confirmDeletion() {
                const input = document.getElementById('deleteConfirmation');
                if (input && input.value === 'DELETE') {
                    submitGdprRequest('erasure');
                } else {
                    alert('Please type DELETE to confirm account deletion.');
                }
            }

            async function submitGdprRequest(type) {
                try {
                    const response = await fetch(gdprBasePath + '/settings/gdpr-request', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_type: type })
                    });
                    const data = await response.json();

                    if (data.success) {
                        showModal(`
                            <div class="gdpr-modal-header">
                                <div class="gdpr-modal-icon gdpr-modal-icon-success">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <h3 class="gdpr-modal-title">Request Submitted</h3>
                                <p class="gdpr-modal-desc">Your request has been submitted successfully. We'll process it within 30 days as required by GDPR.</p>
                            </div>
                            <div class="gdpr-modal-buttons gdpr-modal-buttons-center">
                                <button onclick="location.reload()" class="gdpr-modal-btn gdpr-modal-btn-success">OK</button>
                            </div>
                        `);
                    } else {
                        alert(data.error || 'Failed to submit request. Please try again.');
                        hideModal();
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                    hideModal();
                }
            }
            </script>

        <?php elseif ($section === 'notifications'): ?>
            <!-- NOTIFICATION SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-bell"></i>
                    Notification Settings
                </h2>
                <p class="settings-section-desc">Choose how you want to be notified about activity.</p>
            </div>

            <?php
            // Get current notification preferences (with fallback defaults)
            try {
                $notifPrefs = \Nexus\Models\User::getNotificationPreferences($_SESSION['user_id']);
            } catch (\Exception $e) {
                $notifPrefs = [
                    'email_messages' => 1,
                    'email_connections' => 1,
                    'email_transactions' => 1,
                    'email_reviews' => 1,
                    'push_enabled' => 1
                ];
            }
            ?>

            <form method="POST" action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/settings/notifications">
                <?= Csrf::input() ?>

                <h4 class="settings-subheading">
                    <i class="fa-solid fa-envelope settings-subheading-icon"></i>
                    Email Notifications
                </h4>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>New Messages</h4>
                        <p>Get notified when you receive a new message.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_messages" value="1" <?= ($notifPrefs['email_messages'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Connection Requests</h4>
                        <p>When someone wants to connect with you.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_connections" value="1" <?= ($notifPrefs['email_connections'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Transaction Updates</h4>
                        <p>Credit transfers and exchange confirmations.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_transactions" value="1" <?= ($notifPrefs['email_transactions'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>New Reviews</h4>
                        <p>When someone leaves you a review.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_reviews" value="1" <?= ($notifPrefs['email_reviews'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-divider"></div>

                <h4 class="settings-subheading">
                    <i class="fa-solid fa-building settings-subheading-icon"></i>
                    Organization Notifications
                </h4>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Wallet Payments</h4>
                        <p>When you receive credits from an organization wallet.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_org_payments" value="1" <?= ($notifPrefs['email_org_payments'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Transfer Requests</h4>
                        <p>Updates on your transfer request status (approved/rejected).</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_org_transfers" value="1" <?= ($notifPrefs['email_org_transfers'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Membership Updates</h4>
                        <p>When you're added to an org, role changes, or removal.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_org_membership" value="1" <?= ($notifPrefs['email_org_membership'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Admin Alerts (Admins Only)</h4>
                        <p>New transfer requests, deposits, and membership requests.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="email_org_admin" value="1" <?= ($notifPrefs['email_org_admin'] ?? 1) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-divider"></div>

                <h4 class="settings-subheading">
                    <i class="fa-solid fa-mobile-screen settings-subheading-icon"></i>
                    Push Notifications
                </h4>

                <div class="settings-toggle-row">
                    <div class="settings-toggle-info">
                        <h4>Enable Push Notifications</h4>
                        <p>Receive browser notifications for important updates.</p>
                    </div>
                    <label class="settings-toggle">
                        <input type="checkbox" name="push_enabled" value="1" <?= ($notifPrefs['push_enabled'] ?? 0) ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-submit-section">
                    <button type="submit" class="settings-btn settings-btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Save Notification Settings
                    </button>
                </div>
            </form>

        <?php elseif ($section === 'appearance'): ?>
            <!-- APPEARANCE SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-palette"></i>
                    Appearance Settings
                </h2>
                <p class="settings-section-desc">Customize how the platform looks for you.</p>
            </div>

            <h4 class="settings-subheading">
                <i class="fa-solid fa-circle-half-stroke settings-subheading-icon"></i>
                Theme
            </h4>

            <div class="theme-grid">
                <label class="theme-label">
                    <input type="radio" name="theme" value="light" class="theme-radio"
                           <?= ($_COOKIE['theme'] ?? 'light') === 'light' ? 'checked' : '' ?>
                           onchange="setTheme('light')">
                    <div class="theme-option theme-option-light" data-theme="light">
                        <i class="fa-solid fa-sun theme-icon theme-icon-light"></i>
                        <span class="theme-label-text theme-label-text-light">Light</span>
                    </div>
                </label>

                <label class="theme-label">
                    <input type="radio" name="theme" value="dark" class="theme-radio"
                           <?= ($_COOKIE['theme'] ?? '') === 'dark' ? 'checked' : '' ?>
                           onchange="setTheme('dark')">
                    <div class="theme-option theme-option-dark" data-theme="dark">
                        <i class="fa-solid fa-moon theme-icon theme-icon-dark"></i>
                        <span class="theme-label-text theme-label-text-dark">Dark</span>
                    </div>
                </label>

                <label class="theme-label">
                    <input type="radio" name="theme" value="auto" class="theme-radio"
                           <?= ($_COOKIE['theme'] ?? '') === 'auto' ? 'checked' : '' ?>
                           onchange="setTheme('auto')">
                    <div class="theme-option theme-option-auto" data-theme="auto">
                        <i class="fa-solid fa-circle-half-stroke theme-icon theme-icon-auto"></i>
                        <span class="theme-label-text theme-label-text-auto">System</span>
                    </div>
                </label>
            </div>

            <script>
                // Highlight active theme
                document.querySelectorAll('.theme-option').forEach(option => {
                    const input = option.closest('label').querySelector('input');
                    if (input.checked) {
                        option.style.borderColor = 'rgb(99, 102, 241)';
                        option.style.boxShadow = '0 4px 20px rgba(99, 102, 241, 0.3)';
                    }
                });

                function setTheme(theme) {
                    document.cookie = `theme=${theme};path=/;max-age=31536000`;
                    if (theme === 'dark') {
                        document.documentElement.setAttribute('data-theme', 'dark');
                    } else if (theme === 'light') {
                        document.documentElement.setAttribute('data-theme', 'light');
                    } else {
                        // Auto - check system preference
                        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                            document.documentElement.setAttribute('data-theme', 'dark');
                        } else {
                            document.documentElement.setAttribute('data-theme', 'light');
                        }
                    }

                    // Update visual selection
                    document.querySelectorAll('.theme-option').forEach(option => {
                        option.style.borderColor = 'transparent';
                        option.style.boxShadow = 'none';
                    });
                    const selected = document.querySelector(`input[value="${theme}"]`).closest('label').querySelector('.theme-option');
                    selected.style.borderColor = 'rgb(99, 102, 241)';
                    selected.style.boxShadow = '0 4px 20px rgba(99, 102, 241, 0.3)';
                }
            </script>

            <div class="settings-divider"></div>

            <h4 class="settings-subheading">
                <i class="fa-solid fa-text-height settings-subheading-icon"></i>
                Display
            </h4>

            <div class="settings-toggle-row">
                <div class="settings-toggle-info">
                    <h4>Compact Mode</h4>
                    <p>Reduce spacing for a denser layout.</p>
                </div>
                <label class="settings-toggle">
                    <input type="checkbox" name="compact_mode" value="1">
                    <span class="settings-toggle-slider"></span>
                </label>
            </div>

            <div class="settings-toggle-row">
                <div class="settings-toggle-info">
                    <h4>Reduce Animations</h4>
                    <p>Minimize motion effects throughout the interface.</p>
                </div>
                <label class="settings-toggle">
                    <input type="checkbox" name="reduce_motion" value="1">
                    <span class="settings-toggle-slider"></span>
                </label>
            </div>

            <div class="settings-divider"></div>

            <h4 class="settings-subheading">
                <i class="fa-solid fa-robot settings-subheading-icon"></i>
                AI Assistant
            </h4>

            <div class="settings-toggle-row">
                <div class="settings-toggle-info">
                    <h4>Show AI Assistant Button</h4>
                    <p>Display a floating AI assistant button on all pages for quick access.</p>
                </div>
                <label class="settings-toggle">
                    <input type="checkbox" name="ai_widget_enabled" value="1"
                           <?= ($_COOKIE['ai_widget_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                           onchange="setAiWidgetEnabled(this.checked)">
                    <span class="settings-toggle-slider"></span>
                </label>
            </div>

            <div class="settings-toggle-row <?= ($_COOKIE['ai_widget_enabled'] ?? '0') !== '1' ? 'ai-setting-disabled' : '' ?>" id="ai-pulse-setting">
                <div class="settings-toggle-info">
                    <h4>AI Button Pulse Animation</h4>
                    <p>Show a pulsing animation on the AI assistant button to draw attention.</p>
                </div>
                <label class="settings-toggle">
                    <input type="checkbox" name="ai_pulse_enabled" value="1"
                           <?= ($_COOKIE['ai_pulse_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                           onchange="setAiPulse(this.checked)">
                    <span class="settings-toggle-slider"></span>
                </label>
            </div>

            <script>
                function setAiWidgetEnabled(enabled) {
                    document.cookie = `ai_widget_enabled=${enabled ? '1' : '0'};path=/;max-age=31536000`;
                    // Show/hide the widget immediately if it exists, or reload to apply
                    const widget = document.getElementById('ai-chat-widget');
                    if (widget) {
                        widget.style.display = enabled ? '' : 'none';
                    }
                    // Enable/disable the pulse setting
                    const pulseSetting = document.getElementById('ai-pulse-setting');
                    if (pulseSetting) {
                        pulseSetting.style.opacity = enabled ? '' : '0.5';
                        pulseSetting.style.pointerEvents = enabled ? '' : 'none';
                    }
                    // If enabling and widget doesn't exist, need to reload
                    if (enabled && !widget) {
                        window.location.reload();
                    }
                }

                function setAiPulse(enabled) {
                    document.cookie = `ai_pulse_enabled=${enabled ? '1' : '0'};path=/;max-age=31536000`;
                    // Update the widget immediately if it exists
                    const widget = document.getElementById('ai-chat-widget');
                    if (widget) {
                        if (enabled) {
                            widget.classList.add('pulse-enabled');
                        } else {
                            widget.classList.remove('pulse-enabled');
                        }
                    }
                }
            </script>

        <?php elseif ($section === 'organizations'): ?>
            <!-- ORGANIZATIONS SETTINGS -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-building"></i>
                    My Organizations
                </h2>
                <p class="settings-section-desc">View and manage your organization memberships.</p>
            </div>

            <?php
            // Fetch user's organizations
            $userOrganizations = [];
            try {
                $userOrganizations = \Nexus\Models\OrgMember::getUserOrganizations($_SESSION['user_id']);
            } catch (\Exception $e) {
                // Silently fail if org tables don't exist yet
            }
            ?>

            <?php if (empty($userOrganizations)): ?>
                <div class="orgs-empty">
                    <i class="fa-solid fa-building-circle-xmark orgs-empty-icon"></i>
                    <h3 class="orgs-empty-title">No Organizations Yet</h3>
                    <p class="orgs-empty-desc">You are not a member of any organizations.</p>
                    <a href="<?= TenantContext::getBasePath() ?>/volunteering/organizations" class="settings-btn settings-btn-primary inline-flex">
                        <i class="fa-solid fa-search"></i>
                        Browse Organizations
                    </a>
                </div>
            <?php else: ?>
                <div class="orgs-list">
                    <?php foreach ($userOrganizations as $org): ?>
                        <?php
                        $roleClass = $org['member_role'] === 'owner' ? 'owner' : ($org['member_role'] === 'admin' ? 'admin' : 'member');
                        ?>
                        <div class="settings-toggle-row org-card">
                            <div class="org-card-header">
                                <!-- Org Avatar/Icon -->
                                <div class="org-avatar org-avatar-<?= $roleClass ?>">
                                    <?php if (!empty($org['logo_url'])): ?>
                                        <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="" class="org-avatar-img">
                                    <?php else: ?>
                                        <i class="fa-solid <?php
                                            if ($org['member_role'] === 'owner') echo 'fa-crown org-icon-owner';
                                            elseif ($org['member_role'] === 'admin') echo 'fa-shield org-icon-admin';
                                            else echo 'fa-building org-icon-member';
                                        ?>"></i>
                                    <?php endif; ?>
                                </div>

                                <!-- Org Info -->
                                <div class="org-info">
                                    <h4 class="org-name">
                                        <?= htmlspecialchars($org['name']) ?>
                                        <span class="org-role-badge org-role-badge-<?= $roleClass ?>">
                                            <i class="fa-solid <?php
                                                if ($org['member_role'] === 'owner') echo 'fa-crown';
                                                elseif ($org['member_role'] === 'admin') echo 'fa-shield';
                                                else echo 'fa-user';
                                            ?>"></i>
                                            <?= ucfirst($org['member_role']) ?>
                                        </span>
                                    </h4>
                                    <p class="org-desc">
                                        <?= htmlspecialchars($org['description'] ?? 'No description') ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="org-actions">
                                <a href="<?= TenantContext::getBasePath() ?>/volunteering/organization/<?= $org['id'] ?>" class="org-action-btn org-action-btn-view">
                                    <i class="fa-solid fa-eye"></i> View Organization
                                </a>
                                <?php if (in_array($org['member_role'], ['owner', 'admin'])): ?>
                                    <a href="<?= TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet" class="org-action-btn org-action-btn-wallet">
                                        <i class="fa-solid fa-wallet"></i> Wallet
                                    </a>
                                    <a href="<?= TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members" class="org-action-btn org-action-btn-members">
                                        <i class="fa-solid fa-users"></i> Members
                                    </a>
                                <?php endif; ?>
                                <?php if ($org['member_role'] === 'owner'): ?>
                                    <a href="<?= TenantContext::getBasePath() ?>/volunteering/organization/<?= $org['id'] ?>/edit" class="org-action-btn org-action-btn-edit">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="settings-divider"></div>

                <div class="orgs-browse-link">
                    <a href="<?= TenantContext::getBasePath() ?>/volunteering/organizations">
                        <i class="fa-solid fa-search icon-mr-sm"></i>
                        Browse More Organizations
                    </a>
                </div>
            <?php endif; ?>

        <?php elseif ($section === 'federation'): ?>
            <!-- FEDERATION SETTINGS -->
            <?php
            $federationAvailable = \Nexus\Services\FederationUserService::isFederationAvailableForUser($_SESSION['user_id']);
            $fedSettings = \Nexus\Services\FederationUserService::getUserSettings($_SESSION['user_id']);
            ?>

            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-network-wired"></i>
                    Federation Settings
                </h2>
                <p class="settings-section-desc">Control how you appear to users from other timebanks</p>
            </div>

            <?php if (!$federationAvailable): ?>
                <!-- Federation Not Available -->
                <div class="federation-unavailable">
                    <div class="federation-unavailable-icon">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                    <h3 class="federation-unavailable-title">Federation Not Available</h3>
                    <p class="federation-unavailable-desc">
                        Federation features are not currently enabled for your timebank.
                        Contact your timebank administrator for more information.
                    </p>
                </div>
            <?php else: ?>
                <!-- Federation Available -->
                <form id="federationSettingsForm" method="POST" action="<?= TenantContext::getBasePath() ?>/settings/federation/update">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

                    <!-- Master Opt-In -->
                    <div class="federation-optin-card">
                        <div class="federation-optin-inner">
                            <div>
                                <h3 class="federation-optin-title">
                                    <i class="fa-solid fa-power-off text-primary icon-mr"></i>
                                    Enable Federation
                                </h3>
                                <p class="federation-optin-desc">
                                    Allow your profile to be visible to partner timebanks and enable cross-timebank features.
                                </p>
                            </div>
                            <label class="settings-toggle">
                                <input type="checkbox" name="federation_optin" id="federation_optin" value="1"
                                    <?= $fedSettings['federation_optin'] ? 'checked' : '' ?>
                                    onchange="toggleFederationSections(this.checked)">
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Federation Options (shown when opted in) -->
                    <div id="federationOptions" class="<?= !$fedSettings['federation_optin'] ? 'hidden' : '' ?>">

                        <!-- Visibility Settings -->
                        <div class="federation-options-section">
                            <h4 class="settings-subheading">
                                <i class="fa-solid fa-eye settings-subheading-icon"></i>
                                Visibility Settings
                            </h4>

                            <div class="federation-options-list">
                                <label class="settings-checkbox-card">
                                    <input type="checkbox" name="profile_visible_federated" value="1"
                                        <?= $fedSettings['profile_visible_federated'] ? 'checked' : '' ?>>
                                    <div class="settings-checkbox-content">
                                        <span class="settings-checkbox-title">Profile Visible</span>
                                        <span class="settings-checkbox-desc">Allow users from partner timebanks to view your profile</span>
                                    </div>
                                </label>

                                <label class="settings-checkbox-card">
                                    <input type="checkbox" name="appear_in_federated_search" value="1"
                                        <?= $fedSettings['appear_in_federated_search'] ? 'checked' : '' ?>>
                                    <div class="settings-checkbox-content">
                                        <span class="settings-checkbox-title">Appear in Directory</span>
                                        <span class="settings-checkbox-desc">Show up in federated member searches across partner timebanks</span>
                                    </div>
                                </label>

                                <label class="settings-checkbox-card">
                                    <input type="checkbox" name="show_skills_federated" value="1"
                                        <?= $fedSettings['show_skills_federated'] ? 'checked' : '' ?>>
                                    <div class="settings-checkbox-content">
                                        <span class="settings-checkbox-title">Show Skills</span>
                                        <span class="settings-checkbox-desc">Display your skills to federated users</span>
                                    </div>
                                </label>

                                <label class="settings-checkbox-card">
                                    <input type="checkbox" name="show_location_federated" value="1"
                                        <?= $fedSettings['show_location_federated'] ? 'checked' : '' ?>>
                                    <div class="settings-checkbox-content">
                                        <span class="settings-checkbox-title">Show Location</span>
                                        <span class="settings-checkbox-desc">Display your general location to federated users</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Interaction Settings -->
                        <div class="federation-options-section">
                            <h4 class="settings-subheading">
                                <i class="fa-solid fa-comments settings-subheading-icon"></i>
                                Interaction Settings
                            </h4>

                            <div class="federation-options-list">
                                <label class="settings-checkbox-card">
                                    <input type="checkbox" name="messaging_enabled_federated" value="1"
                                        <?= $fedSettings['messaging_enabled_federated'] ? 'checked' : '' ?>>
                                    <div class="settings-checkbox-content">
                                        <span class="settings-checkbox-title">Accept Messages</span>
                                        <span class="settings-checkbox-desc">Allow users from partner timebanks to send you messages</span>
                                    </div>
                                </label>

                                <label class="settings-checkbox-card">
                                    <input type="checkbox" name="transactions_enabled_federated" value="1"
                                        <?= $fedSettings['transactions_enabled_federated'] ? 'checked' : '' ?>>
                                    <div class="settings-checkbox-content">
                                        <span class="settings-checkbox-title">Accept Transactions</span>
                                        <span class="settings-checkbox-desc">Allow time credit exchanges with users from partner timebanks</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Service Reach -->
                        <div class="federation-options-section">
                            <h4 class="settings-subheading">
                                <i class="fa-solid fa-location-dot settings-subheading-icon"></i>
                                Service Reach
                            </h4>
                            <p class="text-muted text-sm mb-md">
                                How far can you provide your services?
                            </p>

                            <div class="federation-options-list">
                                <label class="settings-radio-card">
                                    <input type="radio" name="service_reach" value="local_only"
                                        <?= $fedSettings['service_reach'] === 'local_only' ? 'checked' : '' ?>>
                                    <div class="settings-radio-content">
                                        <span class="settings-radio-title">
                                            <i class="fa-solid fa-home icon-mr"></i>
                                            Local Only
                                        </span>
                                        <span class="settings-radio-desc">I can only provide services in my local area</span>
                                    </div>
                                </label>

                                <label class="settings-radio-card">
                                    <input type="radio" name="service_reach" value="remote_ok"
                                        <?= $fedSettings['service_reach'] === 'remote_ok' ? 'checked' : '' ?>>
                                    <div class="settings-radio-content">
                                        <span class="settings-radio-title">
                                            <i class="fa-solid fa-laptop icon-mr"></i>
                                            Remote OK
                                        </span>
                                        <span class="settings-radio-desc">I can provide some services remotely (online/phone)</span>
                                    </div>
                                </label>

                                <label class="settings-radio-card">
                                    <input type="radio" name="service_reach" value="travel_ok"
                                        <?= $fedSettings['service_reach'] === 'travel_ok' ? 'checked' : '' ?>
                                        onchange="toggleTravelRadius(this.checked)">
                                    <div class="settings-radio-content">
                                        <span class="settings-radio-title">
                                            <i class="fa-solid fa-car icon-mr"></i>
                                            Will Travel
                                        </span>
                                        <span class="settings-radio-desc">I can travel to provide services</span>
                                    </div>
                                </label>

                                <div id="travelRadiusField" class="travel-radius-field <?= $fedSettings['service_reach'] !== 'travel_ok' ? 'hidden' : '' ?>">
                                    <label class="settings-label">Maximum Travel Distance (km)</label>
                                    <input type="number" name="travel_radius_km" class="settings-input settings-input-narrow"
                                        value="<?= htmlspecialchars($fedSettings['travel_radius_km'] ?? '') ?>"
                                        placeholder="e.g. 50" min="1" max="500">
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="settings-divider"></div>
                        <div class="federation-submit-section">
                            <button type="submit" class="settings-btn settings-btn-primary">
                                <i class="fa-solid fa-check"></i>
                                Save Federation Settings
                            </button>
                        </div>
                    </div>

                    <!-- Quick Opt-Out (when opted in) -->
                    <?php if ($fedSettings['federation_optin']): ?>
                    <div class="federation-optout-card">
                        <div class="federation-optout-inner">
                            <div>
                                <h4 class="federation-optout-title">
                                    <i class="fa-solid fa-power-off icon-mr"></i>
                                    Disable Federation
                                </h4>
                                <p class="federation-optout-desc">
                                    Immediately hide your profile from all partner timebanks
                                </p>
                            </div>
                            <button type="button" onclick="quickOptOut()" class="settings-btn federation-optout-btn">
                                <i class="fa-solid fa-eye-slash"></i>
                                Opt Out
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>

                <script>
                function toggleFederationSections(enabled) {
                    document.getElementById('federationOptions').classList.toggle('hidden', !enabled);
                }

                function toggleTravelRadius(show) {
                    document.getElementById('travelRadiusField').classList.toggle('hidden', !show);
                }

                // Handle radio button changes for travel
                document.querySelectorAll('input[name="service_reach"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        toggleTravelRadius(this.value === 'travel_ok');
                    });
                });

                function quickOptOut() {
                    if (!confirm('This will immediately hide your profile from all partner timebanks. Continue?')) return;

                    fetch('<?= TenantContext::getBasePath() ?>/settings/federation/opt-out', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '<?= Csrf::token() ?>'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.error || 'Failed to opt out');
                        }
                    });
                }
                </script>

            <?php endif; ?>

        <?php else: ?>
            <!-- FALLBACK -->
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="fa-solid fa-gear"></i>
                    <?= ucfirst($section) ?>
                </h2>
                <p class="settings-section-desc">This section is being developed.</p>
            </div>

            <div class="fallback-empty">
                <i class="fa-solid fa-tools fallback-icon"></i>
                <h3 class="fallback-title">Coming Soon</h3>
                <p class="fallback-desc">This feature is currently under development.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
// Reveal settings after CSS has loaded (FOUC prevention)
// CSS is render-blocking so by the time this runs, styles are applied
document.querySelectorAll('.settings-container,.settings-ambient-bg').forEach(function(el) {
    el.classList.add('ready');
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
