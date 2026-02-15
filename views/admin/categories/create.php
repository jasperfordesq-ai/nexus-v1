<?php
// Layout Switcher for Admin Categories Create
// FIXED: Use consistent session variable order (active_layout first)
$layout = layout(); // Fixed: centralized detection

if ($layout === 'modern' || $layout === 'high-contrast') {
    $view = __DIR__ . '/../../modern/admin-legacy/categories/create.php';
    if (file_exists($view)) {
        require $view;
        return;
    }
}
echo "Error: Modern Create Category View not found.";
