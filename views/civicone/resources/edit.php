<?php
/**
 * CivicOne View: Edit Resource
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Edit Resource';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Resource Library', 'href' => $basePath . '/resources'],
        ['text' => 'Edit']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/resources" class="govuk-back-link govuk-!-margin-bottom-6">Back to Resource Library</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-pen-to-square govuk-!-margin-right-2" aria-hidden="true"></i>
            Edit Resource
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Update the document details or category.</p>

        <!-- Current File Display -->
        <div class="govuk-inset-text govuk-!-margin-bottom-6">
            <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1">
                <i class="fa-solid fa-file-lines govuk-!-margin-right-2" aria-hidden="true"></i>
                <?= htmlspecialchars($resource['file_name'] ?? $resource['title']) ?>
            </p>
            <p class="govuk-body-s" style="color: #505a5f;">File cannot be changed. Upload a new resource instead.</p>
        </div>

        <form action="<?= $basePath ?>/resources/<?= $resource['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="title">Document Title</label>
                <input class="govuk-input" type="text" name="title" id="title" value="<?= htmlspecialchars($resource['title']) ?>" required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">
                    Description <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <textarea class="govuk-textarea" name="description" id="description" rows="3"><?= htmlspecialchars($resource['description']) ?></textarea>
            </div>

            <!-- Category -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="category_id">Category</label>
                <select class="govuk-select" name="category_id" id="category_id">
                    <option value="">-- No Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $resource['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                    Save Changes
                </button>
                <a href="<?= $basePath ?>/resources" class="govuk-link">Cancel</a>
            </div>
        </form>

        <!-- Danger Zone -->
        <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

        <div class="govuk-!-padding-6" style="border: 2px solid #d4351c; background: #fef7f7;">
            <h2 class="govuk-heading-m" style="color: #d4351c;">
                <i class="fa-solid fa-triangle-exclamation govuk-!-margin-right-1" aria-hidden="true"></i>
                Danger Zone
            </h2>
            <p class="govuk-body">
                Permanently delete this resource. This action cannot be undone.
            </p>

            <form action="<?= $basePath ?>/resources/<?= $resource['id'] ?>/delete" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this resource? This action cannot be undone.');">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                    <i class="fa-solid fa-trash-can govuk-!-margin-right-1" aria-hidden="true"></i>
                    Delete Permanently
                </button>
            </form>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
