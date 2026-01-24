<?php
/**
 * CivicOne View: Timebanking Guide
 * GOV.UK Design System (WCAG 2.1 AA)
 * Tenant-specific: Hour Timebank only
 */
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Timebanking Guide';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Timebanking Guide</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="guide-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="guide-title">hOUR Timebank</h2>
    </div>
    <div class="govuk-notification-banner__content govuk-!-text-align-center">
        <h1 class="govuk-notification-banner__heading">Building Community</h1>
        <p class="govuk-body-l">Give an hour, get an hour. It's that simple.</p>
        <div class="govuk-button-group" style="justify-content: center;">
            <a href="<?= $basePath ?>/register" class="govuk-button" data-module="govuk-button">Join Community</a>
            <a href="<?= $basePath ?>/impact-report" class="govuk-button govuk-button--secondary" data-module="govuk-button">See Impact</a>
        </div>
    </div>
</div>

<!-- Verified Impact Stats -->
<section class="govuk-!-margin-bottom-8" aria-label="Impact statistics">
    <h2 class="govuk-heading-m govuk-!-text-align-center govuk-!-margin-bottom-4">
        <span class="govuk-tag govuk-tag--blue">Our Verified Impact</span>
    </h2>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="border: 1px solid #b1b4b6; border-top: 5px solid #1d70b8;">
                <p class="govuk-body-s govuk-!-margin-bottom-1" style="color: #505a5f;">Social Return</p>
                <p class="govuk-heading-xl govuk-!-margin-bottom-0" style="color: #1d70b8;">16:1</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="border: 1px solid #b1b4b6; border-top: 5px solid #d53880;">
                <p class="govuk-body-s govuk-!-margin-bottom-1" style="color: #505a5f;">Improved Wellbeing</p>
                <p class="govuk-heading-xl govuk-!-margin-bottom-0" style="color: #d53880;">100%</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="border: 1px solid #b1b4b6; border-top: 5px solid #00703c;">
                <p class="govuk-body-s govuk-!-margin-bottom-1" style="color: #505a5f;">Socially Connected</p>
                <p class="govuk-heading-xl govuk-!-margin-bottom-0" style="color: #00703c;">95%</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-8" style="background: #f3f2f1;">
    <h2 class="govuk-heading-l govuk-!-text-align-center govuk-!-margin-bottom-6">How It Works: 3 Simple Steps</h2>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: white; height: 100%;">
                <div class="govuk-!-margin-bottom-3" style="width: 60px; height: 60px; border-radius: 50%; background: #1d70b8; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-handshake fa-lg" style="color: white;" aria-hidden="true"></i>
                </div>
                <h3 class="govuk-heading-s">Give an Hour</h3>
                <p class="govuk-body-s">Share a skill you love—from practical help to a friendly chat or a lift to the shops.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: white; height: 100%;">
                <div class="govuk-!-margin-bottom-3" style="width: 60px; height: 60px; border-radius: 50%; background: #d53880; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-clock fa-lg" style="color: white;" aria-hidden="true"></i>
                </div>
                <h3 class="govuk-heading-s">Earn a Credit</h3>
                <p class="govuk-body-s">You automatically earn one Time Credit for every hour you spend helping another member.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: white; height: 100%;">
                <div class="govuk-!-margin-bottom-3" style="width: 60px; height: 60px; border-radius: 50%; background: #00703c; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-user-group fa-lg" style="color: white;" aria-hidden="true"></i>
                </div>
                <h3 class="govuk-heading-s">Get Help</h3>
                <p class="govuk-body-s">Spend your credit to get support, learn a new skill, or join a community work day.</p>
            </div>
        </div>
    </div>
</div>

<!-- Fundamental Values -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-8" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
    <h2 class="govuk-heading-l govuk-!-text-align-center govuk-!-margin-bottom-4">Our Fundamental Values</h2>
    <p class="govuk-body-l govuk-!-text-align-center govuk-!-margin-bottom-6">
        At hOUR Timebank, we believe that true wealth is found in our connections with one another. Our community is built on five fundamental values:
    </p>

    <ul class="govuk-list govuk-list--bullet govuk-list--spaced">
        <li><strong>We Are All Assets:</strong> Every human being has something of value to contribute.</li>
        <li><strong>Redefining Work:</strong> We honour the real work of family and community.</li>
        <li><strong>Reciprocity:</strong> Helping works better as a two-way street.</li>
        <li><strong>Social Networks:</strong> People flourish in community and perish in isolation.</li>
    </ul>
</div>

<!-- CTA -->
<div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #1d70b8; color: white;">
    <span class="govuk-tag govuk-tag--yellow govuk-!-margin-bottom-4">Social Impact</span>
    <h2 class="govuk-heading-l" style="color: white;">A 1:16 Return on Investment</h2>
    <p class="govuk-body-l govuk-!-margin-bottom-6" style="color: white; max-width: 600px; margin-left: auto; margin-right: auto;">
        We have a proven, independently validated model. We are now seeking strategic partners to help us secure our core operations and scale our impact across Ireland.
    </p>
    <a href="<?= $basePath ?>/partner" class="govuk-button govuk-button--secondary" data-module="govuk-button">Partner With Us</a>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>