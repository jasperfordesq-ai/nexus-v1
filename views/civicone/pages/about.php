<?php
/**
 * About Page - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'About us';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                About us
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">About us</h1>

                <p class="govuk-body-l">
                    Project NEXUS is a community platform dedicated to the exchange of time and skills.
                    We believe that everyone has something valuable to contribute.
                </p>

            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- Our Values -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-full">
                <h2 class="govuk-heading-l">How timebanking works</h2>
            </div>
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-third">
                <h3 class="govuk-heading-m">Connect</h3>
                <p class="govuk-body">
                    Find neighbours who share your interests and needs.
                    Join a community of people ready to help and be helped.
                </p>
            </div>
            <div class="govuk-grid-column-one-third">
                <h3 class="govuk-heading-m">Exchange</h3>
                <p class="govuk-body">
                    Trade 1 hour of help for 1 time credit.
                    Everyone's time is valued equally, regardless of the service provided.
                </p>
            </div>
            <div class="govuk-grid-column-one-third">
                <h3 class="govuk-heading-m">Grow</h3>
                <p class="govuk-body">
                    Build a stronger, more resilient local community together.
                    Watch connections flourish and neighbourhoods thrive.
                </p>
            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- Our Mission -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h2 class="govuk-heading-l">Our mission</h2>

                <p class="govuk-body">
                    We're building technology that helps communities come together to share skills,
                    support each other, and strengthen local connections.
                </p>

                <p class="govuk-body">
                    Timebanking is a proven model that has helped communities around the world.
                    By valuing everyone's time equally, we create a more inclusive economy
                    where everyone can participate.
                </p>

                <div class="govuk-inset-text">
                    <p class="govuk-body govuk-!-font-weight-bold">
                        "Time is the most valuable currency. When we share our time,
                        we invest in each other and our community."
                    </p>
                </div>

                <h2 class="govuk-heading-l">Who we are</h2>

                <p class="govuk-body">
                    Project NEXUS is developed by hOUR Timebank CLG, a community-led organisation
                    based in West Cork, Ireland. We're committed to open, accessible technology
                    that serves the needs of real communities.
                </p>

                <p class="govuk-body">
                    Our platform follows the GOV.UK Design System standards to ensure
                    it's accessible to everyone, including people with disabilities.
                </p>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-m">Get involved</h2>

                    <p class="govuk-body">
                        Ready to start sharing your time and skills?
                    </p>

                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/register" class="govuk-button" data-module="govuk-button">
                            Join the community
                        </a>
                    </p>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Learn more</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/faq" class="govuk-link">Frequently asked questions</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/help" class="govuk-link">Help centre</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a>
                        </li>
                    </ul>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Contact details</h2>
                    <p class="govuk-body">
                        hOUR Timebank CLG<br>
                        Main Street, Skibbereen<br>
                        Co. Cork, Ireland
                    </p>
                    <p class="govuk-body">
                        <a href="mailto:hello@hourtimebank.ie" class="govuk-link">hello@hourtimebank.ie</a>
                    </p>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
