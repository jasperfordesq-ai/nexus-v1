<?php
/**
 * Legal & Info Page
 * Routes to layout-specific views with header/footer
 */

$layout = layout(); // Fixed: centralized detection


// Modern Layout (Default)
require __DIR__ . '/../modern/pages/legal.php';
