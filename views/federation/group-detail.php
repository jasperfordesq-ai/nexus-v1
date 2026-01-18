<?php
// Federated Group Detail - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../modern/federation/group-detail.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federated group detail view not found";
