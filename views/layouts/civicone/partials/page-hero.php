<?php
/**
 * CivicOne Page Header - Pure GOV.UK Typography
 *
 * Uses standard GOV.UK heading structure without custom hero styling.
 * See: https://design-system.service.gov.uk/styles/typography/
 *
 * Expected variables:
 * - $hero (array): Hero configuration from HeroResolver
 *   - title: Page title (H1) - REQUIRED
 *   - lead: Lead paragraph text - OPTIONAL
 *
 * @version 2.0.0 - Simplified to pure GOV.UK (removed hero component)
 * @since 2026-01-22
 */

// Validate hero config exists and has required title
if (!isset($hero) || !is_array($hero) || empty($hero['title'])) {
    return;
}

$title = $hero['title'];
$lead = $hero['lead'] ?? null;
?>

<h1 class="govuk-heading-xl"><?= htmlspecialchars($title) ?></h1>
<?php if (!empty($lead)): ?>
<p class="govuk-body-l"><?= htmlspecialchars($lead) ?></p>
<?php endif; ?>
