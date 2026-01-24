<?php
// Consolidated Registration View (Single Source of Truth)
// This view adapts to the active layout (Modern, Social, CivicOne) while keeping the same form logic.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<link rel="stylesheet" href="' . Nexus\Core\TenantContext::getBasePath() . '/assets/css/civicone-auth-register.css">';
    echo '<div class="civicone-wrapper civicone-wrapper--auth">';
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
                <div class="honeypot-field" aria-hidden="true">
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
                <div class="form-group auth-org-field" id="org_field_container">
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
                        var type = document.getElementById('profile_type_select').value;
                        var container = document.getElementById('org_field_container');
                        if (type === 'organisation') {
                            container.classList.add('visible');
                        } else {
                            container.classList.remove('visible');
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
                    <div id="password-rules" class="password-rules" role="status" aria-live="polite">
                        <div class="rules-title">Password Requirements:</div>
                        <div id="rule-length" class="password-rule password-rule--invalid">❌ At least 12 characters</div>
                        <div id="rule-upper" class="password-rule password-rule--invalid">❌ At least 1 uppercase letter</div>
                        <div id="rule-lower" class="password-rule password-rule--invalid">❌ At least 1 lowercase letter</div>
                        <div id="rule-number" class="password-rule password-rule--invalid">❌ At least 1 number</div>
                        <div id="rule-symbol" class="password-rule password-rule--invalid">❌ At least 1 special character (!@#$)</div>
                    </div>
                </div>

                <!-- GDPR & Consents -->
                <div class="form-group auth-consent-group">
                    <label class="auth-consent-label">
                        <input type="checkbox" name="gdpr_consent" required class="auth-consent-checkbox">
                        <span>
                            I have read and agree to the <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/terms" target="_blank" class="auth-link-underline">Terms of Service</a> and <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/privacy" target="_blank" class="auth-link-underline">Privacy Policy</a>, and I am 18 years of age or older.
                        </span>
                    </label>
                </div>

                <!-- Explanatory Notice - WCAG AA compliant -->
                <div class="auth-data-notice">
                    <p class="auth-data-notice-title">Data Protection Notice</p>
                    <p><strong>Data Controller:</strong> hOUR Timebank CLG (Ireland)</p>
                    <p><strong>Purpose of Processing:</strong> By clicking "Register," you are entering into a membership agreement with hOUR Timebank. We collect your personal data (name, location, email, and bio) solely to:</p>
                    <ul class="auth-data-notice-list">
                        <li>Administer your account and track time credits.</li>
                        <li>Facilitate safe exchanges between members.</li>
                        <li>Send you essential community updates, swap requests, and operational news.</li>
                    </ul>
                    <p><strong>Legal Basis:</strong> This processing is necessary for the performance of a contract (your membership). As a member, you will be automatically subscribed to our community list to ensure the timebank functions effectively. You may unsubscribe from non-critical newsletters at any time, but you will still receive transactional system alerts.</p>
                    <p><strong>Your Rights:</strong> Your data is stored securely and acts in accordance with the Data Protection Acts 1988-2018. You have the right to access, rectify, or request deletion of your data by contacting us. For full details on how we share data (e.g., with vetting services or email providers), please view our <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/privacy" target="_blank" class="auth-link-underline">Privacy Policy</a>.</p>
                </div>

                <button type="submit" id="submit-btn" class="htb-btn htb-btn-primary auth-submit-btn">Create Account</button>
            </form>

            <script>
                function checkPasswordStrength() {
                    var password = document.getElementById('password').value;
                    var rules = {
                        length: password.length >= 12,
                        upper: /[A-Z]/.test(password),
                        lower: /[a-z]/.test(password),
                        number: /[0-9]/.test(password),
                        symbol: /[\W_]/.test(password)
                    };

                    var valid = true;
                    var ruleKeys = ['length', 'upper', 'lower', 'number', 'symbol'];
                    for (var i = 0; i < ruleKeys.length; i++) {
                        var key = ruleKeys[i];
                        var passed = rules[key];
                        var el = document.getElementById('rule-' + key);
                        if (passed) {
                            el.innerHTML = '✅ ' + el.innerHTML.slice(2);
                            el.classList.remove('password-rule--invalid');
                            el.classList.add('password-rule--valid');
                        } else {
                            el.innerHTML = '❌ ' + el.innerHTML.slice(2);
                            el.classList.remove('password-rule--valid');
                            el.classList.add('password-rule--invalid');
                            valid = false;
                        }
                    }

                    var btn = document.getElementById('submit-btn');
                    if (valid) {
                        btn.classList.add('auth-submit-btn--enabled');
                    } else {
                        btn.classList.remove('auth-submit-btn--enabled');
                    }
                }
            </script>

            <div class="auth-login-link">
                Already have an account? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Login here</a>
            </div>
        </div>
    </div>
</div>

<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/civicone/footer.php';
}
?>