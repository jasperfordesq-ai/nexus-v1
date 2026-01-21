<?php
/**
 * CivicOne Page Hero Component
 * Follows Section 9C of CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md
 *
 * Expected variables:
 * - $hero (array): Hero configuration from HeroResolver
 *   - variant: 'page' or 'banner'
 *   - title: Page title (H1) - REQUIRED
 *   - lead: Lead paragraph text - OPTIONAL
 *   - cta: Call-to-action (only for banner variant) - OPTIONAL
 *     - text: Button text
 *     - url: Button URL
 *
 * @version 1.0.0
 * @since 2026-01-21
 */

// Validate hero config exists and has required title
if (!isset($hero) || !is_array($hero) || empty($hero['title'])) {
    // No hero to render
    return;
}

// Extract hero properties with defaults
$variant = $hero['variant'] ?? 'page';
$title = $hero['title'];
$lead = $hero['lead'] ?? null;
$cta = $hero['cta'] ?? null;

// Validate variant
$validVariants = ['page', 'banner'];
if (!in_array($variant, $validVariants)) {
    $variant = 'page';
}

// Page hero MUST NOT have CTA (Section 9C.3 PH-006)
if ($variant === 'page') {
    $cta = null;
}

// Banner hero: validate CTA structure
if ($variant === 'banner' && $cta !== null) {
    if (!is_array($cta) || empty($cta['text']) || empty($cta['url'])) {
        $cta = null; // Invalid CTA, remove it
    }
}

// Hero CSS classes
$heroClasses = ['civicone-hero', "civicone-hero--{$variant}"];
$heroClassAttr = implode(' ', $heroClasses);
?>

<!-- Page Hero (Section 9C: Page Hero Contract) -->
<div class="<?= htmlspecialchars($heroClassAttr) ?>">

    <?php
    // Section 9C.3 PH-002: Hero MUST contain exactly ONE <h1>
    // Section 9C.3 PH-003: H1 MUST use .civicone-heading-xl class
    ?>
    <h1 class="civicone-heading-xl"><?= htmlspecialchars($title) ?></h1>

    <?php if ($lead !== null && $lead !== ''): ?>
        <?php
        // Section 9C.3 PH-004: Lead paragraph is OPTIONAL
        // Section 9C.3 PH-005: Lead paragraph MUST use .civicone-body-l class
        // Section 10.6 HS-004: Lead paragraph MUST have max-width for readability (70ch)
        ?>
        <p class="civicone-body-l civicone-hero__lead">
            <?= htmlspecialchars($lead) ?>
        </p>
    <?php endif; ?>

    <?php if ($variant === 'banner' && $cta !== null): ?>
        <?php
        // Section 9C.4 BH-004: Primary CTA MUST be an <a> link styled as button
        // Section 9C.4 BH-005: Start button MUST use .civicone-button--start class
        // Section 9C.4 BH-006: Start button MUST include arrow icon (SVG)
        // Section 9C.4 BH-007: SVG MUST have aria-hidden="true" and focusable="false"
        ?>
        <a href="<?= htmlspecialchars($cta['url']) ?>"
           role="button"
           draggable="false"
           class="civicone-button civicone-button--start"
           data-module="govuk-button">
            <?= htmlspecialchars($cta['text']) ?>
            <svg class="civicone-button__start-icon"
                 xmlns="http://www.w3.org/2000/svg"
                 width="17.5"
                 height="19"
                 viewBox="0 0 33 40"
                 aria-hidden="true"
                 focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
    <?php endif; ?>

</div>
<!-- End Page Hero -->
