<?php
/**
 * CivicOne View: Create Goal
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Set a Goal';
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
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Set a New Goal</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/goals" class="govuk-back-link govuk-!-margin-bottom-6">Back to Goals</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-bullseye govuk-!-margin-right-2" aria-hidden="true"></i>
            Set a New Goal
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Create a goal and find an accountability partner to help you achieve it.</p>

        <form action="<?= $basePath ?>/goals/store" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="title">Goal Title</label>
                <div id="title-hint" class="govuk-hint">Short and sweet, e.g. "Learn to Paint" or "Run a 5k"</div>
                <input class="govuk-input" type="text" name="title" id="title" aria-describedby="title-hint" required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">
                    Description & Details <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <div id="description-hint" class="govuk-hint">Share more details about what you want to achieve</div>
                <textarea class="govuk-textarea" name="description" id="description" rows="5" aria-describedby="description-hint"></textarea>
            </div>

            <!-- Target Date -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="deadline">
                    Target Date <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <div id="deadline-hint" class="govuk-hint">When do you want to achieve this goal?</div>
                <input class="govuk-input govuk-input--width-10" type="date" name="deadline" id="deadline" aria-describedby="deadline-hint">
            </div>

            <!-- Public Goal Checkbox -->
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">Visibility</legend>
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="is_public" name="is_public" type="checkbox" value="1" checked>
                            <label class="govuk-label govuk-checkboxes__label" for="is_public">
                                <strong>Make this goal public</strong>
                                <span class="govuk-hint govuk-!-margin-bottom-0">Allow others to see and support this goal. Public goals can get accountability partners.</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i>
                    Create Goal
                </button>
                <a href="<?= $basePath ?>/goals" class="govuk-link">Cancel</a>
            </div>
        </form>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
