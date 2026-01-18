<?php
// Layout Switcher for Admin Attributes Edit
// FIXED: Use consistent session variable order (active_layout first)
$layout = layout(); // Fixed: centralized detection

if ($layout === 'modern' || $layout === 'high-contrast') {
    $view = __DIR__ . '/../../modern/admin/attributes/edit.php';
    if (file_exists($view)) {
        require $view;
        return;
    }
}
echo "Error: Modern Edit Attribute View not found.";
