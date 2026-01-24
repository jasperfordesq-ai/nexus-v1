<?php
/**
 * CivicOne View: Social Prescribing
 * GOV.UK Design System (WCAG 2.1 AA)
 * Tenant-specific: Hour Timebank only
 */
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Social Prescribing';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Social Prescribing</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="sp-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="sp-title">Healthcare Partnership</h2>
    </div>
    <div class="govuk-notification-banner__content govuk-!-text-align-center">
        <h1 class="govuk-notification-banner__heading">Social Prescribing Partner</h1>
        <p class="govuk-body-l">Evidence-Based, Community-Led, and 100% Effective for Wellbeing.</p>
    </div>
</div>

<!-- Outcomes Section -->
<div class="govuk-grid-row govuk-!-margin-bottom-8">
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c; height: 100%;">
            <h2 class="govuk-heading-l" style="color: #00703c;">
                <i class="fa-solid fa-check-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                Validated Outcomes
            </h2>
            <ul class="govuk-list govuk-list--bullet govuk-list--spaced">
                <li>
                    <span class="govuk-tag govuk-tag--green govuk-!-margin-right-1">100%</span>
                    <strong>Improved Wellbeing:</strong> Every member surveyed reported an improvement in emotional, physical, or mental wellbeing.
                </li>
                <li>
                    <span class="govuk-tag govuk-tag--green govuk-!-margin-right-1">95%</span>
                    <strong>Increased Connection:</strong> We are successfully tackling loneliness.
                </li>
                <li>
                    <strong>Strategic Fit:</strong> "Could become part of a social prescribing offering for early intervention".
                </li>
            </ul>
        </div>
    </div>
    <div class="govuk-grid-column-one-half">
        <div class="govuk-inset-text" style="border-left-color: #1d70b8; height: 100%;">
            <p class="govuk-body" style="font-style: italic;">
                "Monica found out about TBI through the outreach mental health team... Since joining TBI, Monica 'feels much more connected to the community which has had a positive mental health impact'."
            </p>
            <p class="govuk-body-s govuk-!-margin-bottom-0">
                <strong>— Monica (Member)</strong><br>
                <span style="color: #505a5f;">Source: 2023 Social Impact Study</span>
            </p>
        </div>
    </div>
</div>

<!-- Referral Pathway -->
<section class="govuk-!-margin-bottom-8" aria-label="Referral pathway steps">
    <h2 class="govuk-heading-l govuk-!-text-align-center govuk-!-margin-bottom-6">The Managed Referral Pathway</h2>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="border: 1px solid #b1b4b6; border-top: 5px solid #1d70b8; height: 100%;">
                <div class="govuk-!-margin-bottom-2" style="width: 40px; height: 40px; border-radius: 50%; background: #1d70b8; display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold;">1</div>
                <h3 class="govuk-heading-s">Formal Referral</h3>
                <p class="govuk-body-s">Warm handover from Link Worker to our TBI Hub Coordinator.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="border: 1px solid #b1b4b6; border-top: 5px solid #1d70b8; height: 100%;">
                <div class="govuk-!-margin-bottom-2" style="width: 40px; height: 40px; border-radius: 50%; background: #1d70b8; display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold;">2</div>
                <h3 class="govuk-heading-s">Onboarding</h3>
                <p class="govuk-body-s">1-to-1 welcome to explain the model and identify skills.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="border: 1px solid #b1b4b6; border-top: 5px solid #1d70b8; height: 100%;">
                <div class="govuk-!-margin-bottom-2" style="width: 40px; height: 40px; border-radius: 50%; background: #1d70b8; display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold;">3</div>
                <h3 class="govuk-heading-s">Connection</h3>
                <p class="govuk-body-s">Active facilitation of first exchanges and group activities.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center" style="border: 1px solid #b1b4b6; border-top: 5px solid #1d70b8; height: 100%;">
                <div class="govuk-!-margin-bottom-2" style="width: 40px; height: 40px; border-radius: 50%; background: #1d70b8; display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold;">4</div>
                <h3 class="govuk-heading-s">Follow-up</h3>
                <p class="govuk-body-s">Feedback to Link Worker on engagement and outcomes.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #1d70b8; color: white;">
    <h2 class="govuk-heading-l" style="color: white;">We are Seeking a Partner to Launch a Formal Pilot</h2>
    <p class="govuk-body-l govuk-!-margin-bottom-6" style="color: white; max-width: 600px; margin-left: auto; margin-right: auto;">
        We are seeking a public sector contract to secure the essential Hub Coordinator role, which is the <strong>lynchpin of the entire service</strong>.
    </p>
    <a href="/uploads/tenants/hour-timebank/THE-RECIPROCITY-PATHWAY_A-Pilot-Proposal.pdf" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button">
        <i class="fa-solid fa-download govuk-!-margin-right-1" aria-hidden="true"></i>
        Download Pilot Proposal
    </a>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>