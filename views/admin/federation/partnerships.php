<?php
// Federation Partnerships - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin-legacy/federation/partnerships.php';

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
echo "Partnerships view not found";
