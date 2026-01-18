<?php
// Federation API Key Details - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin/federation/api-keys-show.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation API key details view not found";
