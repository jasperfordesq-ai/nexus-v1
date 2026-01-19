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
    require dirname(__DIR__) . '/../layouts/modern/header.php';
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
                    <label for="profile_type" class="form-label">Profile Type</label>
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
                            I have read and agree to the <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/terms" target="_blank" style="color: #6366f1; text-decoration: underline;">Terms of Service</a> and <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/privacy" target="_blank" style="color: #6366f1; text-decoration: underline;">Privacy Policy</a>, and I am 18 years of age or older.
                        </span>
                    </label>
                </div>

                <!-- Explanatory Notice -->
                <div style="font-size: 0.8rem; color: #6b7280; line-height: 1.5; margin-bottom: 20px; background: #f9fafb; padding: 15px; border-radius: 8px;">
                    <p style="margin-top: 0; font-weight: bold; color: #374151;">Data Protection Notice</p>
                    <p style="margin: 5px 0;"><strong>Data Controller:</strong> hOUR Timebank CLG (Ireland)</p>
                    <p><strong>Purpose of Processing:</strong> By clicking "Register," you are entering into a membership agreement with hOUR Timebank. We collect your personal data (name, location, email, and bio) solely to:</p>
                    <ul style="padding-left: 20px; margin: 5px 0;">
                        <li>Administer your account and track time credits.</li>
                        <li>Facilitate safe exchanges between members.</li>
                        <li>Send you essential community updates, swap requests, and operational news.</li>
                    </ul>
                    <p><strong>Legal Basis:</strong> This processing is necessary for the performance of a contract (your membership). As a member, you will be automatically subscribed to our community list to ensure the timebank functions effectively. You may unsubscribe from non-critical newsletters at any time, but you will still receive transactional system alerts.</p>
                    <p style="margin-bottom: 0;"><strong>Your Rights:</strong> Your data is stored securely and acts in accordance with the Data Protection Acts 1988-2018. You have the right to access, rectify, or request deletion of your data by contacting us. For full details on how we share data (e.g., with vetting services or email providers), please view our <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/privacy" target="_blank" style="color: #6366f1; text-decoration: underline;">Privacy Policy</a>.</p>
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

<!-- Auth CSS now loaded via header.php: auth.min.css -->

<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/modern/footer.php';
}
?>