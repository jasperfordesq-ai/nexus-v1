<?php
/**
 * CivicOne View: Download Resource
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Download Resource';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

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

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Resource Library', 'href' => $basePath . '/resources'],
        ['text' => 'Download']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <div class="govuk-!-padding-6 govuk-!-text-align-centre civicone-border-standard">

            <div class="civicone-download-icon" aria-hidden="true"><?= $icon ?></div>

            <h1 class="govuk-heading-l govuk-!-margin-top-4"><?= htmlspecialchars($resource['title']) ?></h1>

            <p class="govuk-body civicone-secondary-text">
                <?= $size ?> &middot; <?= ($resource['downloads'] ?? 0) + 1 ?> downloads
            </p>

            <div class="govuk-!-margin-top-6 govuk-!-margin-bottom-6">
                <p class="govuk-heading-xl govuk-!-margin-bottom-1" id="countdown" aria-live="polite">5</p>
                <p class="govuk-body">Your download will start automatically...</p>
            </div>

            <p class="govuk-body-s civicone-secondary-text" id="downloadStatus" aria-live="polite" aria-atomic="true">
                Preparing your file...
            </p>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <a href="<?= $basePath ?>/resources" class="govuk-back-link">Back to Resources</a>

            <p class="govuk-body-s govuk-!-margin-top-4 civicone-secondary-text">
                Download not starting? <a href="<?= $basePath ?>/resources/<?= $resource['id'] ?>/file" id="manualDownload" class="govuk-link">Click here</a>
            </p>
        </div>

    </div>
</div>

<!-- Download countdown handled by civicone-resources-download.min.js -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
