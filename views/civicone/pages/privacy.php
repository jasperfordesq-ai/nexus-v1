<?php
/**
 * Privacy Policy
 * CivicOne Theme - GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = 'Privacy Policy';
$hideHero = true;

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
}
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/legal">Legal</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Privacy Policy</li>
    </ol>
</nav>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-4">
            <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" aria-hidden="true"></i>
            Privacy Policy
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-2">How we collect, use, and protect your personal information</p>
        <p class="govuk-body-s govuk-!-margin-bottom-6" style="color: #505a5f;">
            <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
            Last Updated: <?= date('j F Y') ?>
        </p>
    </div>
    <div class="govuk-grid-column-one-third">
        <!-- Quick Navigation -->
        <nav class="govuk-!-padding-4 civicone-panel-bg" aria-label="Page contents">
            <h2 class="govuk-heading-s govuk-!-margin-bottom-3">Contents</h2>
            <ul class="govuk-list">
                <li><a href="#data-collection" class="govuk-link">Data Collection</a></li>
                <li><a href="#data-usage" class="govuk-link">How We Use Data</a></li>
                <li><a href="#your-rights" class="govuk-link">Your Rights</a></li>
                <li><a href="#cookies" class="govuk-link">Cookies</a></li>
            </ul>
        </nav>
    </div>
</div>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <!-- Introduction -->
        <div class="govuk-inset-text govuk-!-margin-bottom-6" style="border-left-color: #1d70b8;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-handshake govuk-!-margin-right-2" aria-hidden="true"></i>
                Our Commitment to Your Privacy
            </h2>
            <p class="govuk-body"><?= htmlspecialchars($tenantName) ?> is committed to protecting your privacy and ensuring your personal data is handled responsibly. This policy explains what information we collect, why we collect it, and how you can manage your data.</p>
            <p class="govuk-body govuk-!-margin-bottom-0">We believe in <strong>transparency</strong> and <strong>user control</strong>. You have the right to understand and manage how your information is used.</p>
        </div>

        <!-- Data Collection -->
        <section class="govuk-!-margin-bottom-8" id="data-collection">
            <h2 class="govuk-heading-l">
                <i class="fa-solid fa-database govuk-!-margin-right-2" aria-hidden="true"></i>
                Information We Collect
            </h2>
            <p class="govuk-body govuk-!-margin-bottom-4">We collect only the information necessary to provide and improve our services:</p>

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
                        <td class="govuk-table__cell">Name, email, password (encrypted) - Required to create and manage your account</td>
                    </tr>
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell"><strong>Profile Details</strong></td>
                        <td class="govuk-table__cell">Bio, skills, location, photo - Helps connect you with community members</td>
                    </tr>
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell"><strong>Activity Data</strong></td>
                        <td class="govuk-table__cell">Exchanges, messages, time credits - Essential for platform functionality</td>
                    </tr>
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell"><strong>Device Information</strong></td>
                        <td class="govuk-table__cell">Browser type, IP address - Used for security and troubleshooting</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Data Usage -->
        <section class="govuk-!-margin-bottom-8" id="data-usage">
            <h2 class="govuk-heading-l">
                <i class="fa-solid fa-chart-pie govuk-!-margin-right-2" aria-hidden="true"></i>
                How We Use Your Data
            </h2>
            <p class="govuk-body">Your data is used exclusively for the following purposes:</p>
            <ul class="govuk-list govuk-list--bullet govuk-!-margin-bottom-4">
                <li><strong>Service Delivery:</strong> Facilitating time exchanges and community connections</li>
                <li><strong>Communication:</strong> Sending important updates, notifications, and messages from other members</li>
                <li><strong>Security:</strong> Protecting your account and preventing fraud or abuse</li>
                <li><strong>Improvement:</strong> Analyzing usage patterns to enhance platform features</li>
                <li><strong>Legal Compliance:</strong> Meeting regulatory requirements when necessary</li>
            </ul>
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">Important: </span>
                    We do not sell your personal data to third parties. Your information is never shared with advertisers or data brokers.
                </strong>
            </div>
        </section>

        <!-- Profile Visibility -->
        <section class="govuk-!-margin-bottom-8">
            <h2 class="govuk-heading-l">
                <i class="fa-solid fa-eye govuk-!-margin-right-2" aria-hidden="true"></i>
                Profile Visibility
            </h2>
            <p class="govuk-body">Your profile is visible to other verified members of the timebank community. This visibility is essential for facilitating exchanges and building trust within the community.</p>
            <p class="govuk-body">You can control what information appears on your profile through your <strong>account settings</strong>. Some information, like your name and general location, is required for meaningful community participation.</p>
        </section>

        <!-- Data Protection -->
        <section class="govuk-!-margin-bottom-8">
            <h2 class="govuk-heading-l">
                <i class="fa-solid fa-lock govuk-!-margin-right-2" aria-hidden="true"></i>
                How We Protect Your Data
            </h2>
            <p class="govuk-body">We implement robust security measures to safeguard your information:</p>
            <ul class="govuk-list govuk-list--bullet">
                <li><strong>Encryption:</strong> All data is encrypted in transit (HTTPS) and at rest</li>
                <li><strong>Secure Passwords:</strong> Passwords are hashed using industry-standard algorithms</li>
                <li><strong>Access Controls:</strong> Strict internal policies limit who can access your data</li>
                <li><strong>Regular Audits:</strong> We conduct security reviews and update our practices accordingly</li>
                <li><strong>Secure Infrastructure:</strong> Our servers are hosted in certified data centers</li>
            </ul>
        </section>

        <!-- Your Rights -->
        <section class="govuk-!-margin-bottom-8" id="your-rights">
            <div class="govuk-inset-text govuk-!-margin-bottom-6" style="border-left-color: #00703c;">
                <h2 class="govuk-heading-l govuk-!-margin-bottom-4">
                    <i class="fa-solid fa-user-shield govuk-!-margin-right-2" aria-hidden="true"></i>
                    Your Privacy Rights
                </h2>
                <p class="govuk-body govuk-!-margin-bottom-4">You have full control over your personal data. Here are your rights:</p>

                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                        <div class="govuk-!-padding-3" style="border: 1px solid #b1b4b6; border-left: 4px solid #1d70b8;">
                            <p class="govuk-body govuk-!-margin-bottom-1">
                                <i class="fa-solid fa-eye" style="color: #1d70b8;" aria-hidden="true"></i>
                            </p>
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Right to Access</h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">Request a copy of all personal data we hold about you</p>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                        <div class="govuk-!-padding-3" style="border: 1px solid #b1b4b6; border-left: 4px solid #1d70b8;">
                            <p class="govuk-body govuk-!-margin-bottom-1">
                                <i class="fa-solid fa-pen" style="color: #1d70b8;" aria-hidden="true"></i>
                            </p>
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Right to Rectification</h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">Correct any inaccurate or incomplete information</p>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                        <div class="govuk-!-padding-3" style="border: 1px solid #b1b4b6; border-left: 4px solid #1d70b8;">
                            <p class="govuk-body govuk-!-margin-bottom-1">
                                <i class="fa-solid fa-trash" style="color: #1d70b8;" aria-hidden="true"></i>
                            </p>
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Right to Erasure</h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">Request deletion of your account and associated data</p>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                        <div class="govuk-!-padding-3" style="border: 1px solid #b1b4b6; border-left: 4px solid #1d70b8;">
                            <p class="govuk-body govuk-!-margin-bottom-1">
                                <i class="fa-solid fa-download" style="color: #1d70b8;" aria-hidden="true"></i>
                            </p>
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Right to Portability</h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">Export your data in a machine-readable format</p>
                        </div>
                    </div>
                </div>

                <p class="govuk-body govuk-!-margin-bottom-0">To exercise any of these rights, please contact us through the link below.</p>
            </div>
        </section>

        <!-- Cookies -->
        <section class="govuk-!-margin-bottom-8" id="cookies">
            <h2 class="govuk-heading-l">
                <i class="fa-solid fa-cookie-bite govuk-!-margin-right-2" aria-hidden="true"></i>
                Cookies & Tracking
            </h2>
            <p class="govuk-body">We use cookies to enhance your experience on our platform:</p>
            <ul class="govuk-list govuk-list--bullet govuk-!-margin-bottom-4">
                <li><strong>Essential Cookies:</strong> Required for login, security, and basic functionality</li>
                <li><strong>Preference Cookies:</strong> Remember your settings like theme and language</li>
                <li><strong>Analytics Cookies:</strong> Help us understand how people use the platform (anonymized)</li>
            </ul>
            <p class="govuk-body">We do <strong>not</strong> use advertising cookies or share data with ad networks. You can manage cookie preferences in your browser settings.</p>
        </section>

        <!-- Data Retention -->
        <section class="govuk-!-margin-bottom-8">
            <h2 class="govuk-heading-l">
                <i class="fa-solid fa-clock-rotate-left govuk-!-margin-right-2" aria-hidden="true"></i>
                Data Retention
            </h2>
            <p class="govuk-body">We retain your data only as long as necessary:</p>
            <ul class="govuk-list govuk-list--bullet">
                <li><strong>Active Accounts:</strong> Data is kept while your account remains active</li>
                <li><strong>Deleted Accounts:</strong> Personal data is removed within 30 days of account deletion</li>
                <li><strong>Transaction Records:</strong> May be retained longer for legal and audit purposes</li>
            </ul>
        </section>

        <!-- Contact CTA -->
        <div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="background: #1d70b8; color: white;">
            <h2 class="govuk-heading-m" style="color: white;">
                <i class="fa-solid fa-envelope govuk-!-margin-right-2" aria-hidden="true"></i>
                Questions About Your Privacy?
            </h2>
            <p class="govuk-body" style="color: white;">We're here to help. If you have any questions about this policy or want to exercise your data rights, please don't hesitate to reach out.</p>
            <a href="<?= $basePath ?>/contact" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                Contact Our Privacy Team
            </a>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
