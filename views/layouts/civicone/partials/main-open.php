    <!-- Admin Impersonation Banner -->
    <?php
    $impersonationBannerPath = __DIR__ . '/../../civicone/partials/impersonation-banner.php';
    if (!file_exists($impersonationBannerPath)) {
        $impersonationBannerPath = __DIR__ . '/../../modern/partials/impersonation-banner.php';
    }
    if (file_exists($impersonationBannerPath)) {
        require $impersonationBannerPath;
    }

    // Signal that main content area has started (for bridge file compatibility)
    define('MAIN_CONTENT_STARTED', true);
    ?>

    <main id="main-content" class="civic-container">
