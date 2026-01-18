<?php
// Federation API Keys - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../../modern/admin/federation/api-keys.php';

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
echo "Federation API keys view not found";
