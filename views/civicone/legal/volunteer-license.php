<?php
/**
 * Volunteer Module Agreement
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = 'Volunteer Agreement';
\Nexus\Core\SEO::setTitle('Volunteer Agreement');
\Nexus\Core\SEO::setDescription('Terms and conditions for volunteering through our platform.');

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'Hour Timebank';

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <a href="<?= $basePath ?>/volunteering" class="govuk-back-link">Back to volunteering</a>

    <main class="govuk-main-wrapper">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-handshake govuk-!-margin-right-2 govuk-icon--green" aria-hidden="true"></i>
                    Volunteer Agreement
                </h1>

                <p class="govuk-body-l">
                    This agreement outlines the terms and expectations for volunteers participating in activities through <?= htmlspecialchars($tenantName) ?>.
                </p>

                <div class="govuk-inset-text">
                    <p class="govuk-body">
                        By volunteering through our platform, you agree to follow these guidelines and contribute positively to our community.
                    </p>
                </div>

                <!-- Section 1 -->
                <h2 class="govuk-heading-l">1. Your commitment</h2>
                <p class="govuk-body">As a volunteer, you agree to:</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Attend scheduled activities reliably and punctually</li>
                    <li>Provide reasonable notice if you cannot attend</li>
                    <li>Follow the instructions of organization coordinators</li>
                    <li>Treat all participants with respect and dignity</li>
                    <li>Maintain confidentiality where appropriate</li>
                </ul>

                <!-- Section 2 -->
                <h2 class="govuk-heading-l">2. Time credits</h2>
                <p class="govuk-body">When volunteering through our timebank system:</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Time credits are earned for verified volunteer hours</li>
                    <li>1 hour of volunteering = 1 time credit</li>
                    <li>Hours must be logged and verified by the organization</li>
                    <li>Time credits can be used within our community network</li>
                </ul>

                <!-- Section 3 -->
                <h2 class="govuk-heading-l">3. Health and safety</h2>
                <p class="govuk-body">For your safety and the safety of others:</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Report any accidents or near-misses immediately</li>
                    <li>Follow all health and safety procedures</li>
                    <li>Use any required protective equipment provided</li>
                    <li>Do not undertake tasks beyond your capabilities</li>
                </ul>

                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">Warning</span>
                        Never put yourself at risk. If you feel unsafe, stop the activity and inform the coordinator.
                    </strong>
                </div>

                <!-- Section 4 -->
                <h2 class="govuk-heading-l">4. Insurance and liability</h2>
                <p class="govuk-body">
                    Organizations are responsible for ensuring appropriate insurance coverage for their volunteer activities. You should:
                </p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Confirm insurance coverage with each organization</li>
                    <li>Disclose any relevant health conditions</li>
                    <li>Report any incidents promptly</li>
                </ul>

                <!-- Section 5 -->
                <h2 class="govuk-heading-l">5. Code of conduct</h2>
                <p class="govuk-body">All volunteers must:</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Behave professionally and ethically at all times</li>
                    <li>Respect diversity and promote inclusion</li>
                    <li>Not engage in discrimination or harassment</li>
                    <li>Represent the organization positively</li>
                    <li>Protect the privacy of service users</li>
                </ul>

                <!-- Section 6 -->
                <h2 class="govuk-heading-l">6. Ending your volunteering</h2>
                <p class="govuk-body">
                    You may end your volunteering at any time. We ask that you:
                </p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Give reasonable notice when possible</li>
                    <li>Return any equipment or materials</li>
                    <li>Complete any outstanding commitments where feasible</li>
                </ul>

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <p class="govuk-body-s govuk-text--secondary">
                    Last updated: <?= date('F Y') ?>
                </p>

                <div class="govuk-button-group govuk-!-margin-top-6">
                    <a href="<?= $basePath ?>/volunteering" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-search govuk-!-margin-right-2" aria-hidden="true"></i>
                        Find Opportunities
                    </a>
                    <a href="<?= $basePath ?>/legal" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-file-alt govuk-!-margin-right-2" aria-hidden="true"></i>
                        Other Legal Documents
                    </a>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 civicone-panel-bg govuk-panel--blue-border">
                    <h3 class="govuk-heading-s">
                        <i class="fa-solid fa-circle-info govuk-!-margin-right-2 govuk-icon--blue" aria-hidden="true"></i>
                        Need help?
                    </h3>
                    <p class="govuk-body-s">
                        If you have questions about volunteering or this agreement, please contact us.
                    </p>
                    <a href="<?= $basePath ?>/contact" class="govuk-link">Contact support</a>
                </div>

                <div class="govuk-!-padding-4 govuk-!-margin-top-4 civicone-panel-bg govuk-panel--green-border">
                    <h3 class="govuk-heading-s">
                        <i class="fa-solid fa-shield-halved govuk-!-margin-right-2 govuk-icon--green" aria-hidden="true"></i>
                        Your rights
                    </h3>
                    <ul class="govuk-list govuk-list--bullet govuk-body-s">
                        <li>Training and support</li>
                        <li>Safe working environment</li>
                        <li>Recognition for your contributions</li>
                        <li>References upon request</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
