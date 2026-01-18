<?php
// Determine layout
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

// Route to appropriate layout view
switch ($layout) {
    

    case 'civicone':
        require dirname(__DIR__) . '/civicone/events/create.php';
        return;

    case 'modern':
    default:
        require dirname(__DIR__) . '/modern/events/create.php';
        return;
}
