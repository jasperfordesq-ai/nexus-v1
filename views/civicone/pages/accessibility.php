<?php
/**
 * Accessibility Statement - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Accessibility Statement';

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
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/legal">Legal</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Accessibility
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Accessibility statement</h1>

                <p class="govuk-body-l">
                    This accessibility statement applies to <?= htmlspecialchars($tenantName) ?>.
                </p>

                <p class="govuk-body">
                    <strong>Last updated:</strong> <?= date('j F Y') ?>
                </p>

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <!-- Section 1: Commitment -->
                <h2 class="govuk-heading-l">Our commitment to accessibility</h2>

                <p class="govuk-body">
                    <?= htmlspecialchars($tenantName) ?> is committed to ensuring digital accessibility for people
                    with disabilities. We continually improve the user experience for everyone and apply
                    relevant accessibility standards.
                </p>

                <!-- Section 2: Conformance -->
                <h2 class="govuk-heading-l">Conformance status</h2>

                <p class="govuk-body">
                    We aim to conform to the <strong>Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA</strong>.
                    These guidelines explain how to make web content more accessible for people with disabilities.
                </p>

                <div class="govuk-inset-text">
                    Our accessibility audit was completed in January 2026. We continue to make improvements
                    and test regularly with automated tools including pa11y.
                </div>

                <!-- Section 3: Features -->
                <h2 class="govuk-heading-l">Accessibility features</h2>

                <p class="govuk-body">Our platform includes the following accessibility features:</p>

                <h3 class="govuk-heading-m">Navigation and structure</h3>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Skip to main content link for keyboard users</li>
                    <li>Breadcrumb navigation on all major pages</li>
                    <li>Clear and consistent navigation structure</li>
                    <li>Semantic HTML headings in proper hierarchy</li>
                    <li>ARIA landmarks for screen reader navigation</li>
                </ul>

                <h3 class="govuk-heading-m">Visual design</h3>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Colour contrast meeting WCAG AA standards (minimum 4.5:1 for text)</li>
                    <li>Visible focus indicators on all interactive elements (GOV.UK yellow focus ring)</li>
                    <li>Resizable text up to 200% without loss of functionality</li>
                    <li>Support for reduced motion preferences</li>
                </ul>

                <h3 class="govuk-heading-m">Forms and interactions</h3>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Keyboard-accessible forms, buttons, and controls</li>
                    <li>Clear form labels with required field indicators</li>
                    <li>Descriptive error messages linked to form fields</li>
                    <li>Autocomplete attributes for faster form completion</li>
                </ul>

                <h3 class="govuk-heading-m">Content and links</h3>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Alternative text for all meaningful images</li>
                    <li>Descriptive link text (avoiding generic "click here")</li>
                    <li>Accessible SVG icons with proper ARIA labels</li>
                    <li>Data tables with scope attributes and captions</li>
                </ul>

                <!-- Section 4: Assistive technologies -->
                <h2 class="govuk-heading-l">Assistive technologies</h2>

                <p class="govuk-body">
                    This website is designed to be compatible with the following assistive technologies:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>Screen readers (JAWS, NVDA, VoiceOver, TalkBack)</li>
                    <li>Screen magnification software</li>
                    <li>Speech recognition software</li>
                    <li>Keyboard-only navigation</li>
                    <li>Switch access devices</li>
                </ul>

                <!-- Section 5: Known limitations -->
                <h2 class="govuk-heading-l">Known limitations</h2>

                <p class="govuk-body">
                    While we strive for full accessibility, some content may have limitations:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>Some older user-uploaded images may lack alt text</li>
                    <li>Embedded third-party content (maps, videos) may not be fully accessible</li>
                    <li>PDF documents may have varying accessibility levels</li>
                </ul>

                <p class="govuk-body">
                    We are actively working to identify and resolve accessibility issues.
                </p>

                <!-- Section 6: Feedback -->
                <h2 class="govuk-heading-l">Feedback and contact information</h2>

                <p class="govuk-body">
                    We welcome your feedback on the accessibility of <?= htmlspecialchars($tenantName) ?>.
                    Please let us know if you encounter accessibility barriers.
                </p>

                <p class="govuk-body">
                    <a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a> with accessibility feedback.
                </p>

                <p class="govuk-body">When contacting us, please include:</p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>The page URL where you encountered the issue</li>
                    <li>A description of the accessibility barrier</li>
                    <li>The assistive technology you use (if applicable)</li>
                </ul>

                <div class="govuk-inset-text">
                    We aim to respond to accessibility feedback within 5 working days.
                </div>

                <!-- Section 7: Enforcement -->
                <h2 class="govuk-heading-l">Enforcement procedure</h2>

                <p class="govuk-body">
                    If you are not satisfied with our response to your accessibility concern,
                    you may escalate the matter through our formal complaints procedure or contact:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li><strong>Ireland:</strong> Irish Human Rights and Equality Commission (IHREC)</li>
                    <li><strong>UK:</strong> Equality Advisory Support Service (EASS)</li>
                </ul>

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
                            <a href="<?= $basePath ?>/terms" class="govuk-link">Terms of Service</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/cookies" class="govuk-link">Cookie Policy</a>
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
