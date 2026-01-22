<?php
/**
 * CivicOne Leaderboard - Community Rankings
 * Template A: Directory/List (Section 10.3)
 * Glassmorphism leaderboard with stats, streaks, and progress tracking
 * WCAG 2.1 AA Compliant
 */
$heroTitle = 'Leaderboards';
$heroSub = 'See who\'s leading the community';
$heroType = 'Leaderboard';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

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
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/purged/civicone-leaderboard.min.css?v=<?= time() ?>">

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <div class="leaderboard-bg" aria-hidden="true"></div>

        <div class="leaderboard-container">
            <!-- Page Header -->
            <div class="glass-card">
                <div class="leaderboard-header">
                    <h1 class="leaderboard-title">Leaderboards</h1>
                    <div class="filter-group" role="group" aria-label="Time period filters">
                        <?php foreach ($periods as $key => $label): ?>
                            <a href="<?= $basePath ?>/leaderboard?type=<?= $currentType ?>&period=<?= $key ?>"
                               class="filter-btn <?= $currentPeriod === $key ? 'active' : '' ?>"
                               aria-current="<?= $currentPeriod === $key ? 'page' : 'false' ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Type Filters -->
                <div class="filter-group type-filters-margin" role="group" aria-label="Ranking type filters">
                    <?php foreach ($types as $key => $label): ?>
                        <a href="<?= $basePath ?>/leaderboard?type=<?= $key ?>&period=<?= $currentPeriod ?>"
                           class="filter-btn <?= $currentType === $key ? 'active' : '' ?>"
                           aria-current="<?= $currentType === $key ? 'page' : 'false' ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Leaderboard Table -->
                <div class="leaderboard-table">
                    <?php if (empty($leaderboard)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon" aria-hidden="true">🏆</div>
                            <p>No data yet for this leaderboard.</p>
                            <p class="empty-state-subtitle">Be the first to climb the ranks!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($leaderboard as $entry): ?>
                            <?php
                            $rowClass = '';
                            if ($entry['rank'] === 1) $rowClass = 'top-1';
                            elseif ($entry['rank'] === 2) $rowClass = 'top-2';
                            elseif ($entry['rank'] === 3) $rowClass = 'top-3';
                            if (!empty($entry['is_current_user'])) $rowClass .= ' current-user';

                            $medal = \Nexus\Services\LeaderboardService::getMedalIcon($entry['rank']);
                            $formattedScore = \Nexus\Services\LeaderboardService::formatScore($entry['score'], $currentType);
                            $displayName = $entry['first_name'] && $entry['last_name']
                                ? $entry['first_name'] . ' ' . $entry['last_name']
                                : ($entry['name'] ?? 'Unknown');
                            $avatarUrl = $entry['avatar_url'] ?? '/assets/img/defaults/default_avatar.webp';
                            $profileUrl = $basePath . '/profile/' . $entry['user_id'];
                            ?>
                            <a href="<?= $profileUrl ?>" class="leaderboard-row <?= $rowClass ?>">
                                <div class="rank-col">
                                    <?php if ($medal): ?>
                                        <span class="medal" aria-label="Rank <?= $entry['rank'] ?>"><?= $medal ?></span>
                                    <?php else: ?>
                                        <span aria-label="Rank <?= $entry['rank'] ?>">#<?= $entry['rank'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="user-col">
                                    <img src="<?= htmlspecialchars($avatarUrl) ?>"
                                         loading="lazy"
                                         alt="<?= htmlspecialchars($displayName) ?>"
                                         class="user-avatar">
                                    <div class="user-info">
                                        <h4><?= htmlspecialchars($displayName) ?></h4>
                                        <?php if ($currentType === 'xp' && isset($entry['level'])): ?>
                                            <span class="level-badge">Level <?= (int)$entry['level'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="score-col">
                                    <div class="score-value"><?= htmlspecialchars($formattedScore) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_SESSION['user_id']) && $userStats): ?>
                <!-- Your Stats -->
                <div class="glass-card">
                    <h2 class="your-progress-heading">Your Progress</h2>

                    <?php if ($userStreaks && isset($userStreaks['login'])): ?>
                        <?php
                        $loginStreak = $userStreaks['login'];
                        $streakIcon = \Nexus\Services\StreakService::getStreakIcon($loginStreak['current']);
                        ?>
                        <div class="streak-display">
                            <span class="streak-icon" aria-hidden="true"><?= $streakIcon ?></span>
                            <span class="streak-count"><?= $loginStreak['current'] ?></span>
                            <span class="streak-label">day login streak</span>
                            <?php if ($loginStreak['longest'] > $loginStreak['current']): ?>
                                <span class="streak-best">
                                    Best: <?= $loginStreak['longest'] ?> days
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="stats-grid">
                        <!-- Level & XP -->
                        <div class="stat-card">
                            <div class="stat-icon" aria-hidden="true">⭐</div>
                            <div class="stat-value">Level <?= $userStats['level'] ?></div>
                            <div class="stat-label"><?= number_format($userStats['xp']) ?> XP</div>
                            <div class="xp-progress-container">
                                <div class="xp-progress-bar" role="progressbar"
                                     aria-valuenow="<?= $userStats['level_progress'] ?>"
                                     aria-valuemin="0"
                                     aria-valuemax="100"
                                     aria-label="Level progress">
                                    <div class="xp-progress-fill" style="--progress: <?= $userStats['level_progress'] ?>%"></div>
                                </div>
                                <?php if ($userStats['xp_for_next']): ?>
                                    <div class="xp-progress-text">
                                        <?= number_format($userStats['xp_for_next'] - $userStats['xp']) ?> XP to Level <?= $userStats['level'] + 1 ?>
                                    </div>
                                <?php else: ?>
                                    <div class="xp-progress-text">Max Level Reached!</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="stat-card">
                            <div class="stat-icon" aria-hidden="true">🏅</div>
                            <div class="stat-value"><?= $userStats['badges_count'] ?></div>
                            <div class="stat-label">Badges Earned</div>
                        </div>

                        <!-- Activity Streak -->
                        <?php if ($userStreaks && isset($userStreaks['activity'])): ?>
                            <div class="stat-card">
                                <div class="stat-icon" aria-hidden="true">🔥</div>
                                <div class="stat-value"><?= $userStreaks['activity']['current'] ?></div>
                                <div class="stat-label">Activity Streak</div>
                            </div>
                        <?php endif; ?>

                        <!-- Giving Streak -->
                        <?php if ($userStreaks && isset($userStreaks['giving'])): ?>
                            <div class="stat-card">
                                <div class="stat-icon" aria-hidden="true">💝</div>
                                <div class="stat-value"><?= $userStreaks['giving']['current'] ?></div>
                                <div class="stat-label">Giving Streak</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- View Profile Link -->
                <div class="view-profile-container">
                    <a href="<?= $basePath ?>/profile/me" class="filter-btn active">
                        View All Your Badges & Achievements
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div><!-- /civicone-width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
