<?php
// Federation Data Management - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin/federation/data.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation data management view not found";
