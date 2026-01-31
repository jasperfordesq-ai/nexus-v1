<?php
/**
 * Terms of Service - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * Content matches Modern theme (source of truth)
 *
 * @version 2.1.0 - Content sync with Modern theme
 * @since 2026-01-31
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

                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-file-contract govuk-!-margin-right-2" aria-hidden="true"></i>
                    Terms of Service
                </h1>

                <p class="govuk-body-l">The rules and guidelines for using our platform</p>

                <p class="govuk-body govuk-!-margin-bottom-6">
                    <strong>Last updated:</strong> <?= date('j F Y') ?>
                </p>

                <!-- Quick Navigation -->
                <nav class="govuk-!-margin-bottom-6 civicone-panel-bg govuk-!-padding-4" aria-label="Page contents">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-3">Contents</h2>
                    <ul class="govuk-list">
                        <li><a href="#time-credits" class="govuk-link">1. Time Credit System</a></li>
                        <li><a href="#account" class="govuk-link">2. Account Responsibilities</a></li>
                        <li><a href="#community" class="govuk-link">3. Community Guidelines</a></li>
                        <li><a href="#prohibited" class="govuk-link">4. Prohibited Activities</a></li>
                        <li><a href="#safety" class="govuk-link">5. Safety & Meetings</a></li>
                        <li><a href="#liability" class="govuk-link">6. Limitation of Liability</a></li>
                        <li><a href="#termination" class="govuk-link">7. Account Termination</a></li>
                        <li><a href="#changes" class="govuk-link">8. Changes to These Terms</a></li>
                    </ul>
                </nav>

                <!-- Introduction -->
                <div class="govuk-inset-text govuk-!-margin-bottom-6 civicone-inset-blue">
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-handshake govuk-!-margin-right-2" aria-hidden="true"></i>
                        Welcome to <?= htmlspecialchars($tenantName) ?>
                    </h2>
                    <p class="govuk-body">By accessing or using our platform, you agree to be bound by these Terms of Service. Please read them carefully before participating in our community.</p>
                    <p class="govuk-body govuk-!-margin-bottom-0">These terms establish a framework for <strong>fair, respectful, and meaningful exchanges</strong> between community members. Our goal is to create a trusted environment where everyone's time is valued equally.</p>
                </div>

                <!-- Section 1: Time Credit System -->
                <section class="govuk-!-margin-bottom-8" id="time-credits">
                    <h2 class="govuk-heading-l">
                        <i class="fa-solid fa-clock govuk-!-margin-right-2" aria-hidden="true"></i>
                        1. Time Credit System
                    </h2>
                    <p class="govuk-body">Our platform operates on a simple but powerful principle: <strong>everyone's time is equal</strong>.</p>

                    <div class="govuk-inset-text">
                        <strong>1 Hour of Service = 1 Time Credit</strong><br>
                        Time Credits have no monetary value and cannot be exchanged for cash. They exist solely to facilitate community exchanges.
                    </div>

                    <ul class="govuk-list govuk-list--bullet">
                        <li>One hour of service provided equals one Time Credit earned</li>
                        <li>Credits can be used to receive services from other members</li>
                        <li>The type of service does not affect the credit value</li>
                        <li>Credits are tracked automatically through the platform</li>
                    </ul>
                </section>

                <!-- Section 2: Account Responsibilities -->
                <section class="govuk-!-margin-bottom-8" id="account">
                    <h2 class="govuk-heading-l">
                        <i class="fa-solid fa-user govuk-!-margin-right-2" aria-hidden="true"></i>
                        2. Account Responsibilities
                    </h2>
                    <p class="govuk-body">When you create an account, you agree to:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li><strong>Provide accurate information:</strong> Your profile must reflect your true identity and skills</li>
                        <li><strong>Maintain security:</strong> Keep your login credentials confidential and secure</li>
                        <li><strong>Use one account:</strong> Each person may only maintain one active account</li>
                        <li><strong>Stay current:</strong> Update your profile when your skills or availability change</li>
                        <li><strong>Be reachable:</strong> Respond to messages and requests in a timely manner</li>
                    </ul>
                </section>

                <!-- Section 3: Community Guidelines -->
                <section class="govuk-!-margin-bottom-8" id="community">
                    <div class="govuk-inset-text civicone-inset-green">
                        <h2 class="govuk-heading-l govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                            3. Community Guidelines
                        </h2>
                        <p class="govuk-body">Our community is built on <strong>trust, respect, and mutual support</strong>. All members must:</p>
                        <ol class="govuk-list govuk-list--number">
                            <li><strong>Treat everyone with respect</strong> — Be kind and courteous in all interactions</li>
                            <li><strong>Honor your commitments</strong> — If you agree to an exchange, follow through</li>
                            <li><strong>Communicate clearly</strong> — Keep other members informed about your availability</li>
                            <li><strong>Be inclusive</strong> — Welcome members of all backgrounds and abilities</li>
                            <li><strong>Give honest feedback</strong> — Help the community by providing fair reviews</li>
                        </ol>
                    </div>
                </section>

                <!-- Section 4: Prohibited Activities -->
                <section class="govuk-!-margin-bottom-8" id="prohibited">
                    <h2 class="govuk-heading-l">
                        <i class="fa-solid fa-ban govuk-!-margin-right-2" aria-hidden="true"></i>
                        4. Prohibited Activities
                    </h2>
                    <p class="govuk-body">The following activities are strictly prohibited and may result in account termination:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>Harassment or discrimination</li>
                        <li>Fraudulent exchanges</li>
                        <li>Illegal services or activities</li>
                        <li>Spam or solicitation</li>
                        <li>Impersonation</li>
                        <li>Sharing others' private information</li>
                    </ul>

                    <div class="govuk-warning-text">
                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                        <strong class="govuk-warning-text__text">
                            <span class="govuk-visually-hidden">Warning</span>
                            Violation of these rules will result in immediate account review and possible termination.
                        </strong>
                    </div>
                </section>

                <!-- Section 5: Safety & Meetings -->
                <section class="govuk-!-margin-bottom-8" id="safety">
                    <h2 class="govuk-heading-l">
                        <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" aria-hidden="true"></i>
                        5. Safety & Meetings
                    </h2>
                    <p class="govuk-body">Your safety is important. We recommend following these guidelines:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li><strong>First meetings:</strong> Meet in public places for initial exchanges</li>
                        <li><strong>Verify identity:</strong> Confirm the member's profile before meeting</li>
                        <li><strong>Trust your instincts:</strong> If something feels wrong, don't proceed</li>
                        <li><strong>Report concerns:</strong> Let us know about any suspicious behaviour</li>
                        <li><strong>Keep records:</strong> Document exchanges through the platform</li>
                    </ul>
                </section>

                <!-- Section 6: Limitation of Liability -->
                <section class="govuk-!-margin-bottom-8" id="liability">
                    <h2 class="govuk-heading-l">
                        <i class="fa-solid fa-scale-balanced govuk-!-margin-right-2" aria-hidden="true"></i>
                        6. Limitation of Liability
                    </h2>
                    <p class="govuk-body"><?= htmlspecialchars($tenantName) ?> provides a platform for community members to connect and exchange services. However:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>We do not guarantee the quality or safety of any services exchanged</li>
                        <li>We are not responsible for disputes between members</li>
                        <li>Members exchange services at their own risk</li>
                        <li>We recommend obtaining appropriate insurance for professional services</li>
                    </ul>
                    <p class="govuk-body">By using the platform, you agree to hold <?= htmlspecialchars($tenantName) ?> harmless from any claims arising from your participation.</p>
                </section>

                <!-- Section 7: Account Termination -->
                <section class="govuk-!-margin-bottom-8" id="termination">
                    <h2 class="govuk-heading-l">
                        <i class="fa-solid fa-user-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                        7. Account Termination
                    </h2>
                    <p class="govuk-body">We reserve the right to suspend or terminate accounts that violate these terms. Reasons for termination include:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>Repeated violation of community guidelines</li>
                        <li>Fraudulent or deceptive behaviour</li>
                        <li>Harassment of other members</li>
                        <li>Extended inactivity (over 12 months)</li>
                        <li>Providing false information</li>
                    </ul>
                    <p class="govuk-body">You may also close your account at any time through your account settings.</p>
                </section>

                <!-- Section 8: Changes to These Terms -->
                <section class="govuk-!-margin-bottom-8" id="changes">
                    <h2 class="govuk-heading-l">
                        <i class="fa-solid fa-pen-to-square govuk-!-margin-right-2" aria-hidden="true"></i>
                        8. Changes to These Terms
                    </h2>
                    <p class="govuk-body">We may update these terms from time to time to reflect changes in our practices or for legal reasons. When we make significant changes:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>We will notify you via email or platform notification</li>
                        <li>The updated date will be shown at the top of this page</li>
                        <li>Continued use of the platform constitutes acceptance of the new terms</li>
                    </ul>
                </section>

                <!-- Contact CTA -->
                <div class="govuk-!-padding-6 govuk-!-margin-bottom-6 civicone-cta-blue">
                    <h2 class="govuk-heading-m civicone-cta-heading">
                        <i class="fa-solid fa-question-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                        Have Questions?
                    </h2>
                    <p class="govuk-body civicone-cta-text">If you have any questions about these Terms of Service or need clarification on any points, our team is here to help.</p>
                    <a href="<?= $basePath ?>/contact" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                        Contact Us
                    </a>
                </div>

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
                            <a href="<?= $basePath ?>/privacy#cookies" class="govuk-link">Cookie Policy</a>
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
