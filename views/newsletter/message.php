<?php
$layout = 'default';
$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantName = $tenantName ?? 'Newsletter';

$icons = [
    'success' => '&#10004;',
    'error' => '&#10006;',
    'info' => '&#8505;'
];

$colors = [
    'success' => ['bg' => '#d1fae5', 'icon' => '#059669', 'border' => '#a7f3d0'],
    'error' => ['bg' => '#fee2e2', 'icon' => '#dc2626', 'border' => '#fecaca'],
    'info' => ['bg' => '#dbeafe', 'icon' => '#2563eb', 'border' => '#bfdbfe']
];

$icon = $icons[$type] ?? $icons['info'];
$color = $colors[$type] ?? $colors['info'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($tenantName) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .message-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: <?= $color['bg'] ?>;
            border: 2px solid <?= $color['border'] ?>;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 36px;
            color: <?= $color['icon'] ?>;
        }
        h1 {
            font-size: 1.6rem;
            color: #111827;
            margin-bottom: 15px;
        }
        .message-text {
            color: #6b7280;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn-home {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-home:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .tenant-name {
            margin-top: 30px;
            font-size: 0.85rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="message-container">
        <div class="icon"><?= $icon ?></div>

        <h1><?= htmlspecialchars($title) ?></h1>

        <p class="message-text"><?= htmlspecialchars($message) ?></p>

        <a href="<?= $basePath ?>/" class="btn-home">Go to Homepage</a>

        <p class="tenant-name"><?= htmlspecialchars($tenantName) ?></p>
    </div>
</body>
</html>
