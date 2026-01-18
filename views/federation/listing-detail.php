<?php
// Federation Listing Detail - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../modern/federation/listing-detail.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation listing detail view not found";
