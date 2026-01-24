<?php
/**
 * CivicOne View: Edit Goal
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Edit Goal';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/goals">Goals</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/goals/<?= $goal['id'] ?>"><?= htmlspecialchars($goal['title']) ?></a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Edit</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/goals/<?= $goal['id'] ?>" class="govuk-back-link govuk-!-margin-bottom-6">Back to Goal</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-pen-to-square govuk-!-margin-right-2" aria-hidden="true"></i>
            Edit Goal
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Update your commitment.</p>

        <form action="<?= $basePath ?>/goals/<?= $goal['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="title">Goal Title</label>
                <input class="govuk-input" type="text" name="title" id="title" value="<?= htmlspecialchars($goal['title']) ?>" required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">Description</label>
                <textarea class="govuk-textarea" name="description" id="description" rows="4" required><?= htmlspecialchars($goal['description']) ?></textarea>
            </div>

            <!-- Target Date -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="deadline">
                    Target Date <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <input class="govuk-input govuk-input--width-10" type="date" name="deadline" id="deadline" value="<?= $goal['deadline'] ?>">
            </div>

            <!-- Public Goal Checkbox -->
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">Visibility</legend>
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="is_public" name="is_public" type="checkbox" value="1" <?= $goal['is_public'] ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="is_public">
                                <strong>Make this goal public</strong>
                                <span class="govuk-hint govuk-!-margin-bottom-0">Allow others to see this goal and offer to be your accountability partner.</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                    Save Changes
                </button>
                <a href="<?= $basePath ?>/goals/<?= $goal['id'] ?>" class="govuk-link">Cancel</a>
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
                Permanently delete this goal. This action cannot be undone.
            </p>

            <form action="<?= $basePath ?>/goals/<?= $goal['id'] ?>/delete" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this goal? This action cannot be undone.');">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                    <i class="fa-solid fa-trash-can govuk-!-margin-right-1" aria-hidden="true"></i>
                    Delete Goal
                </button>
            </form>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
