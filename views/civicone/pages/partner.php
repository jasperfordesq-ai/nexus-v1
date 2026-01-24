<?php
/**
 * Partner With Us Page - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 * Tenant-specific: Hour Timebank only
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

// Tenant-specific: Hour Timebank only
$tSlug = TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$basePath = TenantContext::getBasePath();
$pageTitle = 'Partner with us';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/about">About</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Partner with us
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Partner with us</h1>

                <p class="govuk-body-l">
                    A 1:16 return on social investment. We're seeking partners to secure core operations
                    and execute our 2026-2030 Strategic Plan.
                </p>

            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- Funding Gap Section -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h2 class="govuk-heading-l">Addressing the funding gap</h2>

                <p class="govuk-body">
                    The closure of our primary social enterprise income stream has created a funding gap.
                    Our <strong>most urgent priority</strong> is funding the central
                    <strong>Hub Coordinator (Broker)</strong> role for our West Cork Centre of Excellence.
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>
                        The Coordinator was identified as the "key enabler for expansion" and positive outcomes
                    </li>
                    <li>
                        This investment transitions us from 100% grant reliance to a diversified, sustainable model
                    </li>
                    <li>
                        Your funding of this role is the foundational first step to unlocking our entire national growth plan
                    </li>
                </ul>

                <h2 class="govuk-heading-l">Deliver measurable social impact</h2>

            </div>
        </div>

        <!-- Impact Cards -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c;">
                    <h3 class="govuk-heading-m">Exceptional social value</h3>
                    <p class="govuk-body">
                        Align your brand with a model proven to return <strong>€16 in social value
                        for every €1 invested</strong>. We tackle social isolation, a critical public
                        health issue in Ireland.
                    </p>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                    <h3 class="govuk-heading-m">Proof and transparency</h3>
                    <p class="govuk-body">
                        Our impact is validated by an independent <strong>2023 Social Impact Study</strong>.
                        We provide clear, data-driven reporting that showcases your commitment to CSR.
                    </p>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #d53880;">
                    <h3 class="govuk-heading-m">Strategic growth</h3>
                    <p class="govuk-body">
                        Invest in a resilient organisation that has a clear <strong>5-year roadmap</strong>
                        to scale from a single region to a national network of over 2,500 active members.
                    </p>
                </div>
            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- CTA Section -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h2 class="govuk-heading-l">Let's discuss your pathfinder investment</h2>

                <p class="govuk-body">
                    Join us in building a more connected, resilient Ireland.
                </p>

                <p class="govuk-body govuk-!-margin-bottom-6">
                    <a href="<?= $basePath ?>/contact" class="govuk-button" data-module="govuk-button">
                        Contact strategy team
                    </a>
                    <a href="<?= $basePath ?>/strategic-plan" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        View strategic plan
                    </a>
                </p>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-s">Our partners and supporters</h2>

                    <p class="govuk-body">
                        We're grateful for the support of organisations across Ireland who share our vision.
                    </p>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Learn more</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/impact-summary" class="govuk-link">Our impact</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/our-story" class="govuk-link">Our story</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/strategic-plan" class="govuk-link">Strategic plan</a>
                        </li>
                    </ul>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
