<?php
/**
 * GOV.UK 403 Error Page - Access Denied / Forbidden
 * Following GOV.UK Design System v5.14.0
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Access denied';
$siteName = $siteName ?? TenantContext::getSetting('site_name') ?? 'this service';
$homeUrl = $basePath ?: '/';
$loginUrl = $basePath . '/login';
$contactUrl = $basePath . '/help/contact';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Access denied</h1>

                <p class="govuk-body">You do not have permission to view this page.</p>

                <p class="govuk-body">This might be because:</p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>you need to <a href="<?= htmlspecialchars($loginUrl) ?>" class="govuk-link">sign in</a> to access this page</li>
                    <li>you do not have the right permissions for this area</li>
                    <li>the page is restricted to certain users</li>
                </ul>

                <h2 class="govuk-heading-m">What you can do</h2>

                <p class="govuk-body">
                    If you think you should have access to this page,
                    <a href="<?= htmlspecialchars($contactUrl) ?>" class="govuk-link">contact us</a>
                    for help.
                </p>

                <p class="govuk-body">
                    <a href="<?= htmlspecialchars($homeUrl) ?>" class="govuk-link">Go to the homepage</a>
                </p>
            </div>
        </div>
    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
