<?php
/**
 * Our Story Page - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Our story';

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
                Our story
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Our story</h1>

                <p class="govuk-body-l">
                    How a small community initiative in West Cork grew into a movement
                    that's transforming how neighbours help each other.
                </p>

            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- Timeline -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h2 class="govuk-heading-l">The beginning</h2>

                <p class="govuk-body">
                    hOUR Timebank began in 2019 as a response to growing social isolation in rural West Cork.
                    A group of community leaders came together with a simple idea: what if we could help
                    neighbours connect by exchanging time and skills?
                </p>

                <p class="govuk-body">
                    The concept of timebanking wasn't new, but we wanted to make it accessible,
                    digital-first, and truly community-led. We started with just 12 founding members
                    in Skibbereen.
                </p>

                <h2 class="govuk-heading-l">Growth and impact</h2>

                <p class="govuk-body">
                    Within the first year, we grew to over 100 active members. Word spread organically
                    as people experienced the benefits first-hand: reduced isolation, new friendships,
                    and practical help when they needed it most.
                </p>

                <div class="govuk-inset-text">
                    <p class="govuk-body">
                        An independent Social Return on Investment study in 2023 found that for every
                        <strong>€1 invested in hOUR Timebank, €16 of social value is created</strong>.
                    </p>
                </div>

                <h2 class="govuk-heading-l">Where we are today</h2>

                <p class="govuk-body">
                    Today, hOUR Timebank has grown beyond West Cork. Our platform, Project NEXUS,
                    now serves multiple communities across Ireland. We've facilitated thousands of
                    exchanges, from gardening help to IT support, from companionship to language lessons.
                </p>

                <p class="govuk-body">
                    But our mission remains the same: building stronger, more connected communities
                    where everyone's time is valued equally.
                </p>

                <h2 class="govuk-heading-l">Our vision for the future</h2>

                <p class="govuk-body">
                    We're working towards a future where every community in Ireland has access to
                    timebanking. Our 2026-2030 Strategic Plan outlines how we'll grow from serving
                    hundreds to thousands of members while maintaining our community-first values.
                </p>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-m">Key milestones</h2>

                    <dl class="govuk-summary-list govuk-summary-list--no-border">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">2019</dt>
                            <dd class="govuk-summary-list__value">Founded in Skibbereen with 12 members</dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">2020</dt>
                            <dd class="govuk-summary-list__value">100+ active members during pandemic</dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">2023</dt>
                            <dd class="govuk-summary-list__value">SROI study confirms 1:16 social return</dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">2024</dt>
                            <dd class="govuk-summary-list__value">Project NEXUS platform launched</dd>
                        </div>
                    </dl>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Learn more</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/about" class="govuk-link">About us</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/impact-summary" class="govuk-link">Our impact</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/strategic-plan" class="govuk-link">Strategic plan</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/partner" class="govuk-link">Partner with us</a>
                        </li>
                    </ul>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
