<?php
/**
 * Goal Delete Confirmation Page - GOV.UK Design System
 * Template D: Form/Flow - Confirmation Pattern
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Delete goal';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/goals">Goals</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/goals/<?= htmlspecialchars($goal['id']) ?>"><?= htmlspecialchars($goal['title']) ?></a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Delete
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <!-- Warning Text -->
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">Warning</span>
                        This action cannot be undone
                    </strong>
                </div>

                <h1 class="govuk-heading-xl">Are you sure you want to delete this goal?</h1>

                <!-- Goal Summary -->
                <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Goal ID</dt>
                        <dd class="govuk-summary-list__value">#<?= htmlspecialchars($goal['id']) ?></dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Title</dt>
                        <dd class="govuk-summary-list__value"><?= htmlspecialchars($goal['title']) ?></dd>
                    </div>
                    <?php if (!empty($goal['description'])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Description</dt>
                        <dd class="govuk-summary-list__value"><?= htmlspecialchars(substr($goal['description'], 0, 200)) ?><?= strlen($goal['description']) > 200 ? '...' : '' ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($goal['progress'])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Progress</dt>
                        <dd class="govuk-summary-list__value"><?= htmlspecialchars($goal['progress']) ?>%</dd>
                    </div>
                    <?php endif; ?>
                </dl>

                <div class="govuk-inset-text">
                    Deleting this goal will permanently remove all associated progress, milestones, and activity history.
                    This includes any buddy connections and social interactions linked to this goal.
                </div>

                <!-- Delete Form -->
                <form action="<?= $basePath ?>/goals/<?= $goal['id'] ?>/delete" method="POST">
                    <?= Csrf::input() ?>

                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                            Yes, delete this goal
                        </button>
                        <a href="<?= $basePath ?>/goals/<?= $goal['id'] ?>" class="govuk-link">Cancel</a>
                    </div>
                </form>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-s">Before you delete</h2>

                    <p class="govuk-body">
                        Consider whether you might want to:
                    </p>

                    <ul class="govuk-list govuk-list--bullet">
                        <li>Mark the goal as complete instead</li>
                        <li>Archive the goal for future reference</li>
                        <li>Update the goal with new targets</li>
                    </ul>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Need help?</h2>
                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/help" class="govuk-link">Visit our help centre</a> if you have
                        questions about managing your goals.
                    </p>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
