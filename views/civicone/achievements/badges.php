<?php
/**
 * CivicOne View: All Badges
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'All Badges';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

// Check for new badge (for confetti)
$newBadge = isset($_GET['new_badge']) ? true : false;
$showcaseUpdated = isset($_GET['showcase_updated']);
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Achievements', 'href' => $basePath . '/achievements'],
        ['text' => 'All Badges']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/achievements" class="govuk-back-link govuk-!-margin-bottom-6">Back to Dashboard</a>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-full">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-medal govuk-!-margin-right-2" aria-hidden="true"></i>
            All Badges
        </h1>
        <p class="govuk-body-l"><?= $totalEarned ?> of <?= $totalAvailable ?> badges earned</p>
    </div>
</div>

<!-- Achievement Navigation -->
<nav class="govuk-!-margin-bottom-6" aria-label="Achievement sections">
    <ul class="govuk-list civicone-flex-nav">
        <li><a href="<?= $basePath ?>/achievements" class="govuk-button govuk-button--secondary" data-module="govuk-button">Dashboard</a></li>
        <li><a href="<?= $basePath ?>/achievements/badges" class="govuk-button" data-module="govuk-button">All Badges</a></li>
        <li><a href="<?= $basePath ?>/achievements/challenges" class="govuk-button govuk-button--secondary" data-module="govuk-button">Challenges</a></li>
        <li><a href="<?= $basePath ?>/achievements/collections" class="govuk-button govuk-button--secondary" data-module="govuk-button">Collections</a></li>
        <li><a href="<?= $basePath ?>/achievements/shop" class="govuk-button govuk-button--secondary" data-module="govuk-button">XP Shop</a></li>
    </ul>
</nav>

<div class="badges-wrapper">
    <?php if ($showcaseUpdated): ?>
    <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
        </div>
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-check-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                Badge showcase updated! These badges will appear on your profile.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Badge Showcase Section -->
    <div class="showcase-section govuk-!-margin-bottom-6">
        <h2 class="govuk-heading-l"><i class="fa-solid fa-star govuk-!-margin-right-2" aria-hidden="true"></i> Badge Showcase</h2>
        <p class="govuk-body">Pin up to 3 badges to display on your profile. Click any earned badge below to add/remove it.</p>

        <form id="showcase-form" action="<?= $basePath ?>/achievements/showcase" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>
            <div class="showcase-badges">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <?php if (isset($showcasedBadges[$i])): ?>
                    <div class="showcase-badge-slot filled" data-key="<?= htmlspecialchars($showcasedBadges[$i]['badge_key']) ?>">
                        <input type="hidden" name="badge_keys[]" value="<?= htmlspecialchars($showcasedBadges[$i]['badge_key']) ?>">
                        <span class="badge-icon"><?= $showcasedBadges[$i]['icon'] ?></span>
                        <span class="badge-name"><?= htmlspecialchars($showcasedBadges[$i]['name']) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="showcase-badge-slot empty">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>Empty Slot</span>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <button type="submit" id="save-showcase" class="govuk-button govuk-!-margin-top-4 govuk-visually-hidden" data-module="govuk-button">
                <i class="fa-solid fa-save govuk-!-margin-right-1" aria-hidden="true"></i> Save Showcase
            </button>
        </form>
    </div>

    <!-- Progress Bar -->
    <div class="badges-progress-bar govuk-!-margin-bottom-6">
        <h2 class="govuk-heading-m">Badge Collection Progress</h2>
        <?php $percent = round(($totalEarned / $totalAvailable) * 100); ?>
        <div class="progress-outer" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Badge collection progress">
            <div class="progress-inner" style="width: <?= $percent ?>%">
                <?= $percent ?>%
            </div>
        </div>
        <p class="govuk-body-s govuk-!-margin-top-2 civicone-secondary-text">
            <?= $totalEarned ?> of <?= $totalAvailable ?> badges earned
        </p>
    </div>

    <!-- Badge Categories -->
    <?php foreach ($badgesByCategory as $type => $category): ?>
    <?php
        $earnedInCategory = count(array_filter($category['badges'], fn($b) => $b['earned']));
        $totalInCategory = count($category['badges']);
    ?>
    <div class="badge-category govuk-!-margin-bottom-6">
        <h3 class="govuk-heading-m">
            <?= htmlspecialchars($category['name']) ?>
            <span class="govuk-tag govuk-tag--grey govuk-!-margin-left-2"><?= $earnedInCategory ?> / <?= $totalInCategory ?></span>
        </h3>

        <div class="badges-grid">
            <?php foreach ($category['badges'] as $badge): ?>
            <div class="badge-item <?= $badge['earned'] ? 'earned' : 'locked' ?>"
                 data-key="<?= htmlspecialchars($badge['key']) ?>"
                 data-name="<?= htmlspecialchars($badge['name']) ?>"
                 data-icon="<?= htmlspecialchars($badge['icon']) ?>">

                <?php if ($badge['earned']): ?>
                <span class="earned-check"><i class="fa-solid fa-check"></i></span>
                <?php endif; ?>

                <?php if (!empty($badge['showcased'])): ?>
                <span class="showcase-star"><i class="fa-solid fa-star"></i></span>
                <?php endif; ?>

                <span class="badge-icon"><?= $badge['icon'] ?></span>
                <div class="badge-name"><?= htmlspecialchars($badge['name']) ?></div>
                <div class="badge-desc"><?= ucfirst($badge['msg'] ?? '') ?></div>

                <?php if ($badge['rarity']): ?>
                <span class="badge-rarity rarity-<?= strtolower($badge['rarity']['label']) ?>">
                    <?= $badge['rarity']['label'] ?> (<?= $badge['rarity']['percent'] ?>%)
                </span>
                <?php elseif ($badge['earned']): ?>
                <span class="badge-rarity rarity-legendary">First!</span>
                <?php endif; ?>

                <?php if (!$badge['earned'] && $badge['threshold'] > 0): ?>
                <div class="badge-threshold">Requires: <?= $badge['threshold'] ?></div>
                <?php endif; ?>

                <?php if ($badge['earned']): ?>
                <button type="button" class="pin-btn <?= !empty($badge['showcased']) ? 'pinned' : '' ?>" onclick="toggleShowcase(this, '<?= htmlspecialchars($badge['key']) ?>', '<?= htmlspecialchars($badge['name']) ?>', '<?= htmlspecialchars($badge['icon']) ?>')">
                    <?= !empty($badge['showcased']) ? '<i class="fa-solid fa-star"></i> Pinned' : '<i class="fa-regular fa-star"></i> Pin' ?>
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Confetti Container -->
<?php if ($newBadge): ?>
<div class="confetti-container" id="confetti-container"></div>
<?php endif; ?>

<!-- JS moved to /assets/js/civicone-achievements.js (2026-01-19) -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
