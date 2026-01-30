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
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Social Prescribing']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

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
        <div class="govuk-!-padding-4 civicone-card-border-left-green civicone-full-height">
            <h2 class="govuk-heading-l civicone-heading-green">
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
        <div class="govuk-inset-text civicone-inset-blue civicone-full-height">
            <p class="govuk-body civicone-text-italic">
                "Monica found out about TBI through the outreach mental health team... Since joining TBI, Monica 'feels much more connected to the community which has had a positive mental health impact'."
            </p>
            <p class="govuk-body-s govuk-!-margin-bottom-0">
                <strong>— Monica (Member)</strong><br>
                <span class="civicone-secondary-text">Source: 2023 Social Impact Study</span>
            </p>
        </div>
    </div>
</div>

<!-- Referral Pathway -->
<section class="govuk-!-margin-bottom-8" aria-label="Referral pathway steps">
    <h2 class="govuk-heading-l govuk-!-text-align-center govuk-!-margin-bottom-6">The Managed Referral Pathway</h2>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-pathway-card civicone-full-height">
                <div class="govuk-!-margin-bottom-2 civicone-step-number">1</div>
                <h3 class="govuk-heading-s">Formal Referral</h3>
                <p class="govuk-body-s">Warm handover from Link Worker to our TBI Hub Coordinator.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-pathway-card civicone-full-height">
                <div class="govuk-!-margin-bottom-2 civicone-step-number">2</div>
                <h3 class="govuk-heading-s">Onboarding</h3>
                <p class="govuk-body-s">1-to-1 welcome to explain the model and identify skills.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-pathway-card civicone-full-height">
                <div class="govuk-!-margin-bottom-2 civicone-step-number">3</div>
                <h3 class="govuk-heading-s">Connection</h3>
                <p class="govuk-body-s">Active facilitation of first exchanges and group activities.</p>
            </div>
        </div>

        <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-pathway-card civicone-full-height">
                <div class="govuk-!-margin-bottom-2 civicone-step-number">4</div>
                <h3 class="govuk-heading-s">Follow-up</h3>
                <p class="govuk-body-s">Feedback to Link Worker on engagement and outcomes.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<div class="govuk-!-padding-6 govuk-!-text-align-center civicone-cta-blue">
    <h2 class="govuk-heading-l civicone-cta-heading">We are Seeking a Partner to Launch a Formal Pilot</h2>
    <p class="govuk-body-l govuk-!-margin-bottom-6 civicone-cta-text civicone-cta-text-centered">
        We are seeking a public sector contract to secure the essential Hub Coordinator role, which is the <strong>lynchpin of the entire service</strong>.
    </p>
    <a href="/uploads/tenants/hour-timebank/THE-RECIPROCITY-PATHWAY_A-Pilot-Proposal.pdf" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button">
        <i class="fa-solid fa-download govuk-!-margin-right-1" aria-hidden="true"></i>
        Download Pilot Proposal
    </a>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>