<?php
// External Partners Show - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin-legacy/federation/external-partners-show.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "External partners show view not found";
