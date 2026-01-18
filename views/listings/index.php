<?php
// Determine layout (same logic as other bridge files)
$layout = layout(); // Fixed: centralized detection

// LOCKDOWN: Tenant forced layouts REMOVED
// Force civicone for public-sector-demo tenant - DISABLED FOR LOCKDOWN
// This was bypassing the layout lockdown system and causing random layout switching
// All tenants should respect the global layout lockdown: 'modern' by default
// if (
//     (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'public-sector-demo') !== false) ||
//     (class_exists('\Nexus\Core\TenantContext') && (\Nexus\Core\TenantContext::get()['slug'] ?? '') === 'public-sector-demo')
// ) {
//     $layout = 'civicone';
// }

// Route to appropriate layout view with header/footer
switch ($layout) {
    

    case 'civicone':
        require __DIR__ . '/../civicone/listings/index.php';
        return;

    case 'modern':
    default:
        require __DIR__ . '/../modern/listings/index.php';
        return;
}
