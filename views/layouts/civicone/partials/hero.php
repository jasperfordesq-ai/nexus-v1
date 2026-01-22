<?php
/**
 * CivicOne Page Header - Pure GOV.UK Typography
 *
 * Uses standard GOV.UK heading structure without custom hero styling.
 * See: https://design-system.service.gov.uk/styles/typography/
 *
 * Expected variables:
 * - $hTitle or $pageTitle: Page heading (H1) - REQUIRED
 * - $hSubtitle or $pageSubtitle: Lead paragraph - OPTIONAL
 *
 * @version 2.0.0 - Simplified to pure GOV.UK (removed hero component)
 * @since 2026-01-22
 */

// Resolve variables
$title = $hTitle ?? $pageTitle ?? '';
$subtitle = $hSubtitle ?? $pageSubtitle ?? '';

// Only render if we have a title
if (empty($title)) {
    return;
}
?>

<h1 class="govuk-heading-xl"><?= htmlspecialchars($title) ?></h1>
<?php if (!empty($subtitle)): ?>
<p class="govuk-body-l"><?= htmlspecialchars($subtitle) ?></p>
<?php endif; ?>
