<?php
// Federation Messages Thread - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/federation/messages/thread.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation messages thread view not found";
