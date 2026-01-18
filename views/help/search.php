<?php
/**
 * Help Search Results - Layout Router
 * Routes to appropriate layout view (modern/civicone)
 */
$layout = layout();

switch ($layout) {
    case 'civicone':
        // Civicone doesn't have a dedicated search view - use modern
        require __DIR__ . '/../modern/help/search.php';
        return;

    case 'modern':
    default:
        require __DIR__ . '/../modern/help/search.php';
        return;
}
