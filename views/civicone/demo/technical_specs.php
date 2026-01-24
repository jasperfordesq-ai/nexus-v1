<?php
/**
 * CivicOne View: Technical Specifications
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = "Technical Specifications - Project NEXUS";
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
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Technical Specifications</li>
    </ol>
</nav>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Technical Proposal</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Project NEXUS: Public Sector Edition</p>

        <!-- Executive Summary -->
        <h2 class="govuk-heading-l">1. Executive Summary</h2>
        <table class="govuk-table govuk-!-margin-bottom-6">
            <tbody class="govuk-table__body">
                <tr class="govuk-table__row">
                    <th scope="row" class="govuk-table__header">Platform</th>
                    <td class="govuk-table__cell">NEXUS (Custom PHP MVC)</td>
                </tr>
                <tr class="govuk-table__row">
                    <th scope="row" class="govuk-table__header">Database</th>
                    <td class="govuk-table__cell">MySQL (Spatial Extensions Enabled)</td>
                </tr>
                <tr class="govuk-table__row">
                    <th scope="row" class="govuk-table__header">Hosting</th>
                    <td class="govuk-table__cell">Ireland-based / Data Sovereign</td>
                </tr>
                <tr class="govuk-table__row">
                    <th scope="row" class="govuk-table__header">Performance</th>
                    <td class="govuk-table__cell">&lt; 300ms Page Load (Zero-Bloat Arch)</td>
                </tr>
            </tbody>
        </table>

        <!-- Security -->
        <h2 class="govuk-heading-l">2. Security & Compliance</h2>
        <ul class="govuk-list govuk-list--bullet govuk-!-margin-bottom-6">
            <li>
                <strong>SQL Injection Proof:</strong> All database interactions utilize PDO Prepared Statements.
            </li>
            <li>
                <strong>Strict Multi-Tenancy:</strong> Physical <code>tenant_id</code> scoping at the Database Wrapper level prevents cross-contamination.
            </li>
            <li>
                <strong>Audit Logging:</strong> Granular tracking of all User/Admin actions.
            </li>
            <li>
                <strong>Accessibility:</strong> WCAG 2.1 Level AA Compliant (CivicOne Layout).
            </li>
        </ul>

        <!-- Integration -->
        <h2 class="govuk-heading-l">3. Integration Capabilities</h2>
        <p class="govuk-body govuk-!-margin-bottom-6">
            The NEXUS platform is API-First. We expose secure RESTful endpoints for integration with existing Council/HSE data portals, CRM systems (Salesforce/Microsoft Dynamics), and volunteer registries.
        </p>

        <div class="govuk-button-group">
            <a href="<?= $basePath ?>/demo" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i>
                Back to Demo Home
            </a>
            <a href="<?= $basePath ?>/compliance" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                View Compliance Statement
            </a>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
