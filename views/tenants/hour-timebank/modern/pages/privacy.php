<?php
/**
 * Privacy Policy - Modern Theme Dispatcher
 * Tenant: Hour Timebank Ireland (tenant 2)
 *
 * This file loads the Modern layout and includes the shared privacy content.
 * The shared content is maintained in the 'shared' folder to ensure
 * consistency between Modern and CivicOne themes.
 *
 * Theme Color: Indigo (#6366f1)
 * Legal Entity: hOUR Timebank CLG (RCN 20162023)
 * GDPR Compliant for Ireland/EU
 */
$pageTitle = 'Privacy Policy';
$hideHero = true;

require __DIR__ . '/../../../../layouts/modern/header.php';

// Include shared content (source of truth for both themes)
require __DIR__ . '/../../shared/pages/privacy-content.php';

require __DIR__ . '/../../../../layouts/modern/footer.php';
