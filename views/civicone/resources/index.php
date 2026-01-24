<?php
/**
 * CivicOne View: Resource Library
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Resource Library';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Resource Library</li>
    </ol>
</nav>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-book-open govuk-!-margin-right-2" aria-hidden="true"></i>
            Resource Library
        </h1>
        <p class="govuk-body-l">Tools, guides, and documents for the community.</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/resources/create" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Upload
        </a>
    </div>
</div>

<?php if (empty($resources)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">📚</span>
            <strong>Library is empty</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">Share the first guide or toolkit!</p>
        <a href="<?= $basePath ?>/resources/create" class="govuk-button govuk-button--start" data-module="govuk-button">
            Upload Resource
            <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
    </div>
<?php else: ?>
    <div class="govuk-grid-row">
        <?php foreach ($resources as $res): ?>
            <?php
            $icon = '📄';
            if (strpos($res['file_type'], 'image') !== false) $icon = '🖼️';
            if (strpos($res['file_type'], 'zip') !== false) $icon = '📦';
            if (strpos($res['file_type'], 'pdf') !== false) $icon = '📕';

            $size = round($res['file_size'] / 1024) . ' KB';
            if ($res['file_size'] > 1024 * 1024) $size = round($res['file_size'] / 1024 / 1024, 1) . ' MB';
            ?>
            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8; height: 100%; display: flex; flex-direction: column;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 1.5rem;" aria-hidden="true"><?= $icon ?></span>
                        <span class="govuk-tag govuk-tag--grey"><?= $size ?></span>
                    </div>

                    <h3 class="govuk-heading-s govuk-!-margin-bottom-2"><?= htmlspecialchars($res['title']) ?></h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-4" style="color: #505a5f; flex-grow: 1;"><?= htmlspecialchars($res['description']) ?></p>

                    <p class="govuk-body-s govuk-!-margin-bottom-3" style="color: #505a5f;">
                        By <strong><?= htmlspecialchars($res['uploader_name']) ?></strong><br>
                        <?= $res['downloads'] ?> downloads
                    </p>

                    <a href="<?= $basePath ?>/resources/<?= $res['id'] ?>/download"
                       class="govuk-button govuk-button--secondary"
                       data-module="govuk-button"
                       aria-label="Download <?= htmlspecialchars($res['title']) ?>"
                       style="width: 100%;">
                        <i class="fa-solid fa-download govuk-!-margin-right-1" aria-hidden="true"></i>
                        Download
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
