<?php
/**
 * Privacy Policy - CivicOne Theme (GOV.UK Compliant)
 * Tenant: Hour Timebank Ireland (tenant 2)
 *
 * This file uses proper GOV.UK Frontend markup following the
 * official Design System patterns from:
 * https://github.com/alphagov/govuk-frontend
 *
 * GDPR Compliant for Ireland/EU
 * Updated: January 2026
 */
$pageTitle = 'Privacy Policy';
$hideHero = true;

require __DIR__ . '/../../../../../layouts/civicone/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>

<!-- Breadcrumbs (GOV.UK Pattern) -->
<div class="govuk-breadcrumbs govuk-!-margin-bottom-6">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/legal">Legal</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Privacy Policy</li>
    </ol>
</div>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Privacy Policy</h1>

        <p class="govuk-body-l">How we collect, use, and protect your personal data under GDPR.</p>

        <p class="govuk-body govuk-!-margin-bottom-6">
            <strong class="govuk-tag govuk-tag--grey">Last updated: <?= date('j F Y') ?></strong>
        </p>

        <!-- Introduction -->
        <div class="govuk-inset-text">
            <p>hOUR Timebank Ireland is committed to protecting your privacy and ensuring your personal data is handled responsibly in accordance with the <strong>General Data Protection Regulation (GDPR)</strong> and the Irish <strong>Data Protection Acts 1988-2018</strong>.</p>
        </div>

        <!-- Contents -->
        <nav class="govuk-!-margin-bottom-8" aria-label="Contents">
            <h2 class="govuk-heading-s">Contents</h2>
            <ol class="govuk-list govuk-list--number">
                <li><a class="govuk-link" href="#data-controller">Data Controller</a></li>
                <li><a class="govuk-link" href="#data-collection">Information We Collect</a></li>
                <li><a class="govuk-link" href="#legal-basis">Legal Basis for Processing</a></li>
                <li><a class="govuk-link" href="#data-usage">How We Use Your Data</a></li>
                <li><a class="govuk-link" href="#data-sharing">Data Sharing</a></li>
                <li><a class="govuk-link" href="#data-protection">How We Protect Your Data</a></li>
                <li><a class="govuk-link" href="#your-rights">Your GDPR Rights</a></li>
                <li><a class="govuk-link" href="#cookies">Cookies</a></li>
                <li><a class="govuk-link" href="#data-retention">Data Retention</a></li>
                <li><a class="govuk-link" href="#complaints">Complaints</a></li>
            </ol>
        </nav>

        <!-- Section 1: Data Controller -->
        <h2 class="govuk-heading-l" id="data-controller">1. Data Controller</h2>

        <p class="govuk-body">The data controller responsible for your personal data is:</p>

        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Legal Name</dt>
                <dd class="govuk-summary-list__value">hOUR Timebank CLG (Company Limited by Guarantee)</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Business Name</dt>
                <dd class="govuk-summary-list__value">Timebank Ireland</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Charity Registration</dt>
                <dd class="govuk-summary-list__value">RCN 20162023 (Charities Regulator)</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Address</dt>
                <dd class="govuk-summary-list__value">21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Data Protection Contact</dt>
                <dd class="govuk-summary-list__value"><a href="mailto:jasper@hour-timebank.ie" class="govuk-link">jasper@hour-timebank.ie</a></dd>
            </div>
        </dl>

        <!-- Section 2: Data Collection -->
        <h2 class="govuk-heading-l" id="data-collection">2. Information We Collect</h2>

        <p class="govuk-body">We collect only the information necessary to provide our timebanking services:</p>

        <table class="govuk-table">
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">Data Type</th>
                    <th scope="col" class="govuk-table__header">Purpose</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell"><strong>Account Information</strong></td>
                    <td class="govuk-table__cell">Name, email, password (encrypted) - Required to manage your account</td>
                </tr>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell"><strong>Profile Details</strong></td>
                    <td class="govuk-table__cell">Bio, skills, location, photo - Helps connect you with members</td>
                </tr>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell"><strong>Contact Information</strong></td>
                    <td class="govuk-table__cell">Phone (optional), address - For arranging exchanges</td>
                </tr>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell"><strong>Activity Data</strong></td>
                    <td class="govuk-table__cell">Exchanges, messages, time credits - Platform functionality</td>
                </tr>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell"><strong>Device Information</strong></td>
                    <td class="govuk-table__cell">Browser type, IP address - Security and troubleshooting</td>
                </tr>
            </tbody>
        </table>

        <!-- Section 3: Legal Basis -->
        <h2 class="govuk-heading-l" id="legal-basis">3. Legal Basis for Processing</h2>

        <p class="govuk-body">Under GDPR, we process your personal data based on:</p>

        <dl class="govuk-summary-list govuk-summary-list--no-border">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Contract Performance</dt>
                <dd class="govuk-summary-list__value">Processing necessary to provide our timebanking services to you</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Consent</dt>
                <dd class="govuk-summary-list__value">Where you have given explicit consent (e.g., marketing communications)</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Legitimate Interests</dt>
                <dd class="govuk-summary-list__value">Improving our services, preventing fraud, platform security</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Legal Obligation</dt>
                <dd class="govuk-summary-list__value">Complying with Irish and EU legal requirements</dd>
            </div>
        </dl>

        <!-- Section 4: Data Usage -->
        <h2 class="govuk-heading-l" id="data-usage">4. How We Use Your Data</h2>

        <p class="govuk-body">Your data is used exclusively for:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li><strong>Service Delivery:</strong> Facilitating time exchanges and community connections</li>
            <li><strong>Communication:</strong> Sending updates, notifications, and messages</li>
            <li><strong>Security:</strong> Protecting your account and preventing fraud</li>
            <li><strong>Improvement:</strong> Analysing usage patterns to enhance features (anonymised)</li>
            <li><strong>Legal Compliance:</strong> Meeting regulatory requirements</li>
            <li><strong>Charitable Reporting:</strong> Aggregated statistics for charity reporting</li>
        </ul>

        <div class="govuk-inset-text">
            <strong>We do not sell your personal data to third parties.</strong> Your information is never shared with advertisers or data brokers.
        </div>

        <!-- Section 5: Data Sharing -->
        <h2 class="govuk-heading-l" id="data-sharing">5. Data Sharing</h2>

        <p class="govuk-body">We may share your data with:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li><strong>Other Members:</strong> Profile information visible to verified members</li>
            <li><strong>Partner Organisations:</strong> Community coordinators can see membership details</li>
            <li><strong>Service Providers:</strong> Trusted providers (hosting, email) under strict data agreements</li>
            <li><strong>Legal Authorities:</strong> When required by law</li>
        </ul>

        <p class="govuk-body">All service providers are bound by GDPR-compliant data processing agreements.</p>

        <!-- Section 6: Data Protection -->
        <h2 class="govuk-heading-l" id="data-protection">6. How We Protect Your Data</h2>

        <p class="govuk-body">We implement robust security measures:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li><strong>Encryption:</strong> Data encrypted in transit (TLS/HTTPS) and at rest</li>
            <li><strong>Secure Passwords:</strong> Passwords hashed using industry-standard algorithms</li>
            <li><strong>Access Controls:</strong> Strict policies limiting who can access your data</li>
            <li><strong>Regular Audits:</strong> Security reviews and practice updates</li>
            <li><strong>EU-Based Hosting:</strong> Data stored within the European Union</li>
        </ul>

        <!-- Section 7: Your Rights -->
        <h2 class="govuk-heading-l" id="your-rights">7. Your GDPR Rights</h2>

        <p class="govuk-body">Under GDPR, you have the following rights:</p>

        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Right of Access</dt>
                <dd class="govuk-summary-list__value">Request a copy of all personal data we hold about you</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Right to Rectification</dt>
                <dd class="govuk-summary-list__value">Correct any inaccurate or incomplete information</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Right to Erasure</dt>
                <dd class="govuk-summary-list__value">Request deletion of your data ("right to be forgotten")</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Right to Restrict</dt>
                <dd class="govuk-summary-list__value">Limit how we process your data in certain circumstances</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Right to Portability</dt>
                <dd class="govuk-summary-list__value">Receive your data in a structured, machine-readable format</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Right to Object</dt>
                <dd class="govuk-summary-list__value">Object to processing for direct marketing or legitimate interests</dd>
            </div>
        </dl>

        <p class="govuk-body">To exercise your rights, contact us at <a href="mailto:jasper@hour-timebank.ie" class="govuk-link">jasper@hour-timebank.ie</a>. We will respond within <strong>one month</strong> as required by GDPR.</p>

        <!-- Section 8: Cookies -->
        <h2 class="govuk-heading-l" id="cookies">8. Cookies & Tracking</h2>

        <p class="govuk-body">We use cookies to enhance your experience:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li><strong>Essential Cookies:</strong> Required for login, security, and basic functionality (cannot be disabled)</li>
            <li><strong>Preference Cookies:</strong> Remember settings like theme preference</li>
            <li><strong>Analytics Cookies:</strong> Help us understand platform usage (anonymised)</li>
        </ul>

        <div class="govuk-inset-text">
            We do <strong>not</strong> use advertising cookies or tracking pixels. We do not share data with advertising networks.
        </div>

        <p class="govuk-body">
            <a href="<?= $basePath ?>/legal/cookies" class="govuk-link">Read our Cookie Policy</a>
        </p>

        <!-- Section 9: Data Retention -->
        <h2 class="govuk-heading-l" id="data-retention">9. Data Retention</h2>

        <p class="govuk-body">We retain your data only as long as necessary:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li><strong>Active Accounts:</strong> Data kept while your account is active</li>
            <li><strong>Inactive Accounts:</strong> May be anonymised after 24 months of inactivity</li>
            <li><strong>Deleted Accounts:</strong> Personal data removed within 30 days</li>
            <li><strong>Transaction Records:</strong> Retained up to 7 years for legal/charity reporting</li>
            <li><strong>Backup Data:</strong> Removed within 90 days of deletion</li>
        </ul>

        <!-- Section 10: Complaints -->
        <h2 class="govuk-heading-l" id="complaints">10. Complaints</h2>

        <p class="govuk-body">If you are not satisfied with how we handle your data, you have the right to lodge a complaint with the <strong>Data Protection Commission</strong> (DPC):</p>

        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Authority</dt>
                <dd class="govuk-summary-list__value">Data Protection Commission</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Address</dt>
                <dd class="govuk-summary-list__value">21 Fitzwilliam Square South, Dublin 2, D02 RD28, Ireland</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Website</dt>
                <dd class="govuk-summary-list__value"><a href="https://www.dataprotection.ie" class="govuk-link" target="_blank" rel="noopener">www.dataprotection.ie</a></dd>
            </div>
        </dl>

        <p class="govuk-body">We encourage you to contact us first so we can try to resolve your concerns directly.</p>

        <!-- Contact -->
        <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

        <h2 class="govuk-heading-m">Questions about your privacy?</h2>
        <p class="govuk-body">If you have questions about this policy, want to exercise your data rights, or have privacy concerns, please contact us:</p>
        <p class="govuk-body">
            <a href="mailto:jasper@hour-timebank.ie" class="govuk-link">jasper@hour-timebank.ie</a>
        </p>

    </div>

    <div class="govuk-grid-column-one-third">
        <!-- Related content sidebar -->
        <aside class="govuk-!-margin-top-6">
            <h2 class="govuk-heading-s">Related content</h2>
            <nav aria-label="Related content">
                <ul class="govuk-list govuk-!-font-size-16">
                    <li><a href="<?= $basePath ?>/terms" class="govuk-link">Terms of Service</a></li>
                    <li><a href="<?= $basePath ?>/legal/cookies" class="govuk-link">Cookie Policy</a></li>
                    <li><a href="<?= $basePath ?>/accessibility" class="govuk-link">Accessibility Statement</a></li>
                    <li><a href="<?= $basePath ?>/legal" class="govuk-link">Legal Hub</a></li>
                </ul>
            </nav>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/../../../../../layouts/civicone/footer.php'; ?>
