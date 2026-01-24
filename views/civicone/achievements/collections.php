<?php
/**
 * CivicOne View: Badge Collections
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Badge Collections';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/achievements">Achievements</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Collections</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/achievements" class="govuk-back-link govuk-!-margin-bottom-6">Back to Dashboard</a>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-full">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-layer-group govuk-!-margin-right-2" aria-hidden="true"></i>
            Badge Collections
        </h1>
        <p class="govuk-body-l">Complete collections for bonus rewards.</p>
    </div>
</div>

<!-- Achievement Navigation -->
<nav class="govuk-!-margin-bottom-6" aria-label="Achievement sections">
    <ul class="govuk-list" style="display: flex; gap: 0.5rem; flex-wrap: wrap; padding: 0; margin: 0;">
        <li><a href="<?= $basePath ?>/achievements" class="govuk-button govuk-button--secondary" data-module="govuk-button">Dashboard</a></li>
        <li><a href="<?= $basePath ?>/achievements/badges" class="govuk-button govuk-button--secondary" data-module="govuk-button">All Badges</a></li>
        <li><a href="<?= $basePath ?>/achievements/challenges" class="govuk-button govuk-button--secondary" data-module="govuk-button">Challenges</a></li>
        <li><a href="<?= $basePath ?>/achievements/collections" class="govuk-button" data-module="govuk-button">Collections</a></li>
        <li><a href="<?= $basePath ?>/achievements/shop" class="govuk-button govuk-button--secondary" data-module="govuk-button">XP Shop</a></li>
    </ul>
</nav>

<div class="collections-wrapper">
    <?php if (empty($collections)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">📚</span>
            <strong>No collections available</strong>
        </p>
        <p class="govuk-body">Badge collections will appear here once they're set up.</p>
    </div>
    <?php else: ?>
    <div class="collections-grid">
        <?php foreach ($collections as $collection): ?>
        <div class="collection-card <?= $collection['is_completed'] ? 'completed' : '' ?>">
            <div class="collection-header">
                <div class="collection-info">
                    <div class="collection-icon" aria-hidden="true">
                        <?= $collection['icon'] ?? '📚' ?>
                    </div>
                    <div>
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1"><?= htmlspecialchars($collection['name']) ?></h3>
                        <p class="govuk-body-s" style="color: #505a5f;"><?= htmlspecialchars($collection['description']) ?></p>
                    </div>
                </div>
                <div class="collection-stats">
                    <span class="govuk-tag govuk-tag--grey">
                        <?= $collection['earned_count'] ?> / <?= $collection['total_count'] ?>
                    </span>
                    <div class="govuk-body-s govuk-!-margin-top-2">
                        <?php if ($collection['is_completed']): ?>
                            <span style="color: #00703c;"><i class="fa-solid fa-check-circle" aria-hidden="true"></i> +<?= $collection['bonus_xp'] ?> XP Claimed</span>
                        <?php else: ?>
                            <span style="color: #1d70b8;"><i class="fa-solid fa-gift" aria-hidden="true"></i> +<?= $collection['bonus_xp'] ?> XP Bonus</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="progress-bar govuk-!-margin-top-3" role="progressbar" aria-valuenow="<?= $collection['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Collection progress">
                <div class="progress-fill" style="width: <?= $collection['progress_percent'] ?>%"></div>
            </div>

            <div class="collection-badges govuk-!-margin-top-4">
                <?php foreach ($collection['badges'] as $badge): ?>
                <div class="badge-item <?= $badge['earned'] ? 'earned' : '' ?>">
                    <div class="badge-icon" aria-hidden="true"><?= $badge['icon'] ?></div>
                    <div class="govuk-body-s"><?= htmlspecialchars($badge['name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
