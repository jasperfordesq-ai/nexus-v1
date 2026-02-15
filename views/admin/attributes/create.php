<?php
// Layout Switcher for Admin Attributes Create
// FIXED: Use consistent session variable order (active_layout first)
$layout = layout(); // Fixed: centralized detection

if ($layout === 'modern' || $layout === 'high-contrast') {
    $view = __DIR__ . '/../../modern/admin-legacy/attributes/create.php';
    if (file_exists($view)) {
        require $view;
        return;
    }
}
echo "Error: Modern Create Attribute View not found.";
