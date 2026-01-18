<?php
/**
 * Help Center Index - Layout Router
 * Routes to appropriate layout view (modern/civicone)
 */
$layout = layout();

switch ($layout) {
    case 'civicone':
        require __DIR__ . '/../civicone/help/index.php';
        return;

    case 'modern':
    default:
        require __DIR__ . '/../modern/help/index.php';
        return;
}
