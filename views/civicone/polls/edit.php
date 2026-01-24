<?php
/**
 * CivicOne View: Edit Poll
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Edit Poll';
require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/polls">Polls</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/polls/<?= $poll['id'] ?>">Poll</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Edit</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>" class="govuk-back-link govuk-!-margin-bottom-6">Back to poll</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Edit poll</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Update your community question.</p>

        <!-- Warning -->
        <div class="govuk-warning-text govuk-!-margin-bottom-6">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">Warning</span>
                Options cannot be changed. You can only edit the question text and deadline. To change voting options, please create a new poll to ensure vote integrity.
            </strong>
        </div>

        <form action="<?= $basePath ?>/polls/<?= $poll['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Question -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="question">Question</label>
                <input type="text" name="question" id="question" class="govuk-input" value="<?= htmlspecialchars($poll['question']) ?>" required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">
                    Description <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <textarea name="description" id="description" class="govuk-textarea" rows="3"><?= htmlspecialchars($poll['description'] ?? '') ?></textarea>
            </div>

            <!-- End Date -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="end_date">
                    End date <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <input type="date" name="end_date" id="end_date" class="govuk-input govuk-input--width-10"
                       value="<?= $poll['end_date'] ? date('Y-m-d', strtotime($poll['end_date'])) : '' ?>">
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                    Save changes
                </button>
                <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>" class="govuk-link">Cancel</a>
            </div>
        </form>

        <!-- Delete Section -->
        <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

        <div class="govuk-!-padding-6" style="border: 2px solid #d4351c; background: #fef7f7;">
            <h2 class="govuk-heading-m" style="color: #d4351c;">
                <i class="fa-solid fa-triangle-exclamation govuk-!-margin-right-1" aria-hidden="true"></i>
                Delete this poll
            </h2>
            <p class="govuk-body">
                This will permanently delete the poll and all votes. This action cannot be undone.
            </p>

            <form action="<?= $basePath ?>/polls/<?= $poll['id'] ?>/delete" method="POST"
                  onsubmit="return confirm('Are you sure? This will permanently delete the poll and all votes.');">
                <?= \Nexus\Core\Csrf::input() ?>

                <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                    <i class="fa-solid fa-trash govuk-!-margin-right-1" aria-hidden="true"></i> Delete poll
                </button>
            </form>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
