<?php
/**
 * CivicOne View: Leaderboard Seasons
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Leaderboard Seasons';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$season = $seasonData['season'] ?? null;
$userRank = $seasonData['user_rank'] ?? null;
$leaderboard = $seasonData['leaderboard'] ?? [];
$rewards = $seasonData['rewards'] ?? [];
$daysRemaining = $seasonData['days_remaining'] ?? 0;
$isEndingSoon = $seasonData['is_ending_soon'] ?? false;
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/achievements">Achievements</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Seasons</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/achievements" class="govuk-back-link govuk-!-margin-bottom-6">Back to Dashboard</a>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-full">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-trophy govuk-!-margin-right-2" aria-hidden="true"></i>
            Leaderboard Seasons
        </h1>
        <p class="govuk-body-l">Compete monthly for exclusive rewards.</p>
    </div>
</div>

<!-- Achievement Navigation -->
<nav class="govuk-!-margin-bottom-6" aria-label="Achievement sections">
    <ul class="govuk-list civicone-button-nav">
        <li><a href="<?= $basePath ?>/achievements" class="govuk-button govuk-button--secondary" data-module="govuk-button">Dashboard</a></li>
        <li><a href="<?= $basePath ?>/achievements/badges" class="govuk-button govuk-button--secondary" data-module="govuk-button">All Badges</a></li>
        <li><a href="<?= $basePath ?>/achievements/challenges" class="govuk-button govuk-button--secondary" data-module="govuk-button">Challenges</a></li>
        <li><a href="<?= $basePath ?>/achievements/collections" class="govuk-button govuk-button--secondary" data-module="govuk-button">Collections</a></li>
        <li><a href="<?= $basePath ?>/achievements/shop" class="govuk-button govuk-button--secondary" data-module="govuk-button">XP Shop</a></li>
        <li><a href="<?= $basePath ?>/achievements/seasons" class="govuk-button" data-module="govuk-button">Seasons</a></li>
    </ul>
</nav>

<div class="seasons-wrapper">
    <?php if (!$season): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
            <strong>No active season</strong>
        </p>
        <p class="govuk-body">Leaderboard seasons will appear here once they're set up.</p>
    </div>
    <?php else: ?>

    <div class="season-header govuk-!-margin-bottom-6">
        <div class="season-title-row civicone-season-title-row">
            <div>
                <h2 class="govuk-heading-l govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-trophy govuk-!-margin-right-2 civicone-icon-orange" aria-hidden="true"></i>
                    <?= htmlspecialchars($season['name']) ?>
                </h2>
                <?php if ($isEndingSoon): ?>
                    <span class="govuk-tag govuk-tag--red">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i> Ending Soon!
                    </span>
                <?php else: ?>
                    <span class="govuk-tag govuk-tag--green">
                        <i class="fa-solid fa-circle" aria-hidden="true"></i> Active
                    </span>
                <?php endif; ?>
            </div>
            <div class="govuk-!-padding-4 civicone-season-countdown">
                <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-secondary-text">Time Remaining</p>
                <p class="govuk-heading-l govuk-!-margin-bottom-0"><?= $daysRemaining ?></p>
                <p class="govuk-body-s civicone-secondary-text"><?= $daysRemaining === 1 ? 'day' : 'days' ?></p>
            </div>
        </div>

        <?php if ($userRank): ?>
        <div class="govuk-notification-banner govuk-!-margin-top-4" role="region" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h3 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Your Position</h3>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading civicone-rank-heading">
                    <span class="govuk-heading-xl govuk-!-margin-bottom-0">#<?= $userRank['position'] ?></span>
                    <span>
                        Your current position<br>
                        <span class="govuk-body-s civicone-secondary-text">
                            <i class="fa-solid fa-star civicone-icon-orange" aria-hidden="true"></i>
                            <?= number_format($userRank['season_xp']) ?> XP this season
                        </span>
                    </span>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-season-card">
                <h3 class="govuk-heading-m"><i class="fa-solid fa-ranking-star govuk-!-margin-right-2" aria-hidden="true"></i> Season Leaderboard</h3>

                <table class="govuk-table">
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header civicone-table-col-narrow">Rank</th>
                            <th scope="col" class="govuk-table__header">Member</th>
                            <th scope="col" class="govuk-table__header govuk-table__header--numeric">XP</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        <?php foreach ($leaderboard as $index => $entry): ?>
                        <?php
                            $position = $index + 1;
                            $isCurrentUser = isset($_SESSION['user_id']) && $entry['user_id'] == $_SESSION['user_id'];
                            $isTop3 = $position <= 3;
                        ?>
                        <tr class="govuk-table__row <?= $isCurrentUser ? 'govuk-!-font-weight-bold civicone-panel-bg' : '' ?>">
                            <td class="govuk-table__cell">
                                <?php if ($position === 1): ?>
                                    <i class="fa-solid fa-crown civicone-medal-gold" aria-hidden="true"></i>
                                <?php elseif ($position === 2): ?>
                                    <span class="civicone-medal-silver"><strong><?= $position ?></strong></span>
                                <?php elseif ($position === 3): ?>
                                    <span class="civicone-medal-bronze"><strong><?= $position ?></strong></span>
                                <?php else: ?>
                                    <?= $position ?>
                                <?php endif; ?>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?>
                                <?php if ($isCurrentUser): ?>
                                    <span class="govuk-tag govuk-tag--light-blue">You</span>
                                <?php endif; ?>
                                <br><span class="govuk-body-s civicone-secondary-text">Level <?= $entry['level'] ?? 1 ?></span>
                            </td>
                            <td class="govuk-table__cell govuk-table__cell--numeric"><?= number_format($entry['season_xp'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="govuk-grid-column-one-third">
            <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-season-card">
                <h3 class="govuk-heading-m"><i class="fa-solid fa-gift govuk-!-margin-right-2" aria-hidden="true"></i> Season Rewards</h3>

                <dl class="govuk-summary-list govuk-summary-list--no-border">
                    <?php if (isset($rewards[1])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">
                            <i class="fa-solid fa-crown civicone-medal-gold" aria-hidden="true"></i> 1st Place
                        </dt>
                        <dd class="govuk-summary-list__value">
                            +<?= $rewards[1]['xp'] ?? 500 ?> XP
                            <?php if (!empty($rewards[1]['badge'])): ?>
                                <br><span class="govuk-body-s">+ Exclusive Badge</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($rewards[2])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">
                            <i class="fa-solid fa-medal civicone-medal-silver" aria-hidden="true"></i> 2nd Place
                        </dt>
                        <dd class="govuk-summary-list__value">+<?= $rewards[2]['xp'] ?? 300 ?> XP</dd>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($rewards[3])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">
                            <i class="fa-solid fa-medal civicone-medal-bronze" aria-hidden="true"></i> 3rd Place
                        </dt>
                        <dd class="govuk-summary-list__value">+<?= $rewards[3]['xp'] ?? 200 ?> XP</dd>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($rewards['top10'])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">
                            <i class="fa-solid fa-award civicone-icon-blue" aria-hidden="true"></i> Top 10
                        </dt>
                        <dd class="govuk-summary-list__value">+<?= $rewards['top10']['xp'] ?? 100 ?> XP</dd>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($rewards['participant'])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">
                            <i class="fa-solid fa-star civicone-icon-grey" aria-hidden="true"></i> Participants
                        </dt>
                        <dd class="govuk-summary-list__value">+<?= $rewards['participant']['xp'] ?? 25 ?> XP</dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <?php if (!empty($allSeasons) && count($allSeasons) > 1): ?>
    <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

    <h3 class="govuk-heading-m"><i class="fa-solid fa-history govuk-!-margin-right-2" aria-hidden="true"></i> Past Seasons</h3>

    <div class="govuk-grid-row">
        <?php foreach ($allSeasons as $pastSeason): ?>
            <?php if ($pastSeason['id'] === $season['id']) continue; ?>
            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
                <div class="govuk-!-padding-4 civicone-season-card">
                    <h4 class="govuk-heading-s govuk-!-margin-bottom-2"><?= htmlspecialchars($pastSeason['name']) ?></h4>
                    <p class="govuk-body-s govuk-!-margin-bottom-2 civicone-secondary-text">
                        <?= date('M j', strtotime($pastSeason['start_date'])) ?> - <?= date('M j, Y', strtotime($pastSeason['end_date'])) ?>
                    </p>
                    <?php if ($pastSeason['status'] === 'completed'): ?>
                        <span class="govuk-tag govuk-tag--green">
                            <i class="fa-solid fa-check-circle" aria-hidden="true"></i> Completed
                        </span>
                    <?php else: ?>
                        <span class="govuk-tag govuk-tag--grey">
                            <?= ucfirst($pastSeason['status']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
