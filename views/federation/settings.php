<?php
// Federation Settings - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../modern/federation/settings.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

// Fallback to modern
if (file_exists($modernView)) {
    require $modernView;
    return;
}

http_response_code(500);
echo "Federation settings view not found";
