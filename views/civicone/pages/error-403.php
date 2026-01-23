<?php
/**
 * GOV.UK 403 Error Page - Access Denied / Forbidden
 * Following GOV.UK Design System v5.14.0
 *
 * Usage: Include this file when a 403 error occurs
 */

// Get site name from context or use default
$siteName = $siteName ?? 'this service';
$homeUrl = $homeUrl ?? '/';
$loginUrl = $loginUrl ?? '/login';
$contactUrl = $contactUrl ?? '/help/contact';
?>
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
