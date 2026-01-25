<?php
/**
 * CivicOne Dashboard - Account Area Hub (Overview)
 * GOV.UK Frontend v5.14.0 Compliant
 * Template: Account Area Template (Template G)
 *
 * v2.0.0 GOV.UK Polish Refactor (2026-01-24):
 * - Uses official GOV.UK Frontend v5.14.0 classes
 * - Proper govuk-grid-row/column layout
 * - MOJ Sub navigation pattern maintained
 *
 * Pattern Sources:
 * - MOJ Sub navigation: https://design-patterns.service.justice.gov.uk/components/sub-navigation/
 * - GOV.UK Page Template: https://design-system.service.gov.uk/styles/page-template/
 */

$hTitle = "My Dashboard";
$hSubtitle = "Welcome back, " . htmlspecialchars($user['name']);

require dirname(__DIR__) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- GOV.UK Breadcrumbs -->
<nav class="govuk-breadcrumbs govuk-!-margin-bottom-4" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Dashboard</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="govuk-grid-row">
    <div class="govuk-grid-column-full">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">My Dashboard</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Welcome back, <?= htmlspecialchars($user['name']) ?></p>
    </div>
</div>

<!-- Account Area Navigation -->
<div class="govuk-grid-row">
    <div class="govuk-grid-column-full">
        <?php require dirname(__DIR__) . '/layouts/civicone/partials/account-navigation.php'; ?>
    </div>
</div>

<!-- Overview Content -->
<div class="govuk-grid-row govuk-!-margin-top-6">
    <div class="govuk-grid-column-full">
        <?php require __DIR__ . '/dashboard/partials/_overview.php'; ?>
    </div>
</div>

<!-- Quick Actions FAB (Mobile) -->
<div id="govukFab" class="govuk-!-display-none-print civicone-fab-container">
    <button type="button" class="govuk-button civicone-fab-button" onclick="toggleGovukFab()" aria-label="Quick Actions" aria-expanded="false" aria-controls="govukFabMenu">
        <span class="civicone-fab-icon" aria-hidden="true">+</span>
    </button>
    <div id="govukFabMenu" hidden class="civicone-fab-menu">
        <ul class="govuk-list govuk-!-margin-bottom-0">
            <li class="govuk-!-margin-bottom-2">
                <a href="<?= $basePath ?>/wallet" class="govuk-link">Send Credits</a>
            </li>
            <li class="govuk-!-margin-bottom-2">
                <a href="<?= $basePath ?>/listings/create" class="govuk-link">New Listing</a>
            </li>
            <li>
                <a href="<?= $basePath ?>/events/create" class="govuk-link">Create Event</a>
            </li>
        </ul>
    </div>
</div>

<!-- Dashboard functions in civicone-dashboard.js -->
<script src="/assets/js/civicone-dashboard.js"></script>
<script>
    // Initialize dashboard with basePath
    if (typeof initCivicOneDashboard === 'function') {
        initCivicOneDashboard('<?= $basePath ?>');
    }
</script>

<?php require dirname(__DIR__) . '/layouts/civicone/footer.php'; ?>
