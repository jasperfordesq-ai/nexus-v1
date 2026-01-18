<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - Project Nexus</title>
    <link href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/nexus-phoenix.min.css" rel="stylesheet">
    <style>
        body {
            font-family: sans-serif;
            text-align: center;
            padding: 50px;
            background: #f3f4f6;
        }

        h1 {
            font-size: 3rem;
            color: #111827;
        }

        p {
            font-size: 1.25rem;
            color: #6b7280;
        }

        a {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php require __DIR__ . '/layouts/header.php'; ?>
    <div class="container" style="text-align: center; padding: 100px 0;">
        <h1>404</h1>
        <p>Page not found.</p>

        <?php if (isset($suggestedUrl) && $suggestedUrl): ?>
            <div class="alert alert-info mt-4" style="max-width: 600px; margin: 30px auto; padding: 20px; background: #dbeafe; border-radius: 8px; border: 1px solid #93c5fd;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #1e40af;">Did you mean?</p>
                <a href="<?= htmlspecialchars($suggestedUrl) ?>" style="font-size: 1.1rem; color: #2563eb;">
                    <?= htmlspecialchars($suggestedUrl) ?>
                </a>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px;">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/" style="display: inline-block; padding: 12px 24px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none;">
                Go to Homepage
            </a>
        </div>
    </div>
    <?php require __DIR__ . '/layouts/footer.php'; ?>
</body>

</html>
```