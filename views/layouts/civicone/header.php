<?php
/**
 * CivicOne Layout Header v2.4 - GOV.UK Service Navigation Pattern
 *
 * REBUILT 2026-01-25: 100% GOV.UK Frontend structure
 * UPDATED 2026-01-30: Simplified to use only Service Navigation (standard pattern)
 *
 * SOURCE REFERENCES:
 * - GOV.UK Frontend: https://github.com/alphagov/govuk-frontend
 * - Service Navigation: https://design-system.service.gov.uk/components/service-navigation/
 * - Skip Link: https://design-system.service.gov.uk/components/skip-link/
 * - Phase Banner: https://design-system.service.gov.uk/components/phase-banner/
 * - Cookie Banner: https://design-system.service.gov.uk/components/cookie-banner/
 *
 * IMPORTANT NOTE ON GOV.UK PATTERNS:
 * The gov.uk HOMEPAGE has a custom mega-menu that is NOT part of GOV.UK Frontend.
 * GOV.UK Frontend provides two separate components:
 * - Header: Simple dark blue bar with logo (for official government sites only)
 * - Service Navigation: Light blue bar with service name and nav links
 *
 * For CivicOne (non-government service), we use only the Service Navigation
 * component which is the standard pattern for GOV.UK-style services.
 *
 * DOCUMENT STRUCTURE (GOV.UK order):
 * 1. Cookie banner (before skip link - GOV.UK pattern)
 * 2. Skip link (WCAG 2.4.1 - first focusable element)
 * 3. Phase banner (optional - for beta/alpha services)
 * 4. Service Navigation (service name, nav items, mobile Menu toggle)
 *
 * CSS: /assets/css/civicone-header-v2.css (Service Navigation)
 * JS:  /assets/js/civicone-header-v2.js
 */

// Document opening (DOCTYPE, html, PHP setup)
require __DIR__ . '/partials/document-open.php';

// Head section (CSS, meta, fonts)
require __DIR__ . '/partials/assets-css.php';

// Body opening (body tag, early scripts)
require __DIR__ . '/partials/body-open.php';

// Cookie Consent Banner - GOV.UK pattern: FIRST element after <body>
// Source: https://github.com/alphagov/govuk-frontend
require __DIR__ . '/../../../views/civicone/partials/cookie-banner.php';

// Skip link and experimental banner (WCAG 2.4.1)
require __DIR__ . '/partials/skip-link-and-banner.php';

// Service Navigation - GOV.UK compliant header
// Note: The gov.uk homepage mega-menu is custom code NOT in GOV.UK Frontend.
// We use the standard Service Navigation component which has:
// - Service name on left
// - Navigation links
// - Mobile "Menu" toggle that shows/hides the nav list
?>
<header class="govuk-template__header" role="banner">
    <?php
    // Service Navigation - the standard GOV.UK header pattern for services
    require __DIR__ . '/partials/service-navigation-v2.php';
    ?>
</header>
<?php

// Main content opening (impersonation banner, <main> tag)
require __DIR__ . '/partials/main-open.php';

// NOTE: All header JavaScript is now in /assets/js/civicone-header-v2.js
// Loaded via assets-js-footer.php
