<?php
// External Partners Create - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin-legacy/federation/external-partners-create.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "External partners create view not found";
