<?php
// Federated Groups Enable Required - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../modern/federation/groups-enable-required.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federated groups enable required view not found";
