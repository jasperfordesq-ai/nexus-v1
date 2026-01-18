<?php
/**
 * 404 Error Page
 * This is the fallback 404 view for the modern layout
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$tenant = TenantContext::get();
$siteName = $tenant['name'] ?? 'Project Nexus';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?= htmlspecialchars($siteName) ?></title>
    <link href="<?= $basePath ?>/assets/css/nexus-phoenix.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .error-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 50px 20px;
        }
        .error-content h1 {
            font-size: 6rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
            line-height: 1;
        }
        .error-content h2 {
            font-size: 1.5rem;
            color: #4b5563;
            margin: 1rem 0;
            font-weight: 500;
        }
        .error-content p {
            font-size: 1.125rem;
            color: #6b7280;
            margin: 0.5rem 0 2rem;
        }
        .suggestion-box {
            max-width: 500px;
            margin: 1.5rem auto;
            padding: 1rem 1.5rem;
            background: #dbeafe;
            border-radius: 8px;
            border: 1px solid #93c5fd;
        }
        .suggestion-box p {
            margin: 0 0 0.5rem;
            font-weight: 600;
            color: #1e40af;
        }
        .suggestion-box a {
            color: #2563eb;
            font-size: 1.1rem;
        }
        .btn-home {
            display: inline-block;
            padding: 12px 28px;
            background: #2563eb;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn-home:hover {
            background: #1d4ed8;
        }
        [data-theme="dark"] body {
            background: #111827;
        }
        [data-theme="dark"] .error-content h1 {
            color: #f9fafb;
        }
        [data-theme="dark"] .error-content h2 {
            color: #d1d5db;
        }
        [data-theme="dark"] .error-content p {
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <h1>404</h1>
            <h2>Page Not Found</h2>
            <p>The page you're looking for doesn't exist or has been moved.</p>

            <?php if (!empty($suggestedUrl)): ?>
                <div class="suggestion-box">
                    <p>Did you mean?</p>
                    <a href="<?= htmlspecialchars($suggestedUrl) ?>">
                        <?= htmlspecialchars($suggestedUrl) ?>
                    </a>
                </div>
            <?php endif; ?>

            <a href="<?= $basePath ?>/" class="btn-home">Go to Homepage</a>
        </div>
    </div>
</body>
</html>
