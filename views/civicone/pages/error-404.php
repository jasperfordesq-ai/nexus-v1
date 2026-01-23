<?php
/**
 * GOV.UK 404 Error Page - Page Not Found
 * Following GOV.UK Design System v5.14.0
 *
 * Reference: https://design-system.service.gov.uk/patterns/page-not-found-pages/
 *
 * Usage: Include this file when a 404 error occurs
 */

// Get site name from context or use default
$siteName = $siteName ?? 'this service';
$homeUrl = $homeUrl ?? '/';
$contactUrl = $contactUrl ?? '/help/contact';
?>
<main class="govuk-main-wrapper" id="main-content" role="main">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">Page not found</h1>

            <p class="govuk-body">If you typed the web address, check it is correct.</p>

            <p class="govuk-body">If you pasted the web address, check you copied the entire address.</p>

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
