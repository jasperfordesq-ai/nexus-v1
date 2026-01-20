    <!-- Admin Impersonation Banner -->
    <?php
    $impersonationBannerPath = __DIR__ . '/../../civicone/partials/impersonation-banner.php';
    if (!file_exists($impersonationBannerPath)) {
        $impersonationBannerPath = __DIR__ . '/../../modern/partials/impersonation-banner.php';
    }
    if (file_exists($impersonationBannerPath)) {
        require $impersonationBannerPath;
    }
    ?>

    <main id="main-content" class="civic-container">
