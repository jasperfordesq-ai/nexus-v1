<?php
// Consolidated Registration View (Single Source of Truth)
// This view adapts to the active layout (Modern, Social, CivicOne) while keeping the same form logic.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<div class="civicone-wrapper" style="padding-top: 40px;">';
} else {
    // Modern (Default)
    $hero_title = "Join the Community";
    $hero_subtitle = "Start exchanging time today.";
    $hero_gradient = 'htb-hero-gradient-create';
    $hero_type = 'Authentication';
    require dirname(__DIR__) . '/../layouts/civicone/header.php';
}
?>

<div class="auth-wrapper">
    <div class="htb-card auth-card">
        <div class="auth-card-body">

            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" method="POST" class="auth-form">
                <?= Nexus\Core\Csrf::input() ?>

                <!-- BOT PROTECTION: Honeypot & Timestamp -->
                <div style="display:none;">
                    <label for="website">Website</label>
                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="registration_start" value="<?= time() ?>">

                <!-- Name Fields Row -->
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" name="first_name" id="first_name" placeholder="e.g. Alice" required class="form-input" autocomplete="given-name">
                    </div>
                </div>

                <!-- Profile Type -->
                <div class="form-group">
                    <label for="profile_type_select" class="form-label">Profile Type</label>
                    <select name="profile_type" id="profile_type_select" required class="form-input" onchange="toggleOrgField()">
                        <option value="individual">Individual</option>
                        <option value="organisation">Organisation</option>
                    </select>
                </div>

                <!-- Organisation Name (Dynamic) -->
                <div class="form-group" id="org_field_container" style="display: none;">
                    <label for="organization_name" class="form-label">Organisation Name</label>
                    <input type="text" name="organization_name" id="organization_name" placeholder="e.g. Acme Corp" class="form-input">
                </div>

                <div class="form-group">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="last_name" placeholder="Smith" required class="form-input" autocomplete="family-name">
                    <p class="form-note">Visible only to site admins (or aggregated for Organisations).</p>
                </div>

                <script>
                    function toggleOrgField() {
                        const type = document.getElementById('profile_type_select').value;
                        const container = document.getElementById('org_field_container');
                        if (type === 'organisation') {
                            container.style.display = 'block';
                        } else {
                            container.style.display = 'none';
                        }
                    }
                </script>

                <!-- Location -->
                <div class="form-group">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" name="location" id="location" placeholder="Start typing your town or city..." required class="form-input mapbox-location-input-v2" autocomplete="address-level2">
                    <input type="hidden" name="latitude" id="location_lat">
                    <input type="hidden" name="longitude" id="location_lng">
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" name="phone" id="phone" placeholder="e.g. 087 123 4567" class="form-input" autocomplete="tel">
                    <p class="form-note">Only visible to administrators.</p>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="e.g. alice@example.com" required class="form-input" autocomplete="email">
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" required class="form-input" autocomplete="new-password" onkeyup="checkPasswordStrength()">

                    <!-- Password Strength Meter -->
                    <div id="password-rules" class="password-rules">
                        <div class="rules-title">Password Requirements:</div>
                        <div id="rule-length">❌ At least 12 characters</div>
                        <div id="rule-upper">❌ At least 1 uppercase letter</div>
                        <div id="rule-lower">❌ At least 1 lowercase letter</div>
                        <div id="rule-number">❌ At least 1 number</div>
                        <div id="rule-symbol">❌ At least 1 special character (!@#$)</div>
                    </div>
                </div>

                <!-- GDPR & Newsletter Consent -->
                <!-- GDPR & Consents -->
                <div class="form-group" style="margin-top: 20px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.9rem; color: #374151;">
                        <input type="checkbox" name="gdpr_consent" required style="margin-top: 4px; transform: scale(1.2);">
                        <span>
                            I have read and agree to the <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/terms" target="_blank" class="civic-text-indigo" style="text-decoration: underline;">Terms of Service</a> and <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/privacy" target="_blank" class="civic-text-indigo" style="text-decoration: underline;">Privacy Policy</a>, and I am 18 years of age or older.
                        </span>
                    </label>
                </div>

                <!-- Explanatory Notice - WCAG AA compliant contrast -->
                <div class="civic-bg-gray-100" style="font-size: 0.8rem; color: #0b0c0c; line-height: 1.5; margin-bottom: 20px; padding: 15px; border-radius: 0;">
                    <p style="margin-top: 0; font-weight: bold; color: #0b0c0c;">Data Protection Notice</p>
                    <p style="margin: 5px 0;"><strong>Data Controller:</strong> hOUR Timebank CLG (Ireland)</p>
                    <p><strong>Purpose of Processing:</strong> By clicking "Register," you are entering into a membership agreement with hOUR Timebank. We collect your personal data (name, location, email, and bio) solely to:</p>
                    <ul style="padding-left: 20px; margin: 5px 0;">
                        <li>Administer your account and track time credits.</li>
                        <li>Facilitate safe exchanges between members.</li>
                        <li>Send you essential community updates, swap requests, and operational news.</li>
                    </ul>
                    <p><strong>Legal Basis:</strong> This processing is necessary for the performance of a contract (your membership). As a member, you will be automatically subscribed to our community list to ensure the timebank functions effectively. You may unsubscribe from non-critical newsletters at any time, but you will still receive transactional system alerts.</p>
                    <p style="margin-bottom: 0;"><strong>Your Rights:</strong> Your data is stored securely and acts in accordance with the Data Protection Acts 1988-2018. You have the right to access, rectify, or request deletion of your data by contacting us. For full details on how we share data (e.g., with vetting services or email providers), please view our <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/privacy" target="_blank" style="color: #1d70b8; text-decoration: underline;">Privacy Policy</a>.</p>
                </div>

                <button type="submit" id="submit-btn" class="htb-btn htb-btn-primary auth-submit-btn">Create Account</button>
            </form>

            <script>
                function checkPasswordStrength() {
                    const password = document.getElementById('password').value;
                    const rules = {
                        length: password.length >= 12,
                        upper: /[A-Z]/.test(password),
                        lower: /[a-z]/.test(password),
                        number: /[0-9]/.test(password),
                        symbol: /[\W_]/.test(password)
                    };

                    let valid = true;
                    for (const [key, passed] of Object.entries(rules)) {
                        const el = document.getElementById('rule-' + key);
                        if (passed) {
                            el.innerHTML = '✅ ' + el.innerHTML.slice(2);
                            el.style.color = '#16a34a';
                        } else {
                            el.innerHTML = '❌ ' + el.innerHTML.slice(2);
                            el.style.color = '#ef4444';
                            valid = false;
                        }
                    }

                    const btn = document.getElementById('submit-btn');
                    if (valid) {
                        btn.style.opacity = '1';
                        btn.style.pointerEvents = 'auto';
                        btn.style.cursor = 'pointer';
                    } else {
                        btn.style.opacity = '0.5';
                        btn.style.pointerEvents = 'none';
                        btn.style.cursor = 'not-allowed';
                    }
                }
            </script>

            <div class="auth-login-link">
                Already have an account? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Login here</a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Scoped Refactor Styles */
    .auth-wrapper {
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
        padding: 0 15px;
        box-sizing: border-box;
        /* Prevent overflow */
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
        /* CRITICAL: Explicit padding to prevent edge flushness */
        padding: 50px;
    }

    .form-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 20px;
        width: 100%;
        /* Default full width */
    }

    .flex-1 {
        flex: 1;
        min-width: 240px;
        /* Force wrap earlier */
    }

    .form-label {
        display: block;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--htb-text-main, #1f2937);
        font-size: 1rem;
    }

    .form-input {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        /* Fix width calc */
        padding: 12px;
        /* Standard padding */
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: #0ea5e9 !important;
    }

    .form-note {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 6px;
    }

    .password-rules {
        margin-top: 12px;
        font-size: 0.85rem;
        color: #6b7280;
        background: #f9fafb;
        padding: 12px;
        border-radius: 6px;
    }

    .rules-title {
        font-weight: 700;
        margin-bottom: 6px;
    }

    .auth-submit-btn {
        width: 100%;
        font-size: 1.1rem;
        padding: 14px;
        background: var(--htb-gradient-create, linear-gradient(135deg, #6366f1 0%, #a855f7 100%));
        opacity: 0.5;
        pointer-events: none;
        border: none;
        color: white;
        border-radius: 8px;
        margin-top: 10px;
    }

    .auth-login-link {
        margin-top: 25px;
        text-align: center;
        font-size: 0.95rem;
        color: var(--htb-text-muted, #6b7280);
    }

    .auth-login-link a {
        color: #0ea5e9;
        font-weight: 600;
        text-decoration: none;
    }

    /* Desktop spacing for no-hero layout */
    @media (min-width: 601px) {
        .auth-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile Responsiveness */
    @media (max-width: 600px) {
        .auth-wrapper {
            padding-top: 120px;
            padding-left: 10px;
            padding-right: 10px;
        }

        .auth-card-body {
            padding: 25px !important;
        }

        .form-row {
            gap: 0;
        }

        .form-group {
            margin-bottom: 15px;
        }
    }

    /* ========================================
       DARK MODE FOR REGISTRATION
       ======================================== */

    [data-theme="dark"] .auth-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    }

    [data-theme="dark"] .form-label {
        color: #e2e8f0;
    }

    [data-theme="dark"] .form-input {
        background: rgba(15, 23, 42, 0.6);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    [data-theme="dark"] .form-input::placeholder {
        color: #64748b;
    }

    [data-theme="dark"] .form-input:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    [data-theme="dark"] .form-note {
        color: #94a3b8;
    }

    /* Password Rules */
    [data-theme="dark"] .password-rules {
        background: rgba(15, 23, 42, 0.6);
        color: #94a3b8;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .rules-title {
        color: #e2e8f0;
    }

    /* GDPR Consent Label */
    [data-theme="dark"] label[style*="color: #374151"] {
        color: #e2e8f0 !important;
    }

    /* Data Protection Notice Box */
    [data-theme="dark"] div[style*="background: #f9fafb"][style*="border-radius: 8px"] {
        background: rgba(15, 23, 42, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] div[style*="background: #f9fafb"] p[style*="color: #374151"] {
        color: #e2e8f0 !important;
    }

    [data-theme="dark"] div[style*="background: #f9fafb"] p,
    [data-theme="dark"] div[style*="background: #f9fafb"] li {
        color: #94a3b8 !important;
    }

    /* Login Link */
    [data-theme="dark"] .auth-login-link {
        color: #94a3b8;
    }

    [data-theme="dark"] .auth-login-link a {
        color: #818cf8;
    }

    /* Select dropdown */
    [data-theme="dark"] select.form-input {
        background: rgba(15, 23, 42, 0.6);
        color: #f1f5f9;
    }

    [data-theme="dark"] select.form-input option {
        background: #1e293b;
        color: #f1f5f9;
    }
</style>

<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/civicone/footer.php';
}
?>