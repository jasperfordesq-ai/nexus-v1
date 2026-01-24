<?php
/**
 * CivicOne View: Compliance Statement
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = "Compliance Statement - Project NEXUS";
require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/demo">Demo</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Compliance</li>
    </ol>
</nav>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <span class="govuk-tag govuk-tag--blue govuk-!-margin-bottom-4">Security & Standards</span>
        <h1 class="govuk-heading-xl">Government-Grade Infrastructure</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Security That Meets National Standards.</p>

        <p class="govuk-body govuk-!-margin-bottom-6">
            Designed with the Irish public sector in mind, our architecture ensures absolute data sovereignty and compliance. We adhere to rigorous standards to ensure trust and reliability for Local Authorities and the HSE.
        </p>

        <!-- Compliance Checklist -->
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key" style="width: 40px;">
                    <span class="govuk-tag govuk-tag--green">✓</span>
                </dt>
                <dd class="govuk-summary-list__value">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">GDPR & Multi-Tenancy</h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">
                        Using strict physical <code>tenant_id</code> isolation to ensure no data leakage between different local authorities. Data is encrypted at rest and in transit.
                    </p>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key" style="width: 40px;">
                    <span class="govuk-tag govuk-tag--green">✓</span>
                </dt>
                <dd class="govuk-summary-list__value">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">S.I. No. 358/2020 Compliance</h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">
                        The CivicOne interface is audited for WCAG 2.1 Level AA accessibility, ensuring inclusivity for all citizens regardless of ability.
                    </p>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key" style="width: 40px;">
                    <span class="govuk-tag govuk-tag--green">✓</span>
                </dt>
                <dd class="govuk-summary-list__value">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Forensic Audit Trails</h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">
                        Every administrative action is logged with Actor ID, IP Address, and Timestamps for full accountability and transparent governance.
                    </p>
                </dd>
            </div>
        </dl>

    </div>

    <div class="govuk-grid-column-one-third">
        <!-- Sidebar -->
        <div class="govuk-!-padding-4" style="background: #f3f2f1;">
            <h2 class="govuk-heading-s">Technical Specs</h2>
            <dl class="govuk-summary-list govuk-summary-list--no-border">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key govuk-body-s">Hosting</dt>
                    <dd class="govuk-summary-list__value govuk-body-s">Dublin, Ireland (EU)</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key govuk-body-s">Encryption</dt>
                    <dd class="govuk-summary-list__value govuk-body-s">AES-256</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key govuk-body-s">Role Access</dt>
                    <dd class="govuk-summary-list__value govuk-body-s">RBAC Level 3</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key govuk-body-s">Sovereignty</dt>
                    <dd class="govuk-summary-list__value govuk-body-s">100% Irish Data Residency</dd>
                </div>
            </dl>
            <a href="<?= $basePath ?>/technical-specs" class="govuk-button govuk-button--secondary" data-module="govuk-button" style="width: 100%;">
                View Full Proposal
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
