<?php
/**
 * CivicOne Layout Header - Government/Public Sector Theme
 * GOV.UK Design System v5.14.0 Integration (govuk-template)
 *
 * Refactored 2026-01-20: Extracted into partials for maintainability
 * See docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md for implementation details
 *
 * GOV.UK Template Classes:
 * - <html class="govuk-template"> in document-open.php
 * - <body class="govuk-template__body"> in body-open.php
 *
 * Partial Structure:
 * - document-open.php: DOCTYPE, html tag with govuk-template class, PHP setup
 * - assets-css.php: <head> section with all CSS/meta/fonts
 * - body-open.php: <body> tag with govuk-template__body class, early scripts
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

// Main content opening (impersonation banner, <main> tag)
require __DIR__ . '/partials/main-open.php';

// NOTE: Page Hero now renders AFTER this header include
// See Section 9C.5 HP-001: Hero MUST render in page template files, NOT in layout header
// Controllers should set $hero array with title/lead/variant before including header
// Or use the hero helper partial in individual view files

// Header JavaScript (interactions, dropdowns, mega menu)
require __DIR__ . '/partials/header-scripts.php';
