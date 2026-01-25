<?php
/**
 * Privacy Policy Content - Ireland Timebank (SHARED)
 * Theme Color: Indigo (#6366f1)
 * Tenant: Hour Timebank Ireland
 * Legal Entity: hOUR Timebank CLG (RCN 20162023)
 * GDPR Compliant for Ireland/EU
 *
 * This file contains ONLY the content - no header/footer includes.
 * Include this from theme-specific dispatcher files.
 */

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'hOUR Timebank Ireland';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'hOUR Timebank Ireland';
}
?>

<div id="privacy-glass-wrapper">
    <div class="privacy-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="privacy-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-shield-halved"></i></span>
                Privacy Policy
            </h1>
            <p>How we collect, use, and protect your personal data under GDPR</p>
            <span class="last-updated">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= date('F j, Y') ?>
            </span>
        </div>

        <!-- Quick Navigation -->
        <div class="privacy-quick-nav">
            <a href="#data-controller" class="privacy-nav-btn">
                <i class="fa-solid fa-building"></i> Data Controller
            </a>
            <a href="#data-collection" class="privacy-nav-btn">
                <i class="fa-solid fa-database"></i> Data Collection
            </a>
            <a href="#your-rights" class="privacy-nav-btn">
                <i class="fa-solid fa-user-shield"></i> Your Rights
            </a>
            <a href="#contact" class="privacy-nav-btn">
                <i class="fa-solid fa-envelope"></i> Contact
            </a>
        </div>

        <!-- Introduction -->
        <div class="privacy-section highlight">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-handshake"></i></div>
                <h2>Our Commitment to Your Privacy</h2>
            </div>
            <p>hOUR Timebank Ireland is committed to protecting your privacy and ensuring your personal data is handled responsibly in accordance with the <strong>General Data Protection Regulation (GDPR)</strong> and the Irish <strong>Data Protection Acts 1988-2018</strong>.</p>
            <p>This policy explains what information we collect, why we collect it, how we use it, and your rights regarding your personal data. We believe in <strong>transparency</strong> and <strong>user control</strong>.</p>
        </div>

        <!-- Data Controller -->
        <div class="privacy-section" id="data-controller">
            <div class="section-header">
                <div class="section-number">1</div>
                <h2>Data Controller</h2>
            </div>
            <p>The data controller responsible for your personal data is:</p>

            <div class="entity-info">
                <dl>
                    <dt>Legal Name</dt>
                    <dd>hOUR Timebank CLG (Company Limited by Guarantee)</dd>

                    <dt>Registered Business Name</dt>
                    <dd>Timebank Ireland</dd>

                    <dt>Charity Registration</dt>
                    <dd>RCN 20162023 (Charities Regulator)</dd>

                    <dt>Registered Address</dt>
                    <dd>21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</dd>

                    <dt>Data Protection Contact</dt>
                    <dd><a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></dd>
                </dl>
            </div>

            <p>If you are a member of a Partner Organisation using our platform, that organisation may also be a data controller for data they collect directly from you. Please refer to their privacy policy for details.</p>
        </div>

        <!-- Data Collection -->
        <div class="privacy-section" id="data-collection">
            <div class="section-header">
                <div class="section-number">2</div>
                <h2>Information We Collect</h2>
            </div>
            <p>We collect only the information necessary to provide our timebanking services:</p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data Type</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Account Information</strong></td>
                        <td>Name, email address, password (encrypted) — Required to create and manage your account</td>
                    </tr>
                    <tr>
                        <td><strong>Profile Details</strong></td>
                        <td>Bio, skills, location, photo — Helps connect you with community members</td>
                    </tr>
                    <tr>
                        <td><strong>Contact Information</strong></td>
                        <td>Phone number (optional), address — For arranging exchanges if you choose to share</td>
                    </tr>
                    <tr>
                        <td><strong>Activity Data</strong></td>
                        <td>Exchanges, messages, time credits — Essential for platform functionality</td>
                    </tr>
                    <tr>
                        <td><strong>Device Information</strong></td>
                        <td>Browser type, IP address, device type — Used for security and troubleshooting</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Legal Basis -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">3</div>
                <h2>Legal Basis for Processing</h2>
            </div>
            <p>Under GDPR, we process your personal data based on the following legal grounds:</p>

            <div class="legal-basis-grid">
                <div class="legal-basis-item">
                    <span class="basis-icon"><i class="fa-solid fa-file-signature"></i></span>
                    <strong>Contract Performance</strong>
                    <span>Processing necessary to provide our timebanking services to you</span>
                </div>
                <div class="legal-basis-item">
                    <span class="basis-icon"><i class="fa-solid fa-check-circle"></i></span>
                    <strong>Consent</strong>
                    <span>Where you have given explicit consent (e.g., marketing communications)</span>
                </div>
                <div class="legal-basis-item">
                    <span class="basis-icon"><i class="fa-solid fa-scale-balanced"></i></span>
                    <strong>Legitimate Interests</strong>
                    <span>Improving our services, preventing fraud, and ensuring platform security</span>
                </div>
                <div class="legal-basis-item">
                    <span class="basis-icon"><i class="fa-solid fa-gavel"></i></span>
                    <strong>Legal Obligation</strong>
                    <span>Complying with Irish and EU legal requirements</span>
                </div>
            </div>
        </div>

        <!-- Data Usage -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">4</div>
                <h2>How We Use Your Data</h2>
            </div>
            <p>Your data is used exclusively for the following purposes:</p>
            <ul>
                <li><strong>Service Delivery:</strong> Facilitating time exchanges and community connections</li>
                <li><strong>Communication:</strong> Sending important updates, notifications, and messages from other members</li>
                <li><strong>Security:</strong> Protecting your account and preventing fraud or abuse</li>
                <li><strong>Improvement:</strong> Analysing usage patterns to enhance platform features (anonymised)</li>
                <li><strong>Legal Compliance:</strong> Meeting regulatory requirements under Irish and EU law</li>
                <li><strong>Charitable Reporting:</strong> Aggregated, anonymised statistics for charity reporting purposes</li>
            </ul>
            <p><strong>We do not sell your personal data to third parties.</strong> Your information is never shared with advertisers or data brokers.</p>
        </div>

        <!-- Data Sharing -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">5</div>
                <h2>Data Sharing</h2>
            </div>
            <p>We may share your data with:</p>
            <ul>
                <li><strong>Other Members:</strong> Your profile information is visible to verified members to facilitate exchanges</li>
                <li><strong>Partner Organisations:</strong> If you join a specific community, that organisation's coordinators can see your membership details</li>
                <li><strong>Service Providers:</strong> Trusted providers who help us operate the platform (hosting, email delivery) under strict data processing agreements</li>
                <li><strong>Legal Authorities:</strong> When required by law or to protect our legal rights</li>
            </ul>
            <p>All service providers are bound by GDPR-compliant data processing agreements and process data only on our instructions.</p>
        </div>

        <!-- Data Protection -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">6</div>
                <h2>How We Protect Your Data</h2>
            </div>
            <p>We implement robust security measures to safeguard your information:</p>
            <ul>
                <li><strong>Encryption:</strong> All data is encrypted in transit (TLS/HTTPS) and at rest</li>
                <li><strong>Secure Passwords:</strong> Passwords are hashed using industry-standard algorithms</li>
                <li><strong>Access Controls:</strong> Strict internal policies limit who can access your data</li>
                <li><strong>Regular Audits:</strong> We conduct security reviews and update our practices accordingly</li>
                <li><strong>EU-Based Hosting:</strong> Your data is stored within the European Union</li>
            </ul>
        </div>

        <!-- Your Rights -->
        <div class="privacy-section highlight" id="your-rights">
            <div class="section-header">
                <div class="section-number">7</div>
                <h2>Your GDPR Rights</h2>
            </div>
            <p>Under GDPR, you have the following rights regarding your personal data:</p>

            <div class="rights-grid">
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-eye"></i></span>
                    <h4>Right of Access</h4>
                    <p>Request a copy of all personal data we hold about you (Subject Access Request)</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-pen"></i></span>
                    <h4>Right to Rectification</h4>
                    <p>Correct any inaccurate or incomplete information we hold</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-trash"></i></span>
                    <h4>Right to Erasure</h4>
                    <p>Request deletion of your data ("right to be forgotten")</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-hand"></i></span>
                    <h4>Right to Restrict</h4>
                    <p>Limit how we process your data in certain circumstances</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-download"></i></span>
                    <h4>Right to Portability</h4>
                    <p>Receive your data in a structured, machine-readable format</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-ban"></i></span>
                    <h4>Right to Object</h4>
                    <p>Object to processing based on legitimate interests or for direct marketing</p>
                </div>
            </div>

            <p>To exercise any of these rights, contact us at <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>. We will respond within <strong>one month</strong> as required by GDPR.</p>
        </div>

        <!-- Cookies -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">8</div>
                <h2>Cookies & Tracking</h2>
            </div>
            <p>We use cookies to enhance your experience on our platform:</p>
            <ul>
                <li><strong>Essential Cookies:</strong> Required for login, security, and basic functionality — these cannot be disabled</li>
                <li><strong>Preference Cookies:</strong> Remember your settings like theme preference and language</li>
                <li><strong>Analytics Cookies:</strong> Help us understand how people use the platform (anonymised data only)</li>
            </ul>
            <p>We do <strong>not</strong> use advertising cookies or tracking pixels. We do not share data with advertising networks. You can manage non-essential cookies through your browser settings.</p>
        </div>

        <!-- Data Retention -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">9</div>
                <h2>Data Retention</h2>
            </div>
            <p>We retain your data only as long as necessary:</p>
            <ul>
                <li><strong>Active Accounts:</strong> Data is kept while your account remains active</li>
                <li><strong>Inactive Accounts:</strong> Accounts inactive for 24 months may be anonymised or deleted after notice</li>
                <li><strong>Deleted Accounts:</strong> Personal data is removed within 30 days of account deletion</li>
                <li><strong>Transaction Records:</strong> May be retained for up to 7 years for legal and charity reporting purposes</li>
                <li><strong>Backup Data:</strong> Removed from backups within 90 days of deletion</li>
            </ul>
        </div>

        <!-- International Transfers -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">10</div>
                <h2>International Data Transfers</h2>
            </div>
            <p>Your data is primarily stored and processed within the European Union. If we need to transfer data outside the EU/EEA, we ensure appropriate safeguards are in place:</p>
            <ul>
                <li>EU-approved Standard Contractual Clauses (SCCs)</li>
                <li>Adequacy decisions by the European Commission</li>
                <li>Other GDPR-compliant transfer mechanisms</li>
            </ul>
        </div>

        <!-- Children -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">11</div>
                <h2>Children's Privacy</h2>
            </div>
            <p>Our platform is intended for users aged <strong>18 years and older</strong>. We do not knowingly collect personal data from anyone under 18. If you believe a child has provided us with personal data, please contact us immediately and we will delete such information.</p>
        </div>

        <!-- Complaints -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">12</div>
                <h2>Complaints</h2>
            </div>
            <p>If you are not satisfied with how we handle your personal data, you have the right to lodge a complaint with the <strong>Data Protection Commission</strong> (DPC), Ireland's supervisory authority:</p>
            <div class="entity-info">
                <dl>
                    <dt>Authority</dt>
                    <dd>Data Protection Commission</dd>

                    <dt>Address</dt>
                    <dd>21 Fitzwilliam Square South, Dublin 2, D02 RD28, Ireland</dd>

                    <dt>Website</dt>
                    <dd><a href="https://www.dataprotection.ie" target="_blank" rel="noopener">www.dataprotection.ie</a></dd>
                </dl>
            </div>
            <p>We encourage you to contact us first so we can try to resolve your concerns directly.</p>
        </div>

        <!-- Changes -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-number">13</div>
                <h2>Changes to This Policy</h2>
            </div>
            <p>We may update this Privacy Policy to reflect changes in our practices, technology, or legal requirements. When we make significant changes:</p>
            <ul>
                <li>We will notify you via email or platform notification</li>
                <li>The updated date will be shown at the top of this page</li>
                <li>For material changes, we may seek your renewed consent where required</li>
            </ul>
        </div>

        <!-- Contact CTA -->
        <div class="privacy-cta" id="contact">
            <h2><i class="fa-solid fa-envelope"></i> Questions About Your Privacy?</h2>
            <p>We're here to help. If you have any questions about this policy, want to exercise your data rights, or have any privacy concerns, please don't hesitate to contact us.</p>
            <a href="mailto:jasper@hour-timebank.ie" class="privacy-cta-btn">
                <i class="fa-solid fa-paper-plane"></i>
                jasper@hour-timebank.ie
            </a>
        </div>

    </div>
</div>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('#privacy-glass-wrapper .privacy-nav-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// Button press states
document.querySelectorAll('#privacy-glass-wrapper .privacy-nav-btn, #privacy-glass-wrapper .privacy-cta-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
</script>
