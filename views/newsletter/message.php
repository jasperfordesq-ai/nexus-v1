<?php
/**
 * Newsletter Message Page
 *
 * Simple message page for newsletter actions (unsubscribe, confirm, etc.)
 * Users reach this via email links.
 */
$tenantName = $tenantName ?? 'Newsletter';
$type = $type ?? 'info';
$title = $title ?? 'Newsletter';
$message = $message ?? '';

$colors = [
    'success' => ['bg' => '#f0fdf4', 'border' => '#86efac', 'text' => '#166534'],
    'error'   => ['bg' => '#fef2f2', 'border' => '#fca5a5', 'text' => '#991b1b'],
    'info'    => ['bg' => '#eff6ff', 'border' => '#93c5fd', 'text' => '#1e40af'],
];
$c = $colors[$type] ?? $colors['info'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $title) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 2.5rem; width: 100%; max-width: 500px; text-align: center; }
        .card h1 { font-size: 1.5rem; margin-bottom: 1rem; color: #1a1a1a; }
        .message { background: <?= $c['bg'] ?>; border: 1px solid <?= $c['border'] ?>; color: <?= $c['text'] ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; line-height: 1.5; }
        .brand { color: #999; font-size: 0.8rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1><?= htmlspecialchars($title) ?></h1>
        <div class="message"><?= $message ?></div>
        <p class="brand"><?= htmlspecialchars($tenantName) ?></p>
    </div>
</body>
</html>
