<?php
/**
 * Layout Preview Mode Banner
 * Shows when user is previewing a layout temporarily
 *
 * CSS: /httpdocs/assets/css/layout-preview-banner.css
 */

if (empty($_SESSION['layout_preview_mode'])) {
    return; // Not in preview mode
}

$previewLayoutName = $_SESSION['layout_preview_name'] ?? 'Unknown Layout';
$currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
$baseUrl = \Nexus\Core\TenantContext::getBasePath();
?>

<div id="layoutPreviewBanner" class="layout-preview-banner">
    <div class="layout-preview-banner__content">
        <i class="fa-solid fa-eye layout-preview-banner__icon"></i>
        <div>
            <div class="layout-preview-banner__title">
                Previewing: <?= htmlspecialchars($previewLayoutName) ?>
            </div>
            <div class="layout-preview-banner__subtitle">
                This is temporary and won't be saved
            </div>
        </div>
    </div>

    <div class="layout-preview-banner__actions">
        <a href="?layout=<?= layout() ?>" class="layout-preview-banner__btn layout-preview-banner__btn--primary">
            <i class="fa-solid fa-check"></i> Keep This Layout
        </a>
        <a href="<?= $baseUrl ?>" class="layout-preview-banner__btn layout-preview-banner__btn--secondary">
            <i class="fa-solid fa-xmark"></i> Cancel
        </a>
    </div>
</div>

<script>
// Add body class for CSS adjustments
document.body.classList.add('has-preview-banner');
</script>
