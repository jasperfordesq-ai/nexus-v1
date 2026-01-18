<?php
// Federation Offline Page - View Dispatcher
$layout = layout();
$modernView = __DIR__ . '/../modern/federation/offline.php';

if (($layout === 'modern' || $layout === 'high-contrast') && file_exists($modernView)) {
    require $modernView;
    return;
}

// Fallback to modern (offline page is only in modern style)
if (file_exists($modernView)) {
    require $modernView;
    return;
}

// Ultimate fallback - simple HTML
http_response_code(503);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - Federation</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #1e293b; color: #f1f5f9; }
        h1 { color: #8b5cf6; }
        a { color: #a78bfa; }
    </style>
</head>
<body>
    <h1>You're Offline</h1>
    <p>Federation features require an internet connection.</p>
    <p><a href="/dashboard">Go to Dashboard</a></p>
</body>
</html>
