<?php
/**
 * Help Article View - Layout Router
 * Routes to appropriate layout view (modern/civicone)
 */
$layout = layout();

switch ($layout) {
    case 'civicone':
        require __DIR__ . '/../civicone/help/show.php';
        return;

    case 'modern':
    default:
        require __DIR__ . '/../modern/help/show.php';
        return;
}
