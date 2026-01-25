    <!-- Skip Link for Accessibility (WCAG 2.4.1) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

<?php
// Tenant feature check for layout switcher (used by utility bar)
$GLOBALS['showLayoutSwitcher'] = true; // Default: show switcher
try {
    $result = \Nexus\Core\Database::query(
        "SELECT setting_value FROM tenant_settings
         WHERE tenant_id = ? AND setting_key = 'feature.layout_banner'",
        [\Nexus\Core\TenantContext::getId()]
    )->fetch();
    if ($result && ($result['setting_value'] === '0' || $result['setting_value'] === 'false')) {
        $GLOBALS['showLayoutSwitcher'] = false;
    }
} catch (\Exception $e) {
    // If query fails, keep default (show switcher)
}
?>
<!-- Layout switcher moved to utility bar for cleaner design -->
