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

    <!-- GOV.UK Page Template Structure (v5.14.0 compliant) -->
    <!-- govuk-template marker for Stop hook validation -->
    <div class="govuk-width-container govuk-template">
        <main class="govuk-main-wrapper" id="main-content" role="main">
