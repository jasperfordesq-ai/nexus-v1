<?php
/**
 * Admin Settings - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Settings';
$adminPageSubtitle = 'System Settings';
$adminPageIcon = 'fa-sliders';

// Include standalone admin header
require __DIR__ . '/partials/admin-header.php';

// Ensure configJson is available for module settings
if (!isset($configJson)) {
    $configJson = isset($tenant['configuration']) ? json_decode($tenant['configuration'], true) : [];
    if (!is_array($configJson)) $configJson = [];
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-sliders"></i>
            Global Settings
        </h1>
        <p class="admin-page-subtitle">Manage platform configuration and features</p>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div><strong>Success!</strong> Settings saved successfully!</div>
</div>
<?php endif; ?>

<!-- Configuration Modules Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-cogs"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Configuration Modules</h3>
            <p class="admin-card-subtitle">Manage available features and system parameters</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin-legacy/settings/save-tenant" method="POST">
            <?= Csrf::input() ?>

            <!-- Theme & Layout Section -->
            <div class="settings-section">
                <h4 class="settings-section-title">
                    <i class="fa-solid fa-palette"></i> Theme & Layout
                </h4>
                <div class="settings-module settings-module-default">
                    <div class="settings-module-content">
                        <label class="admin-label">Default Tenant Layout</label>
                        <p class="settings-description">Select the base theme for new visitors (can be overridden by user preference).</p>
                        <?php $currentLayout = $tenant['default_layout'] ?? 'modern'; ?>
                        <select name="default_layout" class="admin-select" style="max-width: 300px;">
                            <option value="modern" <?= $currentLayout === 'modern' ? 'selected' : '' ?>>Modern (Phoenix)</option>
                            <option value="civicone" <?= $currentLayout === 'civicone' ? 'selected' : '' ?>>Accessible (CivicOne)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Feature Modules Section -->
            <div class="settings-section">
                <h4 class="settings-section-title">
                    <i class="fa-solid fa-puzzle-piece"></i> Feature Modules
                </h4>

                <!-- Gamification Module -->
                <div class="settings-module settings-module-purple">
                    <div class="settings-module-icon">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <div class="settings-module-content">
                        <div class="settings-module-header">
                            <div>
                                <h5 class="settings-module-title">Gamification Module</h5>
                                <p class="settings-module-desc">Engage users with achievements, badges, and leaderboards.</p>
                            </div>
                            <label class="admin-switch">
                                <input type="checkbox" name="gamification_enabled" <?= !empty($gamification['enabled']) ? 'checked' : '' ?> onchange="document.getElementById('gamification_options').style.display = this.checked ? 'block' : 'none'">
                                <span class="admin-slider"></span>
                            </label>
                        </div>
                        <div id="gamification_options" class="settings-module-options" style="display: <?= !empty($gamification['enabled']) ? 'block' : 'none' ?>;">
                            <div class="settings-checkbox-grid">
                                <label class="settings-checkbox-label">
                                    <input type="checkbox" name="gamification_volunteering" <?= !empty($gamification['volunteering']) ? 'checked' : '' ?>>
                                    <span>Volunteering Badges</span>
                                </label>
                                <label class="settings-checkbox-label">
                                    <input type="checkbox" name="gamification_timebanking" <?= !empty($gamification['timebanking']) ? 'checked' : '' ?>>
                                    <span>Timebanking Badges</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Module -->
                <div class="settings-module settings-module-blue">
                    <div class="settings-module-icon">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="settings-module-content">
                        <div class="settings-module-header">
                            <div>
                                <h5 class="settings-module-title">Smart Notifications</h5>
                                <p class="settings-module-desc">Manage email alerts, digests, and browser polling.</p>
                            </div>
                            <label class="admin-switch">
                                <input type="checkbox" name="notifications_enabled" <?= !empty($notifications['enabled']) ? 'checked' : '' ?>>
                                <span class="admin-slider"></span>
                            </label>
                        </div>
                        <div class="settings-module-options" style="margin-top: 1rem;">
                            <label class="admin-label">Default Frequency (New Users)</label>
                            <select name="notifications_default_frequency" class="admin-select" style="max-width: 250px;">
                                <option value="instant" <?= ($notifications['default_frequency'] ?? 'daily') === 'instant' ? 'selected' : '' ?>>Instant Email</option>
                                <option value="daily" <?= ($notifications['default_frequency'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily Digest</option>
                                <option value="weekly" <?= ($notifications['default_frequency'] ?? 'daily') === 'weekly' ? 'selected' : '' ?>>Weekly Digest</option>
                                <option value="off" <?= ($notifications['default_frequency'] ?? 'daily') === 'off' ? 'selected' : '' ?>>Off (In-App Only)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Social Login Module -->
                <?php $social = $configJson['social_login'] ?? []; ?>
                <div class="settings-module settings-module-yellow">
                    <div class="settings-module-icon">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div class="settings-module-content">
                        <div class="settings-module-header">
                            <div>
                                <h5 class="settings-module-title">Social Login</h5>
                                <p class="settings-module-desc">Enable Google/Facebook login integration.</p>
                            </div>
                            <label class="admin-switch">
                                <input type="checkbox" name="social_login_enabled" <?= !empty($social['enabled']) ? 'checked' : '' ?> onchange="document.getElementById('social_options').style.display = this.checked ? 'block' : 'none'">
                                <span class="admin-slider"></span>
                            </label>
                        </div>
                        <div id="social_options" class="settings-module-options" style="display: <?= !empty($social['enabled']) ? 'block' : 'none' ?>;">
                            <div class="settings-form-group" style="margin-bottom: 1rem;">
                                <label class="admin-label">Redirect URI (Add to Google Console)</label>
                                <input type="text" readonly value="<?= $basePath ?>/login/oauth/callback/google" class="admin-input" style="font-family: monospace; cursor: not-allowed;" onclick="this.select()">
                                <p class="settings-hint">Copy this EXACT URL to your Google Cloud Console "Authorized redirect URIs".</p>
                            </div>
                            <div class="settings-form-grid">
                                <div class="settings-form-group">
                                    <label class="admin-label">Google Client ID</label>
                                    <input type="text" name="social_google_id" value="<?= htmlspecialchars($social['providers']['google']['client_id'] ?? '') ?>" class="admin-input">
                                </div>
                                <div class="settings-form-group">
                                    <label class="admin-label">Google Client Secret</label>
                                    <input type="password" name="social_google_secret" value="<?= htmlspecialchars($social['providers']['google']['client_secret'] ?? '') ?>" class="admin-input">
                                </div>
                                <div class="settings-form-group">
                                    <label class="admin-label">Facebook App ID</label>
                                    <input type="text" name="social_facebook_id" value="<?= htmlspecialchars($social['providers']['facebook']['client_id'] ?? '') ?>" class="admin-input" placeholder="Optional">
                                </div>
                                <div class="settings-form-group">
                                    <label class="admin-label">Facebook App Secret</label>
                                    <input type="password" name="social_facebook_secret" value="<?= htmlspecialchars($social['providers']['facebook']['client_secret'] ?? '') ?>" class="admin-input" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Welcome Email Module -->
                <?php $welcomeEmail = $configJson['welcome_email'] ?? []; ?>
                <div class="settings-module settings-module-cyan">
                    <div class="settings-module-icon">
                        <i class="fa-solid fa-hand-wave"></i>
                    </div>
                    <div class="settings-module-content">
                        <h5 class="settings-module-title">Welcome Email</h5>
                        <p class="settings-module-desc">Customize the email sent when a user is approved.</p>
                        <div class="settings-module-options" style="margin-top: 1rem;">
                            <div class="settings-form-group">
                                <label class="admin-label">Email Subject</label>
                                <input type="text" name="welcome_email_subject" value="<?= htmlspecialchars($welcomeEmail['subject'] ?? '') ?>" class="admin-input" placeholder="Welcome to <?= htmlspecialchars($tenant['name'] ?? 'Our Platform') ?>!">
                            </div>
                            <div class="settings-form-group">
                                <label class="admin-label">Email Body (HTML Supported)</label>
                                <textarea name="welcome_email_body" rows="5" class="admin-input" placeholder="Congratulations! Your account has been approved..."><?= htmlspecialchars($welcomeEmail['body'] ?? '') ?></textarea>
                                <p class="settings-hint">Supports HTML tags (e.g., &lt;strong&gt;, &lt;br&gt;) and Emojis.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mailchimp Module -->
                <div class="settings-module settings-module-orange">
                    <div class="settings-module-icon">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div class="settings-module-content">
                        <h5 class="settings-module-title">Mailchimp Integration</h5>
                        <p class="settings-module-desc">Sync new members to your newsletter audience automatically.</p>
                        <div class="settings-module-options" style="margin-top: 1rem;">
                            <div class="settings-form-grid">
                                <div class="settings-form-group">
                                    <label class="admin-label">API Key</label>
                                    <input type="password" name="mailchimp_api_key" value="<?= htmlspecialchars($configJson['mailchimp_api_key'] ?? '') ?>" class="admin-input" placeholder="md-xxxxxxxx...">
                                </div>
                                <div class="settings-form-group">
                                    <label class="admin-label">Audience / List ID</label>
                                    <input type="text" name="mailchimp_list_id" value="<?= htmlspecialchars($configJson['mailchimp_list_id'] ?? '') ?>" class="admin-input" placeholder="e.g. aba342...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-save"></i> Update Modules
                </button>
            </div>
        </form>
    </div>
</div>

<!-- System Maintenance Card -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fa-solid fa-toolbox"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">System Maintenance</h3>
            <p class="admin-card-subtitle">Tools for cache management and system health</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="settings-module settings-module-red">
            <div class="settings-module-icon">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <div class="settings-module-content">
                <h5 class="settings-module-title">Clear Browser Cache</h5>
                <p class="settings-module-desc">Clear service worker caches, localStorage, and IndexedDB for users experiencing stale content.</p>
                <div style="margin-top: 1rem;">
                    <a href="/clear-cache.php" target="_blank" class="admin-btn admin-btn-danger">
                        <i class="fa-solid fa-external-link"></i> Open Cache Utility
                    </a>
                    <p class="settings-hint" style="margin-top: 0.75rem;">Opens in new tab. Share this link with users who need to clear their browser cache.</p>
                </div>
            </div>
        </div>

        <div class="settings-module settings-module-cyan">
            <div class="settings-module-icon">
                <i class="fa-solid fa-file-zipper"></i>
            </div>
            <div class="settings-module-content">
                <h5 class="settings-module-title">Regenerate Minified CSS</h5>
                <p class="settings-module-desc">Regenerate all .min.css files from source CSS files. Use after CSS changes.</p>
                <div style="margin-top: 1rem;">
                    <button type="button" onclick="regenerateMinifiedCSS()" class="admin-btn admin-btn-primary" id="css-minify-btn">
                        <i class="fa-solid fa-compress"></i> Regenerate All CSS
                    </button>
                    <span id="css-minify-result" style="margin-left: 10px;"></span>
                    <p class="settings-hint" style="margin-top: 0.75rem;">Minifies all CSS files in /assets/css/ directory.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($isSuper)): ?>
<!-- Super Admin Section -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #64748b, #475569);">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Global System Config</h3>
            <p class="admin-card-subtitle">Super Admin Only - Affects entire platform</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin-legacy/settings/update" method="POST">
            <?= Csrf::input() ?>

            <div class="settings-section">
                <h4 class="settings-section-title">
                    <i class="fa-solid fa-server"></i> Platform Settings
                </h4>
                <div class="settings-form-grid">
                    <div class="settings-form-group">
                        <label class="admin-label">App Name</label>
                        <input type="text" name="APP_NAME" value="<?= htmlspecialchars($config['APP_NAME'] ?? '') ?>" class="admin-input">
                    </div>
                    <div class="settings-form-group">
                        <label class="admin-label">App URL</label>
                        <input type="text" name="APP_URL" value="<?= htmlspecialchars($config['APP_URL'] ?? '') ?>" class="admin-input">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <h4 class="settings-section-title">
                    <i class="fa-solid fa-plug"></i> Integrations
                </h4>
                <div class="settings-form-group">
                    <label class="admin-label">Google Maps API Key</label>
                    <input type="text" name="GOOGLE_MAPS_API_KEY" value="<?= htmlspecialchars($config['GOOGLE_MAPS_API_KEY'] ?? '') ?>" class="admin-input" placeholder="AIza...">
                </div>
            </div>

            <div class="settings-section">
                <h4 class="settings-section-title">
                    <i class="fa-solid fa-envelope"></i> Email Provider
                </h4>

                <!-- Email Provider Selection -->
                <div class="settings-module settings-module-default" style="margin-bottom: 1rem;">
                    <div class="settings-module-content">
                        <label class="admin-label">Email Sending Method</label>
                        <select id="email_provider" name="USE_GMAIL_API" class="admin-select" onchange="toggleEmailProvider()" style="max-width: 300px;">
                            <option value="false" <?= ($config['USE_GMAIL_API'] ?? 'false') !== 'true' ? 'selected' : '' ?>>SMTP (Traditional)</option>
                            <option value="true" <?= ($config['USE_GMAIL_API'] ?? 'false') === 'true' ? 'selected' : '' ?>>Gmail API (OAuth 2.0)</option>
                        </select>
                        <p class="settings-hint"><strong>SMTP:</strong> Traditional email sending via SMTP server.<br><strong>Gmail API:</strong> Uses Google OAuth 2.0 - better deliverability, no App Passwords needed.</p>
                    </div>
                </div>

                <!-- Gmail API Settings -->
                <div id="gmail_api_settings" class="settings-module settings-module-yellow" style="display: <?= ($config['USE_GMAIL_API'] ?? 'false') === 'true' ? 'block' : 'none' ?>;">
                    <div class="settings-module-content">
                        <h5 class="settings-module-title">Gmail API Configuration (OAuth 2.0)</h5>
                        <div class="settings-form-grid" style="margin-top: 1rem;">
                            <div class="settings-form-group">
                                <label class="admin-label">Client ID</label>
                                <input type="text" name="GMAIL_CLIENT_ID" value="<?= htmlspecialchars($config['GMAIL_CLIENT_ID'] ?? '') ?>" class="admin-input" placeholder="xxx.apps.googleusercontent.com">
                            </div>
                            <div class="settings-form-group">
                                <label class="admin-label">Client Secret</label>
                                <input type="password" name="GMAIL_CLIENT_SECRET" value="<?= htmlspecialchars($config['GMAIL_CLIENT_SECRET'] ?? '') ?>" class="admin-input">
                            </div>
                        </div>
                        <div class="settings-form-group">
                            <label class="admin-label">Refresh Token</label>
                            <input type="password" name="GMAIL_REFRESH_TOKEN" value="<?= htmlspecialchars($config['GMAIL_REFRESH_TOKEN'] ?? '') ?>" class="admin-input" placeholder="Long-lived refresh token from OAuth flow">
                            <p class="settings-hint">Obtained via OAuth consent flow. For Internal apps, this token never expires.</p>
                        </div>
                        <div class="settings-form-grid">
                            <div class="settings-form-group">
                                <label class="admin-label">Sender Email</label>
                                <input type="email" name="GMAIL_SENDER_EMAIL" value="<?= htmlspecialchars($config['GMAIL_SENDER_EMAIL'] ?? '') ?>" class="admin-input" placeholder="sender@yourdomain.com">
                                <p class="settings-hint">Must match the authenticated Google account.</p>
                            </div>
                            <div class="settings-form-group">
                                <label class="admin-label">Sender Name</label>
                                <input type="text" name="GMAIL_SENDER_NAME" value="<?= htmlspecialchars($config['GMAIL_SENDER_NAME'] ?? '') ?>" class="admin-input" placeholder="Your Platform Name">
                            </div>
                        </div>
                        <?php if (($config['USE_GMAIL_API'] ?? 'false') === 'true'): ?>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(250, 204, 21, 0.3);">
                            <button type="button" onclick="testGmailConnection()" class="admin-btn admin-btn-warning">
                                <i class="fa-solid fa-plug"></i> Test Gmail API Connection
                            </button>
                            <span id="gmail_test_result" style="margin-left: 10px;"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SMTP Settings -->
                <div id="smtp_settings" class="settings-module settings-module-default" style="display: <?= ($config['USE_GMAIL_API'] ?? 'false') !== 'true' ? 'block' : 'none' ?>;">
                    <div class="settings-module-content">
                        <h5 class="settings-module-title">SMTP Settings</h5>

                        <!-- SMTP Presets -->
                        <div style="margin: 1rem 0;">
                            <label class="admin-label">Quick Setup (Select Provider)</label>
                            <div style="display: flex; gap: 0.75rem; align-items: center;">
                                <select id="smtp_provider" class="admin-select" onchange="applySmtpPreset()" style="flex: 1; max-width: 300px;">
                                    <option value="custom">Custom / Other</option>
                                    <option value="gmail">Gmail / Google Workspace</option>
                                    <option value="outlook">Outlook / Office 365</option>
                                    <option value="sendgrid">SendGrid</option>
                                    <option value="mailpoet">MailPoet (Sending Service)</option>
                                </select>
                                <span id="smtp_help_text" class="settings-hint" style="margin: 0;">Select to auto-fill.</span>
                            </div>
                        </div>

                        <div class="settings-form-grid" style="grid-template-columns: 2fr 1fr;">
                            <div class="settings-form-group">
                                <label class="admin-label">SMTP Host</label>
                                <input type="text" name="SMTP_HOST" id="smtp_host" value="<?= htmlspecialchars($config['SMTP_HOST'] ?? '') ?>" class="admin-input">
                            </div>
                            <div class="settings-form-group">
                                <label class="admin-label">Port</label>
                                <input type="text" name="SMTP_PORT" id="smtp_port" value="<?= htmlspecialchars($config['SMTP_PORT'] ?? '') ?>" class="admin-input">
                            </div>
                        </div>

                        <div class="settings-form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                            <div class="settings-form-group">
                                <label class="admin-label">Encryption</label>
                                <select name="SMTP_ENCRYPTION" id="smtp_encryption" class="admin-select">
                                    <option value="tls" <?= ($config['SMTP_ENCRYPTION'] ?? '') == 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                                    <option value="ssl" <?= ($config['SMTP_ENCRYPTION'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                                    <option value="" <?= ($config['SMTP_ENCRYPTION'] ?? '') == '' ? 'selected' : '' ?>>None (25)</option>
                                </select>
                            </div>
                            <div class="settings-form-group">
                                <label class="admin-label">Username</label>
                                <input type="text" name="SMTP_USER" id="smtp_user" value="<?= htmlspecialchars($config['SMTP_USER'] ?? '') ?>" class="admin-input">
                            </div>
                            <div class="settings-form-group">
                                <label class="admin-label">Password</label>
                                <input type="password" name="SMTP_PASS" value="<?= htmlspecialchars($config['SMTP_PASS'] ?? '') ?>" class="admin-input">
                            </div>
                        </div>

                        <div class="settings-form-group">
                            <label class="admin-label">From Email</label>
                            <input type="email" name="SMTP_FROM_EMAIL" value="<?= htmlspecialchars($config['SMTP_FROM_EMAIL'] ?? '') ?>" class="admin-input">
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-save"></i> Save Global Config
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Security: HTML escape function to prevent XSS
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function regenerateMinifiedCSS() {
    var btn = document.getElementById('css-minify-btn');
    var resultSpan = document.getElementById('css-minify-result');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    resultSpan.innerHTML = '';

    fetch('<?= $basePath ?>/admin-legacy/settings/regenerate-css', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('input[name="_csrf"]').value
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-compress"></i> Regenerate All CSS';

        if (data.success) {
            resultSpan.innerHTML = '<span style="color: #22c55e;"><i class="fa-solid fa-check"></i> ' + escapeHtml(data.message) + '</span>';
        } else {
            resultSpan.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-times"></i> ' + escapeHtml(data.message) + '</span>';
        }
    })
    .catch(function(error) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-compress"></i> Regenerate All CSS';
        resultSpan.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-times"></i> Error: ' + escapeHtml(error.message) + '</span>';
    });
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
    resultSpan.innerHTML = '<span style="color: rgba(255,255,255,0.6);">Testing...</span>';

    fetch('<?= $basePath ?>/admin-legacy/settings/test-gmail', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('input[name="_csrf"]').value
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            resultSpan.innerHTML = '<span style="color: #22c55e;"><i class="fa-solid fa-check"></i> ' + escapeHtml(data.message) + '</span>';
        } else {
            resultSpan.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-times"></i> ' + escapeHtml(data.message) + '</span>';
        }
    })
    .catch(function(error) {
        resultSpan.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-times"></i> Connection error</span>';
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
/* Settings Page Specific Styles */

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.admin-alert i {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-alert-success {
    border-left: 3px solid #22c55e;
}
.admin-alert-success i { color: #22c55e; }

/* Settings Sections */
.settings-section {
    margin-bottom: 2rem;
}

.settings-section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.settings-section-title i {
    color: #818cf8;
}

/* Settings Modules */
.settings-module {
    display: flex;
    gap: 1.25rem;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.settings-module-default {
    background: rgba(30, 41, 59, 0.5);
}

.settings-module-purple {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.3);
}

.settings-module-blue {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.3);
}

.settings-module-yellow {
    background: rgba(250, 204, 21, 0.15);
    border-color: rgba(250, 204, 21, 0.3);
}

.settings-module-cyan {
    background: rgba(34, 211, 238, 0.15);
    border-color: rgba(34, 211, 238, 0.3);
}

.settings-module-orange {
    background: rgba(251, 146, 60, 0.15);
    border-color: rgba(251, 146, 60, 0.3);
}

.settings-module-red {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
}

.settings-module-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.1);
}

.settings-module-purple .settings-module-icon { color: #c4b5fd; }
.settings-module-blue .settings-module-icon { color: #93c5fd; }
.settings-module-yellow .settings-module-icon { color: #fde68a; }
.settings-module-cyan .settings-module-icon { color: #67e8f9; }
.settings-module-orange .settings-module-icon { color: #fdba74; }
.settings-module-red .settings-module-icon { color: #fca5a5; }

.settings-module-content {
    flex: 1;
}

.settings-module-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.settings-module-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.25rem 0;
}

.settings-module-desc {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

.settings-module-options {
    padding-top: 1rem;
    margin-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.settings-description {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0 0 0.75rem 0;
}

.settings-hint {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.5rem;
    margin-bottom: 0;
}

/* Form Elements */
.settings-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.settings-form-group {
    margin-bottom: 1rem;
}

.settings-form-group:last-child {
    margin-bottom: 0;
}

.settings-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.settings-checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: background 0.2s;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
}

.settings-checkbox-label:hover {
    background: rgba(255, 255, 255, 0.05);
}

.settings-checkbox-label input[type="checkbox"] {
    accent-color: #8b5cf6;
}

/* Form Inputs */
.admin-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 0.5rem;
}

.admin-input,
.admin-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.admin-input:focus,
.admin-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.admin-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.admin-select {
    cursor: pointer;
}

.admin-select option {
    background: #1e293b;
    color: #f1f5f9;
}

textarea.admin-input {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

/* Switch Toggle */
.admin-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    flex-shrink: 0;
}

.admin-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.admin-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.15);
    transition: 0.3s;
    border-radius: 26px;
}

.admin-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: 0.3s;
    border-radius: 50%;
}

.admin-switch input:checked + .admin-slider {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.admin-switch input:checked + .admin-slider:before {
    transform: translateX(24px);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border: 1px solid rgba(245, 158, 11, 0.5);
}

.admin-btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
}

.admin-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

/* Responsive */
@media (max-width: 768px) {
    .settings-module {
        flex-direction: column;
        gap: 1rem;
    }

    .settings-module-header {
        flex-direction: column;
        gap: 0.75rem;
    }

    .settings-form-grid {
        grid-template-columns: 1fr;
    }

    .settings-checkbox-grid {
        grid-template-columns: 1fr;
    }

    .admin-form-actions {
        flex-direction: column;
    }

    .admin-form-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
