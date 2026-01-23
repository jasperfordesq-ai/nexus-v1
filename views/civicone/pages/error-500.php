<?php
/**
 * GOV.UK 500 Error Page - Service Unavailable / Server Error
 * Following GOV.UK Design System v5.14.0
 *
 * Reference: https://design-system.service.gov.uk/patterns/problem-with-the-service-pages/
 *
 * Usage: Include this file when a 500 error occurs
 */

// Get site name from context or use default
$siteName = $siteName ?? 'this service';
$homeUrl = $homeUrl ?? '/';
$contactUrl = $contactUrl ?? '/help/contact';
$referenceNumber = $referenceNumber ?? null;
?>
<main class="govuk-main-wrapper" id="main-content" role="main">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">Sorry, there is a problem with the service</h1>

            <p class="govuk-body">Try again later.</p>

            <?php if ($referenceNumber): ?>
            <p class="govuk-body">
                Your reference number is <strong><?= htmlspecialchars($referenceNumber) ?></strong>.
                Please quote this when contacting us.
            </p>
            <?php endif; ?>

            <p class="govuk-body">
                We saved your answers. They will be available for 30 days.
            </p>

            <p class="govuk-body">
                <a href="<?= htmlspecialchars($contactUrl) ?>" class="govuk-link">Contact us</a>
                if you have any questions.
            </p>

            <h2 class="govuk-heading-m">What you can do</h2>

            <ul class="govuk-list govuk-list--bullet">
                <li>
                    <a href="<?= htmlspecialchars($homeUrl) ?>" class="govuk-link">Go to the homepage</a>
                </li>
                <li>Try again in a few minutes</li>
                <li>
                    <a href="<?= htmlspecialchars($contactUrl) ?>" class="govuk-link">Contact support</a>
                    if the problem continues
                </li>
            </ul>
        </div>
    </div>
</main>
