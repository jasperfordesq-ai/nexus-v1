    <!-- Skip Link for Accessibility (WCAG 2.4.1) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

<?php
// Tenant feature check for layout banner
$showLayoutBanner = true; // Default: show banner
try {
    $result = \Nexus\Core\Database::query(
        "SELECT setting_value FROM tenant_settings
         WHERE tenant_id = ? AND setting_key = 'feature.layout_banner'",
        [\Nexus\Core\TenantContext::getId()]
    )->fetch();
    if ($result && ($result['setting_value'] === '0' || $result['setting_value'] === 'false')) {
        $showLayoutBanner = false;
    }
} catch (\Exception $e) {
    // If query fails, keep default (show banner)
}
?>

<?php if ($showLayoutBanner): ?>
    <!-- Development Banner - Prominent dev environment indicator -->
    <div class="civicone-dev-banner civicone-dev-banner--accessible" role="status" aria-live="polite">
        <div class="civicone-dev-banner-content">
            <div>
                <span class="civicone-dev-badge">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    DEVELOPMENT
                </span>
                <span class="civicone-dev-text">
                    <strong>Development Environment</strong>
                    <span>WCAG 2.1 AA Compliant — High contrast, keyboard-friendly design</span>
                </span>
            </div>
            <a href="#" data-layout-switcher="modern" class="civicone-dev-switch">
                <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
                Switch to Modern
            </a>
        </div>
    </div>
<?php endif; ?>
