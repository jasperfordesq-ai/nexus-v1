<?php
/**
 * Terms of Service - CivicOne Theme (GOV.UK Compliant)
 * Tenant: Hour Timebank Ireland (tenant 2)
 *
 * This file uses proper GOV.UK Frontend markup following the
 * official Design System patterns from:
 * https://github.com/alphagov/govuk-frontend
 *
 * Updated: January 2026
 */
$pageTitle = 'Terms of Service';
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
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Terms of Service</li>
    </ol>
</div>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Terms of Service</h1>

        <p class="govuk-body-l">Terms and conditions for hOUR Timebank Ireland members and partner organisations.</p>

        <p class="govuk-body govuk-!-margin-bottom-6">
            <strong class="govuk-tag govuk-tag--grey">Last updated: <?= date('j F Y') ?></strong>
        </p>

        <!-- Agreement to Terms -->
        <div class="govuk-inset-text">
            <p>By accessing or using hOUR Timebank Ireland (accessible at <strong>hour-timebank.ie</strong>), you agree to be bound by these Terms of Service.</p>
            <p>These terms establish a framework for fair, respectful, and meaningful exchanges between community members across Ireland.</p>
        </div>

        <!-- Contents -->
        <nav class="govuk-!-margin-bottom-8" aria-label="Contents">
            <h2 class="govuk-heading-s">Contents</h2>
            <ol class="govuk-list govuk-list--number">
                <li><a class="govuk-link" href="#platform-role">Platform Role</a></li>
                <li><a class="govuk-link" href="#who-we-are">Who We Are</a></li>
                <li><a class="govuk-link" href="#time-credits">Time Credit System</a></li>
                <li><a class="govuk-link" href="#safeguarding">Safeguarding</a></li>
                <li><a class="govuk-link" href="#partners">Partner Organisations</a></li>
                <li><a class="govuk-link" href="#account">Account Responsibilities</a></li>
                <li><a class="govuk-link" href="#privacy">Privacy & Data Protection</a></li>
                <li><a class="govuk-link" href="#community">Community Guidelines</a></li>
                <li><a class="govuk-link" href="#prohibited">Prohibited Activities</a></li>
                <li><a class="govuk-link" href="#relationship">Nature of Relationship</a></li>
                <li><a class="govuk-link" href="#risk">Assumption of Risk</a></li>
                <li><a class="govuk-link" href="#liability">Limitation of Liability</a></li>
                <li><a class="govuk-link" href="#disputes">Dispute Resolution</a></li>
                <li><a class="govuk-link" href="#governing-law">Governing Law</a></li>
            </ol>
        </nav>

        <!-- Section 1: Platform Role -->
        <h2 class="govuk-heading-l" id="platform-role">1. Platform Role & Responsibility</h2>

        <p class="govuk-body"><strong>hOUR Timebank Ireland is a connection platform only.</strong> We provide technology and tools that enable community members to find and arrange service exchanges with each other.</p>

        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">Warning</span>
                We are NOT a service provider. We do not perform, supervise, direct, or control any services exchanged between members.
            </strong>
        </div>

        <h3 class="govuk-heading-s">What we do</h3>
        <ul class="govuk-list govuk-list--bullet">
            <li>Provide a platform for members to create profiles and list services</li>
            <li>Facilitate communication between members through messaging</li>
            <li>Track Time Credits earned and spent</li>
            <li>Offer tools for reviews and feedback</li>
            <li>Moderate content for compliance with guidelines</li>
        </ul>

        <h3 class="govuk-heading-s">What we do NOT do</h3>
        <ul class="govuk-list govuk-list--bullet">
            <li>Perform any services on behalf of members</li>
            <li>Employ, hire, or contract with members</li>
            <li>Supervise or control how services are performed</li>
            <li>Verify the quality or safety of services</li>
            <li>Guarantee outcomes or resolve service quality disputes</li>
        </ul>

        <!-- Section 2: Who We Are -->
        <h2 class="govuk-heading-l" id="who-we-are">2. Who We Are</h2>

        <p class="govuk-body">hOUR Timebank Ireland is operated by <strong>hOUR Timebank CLG</strong>, an Irish registered charity.</p>

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
                <dt class="govuk-summary-list__key">Contact</dt>
                <dd class="govuk-summary-list__value"><a href="mailto:jasper@hour-timebank.ie" class="govuk-link">jasper@hour-timebank.ie</a></dd>
            </div>
        </dl>

        <!-- Section 3: Time Credits -->
        <h2 class="govuk-heading-l" id="time-credits">3. Time Credit System</h2>

        <p class="govuk-body">Our platform operates on a simple principle: <strong>everyone's time is equal</strong>.</p>

        <p class="govuk-body">One hour of service provided equals one Time Credit earned.</p>

        <div class="govuk-inset-text">
            Time Credits have no monetary value and cannot be exchanged for cash. They exist solely to facilitate community exchanges and are not considered payment under Irish law.
        </div>

        <ul class="govuk-list govuk-list--bullet">
            <li>Credits can be used to receive services from other members</li>
            <li>The type of service does not affect the credit value</li>
            <li>Credits are tracked automatically through the platform</li>
            <li>Credits cannot be transferred, sold, or inherited</li>
        </ul>

        <!-- Section 4: Safeguarding -->
        <h2 class="govuk-heading-l" id="safeguarding">4. Safeguarding & Prohibited Services</h2>

        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">Warning</span>
                ALL services involving children or vulnerable persons are strictly prohibited on this platform. No exceptions.
            </strong>
        </div>

        <p class="govuk-body">Under the <strong>National Vetting Bureau (Children and Vulnerable Persons) Acts 2012-2016</strong>, certain activities require Garda Vetting. hOUR Timebank CLG does NOT provide Garda Vetting services.</p>

        <h3 class="govuk-heading-s">Absolutely prohibited services</h3>
        <ul class="govuk-list govuk-list--bullet">
            <li>Childminding or babysitting</li>
            <li>Tutoring or teaching children</li>
            <li>Transport for children or vulnerable persons</li>
            <li>Care assistance for vulnerable adults</li>
            <li>Home visits to vulnerable persons</li>
            <li>Youth group or children's activities</li>
        </ul>

        <p class="govuk-body">Offering or requesting prohibited services will result in <strong>immediate account termination</strong>.</p>

        <!-- Section 5: Partners -->
        <h2 class="govuk-heading-l" id="partners">5. Partner Organisations</h2>

        <p class="govuk-body">hOUR Timebank Ireland provides platform services to Irish community groups, voluntary organisations, and charities who wish to operate timebanking within their communities.</p>

        <h3 class="govuk-heading-s">Partner responsibilities</h3>
        <ul class="govuk-list govuk-list--bullet">
            <li>Ensure compliance with these Terms within their community</li>
            <li>Maintain accurate records of members and exchanges</li>
            <li>Handle member data in accordance with GDPR</li>
            <li>Report issues or concerns promptly</li>
        </ul>

        <!-- Section 6: Account -->
        <h2 class="govuk-heading-l" id="account">6. Account Responsibilities</h2>

        <p class="govuk-body">When you create an account, you agree to:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li>Provide accurate information</li>
            <li>Keep your login credentials secure</li>
            <li>Use only one account</li>
            <li>Keep your profile up to date</li>
            <li>Be reachable and respond to messages</li>
            <li>Be 18 years or older</li>
        </ul>

        <div class="govuk-inset-text">
            <strong>Privacy settings are your responsibility.</strong> You are solely responsible for reviewing, understanding, and maintaining your privacy settings.
        </div>

        <!-- Section 7: Privacy -->
        <h2 class="govuk-heading-l" id="privacy">7. Privacy & Data Protection</h2>

        <p class="govuk-body">We are committed to protecting your personal data in accordance with the <strong>General Data Protection Regulation (GDPR)</strong> and Irish data protection law.</p>

        <ul class="govuk-list govuk-list--bullet">
            <li>We collect only information necessary to operate the platform</li>
            <li>Your data is stored securely and never sold</li>
            <li>You have the right to access, correct, or delete your data</li>
        </ul>

        <p class="govuk-body">
            <a href="<?= $basePath ?>/privacy" class="govuk-link">Read our full Privacy Policy</a>
        </p>

        <!-- Section 8: Community -->
        <h2 class="govuk-heading-l" id="community">8. Community Guidelines</h2>

        <p class="govuk-body">Our community is built on trust, respect, and mutual support. All members must:</p>
        <ol class="govuk-list govuk-list--number">
            <li>Treat everyone with respect</li>
            <li>Honour commitments</li>
            <li>Communicate clearly</li>
            <li>Be inclusive</li>
            <li>Give honest feedback</li>
            <li>Respect boundaries</li>
        </ol>

        <!-- Section 9: Prohibited -->
        <h2 class="govuk-heading-l" id="prohibited">9. Prohibited Activities</h2>

        <p class="govuk-body">The following activities may result in account termination:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li>Harassment or discrimination</li>
            <li>Fraudulent exchanges</li>
            <li>Illegal services or activities</li>
            <li>Commercial exploitation</li>
            <li>Impersonation</li>
            <li>Sharing others' private information</li>
            <li>Requesting cash payments</li>
        </ul>

        <!-- Section 10: Relationship -->
        <h2 class="govuk-heading-l" id="relationship">10. Nature of Relationship</h2>

        <div class="govuk-inset-text">
            Members are <strong>NOT</strong> employees, workers, agents, or contractors of hOUR Timebank CLG. No employment relationship exists or is created by participation in the timebank.
        </div>

        <ul class="govuk-list govuk-list--bullet">
            <li>We do not direct or control your activities</li>
            <li>You are not entitled to employment benefits</li>
            <li>Exchanges are personal arrangements between individuals</li>
            <li>You are responsible for your own tax obligations</li>
        </ul>

        <!-- Section 11: Risk -->
        <h2 class="govuk-heading-l" id="risk">11. Assumption of Risk</h2>

        <p class="govuk-body">By participating, you acknowledge that:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li>You voluntarily choose to participate</li>
            <li>Exchanges carry inherent risks</li>
            <li>You are responsible for assessing suitability of exchanges</li>
            <li>You are responsible for your own safety</li>
        </ul>

        <p class="govuk-body">hOUR Timebank CLG does <strong>NOT</strong> verify qualifications, certifications, or skills of members.</p>

        <!-- Section 12: Liability -->
        <h2 class="govuk-heading-l" id="liability">12. Limitation of Liability</h2>

        <p class="govuk-body">To the maximum extent permitted by Irish law:</p>
        <ul class="govuk-list govuk-list--bullet">
            <li>We do not guarantee quality, safety, or suitability of services</li>
            <li>We are not responsible for disputes between members</li>
            <li>We do not supervise exchanges</li>
            <li>We are not liable for any loss or injury arising from exchanges</li>
        </ul>

        <p class="govuk-body">Nothing in these terms excludes liability for death or personal injury caused by negligence, or fraud.</p>

        <!-- Section 13: Disputes -->
        <h2 class="govuk-heading-l" id="disputes">13. Dispute Resolution</h2>

        <p class="govuk-body">If a dispute arises:</p>
        <ol class="govuk-list govuk-list--number">
            <li><strong>Direct Communication:</strong> Attempt to resolve directly with the other member</li>
            <li><strong>Platform Mediation:</strong> Request informal mediation from hOUR Timebank CLG</li>
            <li><strong>External Mediation:</strong> Consider the Mediators' Institute of Ireland</li>
            <li><strong>Legal Proceedings:</strong> Subject to Irish court jurisdiction</li>
        </ol>

        <details class="govuk-details">
            <summary class="govuk-details__summary">
                <span class="govuk-details__summary-text">Small claims procedure</span>
            </summary>
            <div class="govuk-details__text">
                For claims under €2,000, you may use the Small Claims Court, which is a low-cost, informal way to resolve disputes.
            </div>
        </details>

        <!-- Section 14: Governing Law -->
        <h2 class="govuk-heading-l" id="governing-law">14. Governing Law</h2>

        <p class="govuk-body">These Terms are governed by and construed in accordance with the <strong>laws of the Republic of Ireland</strong>.</p>

        <p class="govuk-body">Any disputes shall be subject to the exclusive jurisdiction of the <strong>Irish courts</strong>.</p>

        <!-- Contact -->
        <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

        <h2 class="govuk-heading-m">Questions?</h2>
        <p class="govuk-body">If you have questions about these Terms, please contact us:</p>
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
                    <li><a href="<?= $basePath ?>/privacy" class="govuk-link">Privacy Policy</a></li>
                    <li><a href="<?= $basePath ?>/legal/cookies" class="govuk-link">Cookie Policy</a></li>
                    <li><a href="<?= $basePath ?>/accessibility" class="govuk-link">Accessibility Statement</a></li>
                    <li><a href="<?= $basePath ?>/legal" class="govuk-link">Legal Hub</a></li>
                </ul>
            </nav>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/../../../../../layouts/civicone/footer.php'; ?>
