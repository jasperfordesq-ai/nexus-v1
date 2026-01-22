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
    <!-- Modern Layout Notice Banner - Premium Design -->
    <div class="modern-experimental-banner" role="status" aria-live="polite">
        <div class="modern-banner-wrapper">
            <div class="modern-banner-content">
                <span class="modern-experimental-badge">
                    <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
                    MODERN (Stable)
                </span>
                <span class="modern-experimental-text">
                    <strong>Next-Gen Interface</strong> — Fast, beautiful, responsive design
                </span>
            </div>
            <a href="#" data-layout-switcher="civicone" class="modern-experimental-switch">
                <i class="fa-solid fa-universal-access" aria-hidden="true"></i>
                Switch to Accessible
            </a>
        </div>
    </div>
<?php endif; ?>
