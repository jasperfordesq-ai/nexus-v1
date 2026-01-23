<?php
/**
 * CivicOne Resources Download - Download Confirmation Page
 * Template E: Content/Article (Section 10.9)
 * Automatic download with countdown timer
 * WCAG 2.1 AA Compliant
 */
$heroTitle = "Downloading Resource";
$heroSub = "Your download will begin shortly.";
$heroType = 'Download';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Determine file icon
$icon = '📄';
if (strpos($resource['file_type'] ?? '', 'image') !== false) $icon = '🖼️';
if (strpos($resource['file_type'] ?? '', 'zip') !== false) $icon = '📦';
if (strpos($resource['file_type'] ?? '', 'pdf') !== false) $icon = '📕';
if (strpos($resource['file_type'] ?? '', 'doc') !== false) $icon = '📝';

$size = round(($resource['file_size'] ?? 0) / 1024) . ' KB';
if (($resource['file_size'] ?? 0) > 1024 * 1024) {
    $size = round(($resource['file_size'] ?? 0) / 1024 / 1024, 1) . ' MB';
}
?>

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper">
        <div class="civic-download-container">
            <div class="civic-download-card">
                <div class="civic-download-icon" aria-hidden="true"><?= $icon ?></div>

                <h1 class="civic-resource-download-title"><?= htmlspecialchars($resource['title']) ?></h1>

                <div class="civic-resource-download-meta">
                    <?= $size ?> &bull; <?= ($resource['downloads'] ?? 0) + 1 ?> downloads
                </div>

                <div class="civic-countdown-number" id="countdown" aria-live="polite">5</div>
                <div class="civic-countdown-text">Your download will start automatically...</div>

                <div class="civic-download-status" id="downloadStatus" aria-live="polite" aria-atomic="true">
                    Preparing your file...
                </div>

                <a href="<?= $basePath ?>/resources" class="civic-download-back-link">
                    <span aria-hidden="true">&larr;</span> Back to Resources
                </a>

                <p class="civic-download-manual-link">
                    Download not starting? <a href="<?= $basePath ?>/resources/<?= $resource['id'] ?>/file" id="manualDownload">Click here</a>
                </p>
            </div>
        </div>
    </main>
</div><!-- /civicone-width-container -->

<script src="/assets/js/civicone-resources-download.js?v=<?= time() ?>"></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
