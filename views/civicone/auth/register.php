<?php
/**
 * CivicOne View: Register
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Create an Account';
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Create an account</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Join the community and start exchanging time today.</p>

        <form action="<?= $basePath ?>/register" method="POST" id="register-form">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- BOT PROTECTION: Honeypot & Timestamp -->
            <div class="civicone-honeypot" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="registration_start" value="<?= time() ?>">

            <!-- Name Fields -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="first_name">First name</label>
                <input type="text" name="first_name" id="first_name" class="govuk-input govuk-input--width-20" required autocomplete="given-name" spellcheck="false">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="last_name">Last name</label>
                <div id="last_name-hint" class="govuk-hint">Visible only to site administrators</div>
                <input type="text" name="last_name" id="last_name" class="govuk-input govuk-input--width-20" required autocomplete="family-name" aria-describedby="last_name-hint" spellcheck="false">
            </div>

            <!-- Profile Type -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="profile_type_select">Profile type</label>
                <select name="profile_type" id="profile_type_select" class="govuk-select" required onchange="toggleOrgField()">
                    <option value="individual">Individual</option>
                    <option value="organisation">Organisation</option>
                </select>
            </div>

            <!-- Organisation Name (Dynamic) -->
            <div class="govuk-form-group hidden" id="org_field_container">
                <label class="govuk-label" for="organization_name">Organisation name</label>
                <input type="text" name="organization_name" id="organization_name" class="govuk-input">
            </div>

            <!-- Location -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="location">Location</label>
                <div id="location-hint" class="govuk-hint">Start typing your town or city</div>
                <input type="text" name="location" id="location" class="govuk-input mapbox-location-input-v2" required autocomplete="address-level2" aria-describedby="location-hint">
                <input type="hidden" name="latitude" id="location_lat">
                <input type="hidden" name="longitude" id="location_lng">
            </div>

            <!-- Phone -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="phone">Phone number (optional)</label>
                <div id="phone-hint" class="govuk-hint">Only visible to administrators</div>
                <input type="tel" name="phone" id="phone" class="govuk-input govuk-input--width-20" autocomplete="tel" aria-describedby="phone-hint">
            </div>

            <!-- Email -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="email">Email address</label>
                <input type="email" name="email" id="email" class="govuk-input" required autocomplete="email">
            </div>

            <!-- Password -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="password">Password</label>
                <div id="password-hint" class="govuk-hint">Must be at least 12 characters with uppercase, lowercase, number and special character</div>
                <input type="password" name="password" id="password" class="govuk-input" required autocomplete="new-password" aria-describedby="password-hint password-rules" onkeyup="checkPasswordStrength()">

                <!-- Password Strength Indicator -->
                <div id="password-rules" class="govuk-!-margin-top-3" role="status" aria-live="polite">
                    <p class="govuk-body-s govuk-!-margin-bottom-2"><strong>Password requirements:</strong></p>
                    <ul class="govuk-list govuk-body-s">
                        <li id="rule-length" class="password-rule password-rule--invalid"><span aria-hidden="true">❌</span> At least 12 characters</li>
                        <li id="rule-upper" class="password-rule password-rule--invalid"><span aria-hidden="true">❌</span> At least 1 uppercase letter</li>
                        <li id="rule-lower" class="password-rule password-rule--invalid"><span aria-hidden="true">❌</span> At least 1 lowercase letter</li>
                        <li id="rule-number" class="password-rule password-rule--invalid"><span aria-hidden="true">❌</span> At least 1 number</li>
                        <li id="rule-symbol" class="password-rule password-rule--invalid"><span aria-hidden="true">❌</span> At least 1 special character</li>
                    </ul>
                </div>
            </div>

            <!-- GDPR Consent -->
            <div class="govuk-form-group govuk-!-margin-top-6">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                        <h2 class="govuk-fieldset__heading">Terms and conditions</h2>
                    </legend>
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="gdpr_consent" name="gdpr_consent" type="checkbox" required>
                            <label class="govuk-label govuk-checkboxes__label" for="gdpr_consent">
                                I have read and agree to the <a href="<?= $basePath ?>/terms" target="_blank" class="govuk-link">Terms of Service</a> and <a href="<?= $basePath ?>/privacy" target="_blank" class="govuk-link">Privacy Policy</a>, and I am 18 years of age or older.
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>

            <!-- Data Protection Notice -->
            <div class="govuk-inset-text govuk-!-margin-bottom-6">
                <h3 class="govuk-heading-s">
                    <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" aria-hidden="true"></i>
                    Data Protection Notice
                </h3>
                <p class="govuk-body-s govuk-!-margin-bottom-2"><strong>Data Controller:</strong> hOUR Timebank CLG (Ireland)</p>
                <p class="govuk-body-s govuk-!-margin-bottom-2"><strong>Purpose:</strong> We collect your personal data to administer your account, facilitate exchanges, and send community updates.</p>
                <p class="govuk-body-s govuk-!-margin-bottom-2"><strong>Legal Basis:</strong> Performance of contract (your membership). You may unsubscribe from non-critical newsletters at any time.</p>
                <p class="govuk-body-s govuk-!-margin-bottom-0"><strong>Your Rights:</strong> Access, rectify, or request deletion of your data. See our <a href="<?= $basePath ?>/privacy" target="_blank" class="govuk-link">Privacy Policy</a>.</p>
            </div>

            <button type="submit" id="submit-btn" class="govuk-button" data-module="govuk-button">
                Create account
            </button>
        </form>

        <p class="govuk-body govuk-!-margin-top-6">
            Already have an account? <a href="<?= $basePath ?>/login" class="govuk-link">Sign in</a>
        </p>

    </div>
</div>

<script>
function toggleOrgField() {
    var type = document.getElementById('profile_type_select').value;
    var container = document.getElementById('org_field_container');
    if (type === 'organisation') {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

function checkPasswordStrength() {
    var password = document.getElementById('password').value;
    var rules = {
        length: password.length >= 12,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        symbol: /[\W_]/.test(password)
    };

    var ruleKeys = ['length', 'upper', 'lower', 'number', 'symbol'];
    for (var i = 0; i < ruleKeys.length; i++) {
        var key = ruleKeys[i];
        var el = document.getElementById('rule-' + key);
        var span = el.querySelector('span');
        if (rules[key]) {
            span.textContent = '✅';
            el.classList.add('password-rule--valid');
            el.classList.remove('password-rule--invalid');
        } else {
            span.textContent = '❌';
            el.classList.add('password-rule--invalid');
            el.classList.remove('password-rule--valid');
        }
    }
}
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
