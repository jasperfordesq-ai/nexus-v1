<?php
// Federated Events - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../modern/federation/events.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federated events view not found";
