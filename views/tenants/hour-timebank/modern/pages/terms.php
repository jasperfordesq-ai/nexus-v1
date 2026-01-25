<?php
/**
 * Terms of Service - Modern Theme Dispatcher
 * Tenant: Hour Timebank Ireland (tenant 2)
 *
 * This file loads the Modern layout and includes the shared terms content.
 * The shared content is maintained in the 'shared' folder to ensure
 * consistency between Modern and CivicOne themes.
 *
 * Theme Color: Blue (#3b82f6)
 * Legal Entity: hOUR Timebank CLG (RCN 20162023)
 * Updated: January 2026 - Insurance feedback incorporated
 */
$pageTitle = 'Terms of Service';
$hideHero = true;

require __DIR__ . '/../../../../../layouts/modern/header.php';

// Include shared content (source of truth for both themes)
require __DIR__ . '/../../shared/pages/terms-content.php';

require __DIR__ . '/../../../../../layouts/modern/footer.php';
