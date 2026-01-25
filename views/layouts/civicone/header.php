<?php
/**
 * CivicOne Layout Header v2.1 - Pure GOV.UK Compliance
 *
 * REBUILT 2026-01-25: 100% GOV.UK Frontend structure
 * SOURCE: https://github.com/alphagov/govuk-frontend
 *
 * Structure (matches GOV.UK exactly):
 * - Skip link (WCAG 2.4.1)
 * - Phase banner (optional)
 * - Service Navigation (includes account links - GOV.UK pattern)
 *
 * NO utility bar - account/sign in moved to service navigation
 * NO notifications drawer - notifications accessed via dedicated page
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

// Service navigation with account links (GOV.UK compliant) - v2.1
?>
<header class="govuk-template__header" role="banner">
    <?php require __DIR__ . '/partials/service-navigation-v2.php'; ?>
</header>
<?php

// Main content opening (impersonation banner, <main> tag)
require __DIR__ . '/partials/main-open.php';

// NOTE: All header JavaScript is now in /assets/js/civicone-header-v2.js
// Loaded via assets-js-footer.php
