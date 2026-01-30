<?php
/**
 * CivicOne View: Upload Resource
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Upload Resource';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Resource Library', 'href' => $basePath . '/resources'],
        ['text' => 'Upload']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/resources" class="govuk-back-link govuk-!-margin-bottom-6">Back to Resource Library</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-cloud-arrow-up govuk-!-margin-right-2" aria-hidden="true"></i>
            Upload Resource
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Share useful documents, guides, or forms with the community.</p>

        <form action="<?= $basePath ?>/resources/store" method="POST" enctype="multipart/form-data">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="title">Document Title</label>
                <div id="title-hint" class="govuk-hint">For example, "Volunteer Guide 2025" or "Safety Checklist"</div>
                <input class="govuk-input" type="text" name="title" id="title" aria-describedby="title-hint" required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">
                    Description <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <div id="description-hint" class="govuk-hint">Briefly describe what this file contains</div>
                <textarea class="govuk-textarea" name="description" id="description" rows="3" aria-describedby="description-hint"></textarea>
            </div>

            <!-- Category -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="category_id">Category</label>
                <select class="govuk-select" name="category_id" id="category_id">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- File Upload -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="file">Select File</label>
                <div id="file-hint" class="govuk-hint">PDF, DOC, DOCX, ZIP, JPG (Max 5MB)</div>
                <input class="govuk-file-upload" type="file" name="file" id="file" aria-describedby="file-hint" required>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-cloud-arrow-up govuk-!-margin-right-1" aria-hidden="true"></i>
                    Upload Document
                </button>
                <a href="<?= $basePath ?>/resources" class="govuk-link">Cancel</a>
            </div>
        </form>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
