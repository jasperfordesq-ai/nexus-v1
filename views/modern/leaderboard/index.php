<?php
/**
 * Leaderboard View
 * Path: views/modern/leaderboard/index.php
 */

$hTitle = 'Leaderboards';
$hSubtitle = 'See who\'s leading the community';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

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


<div class="leaderboard-bg"></div>

<div class="leaderboard-container">
    <!-- Page Header -->
    <div class="glass-card">
        <div class="leaderboard-header">
            <h1 class="leaderboard-title">Leaderboards</h1>
            <div class="filter-group">
                <?php foreach ($periods as $key => $label): ?>
                    <a href="<?= $basePath ?>/leaderboard?type=<?= $currentType ?>&period=<?= $key ?>"
                       class="filter-btn <?= $currentPeriod === $key ? 'active' : '' ?>">
                        <?= htmlspecialchars($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Type Filters -->
        <div class="filter-group" style="margin-bottom: 24px;">
            <?php foreach ($types as $key => $label): ?>
                <a href="<?= $basePath ?>/leaderboard?type=<?= $key ?>&period=<?= $currentPeriod ?>"
                   class="filter-btn <?= $currentType === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Leaderboard Table -->
        <div class="leaderboard-table">
            <?php if (empty($leaderboard)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏆</div>
                    <p>No data yet for this leaderboard.</p>
                    <p style="font-size: 0.875rem;">Be the first to climb the ranks!</p>
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
                                <span class="medal"><?= $medal ?></span>
                            <?php else: ?>
                                #<?= $entry['rank'] ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-col">
                            <?= webp_avatar($avatarUrl, $displayName, 48) ?>
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
            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 16px; color: #1e293b;">Your Progress</h2>

            <?php if ($userStreaks && isset($userStreaks['login'])): ?>
                <?php
                $loginStreak = $userStreaks['login'];
                $streakIcon = \Nexus\Services\StreakService::getStreakIcon($loginStreak['current']);
                ?>
                <div class="streak-display">
                    <span class="streak-icon"><?= $streakIcon ?></span>
                    <span class="streak-count"><?= $loginStreak['current'] ?></span>
                    <span class="streak-label">day login streak</span>
                    <?php if ($loginStreak['longest'] > $loginStreak['current']): ?>
                        <span style="margin-left: auto; font-size: 0.75rem; color: #94a3b8;">
                            Best: <?= $loginStreak['longest'] ?> days
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <!-- Level & XP -->
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-value">Level <?= $userStats['level'] ?></div>
                    <div class="stat-label"><?= number_format($userStats['xp']) ?> XP</div>
                    <div class="xp-progress-container">
                        <div class="xp-progress-bar">
                            <div class="xp-progress-fill" style="width: <?= $userStats['level_progress'] ?>%"></div>
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
                    <div class="stat-icon">🏅</div>
                    <div class="stat-value"><?= $userStats['badges_count'] ?></div>
                    <div class="stat-label">Badges Earned</div>
                </div>

                <!-- Activity Streak -->
                <?php if ($userStreaks && isset($userStreaks['activity'])): ?>
                    <div class="stat-card">
                        <div class="stat-icon">🔥</div>
                        <div class="stat-value"><?= $userStreaks['activity']['current'] ?></div>
                        <div class="stat-label">Activity Streak</div>
                    </div>
                <?php endif; ?>

                <!-- Giving Streak -->
                <?php if ($userStreaks && isset($userStreaks['giving'])): ?>
                    <div class="stat-card">
                        <div class="stat-icon">💝</div>
                        <div class="stat-value"><?= $userStreaks['giving']['current'] ?></div>
                        <div class="stat-label">Giving Streak</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- View Profile Link -->
        <div style="text-align: center; margin-top: 24px;">
            <a href="<?= $basePath ?>/profile/me" class="filter-btn active" style="display: inline-block;">
                View All Your Badges & Achievements
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
