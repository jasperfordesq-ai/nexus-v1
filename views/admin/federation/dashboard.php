<?php
// Federation Admin Dashboard - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin/federation/dashboard.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

// Fallback to modern
if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation admin dashboard view not found";
