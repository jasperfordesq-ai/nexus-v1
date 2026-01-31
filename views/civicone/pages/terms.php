<?php
/**
 * Terms of Service - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Terms of Service';

// Get tenant info
$tenantName = 'This Community';
$tenant = TenantContext::get();
$tenantName = $tenant['name'] ?? 'This Community';

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<div class="govuk-width-container">

    <?= civicone_govuk_breadcrumbs([
        'items' => [
            ['text' => 'Home', 'href' => $basePath],
            ['text' => 'Legal', 'href' => $basePath . '/legal'],
            ['text' => 'Terms of Service']
        ],
        'class' => 'govuk-!-margin-bottom-6'
    ]) ?>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Terms of Service</h1>

                <p class="govuk-body-l">
                    These terms govern your use of <?= htmlspecialchars($tenantName) ?>.
                    By using this service, you agree to these terms.
                </p>

                <p class="govuk-body govuk-!-margin-bottom-6">
                    <strong>Last updated:</strong> <?= date('j F Y') ?>
                </p>

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <!-- Section 1 -->
                <h2 class="govuk-heading-l" id="introduction">1. Introduction</h2>

                <p class="govuk-body">
                    Welcome to <?= htmlspecialchars($tenantName) ?>. By accessing or using our platform,
                    you agree to be bound by these Terms of Service and our
                    <a href="<?= $basePath ?>/privacy" class="govuk-link">Privacy Policy</a>.
                </p>

                <p class="govuk-body">
                    If you do not agree to these terms, please do not use our service.
                </p>

                <!-- Section 2 -->
                <h2 class="govuk-heading-l" id="time-credits">2. Time Credit System</h2>

                <p class="govuk-body">
                    Our platform operates on a time-based exchange system:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>One hour of service equals one Time Credit</li>
                    <li>All services are valued equally, regardless of type</li>
                    <li>Time Credits have no monetary value and cannot be exchanged for cash</li>
                    <li>Credits can only be used within the timebank network</li>
                </ul>

                <div class="govuk-inset-text">
                    Time Credits are a measure of reciprocity within the community, not a form of payment.
                </div>

                <!-- Section 3 -->
                <h2 class="govuk-heading-l" id="membership">3. Membership</h2>

                <p class="govuk-body">
                    To use this service, you must:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>Be at least 18 years of age</li>
                    <li>Provide accurate and complete registration information</li>
                    <li>Maintain the security of your account credentials</li>
                    <li>Accept responsibility for all activity under your account</li>
                </ul>

                <!-- Section 4 -->
                <h2 class="govuk-heading-l" id="community-guidelines">4. Community Guidelines</h2>

                <p class="govuk-body">
                    Our community is built on trust and mutual respect. As a member, you agree to:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>Treat all members with respect and courtesy</li>
                    <li>Provide services to the best of your ability</li>
                    <li>Communicate honestly and promptly</li>
                    <li>Honor your commitments and scheduled exchanges</li>
                    <li>Report any concerns or issues to the community coordinators</li>
                </ul>

                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">Warning</span>
                        Members who violate community guidelines may have their accounts suspended or terminated.
                    </strong>
                </div>

                <!-- Section 5 -->
                <h2 class="govuk-heading-l" id="liability">5. Limitation of Liability</h2>

                <p class="govuk-body">
                    <?= htmlspecialchars($tenantName) ?> facilitates connections between members but is not
                    responsible for the quality of services exchanged. Members exchange services at their own risk.
                </p>

                <p class="govuk-body">
                    We do not provide insurance for exchanges. Members are encouraged to discuss expectations
                    and any concerns before beginning an exchange.
                </p>

                <!-- Section 6 -->
                <h2 class="govuk-heading-l" id="changes">6. Changes to Terms</h2>

                <p class="govuk-body">
                    We may update these terms from time to time. Significant changes will be communicated
                    to members via email or platform notification. Continued use of the service after
                    changes constitutes acceptance of the new terms.
                </p>

                <!-- Section 7 -->
                <h2 class="govuk-heading-l" id="contact">7. Contact Us</h2>

                <p class="govuk-body">
                    If you have questions about these terms, please
                    <a href="<?= $basePath ?>/contact" class="govuk-link">contact us</a>.
                </p>

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <p class="govuk-body">
                    <a href="<?= $basePath ?>/legal" class="govuk-link">
                        <span aria-hidden="true">←</span> Back to Legal Hub
                    </a>
                </p>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">
                    <h2 class="govuk-heading-s">Related pages</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/privacy" class="govuk-link">Privacy Policy</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/cookies" class="govuk-link">Cookie Policy</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/accessibility" class="govuk-link">Accessibility Statement</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/contact" class="govuk-link">Contact Us</a>
                        </li>
                    </ul>
                </aside>
            </div>
        </div>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
