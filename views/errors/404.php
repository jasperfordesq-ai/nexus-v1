<?php
/**
 * 404 Not Found Error Page
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$message = $message ?? 'The page you are looking for could not be found.';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/error-pages.css">
</head>
<body class="error-page">
    <div class="error-container">
        <i class="fa-solid fa-compass error-icon error-icon--404"></i>
        <div class="error-code error-code--404">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message"><?= htmlspecialchars($message) ?></p>
        <div>
            <a href="<?= $basePath ?>/" class="btn">
                <i class="fa-solid fa-home"></i> Go Home
            </a>
            <a href="javascript:history.back()" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Go Back
            </a>
        </div>
    </div>
</body>
</html>
