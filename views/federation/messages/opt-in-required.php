<?php
// Federation Opt-In Required - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/federation/messages/opt-in-required.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation opt-in view not found";
