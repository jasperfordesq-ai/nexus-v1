<?php
/**
 * CivicOne View: Our Story
 * GOV.UK Design System (WCAG 2.1 AA)
 * Tenant-specific: Hour Timebank only
 */
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Our Story';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/about">About</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Our Story</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="hero-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="hero-title">
            <span class="govuk-tag govuk-tag--blue govuk-!-margin-right-2">HOUR TIMEBANK CLG</span>
        </h2>
    </div>
    <div class="govuk-notification-banner__content">
        <h1 class="govuk-notification-banner__heading">Our Mission & Vision</h1>
        <p class="govuk-body-l">Building a resilient and equitable society based on mutual respect.</p>
    </div>
</div>

<!-- Mission & Vision Grid -->
<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <!-- Mission -->
    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8; height: 100%;">
            <h2 class="govuk-heading-m" style="color: #1d70b8;">
                <i class="fa-solid fa-flag govuk-!-margin-right-2" aria-hidden="true"></i>
                Our Mission
            </h2>
            <p class="govuk-body">
                To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received, building a resilient and equitable society based on mutual respect.
            </p>
        </div>
    </div>

    <!-- Vision -->
    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c; height: 100%;">
            <h2 class="govuk-heading-m" style="color: #00703c;">
                <i class="fa-solid fa-eye govuk-!-margin-right-2" aria-hidden="true"></i>
                Our Vision
            </h2>
            <p class="govuk-body">
                An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient, and thriving local communities.
            </p>
        </div>
    </div>
</div>

<!-- Values Section -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-6 civicone-panel-bg">
    <h2 class="govuk-heading-l govuk-!-text-align-center govuk-!-margin-bottom-6">The Values That Guide Every Hour</h2>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: white; height: 100%;">
                <p class="govuk-body govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-scale-balanced fa-2x" style="color: #1d70b8;" aria-hidden="true"></i>
                </p>
                <h3 class="govuk-heading-s" style="color: #1d70b8;">Reciprocity & Equality</h3>
                <p class="govuk-body-s">
                    We believe in a two-way street; everyone has something to give. We honour the time and skills of all members equally—one hour equals one hour, no matter the service.
                </p>
            </div>
        </div>

        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: white; height: 100%;">
                <p class="govuk-body govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-network-wired fa-2x" style="color: #d53880;" aria-hidden="true"></i>
                </p>
                <h3 class="govuk-heading-s" style="color: #d53880;">Inclusion & Connection</h3>
                <p class="govuk-body-s">
                    We welcome people of all ages, backgrounds, and abilities, celebrating everyone as a valuable asset. We exist to reduce isolation and build meaningful relationships.
                </p>
            </div>
        </div>

        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: white; height: 100%;">
                <p class="govuk-body govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-hand-holding-heart fa-2x" style="color: #00703c;" aria-hidden="true"></i>
                </p>
                <h3 class="govuk-heading-s" style="color: #00703c;">Empowerment & Resilience</h3>
                <p class="govuk-body-s">
                    We provide a platform for individuals to recognize their own value and actively participate in building community. This mechanism is proven to build resilience.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Professional Foundation -->
<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
            <h2 class="govuk-heading-l">Our Professional Foundation</h2>
            <p class="govuk-body">
                Our journey began in 2012 with the Clonakilty Favour Exchange. To ensure long-term stability and impact, the directors established hOUR Timebank CLG as a formal, registered Irish charity in 2017.
            </p>

            <div class="govuk-!-margin-bottom-4">
                <span class="govuk-tag govuk-tag--green govuk-!-margin-right-2">Registered Charity</span>
                <span class="govuk-tag govuk-tag--blue govuk-!-margin-right-2">Rethink Ireland Awardee</span>
                <span class="govuk-tag govuk-tag--purple">1:16 SROI Impact</span>
            </div>
        </div>
    </div>
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4 civicone-panel-bg">
            <h3 class="govuk-heading-s">Want proof of our impact?</h3>
            <p class="govuk-body-s govuk-!-margin-bottom-4">We have an independently verified Social Return on Investment (SROI) study.</p>
            <a href="<?= $basePath ?>/impact-report" class="govuk-button" data-module="govuk-button">View Full Report</a>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
