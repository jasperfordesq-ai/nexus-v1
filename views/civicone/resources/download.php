<?php
/**
 * CivicOne View: Download Resource
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Download Resource';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
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

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/resources">Resource Library</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Download</li>
    </ol>
</nav>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <div class="govuk-!-padding-6 govuk-!-text-align-centre" style="border: 1px solid #b1b4b6;">

            <div style="font-size: 4rem;" aria-hidden="true"><?= $icon ?></div>

            <h1 class="govuk-heading-l govuk-!-margin-top-4"><?= htmlspecialchars($resource['title']) ?></h1>

            <p class="govuk-body" style="color: #505a5f;">
                <?= $size ?> &middot; <?= ($resource['downloads'] ?? 0) + 1 ?> downloads
            </p>

            <div class="govuk-!-margin-top-6 govuk-!-margin-bottom-6">
                <p class="govuk-heading-xl govuk-!-margin-bottom-1" id="countdown" aria-live="polite">5</p>
                <p class="govuk-body">Your download will start automatically...</p>
            </div>

            <p class="govuk-body-s" id="downloadStatus" aria-live="polite" aria-atomic="true" style="color: #505a5f;">
                Preparing your file...
            </p>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <a href="<?= $basePath ?>/resources" class="govuk-back-link">Back to Resources</a>

            <p class="govuk-body-s govuk-!-margin-top-4" style="color: #505a5f;">
                Download not starting? <a href="<?= $basePath ?>/resources/<?= $resource['id'] ?>/file" id="manualDownload" class="govuk-link">Click here</a>
            </p>
        </div>

    </div>
</div>

<script>
(function() {
    var countdown = 5;
    var countdownEl = document.getElementById('countdown');
    var statusEl = document.getElementById('downloadStatus');
    var downloadUrl = '<?= $basePath ?>/resources/<?= $resource['id'] ?>/file';

    var timer = setInterval(function() {
        countdown--;
        countdownEl.textContent = countdown;

        if (countdown <= 0) {
            clearInterval(timer);
            statusEl.textContent = 'Download started!';
            window.location.href = downloadUrl;
        }
    }, 1000);
})();
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
