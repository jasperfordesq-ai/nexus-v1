<?php
/**
 * GOV.UK 404 Error Page - Page Not Found
 * Following GOV.UK Design System v5.14.0
 *
 * Reference: https://design-system.service.gov.uk/patterns/page-not-found-pages/
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Page not found';
$siteName = $siteName ?? TenantContext::getSetting('site_name') ?? 'this service';
$homeUrl = $basePath ?: '/';
$contactUrl = $basePath . '/help/contact';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Page not found</h1>

                <p class="govuk-body">If you typed the web address, check it is correct.</p>

                <p class="govuk-body">If you pasted the web address, check you copied the entire address.</p>

                <?php if (isset($suggestedUrl) && $suggestedUrl): ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body">Did you mean: <a href="<?= htmlspecialchars($suggestedUrl) ?>" class="govuk-link"><?= htmlspecialchars($suggestedUrl) ?></a>?</p>
                </div>
                <?php endif; ?>

                <p class="govuk-body">
                    If the web address is correct or you selected a link or button,
                    <a href="<?= htmlspecialchars($contactUrl) ?>" class="govuk-link">contact us</a>
                    if you need to speak to someone about <?= htmlspecialchars($siteName) ?>.
                </p>

                <p class="govuk-body">
                    <a href="<?= htmlspecialchars($homeUrl) ?>" class="govuk-link">Go to the homepage</a>
                </p>
            </div>
        </div>
    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
