<?php
// Federation API Keys Create - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin-legacy/federation/api-keys-create.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation API keys create view not found";
