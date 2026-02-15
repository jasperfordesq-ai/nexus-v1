<?php
// Master Admin Dashboard Dispatcher
// Path: views/admin-legacy/dashboard.php

// Force Modern Layout or use Session
// FIXED: Use consistent session variable order (active_layout first)
$layout = layout(); // Fixed: centralized detection

// Target Path Resolution
// Current Dir: views/admin-legacy/
// Target: views/modern/admin-legacy/dashboard.php
$modernView = __DIR__ . '/../modern/admin-legacy/dashboard.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

// Fallback / Error Handling
if (file_exists($modernView)) {
    // If layout wasn't set but Modern exists, default to it
    require $modernView;
    return;
}

// Last Resort Debug
http_response_code(500);
echo "<div style='padding:20px; font-family:sans-serif;'>";
echo "<h2>View Dispatch Error</h2>";
echo "<p>Could not locate modern dashboard view.</p>";
echo "<p><strong>Searched Path:</strong> " . htmlspecialchars($modernView) . "</p>";
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";
echo "</div>";
