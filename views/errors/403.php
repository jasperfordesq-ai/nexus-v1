<?php
/**
 * 403 Forbidden Error Page
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$message = $message ?? 'You do not have permission to access this resource.';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/error-pages.css">
</head>
<body class="error-page">
    <div class="error-container">
        <i class="fa-solid fa-shield-halved error-icon error-icon--403"></i>
        <div class="error-code error-code--403">403</div>
        <h1 class="error-title">Access Denied</h1>
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
