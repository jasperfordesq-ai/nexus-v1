<?php
// PROFILE SHOW DISPATCHER
// Routes to Modern (default) or Civic layout.

$layout = layout(); // Fixed: centralized detection

// 1. CivicOne Layout
if ($layout === 'civicone') {
    $legacyPath = __DIR__ . '/../civicone/profile/show.php';
    if (file_exists($legacyPath)) {
        require $legacyPath;
        return;
    }
}

// 2. Modern Layout (Default)
require __DIR__ . '/../modern/profile/show.php';
