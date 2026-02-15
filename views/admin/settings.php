<?php
// Phoenix View: Admin Settings (Legacy Clone of Modern)
// This file is overwritten to ensure parity with the Modern layout for all users.

$hTitle = 'Global Settings';
$hSubtitle = 'System Configuration';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Configuration';

// Require Modern Header (forcing modern layout even for legacy view file)
require dirname(__DIR__, 1) . '/layouts/modern/header.php';
// Load Settings if not already set (legacy fallback)
if (!isset($notifications)) {
    $tenant = \Nexus\Core\TenantContext::get();
    $configJson = json_decode($tenant['configuration'] ?? '{}', true);
    $notifications = $configJson['notifications'] ?? [];
    $gamification = json_decode($tenant['gamification_config'] ?? '{}', true);
}
?>

<div class="admin-settings-wrapper">

    <!-- Centered Container -->
    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px;">

        <div style="margin-bottom: 10px;">
            <a href="/admin" style="text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 5px; background: rgba(0,0,0,0.2); padding: 6px 14px; border-radius: 20px; backdrop-filter: blur(4px); font-size: 0.9rem; transition: background 0.2s;">
                &larr; Back to Dashboard
            </a>
        </div>

        <div class="nexus-card">

            <div class="nexus-card-header" style="padding: 25px 30px; border-bottom: 1px solid #eee;">
                <h2 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #111827;">Configuration Modules</h2>
                <div style="font-size: 0.9rem; color: #6b7280; margin-top: 4px;">Manage available features and system parameters</div>
            </div>

            <div class="nexus-card-body" style="padding: 30px;">

                <?php if (isset($_GET['saved'])): ?>
                    <div style="background: #ecfdf5; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; border: 1px solid #bbf7d0; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.2rem;">✅</span> Settings saved successfully!
                    </div>
                <?php endif; ?>

                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/settings/save-tenant" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>

                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Theme & Layout</h3>
                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                        <div style="margin-bottom: 15px;">
                            <label class="nexus-label" style="font-weight: 700; color: #1f2937;">Default Tenant Layout</label>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 10px;">Select the base theme for new visitors (can be overridden by user preference).</div>
                            <?php $currentLayout = $tenant['default_layout'] ?? 'modern'; ?>
                            <select name="default_layout" class="nexus-select" style="max-width: 300px;">
                                <option value="modern" <?= $currentLayout === 'modern' ? 'selected' : '' ?>>✨ Modern (Phoenix)</option>
                                <option value="civicone" <?= $currentLayout === 'civicone' ? 'selected' : '' ?>>👁️ Accessible (CivicOne)</option>
                            </select>
                        </div>
                    </div>

                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Feature Modules</h3>

                    <!-- Gamification Module -->
                    <div style="background: #fdf4ff; border: 1px solid #f0abfc; border-radius: 12px; padding: 25px; margin-bottom: 30px; transition: border-color 0.2s;">
                        <div style="display: flex; align-items: flex-start; gap: 20px; margin-bottom: 0;">
                            <div style="width: 50px; height: 50px; background: #fae8ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">🏆</div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h4 style="margin: 0 0 5px 0; font-size: 1.1rem; color: #86198f; font-weight: 700;">Gamification Module</h4>
                                        <p style="margin: 0; font-size: 0.95rem; color: #a21caf;">Engage users with achievements, badges, and leaderboards.</p>
                                    </div>
                                    <label class="nexus-switch">
                                        <input type="checkbox" name="gamification_enabled" <?= !empty($gamification['enabled']) ? 'checked' : '' ?> onchange="document.getElementById('gamification_options').style.display = this.checked ? 'block' : 'none'">
                                        <span class="nexus-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div id="gamification_options" style="padding-top: 20px; margin-top: 20px; border-top: 1px solid rgba(240, 171, 252, 0.4); display: <?= !empty($gamification['enabled']) ? 'block' : 'none' ?>;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.1s; user-select: none;">
                                    <input type="checkbox" name="gamification_volunteering" <?= !empty($gamification['volunteering']) ? 'checked' : '' ?> style="accent-color: #d946ef;">
                                    <span style="color: #4a044e; font-weight: 500;">Volunteering Badges</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.1s; user-select: none;">
                                    <input type="checkbox" name="gamification_timebanking" <?= !empty($gamification['timebanking']) ? 'checked' : '' ?> style="accent-color: #d946ef;">
                                    <span style="color: #4a044e; font-weight: 500;">Timebanking Badges</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Module -->
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                <div style="display: flex; align-items: flex-start; gap: 20px;">
                    <div style="width: 50px; height: 50px; background: #dbeafe; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">🔔</div>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h4 style="margin: 0 0 5px 0; font-size: 1.1rem; color: #1e40af; font-weight: 700;">Smart Notifications</h4>
                                <p style="margin: 0; font-size: 0.95rem; color: #1d4ed8;">Manage email alerts, digests, and browser polling.</p>
                            </div>
                            <label class="nexus-switch">
                                <input type="checkbox" name="notifications_enabled" <?= !empty($notifications['enabled']) ? 'checked' : '' ?>>
                                <span class="nexus-slider"></span>
                            </label>
                        </div>
                        <div style="margin-top: 15px;">
                            <label class="nexus-label">Default Frequency (New Users)</label>
                            <select name="notifications_default_frequency" class="nexus-select" style="max-width: 250px;">
                                <option value="instant" <?= ($notifications['default_frequency'] ?? 'daily') === 'instant' ? 'selected' : '' ?>>Instant Email</option>
                                <option value="daily" <?= ($notifications['default_frequency'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily Digest</option>
                                <option value="weekly" <?= ($notifications['default_frequency'] ?? 'daily') === 'weekly' ? 'selected' : '' ?>>Weekly Digest</option>
                                <option value="off" <?= ($notifications['default_frequency'] ?? 'daily') === 'off' ? 'selected' : '' ?>>Off (In-App Only)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

                    <!-- Social Login Module -->
                <?php $social = $configJson['social_login'] ?? []; ?>
                <div style="background: #fffbe3; border: 1px solid #fde047; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                    <div style="display: flex; align-items: flex-start; gap: 20px;">
                        <div style="width: 50px; height: 50px; background: #fefce8; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">🔑</div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; font-size: 1.1rem; color: #854d0e; font-weight: 700;">Social Login</h4>
                                    <p style="margin: 0; font-size: 0.95rem; color: #a16207;">Enable Google/Facebook login integration.</p>
                                </div>
                                <label class="nexus-switch">
                                    <input type="checkbox" name="social_login_enabled" <?= !empty($social['enabled']) ? 'checked' : '' ?> onchange="document.getElementById('social_options').style.display = this.checked ? 'block' : 'none'">
                                    <span class="nexus-slider"></span>
                                </label>
                            </div>

                            <div id="social_options" style="padding-top: 20px; margin-top: 20px; border-top: 1px solid rgba(253, 224, 71, 0.5); display: <?= !empty($social['enabled']) ? 'block' : 'none' ?>;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div>
                                        <label class="nexus-label">Google Client ID</label>
                                        <input type="text" name="social_google_id" value="<?= htmlspecialchars($social['providers']['google']['client_id'] ?? '') ?>" class="nexus-input">
                                    </div>
                                    <div>
                                        <label class="nexus-label">Google Client Secret</label>
                                        <input type="password" name="social_google_secret" value="<?= htmlspecialchars($social['providers']['google']['client_secret'] ?? '') ?>" class="nexus-input">
                                    </div>
                                    <div>
                                        <label class="nexus-label">Facebook App ID</label>
                                        <input type="text" name="social_facebook_id" value="<?= htmlspecialchars($social['providers']['facebook']['client_id'] ?? '') ?>" class="nexus-input" placeholder="Optional">
                                    </div>
                                    <div>
                                        <label class="nexus-label">Facebook App Secret</label>
                                        <input type="password" name="social_facebook_secret" value="<?= htmlspecialchars($social['providers']['facebook']['client_secret'] ?? '') ?>" class="nexus-input" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align: right; margin-bottom: 20px;">
                    <button type="submit" class="nexus-btn nexus-btn-primary" style="padding: 10px 24px;">Update Modules</button>
                </div>
                </form>

                <?php if (!empty($isSuper)): ?>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 40px 0;">

                    <form action="/admin-legacy/settings/update" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>

                        <div style="/* Super Admin Section Style */ background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 25px;">
                            <div style="margin-bottom: 20px;">
                                <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700;">Global System Config</h3>
                                <div style="font-size: 0.85rem; color: #64748b;">Super Admin Only • Affects entire platform</div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                                <div>
                                    <label class="nexus-label">App Name</label>
                                    <input type="text" name="APP_NAME" value="<?= htmlspecialchars($config['APP_NAME']) ?>" class="nexus-input">
                                </div>
                                <div>
                                    <label class="nexus-label">App URL</label>
                                    <input type="text" name="APP_URL" value="<?= htmlspecialchars($config['APP_URL']) ?>" class="nexus-input">
                                </div>
                            </div>

                            <h4 style="font-size: 0.95rem; font-weight: 600; color: #475569; margin-bottom: 15px; text-transform: uppercase;">Integrations</h4>
                            <div style="margin-bottom: 30px;">
                                <label class="nexus-label">Mapbox Access Token (Public)</label>
                                <input type="text" name="MAPBOX_ACCESS_TOKEN" value="<?= htmlspecialchars($config['MAPBOX_ACCESS_TOKEN']) ?>" class="nexus-input" placeholder="pk....">
                            </div>

                            <h4 style="font-size: 0.95rem; font-weight: 600; color: #475569; margin-bottom: 15px; text-transform: uppercase;">Email Provider</h4>

                            <!-- Email Provider Selection -->
                            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                                <label class="nexus-label" style="color: #2563eb;">Email Sending Method</label>
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                                    <select id="email_provider" name="USE_GMAIL_API" class="nexus-select" onchange="toggleEmailProvider()" style="margin-bottom: 0; flex: 1;">
                                        <option value="false" <?= ($config['USE_GMAIL_API'] ?? 'false') !== 'true' ? 'selected' : '' ?>>SMTP (Traditional)</option>
                                        <option value="true" <?= ($config['USE_GMAIL_API'] ?? 'false') === 'true' ? 'selected' : '' ?>>Gmail API (OAuth 2.0)</option>
                                    </select>
                                </div>
                                <div style="font-size: 0.85rem; color: #64748b;">
                                    <strong>SMTP:</strong> Traditional email sending via SMTP server.<br>
                                    <strong>Gmail API:</strong> Uses Google OAuth 2.0 - better deliverability, no App Passwords needed.
                                </div>
                            </div>

                            <!-- Gmail API Settings (shown when Gmail API selected) -->
                            <div id="gmail_api_settings" style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin-bottom: 20px; display: <?= ($config['USE_GMAIL_API'] ?? 'false') === 'true' ? 'block' : 'none' ?>;">
                                <h5 style="margin: 0 0 15px 0; font-size: 0.95rem; color: #92400e; font-weight: 600;">Gmail API Configuration (OAuth 2.0)</h5>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <label class="nexus-label">Client ID</label>
                                        <input type="text" name="GMAIL_CLIENT_ID" value="<?= htmlspecialchars($config['GMAIL_CLIENT_ID'] ?? '') ?>" class="nexus-input" placeholder="xxx.apps.googleusercontent.com">
                                    </div>
                                    <div>
                                        <label class="nexus-label">Client Secret</label>
                                        <input type="password" name="GMAIL_CLIENT_SECRET" value="<?= htmlspecialchars($config['GMAIL_CLIENT_SECRET'] ?? '') ?>" class="nexus-input">
                                    </div>
                                </div>

                                <div style="margin-bottom: 15px;">
                                    <label class="nexus-label">Refresh Token</label>
                                    <input type="password" name="GMAIL_REFRESH_TOKEN" value="<?= htmlspecialchars($config['GMAIL_REFRESH_TOKEN'] ?? '') ?>" class="nexus-input" placeholder="Long-lived refresh token from OAuth flow">
                                    <div style="font-size: 0.8rem; color: #78716c; margin-top: 5px;">Obtained via OAuth consent flow. For Internal apps, this token never expires.</div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <label class="nexus-label">Sender Email</label>
                                        <input type="email" name="GMAIL_SENDER_EMAIL" value="<?= htmlspecialchars($config['GMAIL_SENDER_EMAIL'] ?? '') ?>" class="nexus-input" placeholder="sender@yourdomain.com">
                                        <div style="font-size: 0.8rem; color: #78716c; margin-top: 5px;">Must match the authenticated Google account.</div>
                                    </div>
                                    <div>
                                        <label class="nexus-label">Sender Name</label>
                                        <input type="text" name="GMAIL_SENDER_NAME" value="<?= htmlspecialchars($config['GMAIL_SENDER_NAME'] ?? '') ?>" class="nexus-input" placeholder="Your Platform Name">
                                    </div>
                                </div>

                                <?php if (($config['USE_GMAIL_API'] ?? 'false') === 'true'): ?>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #fcd34d;">
                                    <button type="button" onclick="testGmailConnection()" class="nexus-btn" style="background: #f59e0b; color: white; padding: 8px 16px;">Test Gmail API Connection</button>
                                    <span id="gmail_test_result" style="margin-left: 10px; font-size: 0.9rem;"></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- SMTP Settings (shown when SMTP selected) -->
                            <div id="smtp_settings" style="display: <?= ($config['USE_GMAIL_API'] ?? 'false') !== 'true' ? 'block' : 'none' ?>;">
                            <h5 style="font-size: 0.95rem; font-weight: 600; color: #475569; margin-bottom: 15px;">SMTP Settings</h5>

                            <!-- Legacy SMTP Presets Implemented in Modern Design -->
                            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                                <label class="nexus-label" style="color: #2563eb;">Quick Setup (Select Provider)</label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select id="smtp_provider" class="nexus-select" onchange="applySmtpPreset()" style="margin-bottom: 0; flex: 1;">
                                        <option value="custom">Custom / Other</option>
                                        <option value="gmail">Gmail / Google Workspace (SMTP)</option>
                                        <option value="outlook">Outlook / Office 365</option>
                                        <option value="sendgrid">SendGrid</option>
                                        <option value="mailpoet">MailPoet (Sending Service)</option>
                                    </select>
                                    <span style="font-size: 0.85rem; color: #64748b;" id="smtp_help_text">Select to auto-fill.</span>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div style="grid-column: span 2;">
                                    <label class="nexus-label">SMTP Host</label>
                                    <input type="text" name="SMTP_HOST" id="smtp_host" value="<?= htmlspecialchars($config['SMTP_HOST']) ?>" class="nexus-input">
                                </div>
                                <div>
                                    <label class="nexus-label">Port</label>
                                    <input type="text" name="SMTP_PORT" id="smtp_port" value="<?= htmlspecialchars($config['SMTP_PORT']) ?>" class="nexus-input">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label class="nexus-label">Encryption</label>
                                    <select name="SMTP_ENCRYPTION" id="smtp_encryption" class="nexus-select">
                                        <option value="tls" <?= ($config['SMTP_ENCRYPTION'] ?? '') == 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                                        <option value="ssl" <?= ($config['SMTP_ENCRYPTION'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                                        <option value="" <?= ($config['SMTP_ENCRYPTION'] ?? '') == '' ? 'selected' : '' ?>>None (25)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="nexus-label">Username</label>
                                    <input type="text" name="SMTP_USER" id="smtp_user" value="<?= htmlspecialchars($config['SMTP_USER']) ?>" class="nexus-input">
                                </div>
                                <div>
                                    <label class="nexus-label">Password</label>
                                    <input type="password" name="SMTP_PASS" value="<?= htmlspecialchars($config['SMTP_PASS']) ?>" class="nexus-input">
                                </div>
                            </div>

                            <div style="margin-bottom: 20px;">
                                <label class="nexus-label">From Email</label>
                                <input type="email" name="SMTP_FROM_EMAIL" value="<?= htmlspecialchars($config['SMTP_FROM_EMAIL']) ?>" class="nexus-input">
                            </div>
                            </div><!-- End smtp_settings -->

                            <div style="text-align: right;">
                                <button type="submit" class="nexus-btn nexus-btn-primary">Save Global Config</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
    // Security: HTML escape function to prevent XSS
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function toggleEmailProvider() {
        var provider = document.getElementById('email_provider').value;
        var gmailSettings = document.getElementById('gmail_api_settings');
        var smtpSettings = document.getElementById('smtp_settings');

        if (provider === 'true') {
            gmailSettings.style.display = 'block';
            smtpSettings.style.display = 'none';
        } else {
            gmailSettings.style.display = 'none';
            smtpSettings.style.display = 'block';
        }
    }

    function testGmailConnection() {
        var resultSpan = document.getElementById('gmail_test_result');
        resultSpan.innerHTML = '<span style="color: #6b7280;">Testing...</span>';

        fetch('/admin-legacy/settings/test-gmail', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('input[name="_csrf"]').value
            }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                resultSpan.innerHTML = '<span style="color: #16a34a;">&#10004; ' + escapeHtml(data.message) + '</span>';
            } else {
                resultSpan.innerHTML = '<span style="color: #dc2626;">&#10008; ' + escapeHtml(data.message) + '</span>';
            }
        })
        .catch(function(error) {
            resultSpan.innerHTML = '<span style="color: #dc2626;">&#10008; Connection error</span>';
        });
    }

    function applySmtpPreset() {
        const provider = document.getElementById('smtp_provider').value;
        const host = document.getElementById('smtp_host');
        const port = document.getElementById('smtp_port');
        const enc = document.getElementById('smtp_encryption');
        const user = document.getElementById('smtp_user');
        const help = document.getElementById('smtp_help_text');

        switch (provider) {
            case 'gmail':
                host.value = 'smtp.gmail.com';
                port.value = '587';
                enc.value = 'tls';
                help.innerText = 'Requires App Password if 2FA is enabled.';
                break;
            case 'outlook':
                host.value = 'smtp.office365.com';
                port.value = '587';
                enc.value = 'tls';
                help.innerText = 'Requires App Password.';
                break;
            case 'sendgrid':
                host.value = 'smtp.sendgrid.net';
                port.value = '587';
                enc.value = 'tls';
                user.value = 'apikey';
                help.innerText = 'Username is "apikey". Password is SendGrid API Key.';
                break;
            case 'mailpoet':
                host.value = 'smtp.sendingservice.net';
                port.value = '587';
                enc.value = 'tls';
                help.innerText = 'Use your MailPoet credentials.';
                break;
            case 'custom':
                help.innerText = 'Enter settings manually.';
                break;
        }
    }
</script>

<style>
    .admin-settings-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    /* Desktop spacing */
    @media (min-width: 601px) {
        .admin-settings-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile responsiveness */
    @media (max-width: 600px) {
        .admin-settings-wrapper {
            padding: 120px 15px 100px 15px;
        }

        .admin-settings-wrapper .nexus-card {
            border-radius: 12px;
        }

        .admin-settings-wrapper [style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<?php require dirname(__DIR__, 1) . '/layouts/modern/footer.php'; ?>