<?php
/**
 * Legal Hub - GOV.UK Design System
 * Template E: Content/Article (Hub variant)
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Legal information';

// Get tenant info
$tenant = TenantContext::get();
$tenantName = $tenant['name'] ?? 'This Community';

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Legal
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Legal information</h1>

                <p class="govuk-body-l">
                    Information about how we handle your data, the terms of using this service,
                    and our commitment to accessibility.
                </p>

            </div>
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-full">

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <!-- Legal Pages Grid -->
                <div class="govuk-grid-row">

                    <!-- Privacy Policy -->
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <div class="civicone-card-bordered">
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                <a href="<?= $basePath ?>/privacy" class="govuk-link">Privacy Policy</a>
                            </h2>
                            <p class="govuk-body-s">
                                How we collect, use, and protect your personal information.
                            </p>
                        </div>
                    </div>

                    <!-- Terms of Service -->
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <div class="civicone-card-bordered">
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                <a href="<?= $basePath ?>/terms" class="govuk-link">Terms of Service</a>
                            </h2>
                            <p class="govuk-body-s">
                                The rules and guidelines for using this platform.
                            </p>
                        </div>
                    </div>

                    <!-- Accessibility -->
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <div class="civicone-card-bordered">
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                <a href="<?= $basePath ?>/accessibility" class="govuk-link">Accessibility Statement</a>
                            </h2>
                            <p class="govuk-body-s">
                                Our commitment to making this service accessible to everyone.
                            </p>
                        </div>
                    </div>

                    <!-- Cookie Policy -->
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <div class="civicone-card-bordered">
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                <a href="<?= $basePath ?>/cookies" class="govuk-link">Cookie Policy</a>
                            </h2>
                            <p class="govuk-body-s">
                                How we use cookies and similar technologies.
                            </p>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <div class="civicone-card-bordered">
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                <a href="<?= $basePath ?>/contact" class="govuk-link">Contact Us</a>
                            </h2>
                            <p class="govuk-body-s">
                                Get in touch with questions or feedback.
                            </p>
                        </div>
                    </div>

                    <!-- Help Centre -->
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <div class="civicone-card-bordered">
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                <a href="<?= $basePath ?>/help" class="govuk-link">Help Centre</a>
                            </h2>
                            <p class="govuk-body-s">
                                Guides and documentation for using the platform.
                            </p>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h2 class="govuk-heading-m">About <?= htmlspecialchars($tenantName) ?></h2>

                <p class="govuk-body">
                    <?= htmlspecialchars($tenantName) ?> is a community timebank that enables
                    members to exchange skills and services using time credits.
                </p>

                <p class="govuk-body">
                    <a href="<?= $basePath ?>/about" class="govuk-link">Learn more about us</a>
                </p>

            </div>
        </div>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
