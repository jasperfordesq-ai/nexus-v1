<?php
/**
 * CivicOne Layout Header - Government/Public Sector Theme
 *
 * Refactored 2026-01-20: Extracted into partials for maintainability
 * See docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md for implementation details
 *
 * Partial Structure:
 * - document-open.php: DOCTYPE, html tag, PHP setup (variables, home detection)
 * - assets-css.php: <head> section with all CSS/meta/fonts
 * - body-open.php: <body> tag with classes, early scripts, component CSS links
 * - skip-link-and-banner.php: WCAG skip link and experimental banner
 * - utility-bar.php: Top utility navigation (dropdowns, user menu, notifications)
 * - site-header.php: Main header with logo, nav, mega menu, search
 * - hero.php: Hero banner section
 * - main-open.php: Impersonation banner and <main> opening
 * - header-scripts.php: JavaScript for header interactions
 */

// Document opening (DOCTYPE, html, PHP setup)
require __DIR__ . '/partials/document-open.php';

// Head section (CSS, meta, fonts)
require __DIR__ . '/partials/assets-css.php';

// Body opening (body tag, early scripts, component CSS)
require __DIR__ . '/partials/body-open.php';

// Skip link and experimental banner (WCAG 2.4.1)
require __DIR__ . '/partials/skip-link-and-banner.php';

// Utility bar (top navigation)
require __DIR__ . '/partials/utility-bar.php';

// Main site header (logo, navigation, mega menu, search)
require __DIR__ . '/partials/site-header.php';

// Hero banner (conditional - only show on specific pages)
// Per Section 9A.5: Hero should be page-specific, not global
// Set $showHero = true in individual view files to enable
if ($showHero ?? false) {
    require __DIR__ . '/partials/hero.php';
}

// Main content opening (impersonation banner, <main> tag)
require __DIR__ . '/partials/main-open.php';

// Header JavaScript (interactions, dropdowns, mega menu)
require __DIR__ . '/partials/header-scripts.php';
