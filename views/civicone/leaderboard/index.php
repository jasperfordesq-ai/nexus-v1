<?php
/**
 * CivicOne View: Leaderboard
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Leaderboards';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

// Data passed from controller
$leaderboard = $leaderboard ?? [];
$currentType = $currentType ?? 'xp';
$currentPeriod = $currentPeriod ?? 'all_time';
$types = $types ?? [];
$periods = $periods ?? [];
$userStats = $userStats ?? null;
$userStreaks = $userStreaks ?? null;

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Leaderboards']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<h1 class="govuk-heading-xl">
    <i class="fa-solid fa-trophy govuk-!-margin-right-2" aria-hidden="true"></i>
    Leaderboards
</h1>

<!-- Filter Controls -->
<div class="govuk-!-margin-bottom-6">
    <div class="govuk-grid-row govuk-!-margin-bottom-4">
        <div class="govuk-grid-column-full">
            <p class="govuk-body govuk-!-margin-bottom-2"><strong>Time Period:</strong></p>
            <div class="govuk-button-group">
                <?php foreach ($periods as $key => $label): ?>
                    <a href="<?= $basePath ?>/leaderboard?type=<?= $currentType ?>&period=<?= $key ?>"
                       class="govuk-button <?= $currentPeriod === $key ? '' : 'govuk-button--secondary' ?>"
                       data-module="govuk-button"
                       <?= $currentPeriod === $key ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-full">
            <p class="govuk-body govuk-!-margin-bottom-2"><strong>Ranking Type:</strong></p>
            <div class="govuk-button-group">
                <?php foreach ($types as $key => $label): ?>
                    <a href="<?= $basePath ?>/leaderboard?type=<?= $key ?>&period=<?= $currentPeriod ?>"
                       class="govuk-button <?= $currentType === $key ? '' : 'govuk-button--secondary' ?>"
                       data-module="govuk-button"
                       <?= $currentType === $key ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Leaderboard Table -->
<?php if (empty($leaderboard)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">🏆</span>
            <strong>No data yet for this leaderboard</strong>
        </p>
        <p class="govuk-body">Be the first to climb the ranks!</p>
    </div>
<?php else: ?>
    <table class="govuk-table" aria-label="Leaderboard rankings">
        <caption class="govuk-table__caption govuk-visually-hidden">Community leaderboard rankings</caption>
        <thead class="govuk-table__head">
            <tr class="govuk-table__row">
                <th scope="col" class="govuk-table__header civicone-rank-column">Rank</th>
                <th scope="col" class="govuk-table__header">Member</th>
                <th scope="col" class="govuk-table__header govuk-table__header--numeric">Score</th>
            </tr>
        </thead>
        <tbody class="govuk-table__body">
            <?php foreach ($leaderboard as $entry):
                $medal = \Nexus\Services\LeaderboardService::getMedalIcon($entry['rank']);
                $formattedScore = \Nexus\Services\LeaderboardService::formatScore($entry['score'], $currentType);
                $displayName = $entry['first_name'] && $entry['last_name']
                    ? $entry['first_name'] . ' ' . $entry['last_name']
                    : ($entry['name'] ?? 'Unknown');
                $avatarUrl = $entry['avatar_url'] ?? '/assets/img/defaults/default_avatar.webp';
                $profileUrl = $basePath . '/profile/' . $entry['user_id'];
                $isCurrentUser = !empty($entry['is_current_user']);

                // Determine tag color based on rank
                $tagClass = 'govuk-tag--grey';
                if ($entry['rank'] === 1) $tagClass = 'govuk-tag--yellow';
                elseif ($entry['rank'] === 2) $tagClass = 'govuk-tag--grey';
                elseif ($entry['rank'] === 3) $tagClass = 'govuk-tag--orange';
            ?>
                <tr class="govuk-table__row <?= $isCurrentUser ? 'civicone-panel-bg' : '' ?>">
                    <td class="govuk-table__cell">
                        <?php if ($medal): ?>
                            <span class="govuk-tag <?= $tagClass ?>" aria-label="Rank <?= $entry['rank'] ?>">
                                <?= $medal ?> #<?= $entry['rank'] ?>
                            </span>
                        <?php else: ?>
                            <span class="govuk-body">#<?= $entry['rank'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell">
                        <a href="<?= $profileUrl ?>" class="govuk-link civicone-member-link">
                            <img src="<?= htmlspecialchars($avatarUrl) ?>"
                                 loading="lazy"
                                 alt=""
                                 class="civicone-table-avatar">
                            <span>
                                <strong><?= htmlspecialchars($displayName) ?></strong>
                                <?php if ($currentType === 'xp' && isset($entry['level'])): ?>
                                    <br><span class="govuk-body-s civicone-secondary-text">Level <?= (int)$entry['level'] ?></span>
                                <?php endif; ?>
                                <?php if ($isCurrentUser): ?>
                                    <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-left-2">You</span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </td>
                    <td class="govuk-table__cell govuk-table__cell--numeric">
                        <strong><?= htmlspecialchars($formattedScore) ?></strong>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (isset($_SESSION['user_id']) && $userStats): ?>
    <!-- Your Progress Section -->
    <h2 class="govuk-heading-l govuk-!-margin-top-8">
        <i class="fa-solid fa-chart-line govuk-!-margin-right-2" aria-hidden="true"></i>
        Your Progress
    </h2>

    <?php if ($userStreaks && isset($userStreaks['login'])): ?>
        <?php
        $loginStreak = $userStreaks['login'];
        $streakIcon = \Nexus\Services\StreakService::getStreakIcon($loginStreak['current']);
        ?>
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg civicone-streak-panel">
            <p class="govuk-body-l govuk-!-margin-bottom-0">
                <span aria-hidden="true"><?= $streakIcon ?></span>
                <strong><?= $loginStreak['current'] ?> day login streak</strong>
                <?php if ($loginStreak['longest'] > $loginStreak['current']): ?>
                    <span class="govuk-body-s govuk-!-margin-left-2 civicone-secondary-text">
                        Best: <?= $loginStreak['longest'] ?> days
                    </span>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="govuk-grid-row govuk-!-margin-bottom-6">
        <!-- Level & XP -->
        <div class="govuk-grid-column-one-quarter">
            <div class="govuk-!-padding-4 civicone-stat-card">
                <p class="govuk-body-s govuk-!-margin-bottom-1" aria-hidden="true">⭐</p>
                <p class="govuk-heading-m govuk-!-margin-bottom-1">Level <?= $userStats['level'] ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-3 civicone-secondary-text"><?= number_format($userStats['xp']) ?> XP</p>
                <div class="civicone-panel-bg civicone-xp-progress-bar" role="progressbar" aria-valuenow="<?= $userStats['level_progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="civicone-xp-progress-fill" style="width: <?= $userStats['level_progress'] ?>%;"></div>
                </div>
                <?php if ($userStats['xp_for_next']): ?>
                    <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0 civicone-secondary-text">
                        <?= number_format($userStats['xp_for_next'] - $userStats['xp']) ?> XP to Level <?= $userStats['level'] + 1 ?>
                    </p>
                <?php else: ?>
                    <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0 civicone-text-success">Max Level!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Badges -->
        <div class="govuk-grid-column-one-quarter">
            <div class="govuk-!-padding-4 civicone-stat-card">
                <p class="govuk-body-s govuk-!-margin-bottom-1" aria-hidden="true">🏅</p>
                <p class="govuk-heading-m govuk-!-margin-bottom-1"><?= $userStats['badges_count'] ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Badges Earned</p>
            </div>
        </div>

        <?php if ($userStreaks && isset($userStreaks['activity'])): ?>
        <!-- Activity Streak -->
        <div class="govuk-grid-column-one-quarter">
            <div class="govuk-!-padding-4 civicone-stat-card">
                <p class="govuk-body-s govuk-!-margin-bottom-1" aria-hidden="true">🔥</p>
                <p class="govuk-heading-m govuk-!-margin-bottom-1"><?= $userStreaks['activity']['current'] ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Activity Streak</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($userStreaks && isset($userStreaks['giving'])): ?>
        <!-- Giving Streak -->
        <div class="govuk-grid-column-one-quarter">
            <div class="govuk-!-padding-4 civicone-stat-card">
                <p class="govuk-body-s govuk-!-margin-bottom-1" aria-hidden="true">💝</p>
                <p class="govuk-heading-m govuk-!-margin-bottom-1"><?= $userStreaks['giving']['current'] ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Giving Streak</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <a href="<?= $basePath ?>/profile/me" class="govuk-button govuk-button--secondary" data-module="govuk-button">
        <i class="fa-solid fa-medal govuk-!-margin-right-1" aria-hidden="true"></i>
        View All Your Badges & Achievements
    </a>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
