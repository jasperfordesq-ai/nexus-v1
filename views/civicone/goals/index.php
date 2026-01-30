<?php
/**
 * CivicOne View: Goals Index
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$heroTitle = "Goal Buddy";
$heroSub = "Track and share your personal goals.";
$heroType = 'Self Improvement';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Goals']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-bullseye govuk-!-margin-right-2" aria-hidden="true"></i>
            Your Goals
        </h1>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/goals/create" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> New Goal
        </a>
    </div>
</div>

<?php if (empty($goals)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">🎯</span>
            <strong>No goals set yet</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">Set a goal to get started!</p>
        <a href="<?= $basePath ?>/goals/create" class="govuk-button govuk-button--start" data-module="govuk-button">
            Create your first goal
            <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
    </div>
<?php else: ?>
    <div class="govuk-grid-row">
        <?php foreach ($goals as $goal):
            $progress = $goal['progress'] ?? 0;
            $progressClass = $progress >= 80 ? 'civicone-goal-card--complete' : ($progress >= 50 ? 'civicone-goal-card--progress' : 'civicone-goal-card--started');
            $progressBarClass = $progress >= 80 ? 'civicone-progress-bar--green' : ($progress >= 50 ? 'civicone-progress-bar--blue' : 'civicone-progress-bar--grey');
            $tagClass = $progress >= 80 ? 'govuk-tag--green' : ($progress >= 50 ? 'govuk-tag--light-blue' : 'govuk-tag--grey');
        ?>
            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                <div class="govuk-!-padding-4 civicone-sidebar-card civicone-goal-card <?= $progressClass ?>">
                    <h3 class="govuk-heading-m govuk-!-margin-bottom-3"><?= htmlspecialchars($goal['title']) ?></h3>

                    <!-- Progress Bar -->
                    <div class="govuk-!-margin-bottom-2" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= $progress ?>% complete">
                        <div class="civicone-progress-bar">
                            <div class="civicone-progress-bar__fill <?= $progressBarClass ?>" style="width: <?= $progress ?>%;"></div>
                        </div>
                    </div>

                    <p class="govuk-body-s govuk-!-margin-bottom-4">
                        <span class="govuk-tag <?= $tagClass ?>">
                            <?= $progress ?>% Complete
                        </span>
                    </p>

                    <a href="<?= $basePath ?>/goals/<?= $goal['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button" aria-label="Update progress for: <?= htmlspecialchars($goal['title']) ?>">
                        <i class="fa-solid fa-chart-line govuk-!-margin-right-1" aria-hidden="true"></i> Update Progress
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
