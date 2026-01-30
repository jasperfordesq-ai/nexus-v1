<?php
/**
 * CivicOne View: Challenges
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Challenges';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Achievements', 'href' => $basePath . '/achievements'],
        ['text' => 'Challenges']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/achievements" class="govuk-back-link govuk-!-margin-bottom-6">Back to Dashboard</a>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-full">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-flag-checkered govuk-!-margin-right-2" aria-hidden="true"></i>
            Challenges
        </h1>
        <p class="govuk-body-l">Complete challenges to earn bonus XP.</p>
    </div>
</div>

<!-- Achievement Navigation -->
<nav class="govuk-!-margin-bottom-6" aria-label="Achievement sections">
    <ul class="govuk-list civicone-nav-list-sm">
        <li><a href="<?= $basePath ?>/achievements" class="govuk-button govuk-button--secondary" data-module="govuk-button">Dashboard</a></li>
        <li><a href="<?= $basePath ?>/achievements/badges" class="govuk-button govuk-button--secondary" data-module="govuk-button">All Badges</a></li>
        <li><a href="<?= $basePath ?>/achievements/challenges" class="govuk-button" data-module="govuk-button">Challenges</a></li>
        <li><a href="<?= $basePath ?>/achievements/collections" class="govuk-button govuk-button--secondary" data-module="govuk-button">Collections</a></li>
        <li><a href="<?= $basePath ?>/achievements/shop" class="govuk-button govuk-button--secondary" data-module="govuk-button">XP Shop</a></li>
    </ul>
</nav>

<div class="challenges-wrapper">
    <?php if (empty($challenges)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">🎯</span>
            <strong>No active challenges</strong>
        </p>
        <p class="govuk-body">Check back soon for new challenges to complete!</p>
    </div>
    <?php else: ?>
    <div class="challenges-grid">
        <?php foreach ($challenges as $challenge): ?>
        <div class="challenge-card <?= $challenge['is_completed'] ? 'completed' : '' ?>">
            <div class="challenge-header">
                <span class="govuk-tag <?= $challenge['challenge_type'] === 'daily' ? 'govuk-tag--light-blue' : ($challenge['challenge_type'] === 'weekly' ? 'govuk-tag--purple' : 'govuk-tag--yellow') ?>">
                    <?= ucfirst($challenge['challenge_type']) ?>
                </span>
                <span class="govuk-body-s civicone-secondary-text">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                    <?php if ($challenge['hours_remaining'] < 24): ?>
                        <?= round($challenge['hours_remaining']) ?> hours left
                    <?php else: ?>
                        <?= round($challenge['days_remaining']) ?> days left
                    <?php endif; ?>
                </span>
            </div>

            <h3 class="govuk-heading-s govuk-!-margin-top-3"><?= htmlspecialchars($challenge['title']) ?></h3>
            <p class="govuk-body-s civicone-secondary-text"><?= htmlspecialchars($challenge['description']) ?></p>

            <div class="challenge-progress govuk-!-margin-top-3">
                <div class="progress-bar" role="progressbar" aria-valuenow="<?= $challenge['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Challenge progress">
                    <div class="progress-fill" style="width: <?= $challenge['progress_percent'] ?>%"></div>
                </div>
                <div class="govuk-body-s govuk-!-margin-top-1 civicone-flex-between">
                    <span><?= $challenge['user_progress'] ?> / <?= $challenge['target_count'] ?></span>
                    <span><?= $challenge['progress_percent'] ?>%</span>
                </div>
            </div>

            <div class="challenge-reward govuk-!-margin-top-4 civicone-flex-between-center">
                <span class="govuk-body govuk-!-font-weight-bold civicone-xp-text">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    +<?= $challenge['xp_reward'] ?> XP
                </span>
                <?php if ($challenge['is_completed']): ?>
                <span class="govuk-tag govuk-tag--green">
                    <i class="fa-solid fa-check-circle" aria-hidden="true"></i> Completed
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
