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

// Flash Messages
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// Hide hero section
$hideHero = true;
$pageTitle = 'Settings';

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
                <i class="fa-solid fa-gear" style="margin-right: 8px;"></i>
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
                <a href="?section=<?= $key ?>" class="settings-nav-item <?= $isActive ? 'active' : '' ?>">
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
                        <input type="file" name="avatar" id="avatar_upload" accept="image/*" style="display: none;"
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

                <div class="settings-form-group" id="org_name_container" style="display: <?= ($user['profile_type'] ?? 'individual') === 'organisation' ? 'block' : 'none' ?>;">
                    <label class="settings-label">Organisation Name</label>
                    <input type="text" name="organization_name" value="<?= htmlspecialchars($user['organization_name'] ?? '') ?>"
                           class="settings-input" placeholder="e.g. hOUR Timebank, Community Garden Co-op">
                    <p class="settings-hint">This name will be displayed instead of your personal name on posts and listings.</p>
                </div>

                <script>
                function toggleOrgNameField() {
                    const select = document.getElementById('profile_type_select');
                    const container = document.getElementById('org_name_container');
                    container.style.display = select.value === 'organisation' ? 'block' : 'none';
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
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="text" value="<?= ucfirst($user['profile_type'] ?? 'Individual') ?><?= ($user['profile_type'] ?? '') === 'organisation' && !empty($user['organization_name']) ? ' (' . htmlspecialchars($user['organization_name']) . ')' : '' ?>"
                           class="settings-input" disabled style="flex: 1;">
                    <a href="?section=profile#profile_type_select" class="settings-btn settings-btn-secondary" style="white-space: nowrap;">
                        <i class="fa-solid fa-pen"></i> Change
                    </a>
                </div>
                <p class="settings-hint">You can change your account type in the Profile section.</p>
            </div>

            <div class="settings-divider"></div>

            <div class="settings-section-header" style="margin-bottom: 20px;">
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: rgb(var(--settings-danger));">
                    <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
                    Danger Zone
                </h3>
            </div>

            <div class="settings-toggle-row" style="background: rgba(var(--settings-danger), 0.05); border: 1px solid rgba(var(--settings-danger), 0.2);">
                <div class="settings-toggle-info">
                    <h4 style="color: rgb(var(--settings-danger));">Delete Account</h4>
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
            <div id="biometric-section" style="display: none; margin-bottom: 32px; padding: 24px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.08)); border-radius: 20px; border: 1px solid rgba(99, 102, 241, 0.2);">
                <div style="display: flex; align-items: flex-start; gap: 20px;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, rgb(99, 102, 241), rgb(139, 92, 246)); border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                        </svg>
                    </div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 6px 0; font-size: 1.2rem; font-weight: 800; color: rgb(var(--settings-text));">Biometric Login</h3>
                        <p style="margin: 0 0 16px 0; font-size: 0.95rem; color: rgb(var(--settings-muted)); line-height: 1.5;">Use your fingerprint or Face ID to sign in quickly and securely. Each device needs to be registered separately.</p>

                        <div id="biometric-status" style="margin-bottom: 16px;">
                            <span id="biometric-status-text" style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; background: rgba(var(--settings-muted), 0.15); color: rgb(var(--settings-muted));">
                                <i class="fas fa-circle-notch fa-spin"></i> Checking...
                            </span>
                        </div>

                        <div id="biometric-actions" style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <button type="button" id="btn-enroll-biometric" onclick="enrollBiometric()" style="display: none; padding: 12px 24px; background: linear-gradient(135deg, rgb(99, 102, 241), rgb(139, 92, 246)); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 0.95rem; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);">
                                <i class="fas fa-fingerprint" style="margin-right: 8px;"></i> Set Up Biometric Login
                            </button>
                            <button type="button" id="btn-add-device" onclick="enrollBiometric()" style="display: none; padding: 12px 24px; background: linear-gradient(135deg, rgb(99, 102, 241), rgb(139, 92, 246)); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 0.95rem; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);">
                                <i class="fas fa-plus" style="margin-right: 8px;"></i> Register This Device
                            </button>
                            <button type="button" id="btn-remove-biometric" onclick="removeBiometric()" style="display: none; padding: 12px 24px; background: linear-gradient(135deg, rgb(239, 68, 68), #dc2626); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 0.95rem; transition: all 0.3s ease;">
                                <i class="fas fa-trash" style="margin-right: 8px;"></i> Remove All Devices
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="biometric-not-supported" style="display: none; margin-bottom: 32px; padding: 20px 24px; background: rgba(var(--settings-muted), 0.1); border-radius: 16px; color: rgb(var(--settings-muted)); font-size: 0.95rem;">
                <i class="fas fa-info-circle" style="margin-right: 10px;"></i>
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
                        notSupported.style.display = 'block';
                        return;
                    }

                    try {
                        const result = await NativeBiometric.isAvailable();
                        if (!result.isAvailable) {
                            notSupported.style.display = 'block';
                            return;
                        }

                        section.style.display = 'block';

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
                            statusText.innerHTML = '<i class="fas fa-check-circle" style="color: rgb(16, 185, 129);"></i> Enabled on this device';
                            statusText.style.background = 'rgba(16, 185, 129, 0.15)';
                            statusText.style.color = 'rgb(16, 185, 129)';
                            btnEnroll.style.display = 'none';
                            btnAddDevice.style.display = 'none';
                            btnRemove.style.display = 'inline-flex';
                        } else {
                            statusText.innerHTML = '<i class="fas fa-circle" style="opacity: 0.5;"></i> Not set up';
                            btnEnroll.style.display = 'inline-flex';
                            btnAddDevice.style.display = 'none';
                            btnRemove.style.display = 'none';
                        }
                    } catch (e) {
                        console.error('Native biometric check error:', e);
                        notSupported.style.display = 'block';
                    }
                    return;
                }

                // Browser WebAuthn flow (original code)
                if (!window.PublicKeyCredential) {
                    notSupported.style.display = 'block';
                    return;
                }

                try {
                    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                    if (!available) {
                        notSupported.style.display = 'block';
                        return;
                    }

                    section.style.display = 'block';

                    const response = await fetch('/api/webauthn/status', { credentials: 'include' });
                    const data = await response.json();

                    if (data.registered && data.count > 0) {
                        const deviceWord = data.count === 1 ? 'device' : 'devices';
                        statusText.innerHTML = '<i class="fas fa-check-circle" style="color: rgb(16, 185, 129);"></i> Enabled on ' + data.count + ' ' + deviceWord;
                        statusText.style.background = 'rgba(16, 185, 129, 0.15)';
                        statusText.style.color = 'rgb(16, 185, 129)';
                        btnEnroll.style.display = 'none';
                        btnAddDevice.style.display = 'inline-flex';
                        btnRemove.style.display = 'inline-flex';
                    } else {
                        statusText.innerHTML = '<i class="fas fa-circle" style="opacity: 0.5;"></i> Not set up';
                        btnEnroll.style.display = 'inline-flex';
                        btnAddDevice.style.display = 'none';
                        btnRemove.style.display = 'none';
                    }
                } catch (e) {
                    console.error('Biometric check error:', e);
                    statusText.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error checking status';
                }
            }

            async function enrollBiometric() {
                const btnEnroll = document.getElementById('btn-enroll-biometric');
                const btnAddDevice = document.getElementById('btn-add-device');
                const btn = btnEnroll.style.display !== 'none' ? btnEnroll : btnAddDevice;
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

            <h4 style="margin: 0 0 20px 0; font-weight: 700; color: rgb(var(--settings-text)); font-size: 1.1rem;">
                <i class="fa-solid fa-key" style="margin-right: 10px; color: rgb(var(--settings-primary));"></i>
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
            <div style="margin-bottom: 32px;">
                <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text)); display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-file-contract" style="color: rgb(var(--settings-primary));"></i>
                    Your Consents
                </h4>
                <p style="color: rgb(var(--settings-muted)); font-size: 0.9rem; margin-bottom: 20px;">
                    Review and manage how we use your data. You can withdraw consent at any time.
                </p>

                <?php if (empty($consentTypes)): ?>
                    <div style="padding: 24px; background: rgba(var(--settings-surface), 0.4); border-radius: 16px; text-align: center;">
                        <i class="fa-solid fa-check-circle" style="font-size: 2rem; color: #10b981; margin-bottom: 12px; display: block;"></i>
                        <p style="color: rgb(var(--settings-muted)); margin: 0;">No consent preferences available at this time.</p>
                    </div>
                <?php else: ?>
                    <form id="consentsForm">
                        <?php foreach ($consentTypes as $type): ?>
                            <?php
                            $hasConsent = isset($consentsByType[$type['slug']]) && $consentsByType[$type['slug']]['consent_given'];
                            $isRequired = $type['is_required'] ?? false;
                            ?>
                            <div class="settings-toggle-row" style="position: relative;">
                                <div class="settings-toggle-info">
                                    <h4 style="display: flex; align-items: center; gap: 8px;">
                                        <?= htmlspecialchars($type['name']) ?>
                                        <?php if ($isRequired): ?>
                                            <span style="padding: 2px 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">REQUIRED</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p><?= htmlspecialchars($type['description'] ?? '') ?></p>
                                    <?php if (!empty($type['current_version'])): ?>
                                        <small style="color: rgb(var(--settings-muted)); font-size: 0.75rem;">Version <?= htmlspecialchars($type['current_version']) ?></small>
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
            <div style="margin-bottom: 32px;">
                <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text)); display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-scale-balanced" style="color: rgb(var(--settings-primary));"></i>
                    Your Data Rights
                </h4>
                <p style="color: rgb(var(--settings-muted)); font-size: 0.9rem; margin-bottom: 20px;">
                    Under GDPR, you have the right to access, export, and request deletion of your personal data.
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <!-- Export Data -->
                    <div style="padding: 24px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.15)); display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-download" style="color: #6366f1; font-size: 1.1rem;"></i>
                            </div>
                            <h5 style="margin: 0; color: rgb(var(--settings-text)); font-weight: 700;">Export My Data</h5>
                        </div>
                        <p style="color: rgb(var(--settings-muted)); font-size: 0.85rem; margin-bottom: 16px; line-height: 1.5;">
                            Request a copy of all your personal data in a machine-readable format (JSON).
                        </p>
                        <button type="button" onclick="requestDataExport()" class="settings-btn settings-btn-primary" style="width: 100%; justify-content: center;">
                            <i class="fa-solid fa-file-export"></i>
                            Request Data Export
                        </button>
                    </div>

                    <!-- Data Portability -->
                    <div style="padding: 24px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.08), rgba(14, 165, 233, 0.05)); border: 1px solid rgba(6, 182, 212, 0.2); border-radius: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(14, 165, 233, 0.15)); display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-right-left" style="color: #06b6d4; font-size: 1.1rem;"></i>
                            </div>
                            <h5 style="margin: 0; color: rgb(var(--settings-text)); font-weight: 700;">Data Portability</h5>
                        </div>
                        <p style="color: rgb(var(--settings-muted)); font-size: 0.85rem; margin-bottom: 16px; line-height: 1.5;">
                            Transfer your data to another service in a standard format.
                        </p>
                        <button type="button" onclick="requestDataPortability()" class="settings-btn" style="width: 100%; justify-content: center; background: rgba(6, 182, 212, 0.15); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.3);">
                            <i class="fa-solid fa-cloud-arrow-down"></i>
                            Request Portability Export
                        </button>
                    </div>

                    <!-- Delete Account -->
                    <div style="padding: 24px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(220, 38, 38, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.15)); display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-trash-can" style="color: #ef4444; font-size: 1.1rem;"></i>
                            </div>
                            <h5 style="margin: 0; color: rgb(var(--settings-text)); font-weight: 700;">Delete My Account</h5>
                        </div>
                        <p style="color: rgb(var(--settings-muted)); font-size: 0.85rem; margin-bottom: 16px; line-height: 1.5;">
                            Permanently delete your account and all associated data. This cannot be undone.
                        </p>
                        <button type="button" onclick="requestAccountDeletion()" class="settings-btn" style="width: 100%; justify-content: center; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                            <i class="fa-solid fa-user-slash"></i>
                            Request Account Deletion
                        </button>
                    </div>
                </div>
            </div>

            <div class="settings-divider"></div>

            <!-- My Requests -->
            <div>
                <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text)); display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-clipboard-list" style="color: rgb(var(--settings-primary));"></i>
                    My Data Requests
                </h4>

                <?php if (empty($gdprRequests)): ?>
                    <div style="padding: 32px; background: rgba(var(--settings-surface), 0.4); border-radius: 16px; text-align: center;">
                        <i class="fa-solid fa-inbox" style="font-size: 2.5rem; color: rgb(var(--settings-muted)); opacity: 0.4; margin-bottom: 12px; display: block;"></i>
                        <p style="color: rgb(var(--settings-muted)); margin: 0;">You haven't submitted any data requests yet.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($gdprRequests as $request): ?>
                            <?php
                            $statusColors = [
                                'pending' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
                                'in_progress' => ['bg' => 'rgba(99, 102, 241, 0.15)', 'color' => '#6366f1', 'icon' => 'fa-spinner fa-spin'],
                                'completed' => ['bg' => 'rgba(16, 185, 129, 0.15)', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
                                'rejected' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444', 'icon' => 'fa-times-circle'],
                            ];
                            $status = $statusColors[$request['status']] ?? $statusColors['pending'];
                            $typeLabels = [
                                'access' => 'Data Export',
                                'erasure' => 'Account Deletion',
                                'portability' => 'Data Portability',
                                'rectification' => 'Data Correction',
                                'restriction' => 'Processing Restriction',
                                'objection' => 'Processing Objection',
                            ];
                            ?>
                            <div style="padding: 16px 20px; background: rgba(var(--settings-surface), 0.6); border: 1px solid rgba(var(--settings-primary), 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= $status['bg'] ?>; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid <?= $status['icon'] ?>" style="color: <?= $status['color'] ?>;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: rgb(var(--settings-text)); margin-bottom: 2px;">
                                            <?= $typeLabels[$request['request_type']] ?? ucfirst($request['request_type']) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: rgb(var(--settings-muted));">
                                            Submitted <?= date('M j, Y', strtotime($request['requested_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span style="padding: 5px 12px; background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
                                        <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                    </span>
                                    <?php if ($request['status'] === 'completed' && !empty($request['download_url'])): ?>
                                        <a href="<?= htmlspecialchars($request['download_url']) ?>" style="padding: 6px 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
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
            <div id="gdprModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center;">
                <div style="background: rgb(var(--settings-surface)); border: 1px solid rgba(var(--settings-primary), 0.2); border-radius: 20px; padding: 32px; max-width: 480px; width: 90%; box-shadow: 0 25px 50px rgba(0,0,0,0.3);">
                    <div id="modalContent"></div>
                </div>
            </div>

            <script>
            const gdprBasePath = '<?= TenantContext::getBasePath() ?>';

            function showModal(content) {
                document.getElementById('modalContent').innerHTML = content;
                document.getElementById('gdprModal').style.display = 'flex';
            }

            function hideModal() {
                document.getElementById('gdprModal').style.display = 'none';
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
                    <div style="text-align: center; margin-bottom: 24px;">
                        <div style="width: 64px; height: 64px; border-radius: 16px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.15)); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <i class="fa-solid fa-file-export" style="font-size: 1.75rem; color: #6366f1;"></i>
                        </div>
                        <h3 style="margin: 0 0 8px 0; color: rgb(var(--settings-text));">Request Data Export</h3>
                        <p style="color: rgb(var(--settings-muted)); font-size: 0.9rem; margin: 0;">We'll prepare a copy of all your personal data. This usually takes 1-3 business days.</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button onclick="hideModal()" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid rgba(var(--settings-primary), 0.2); background: transparent; color: rgb(var(--settings-text)); font-weight: 600; cursor: pointer;">Cancel</button>
                        <button onclick="submitGdprRequest('access')" style="flex: 1; padding: 12px; border-radius: 10px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-weight: 600; cursor: pointer;">Confirm Request</button>
                    </div>
                `);
            }

            function requestDataPortability() {
                showModal(`
                    <div style="text-align: center; margin-bottom: 24px;">
                        <div style="width: 64px; height: 64px; border-radius: 16px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(14, 165, 233, 0.15)); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <i class="fa-solid fa-right-left" style="font-size: 1.75rem; color: #06b6d4;"></i>
                        </div>
                        <h3 style="margin: 0 0 8px 0; color: rgb(var(--settings-text));">Request Data Portability</h3>
                        <p style="color: rgb(var(--settings-muted)); font-size: 0.9rem; margin: 0;">We'll prepare your data in a standard format that can be transferred to another service.</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button onclick="hideModal()" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid rgba(var(--settings-primary), 0.2); background: transparent; color: rgb(var(--settings-text)); font-weight: 600; cursor: pointer;">Cancel</button>
                        <button onclick="submitGdprRequest('portability')" style="flex: 1; padding: 12px; border-radius: 10px; border: none; background: linear-gradient(135deg, #06b6d4, #0ea5e9); color: white; font-weight: 600; cursor: pointer;">Confirm Request</button>
                    </div>
                `);
            }

            function requestAccountDeletion() {
                showModal(`
                    <div style="text-align: center; margin-bottom: 24px;">
                        <div style="width: 64px; height: 64px; border-radius: 16px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.15)); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <i class="fa-solid fa-exclamation-triangle" style="font-size: 1.75rem; color: #ef4444;"></i>
                        </div>
                        <h3 style="margin: 0 0 8px 0; color: rgb(var(--settings-text));">Delete Your Account?</h3>
                        <p style="color: rgb(var(--settings-muted)); font-size: 0.9rem; margin: 0;">This action is <strong>permanent and irreversible</strong>. All your data, including profile, credits, transactions, and history will be permanently deleted.</p>
                    </div>
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 10px; padding: 12px; margin-bottom: 20px;">
                        <p style="color: #ef4444; font-size: 0.85rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-info-circle"></i>
                            Type <strong>DELETE</strong> to confirm
                        </p>
                    </div>
                    <input type="text" id="deleteConfirmation" placeholder="Type DELETE to confirm" style="width: 100%; padding: 12px; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; background: transparent; color: rgb(var(--settings-text)); margin-bottom: 16px; box-sizing: border-box;">
                    <div style="display: flex; gap: 12px;">
                        <button onclick="hideModal()" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid rgba(var(--settings-primary), 0.2); background: transparent; color: rgb(var(--settings-text)); font-weight: 600; cursor: pointer;">Cancel</button>
                        <button onclick="confirmDeletion()" style="flex: 1; padding: 12px; border-radius: 10px; border: none; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; font-weight: 600; cursor: pointer;">Delete Account</button>
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
                            <div style="text-align: center;">
                                <div style="width: 64px; height: 64px; border-radius: 16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.15)); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                    <i class="fa-solid fa-check" style="font-size: 1.75rem; color: #10b981;"></i>
                                </div>
                                <h3 style="margin: 0 0 8px 0; color: rgb(var(--settings-text));">Request Submitted</h3>
                                <p style="color: rgb(var(--settings-muted)); font-size: 0.9rem; margin: 0 0 20px 0;">Your request has been submitted successfully. We'll process it within 30 days as required by GDPR.</p>
                                <button onclick="location.reload()" style="padding: 12px 24px; border-radius: 10px; border: none; background: linear-gradient(135deg, #10b981, #059669); color: white; font-weight: 600; cursor: pointer;">OK</button>
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

                <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text));">
                    <i class="fa-solid fa-envelope" style="margin-right: 8px; color: rgb(var(--settings-primary));"></i>
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

                <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text));">
                    <i class="fa-solid fa-building" style="margin-right: 8px; color: rgb(var(--settings-primary));"></i>
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

                <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text));">
                    <i class="fa-solid fa-mobile-screen" style="margin-right: 8px; color: rgb(var(--settings-primary));"></i>
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

            <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text));">
                <i class="fa-solid fa-circle-half-stroke" style="margin-right: 8px; color: rgb(var(--settings-primary));"></i>
                Theme
            </h4>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
                <label style="cursor: pointer;">
                    <input type="radio" name="theme" value="light" style="display: none;"
                           <?= ($_COOKIE['theme'] ?? 'light') === 'light' ? 'checked' : '' ?>
                           onchange="setTheme('light')">
                    <div style="padding: 24px; border-radius: 16px; text-align: center; border: 3px solid transparent; background: linear-gradient(135deg, #f8fafc, #e2e8f0); transition: all 0.3s ease;"
                         class="theme-option" data-theme="light">
                        <i class="fa-solid fa-sun" style="font-size: 2rem; color: #f59e0b; margin-bottom: 12px; display: block;"></i>
                        <span style="font-weight: 700; color: #1f2937;">Light</span>
                    </div>
                </label>

                <label style="cursor: pointer;">
                    <input type="radio" name="theme" value="dark" style="display: none;"
                           <?= ($_COOKIE['theme'] ?? '') === 'dark' ? 'checked' : '' ?>
                           onchange="setTheme('dark')">
                    <div style="padding: 24px; border-radius: 16px; text-align: center; border: 3px solid transparent; background: linear-gradient(135deg, #1e293b, #0f172a); transition: all 0.3s ease;"
                         class="theme-option" data-theme="dark">
                        <i class="fa-solid fa-moon" style="font-size: 2rem; color: #818cf8; margin-bottom: 12px; display: block;"></i>
                        <span style="font-weight: 700; color: #f1f5f9;">Dark</span>
                    </div>
                </label>

                <label style="cursor: pointer;">
                    <input type="radio" name="theme" value="auto" style="display: none;"
                           <?= ($_COOKIE['theme'] ?? '') === 'auto' ? 'checked' : '' ?>
                           onchange="setTheme('auto')">
                    <div style="padding: 24px; border-radius: 16px; text-align: center; border: 3px solid transparent; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); transition: all 0.3s ease;"
                         class="theme-option" data-theme="auto">
                        <i class="fa-solid fa-circle-half-stroke" style="font-size: 2rem; color: #6366f1; margin-bottom: 12px; display: block;"></i>
                        <span style="font-weight: 700; color: #312e81;">System</span>
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

            <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text));">
                <i class="fa-solid fa-text-height" style="margin-right: 8px; color: rgb(var(--settings-primary));"></i>
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

            <h4 style="margin: 0 0 16px 0; font-weight: 700; color: rgb(var(--settings-text));">
                <i class="fa-solid fa-robot" style="margin-right: 8px; color: rgb(var(--settings-primary));"></i>
                AI Assistant
            </h4>

            <div class="settings-toggle-row">
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
                <div style="text-align: center; padding: 60px 20px; background: rgba(var(--settings-surface), 0.4); border-radius: 20px;">
                    <i class="fa-solid fa-building-circle-xmark" style="font-size: 4rem; color: rgb(var(--settings-muted)); opacity: 0.4; margin-bottom: 20px; display: block;"></i>
                    <h3 style="margin: 0 0 8px 0; color: rgb(var(--settings-text));">No Organizations Yet</h3>
                    <p style="color: rgb(var(--settings-muted)); margin-bottom: 20px;">You are not a member of any organizations.</p>
                    <a href="<?= TenantContext::getBasePath() ?>/volunteering/organizations" class="settings-btn settings-btn-primary" style="display: inline-flex;">
                        <i class="fa-solid fa-search"></i>
                        Browse Organizations
                    </a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($userOrganizations as $org): ?>
                        <div class="settings-toggle-row" style="flex-direction: column; align-items: stretch; gap: 16px;">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <!-- Org Avatar/Icon -->
                                <div style="width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, <?php
                                    if ($org['member_role'] === 'owner') {
                                        echo 'rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.15)';
                                    } elseif ($org['member_role'] === 'admin') {
                                        echo 'rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.15)';
                                    } else {
                                        echo 'rgba(99, 102, 241, 0.15), rgba(79, 70, 229, 0.1)';
                                    }
                                ?>); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <?php if (!empty($org['logo_url'])): ?>
                                        <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fa-solid <?php
                                            if ($org['member_role'] === 'owner') {
                                                echo 'fa-crown" style="color: #f59e0b;';
                                            } elseif ($org['member_role'] === 'admin') {
                                                echo 'fa-shield" style="color: #8b5cf6;';
                                            } else {
                                                echo 'fa-building" style="color: rgb(var(--settings-primary));';
                                            }
                                        ?> font-size: 1.25rem;"></i>
                                    <?php endif; ?>
                                </div>

                                <!-- Org Info -->
                                <div style="flex: 1; min-width: 0;">
                                    <h4 style="margin: 0 0 4px 0; font-size: 1.05rem; font-weight: 700; color: rgb(var(--settings-text)); display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                        <?= htmlspecialchars($org['name']) ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; <?php
                                            if ($org['member_role'] === 'owner') {
                                                echo 'background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.15)); color: #b45309;';
                                            } elseif ($org['member_role'] === 'admin') {
                                                echo 'background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.15)); color: #7c3aed;';
                                            } else {
                                                echo 'background: rgba(var(--settings-primary), 0.1); color: rgb(var(--settings-primary));';
                                            }
                                        ?>">
                                            <i class="fa-solid <?php
                                                if ($org['member_role'] === 'owner') echo 'fa-crown';
                                                elseif ($org['member_role'] === 'admin') echo 'fa-shield';
                                                else echo 'fa-user';
                                            ?>"></i>
                                            <?= ucfirst($org['member_role']) ?>
                                        </span>
                                    </h4>
                                    <p style="margin: 0; font-size: 0.9rem; color: rgb(var(--settings-muted)); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($org['description'] ?? 'No description') ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; padding-top: 12px; border-top: 1px solid rgba(var(--settings-primary), 0.08);">
                                <a href="<?= TenantContext::getBasePath() ?>/volunteering/organization/<?= $org['id'] ?>" style="padding: 10px 16px; border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: rgba(var(--settings-primary), 0.1); color: rgb(var(--settings-primary)); display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                                    <i class="fa-solid fa-eye"></i> View Organization
                                </a>
                                <?php if (in_array($org['member_role'], ['owner', 'admin'])): ?>
                                    <a href="<?= TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet" style="padding: 10px 16px; border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.1)); color: #059669; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                                        <i class="fa-solid fa-wallet"></i> Wallet
                                    </a>
                                    <a href="<?= TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members" style="padding: 10px 16px; border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: rgba(139, 92, 246, 0.1); color: #7c3aed; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                                        <i class="fa-solid fa-users"></i> Members
                                    </a>
                                <?php endif; ?>
                                <?php if ($org['member_role'] === 'owner'): ?>
                                    <a href="<?= TenantContext::getBasePath() ?>/volunteering/organization/<?= $org['id'] ?>/edit" style="padding: 10px 16px; border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: rgba(245, 158, 11, 0.1); color: #d97706; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="settings-divider"></div>

                <div style="text-align: center;">
                    <a href="<?= TenantContext::getBasePath() ?>/volunteering/organizations" style="color: rgb(var(--settings-primary)); text-decoration: none; font-weight: 600; font-size: 0.95rem;">
                        <i class="fa-solid fa-search" style="margin-right: 6px;"></i>
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
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 24px; border-radius: 50%; background: linear-gradient(135deg, rgba(107, 114, 128, 0.15), rgba(107, 114, 128, 0.1)); display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-network-wired" style="font-size: 2rem; color: rgb(var(--settings-muted));"></i>
                    </div>
                    <h3 style="margin: 0 0 12px 0; color: rgb(var(--settings-text));">Federation Not Available</h3>
                    <p style="color: rgb(var(--settings-muted)); max-width: 400px; margin: 0 auto;">
                        Federation features are not currently enabled for your timebank.
                        Contact your timebank administrator for more information.
                    </p>
                </div>
            <?php else: ?>
                <!-- Federation Available -->
                <form id="federationSettingsForm" method="POST" action="<?= TenantContext::getBasePath() ?>/settings/federation/update">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

                    <!-- Master Opt-In -->
                    <div style="padding: 24px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.08)); border-radius: 20px; border: 1px solid rgba(99, 102, 241, 0.2); margin-bottom: 32px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px;">
                            <div>
                                <h3 style="margin: 0 0 8px 0; color: rgb(var(--settings-text)); font-size: 1.15rem;">
                                    <i class="fa-solid fa-power-off" style="color: rgb(var(--settings-primary)); margin-right: 8px;"></i>
                                    Enable Federation
                                </h3>
                                <p style="margin: 0; color: rgb(var(--settings-muted)); font-size: 0.9rem;">
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
                    <div id="federationOptions" style="<?= $fedSettings['federation_optin'] ? '' : 'display: none;' ?>">

                        <!-- Visibility Settings -->
                        <div style="margin-bottom: 32px;">
                            <h4 style="margin: 0 0 16px 0; color: rgb(var(--settings-text)); font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-eye" style="color: rgb(var(--settings-primary));"></i>
                                Visibility Settings
                            </h4>

                            <div style="display: flex; flex-direction: column; gap: 12px;">
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
                        <div style="margin-bottom: 32px;">
                            <h4 style="margin: 0 0 16px 0; color: rgb(var(--settings-text)); font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-comments" style="color: rgb(var(--settings-primary));"></i>
                                Interaction Settings
                            </h4>

                            <div style="display: flex; flex-direction: column; gap: 12px;">
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
                        <div style="margin-bottom: 32px;">
                            <h4 style="margin: 0 0 16px 0; color: rgb(var(--settings-text)); font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-location-dot" style="color: rgb(var(--settings-primary));"></i>
                                Service Reach
                            </h4>
                            <p style="margin: 0 0 16px 0; color: rgb(var(--settings-muted)); font-size: 0.9rem;">
                                How far can you provide your services?
                            </p>

                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <label class="settings-radio-card">
                                    <input type="radio" name="service_reach" value="local_only"
                                        <?= $fedSettings['service_reach'] === 'local_only' ? 'checked' : '' ?>>
                                    <div class="settings-radio-content">
                                        <span class="settings-radio-title">
                                            <i class="fa-solid fa-home" style="margin-right: 8px;"></i>
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
                                            <i class="fa-solid fa-laptop" style="margin-right: 8px;"></i>
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
                                            <i class="fa-solid fa-car" style="margin-right: 8px;"></i>
                                            Will Travel
                                        </span>
                                        <span class="settings-radio-desc">I can travel to provide services</span>
                                    </div>
                                </label>

                                <div id="travelRadiusField" style="<?= $fedSettings['service_reach'] === 'travel_ok' ? '' : 'display: none;' ?> margin-left: 32px; margin-top: 8px;">
                                    <label class="settings-label">Maximum Travel Distance (km)</label>
                                    <input type="number" name="travel_radius_km" class="settings-input"
                                        value="<?= htmlspecialchars($fedSettings['travel_radius_km'] ?? '') ?>"
                                        placeholder="e.g. 50" min="1" max="500" style="max-width: 200px;">
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="settings-divider"></div>
                        <div style="display: flex; justify-content: flex-end; gap: 12px;">
                            <button type="submit" class="settings-btn settings-btn-primary">
                                <i class="fa-solid fa-check"></i>
                                Save Federation Settings
                            </button>
                        </div>
                    </div>

                    <!-- Quick Opt-Out (when opted in) -->
                    <?php if ($fedSettings['federation_optin']): ?>
                    <div style="margin-top: 32px; padding: 20px; background: rgba(239, 68, 68, 0.08); border-radius: 16px; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                            <div>
                                <h4 style="margin: 0 0 4px 0; color: #dc2626; font-size: 0.95rem;">
                                    <i class="fa-solid fa-power-off" style="margin-right: 8px;"></i>
                                    Disable Federation
                                </h4>
                                <p style="margin: 0; color: rgb(var(--settings-muted)); font-size: 0.85rem;">
                                    Immediately hide your profile from all partner timebanks
                                </p>
                            </div>
                            <button type="button" onclick="quickOptOut()" class="settings-btn" style="background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3);">
                                <i class="fa-solid fa-eye-slash"></i>
                                Opt Out
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>

                <script>
                function toggleFederationSections(enabled) {
                    document.getElementById('federationOptions').style.display = enabled ? '' : 'none';
                }

                function toggleTravelRadius(show) {
                    document.getElementById('travelRadiusField').style.display = show ? '' : 'none';
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

            <div style="text-align: center; padding: 60px 20px;">
                <i class="fa-solid fa-tools" style="font-size: 4rem; color: rgb(var(--settings-muted)); opacity: 0.4; margin-bottom: 20px; display: block;"></i>
                <h3 style="margin: 0 0 8px 0; color: rgb(var(--settings-text));">Coming Soon</h3>
                <p style="color: rgb(var(--settings-muted));">This feature is currently under development.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
