<?php
/**
 * Layout Preview Mode Banner
 * Shows when user is previewing a layout temporarily
 *
 * CSS: /assets/css/civicone-preview-banner.css
 */

if (empty($_SESSION['layout_preview_mode'])) {
    return; // Not in preview mode
}

$previewLayoutName = $_SESSION['layout_preview_name'] ?? 'Unknown Layout';
$currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
$baseUrl = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="/assets/css/civicone-preview-banner.css">

<div id="layoutPreviewBanner" class="civicone-preview-banner">
    <div class="civicone-preview-banner__content">
        <i class="fa-solid fa-eye civicone-preview-banner__icon" aria-hidden="true"></i>
        <div class="civicone-preview-banner__text">
            <span class="civicone-preview-banner__title">Previewing: <?= htmlspecialchars($previewLayoutName) ?></span>
            <span class="civicone-preview-banner__subtitle">This is temporary and won't be saved</span>
        </div>
    </div>

    <div class="civicone-preview-banner__actions">
        <a href="?layout=<?= layout() ?>" class="civicone-preview-banner__btn">
            <i class="fa-solid fa-check" aria-hidden="true"></i> Keep This Layout
        </a>
        <a href="<?= $baseUrl ?>" class="civicone-preview-banner__btn civicone-preview-banner__btn--secondary">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancel
        </a>
    </div>
</div>
