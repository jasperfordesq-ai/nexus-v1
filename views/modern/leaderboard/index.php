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

<style>
/* Leaderboard Background */
.leaderboard-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #fef3c7 25%, #fde68a 50%, #fef3c7 75%, #f8fafc 100%);
}

.leaderboard-bg::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background:
        radial-gradient(ellipse at 20% 30%, rgba(245, 158, 11, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(234, 179, 8, 0.12) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 80%, rgba(217, 119, 6, 0.1) 0%, transparent 50%);
}

[data-theme="dark"] .leaderboard-bg {
    background: linear-gradient(135deg, #0f172a 0%, #422006 50%, #0f172a 100%);
}

/* Container */
.leaderboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 120px 24px 40px 24px;
}

/* Glass Card */
.glass-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    padding: 24px;
    margin-bottom: 24px;
    position: relative;
    z-index: 1;
}

/* Disable any hover effects on glass card */
.glass-card:hover {
    transform: none !important;
    scale: none !important;
    animation: none !important;
}

[data-theme="dark"] .glass-card {
    background: rgba(30, 41, 59, 0.85);
    border-color: rgba(255, 255, 255, 0.1);
}

/* Header */
.leaderboard-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.leaderboard-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
}

[data-theme="dark"] .leaderboard-title {
    color: #f1f5f9;
}

/* Filters */
.filter-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 10;
}

.filter-btn {
    padding: 12px 20px;
    border-radius: 12px;
    border: 2px solid rgba(0, 0, 0, 0.15);
    background: rgba(255, 255, 255, 0.9);
    color: #475569;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-block;
    position: relative;
    z-index: 10;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.filter-btn:hover {
    background: rgba(255, 255, 255, 1);
    border-color: #f59e0b;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
}

.filter-btn:active {
    transform: translateY(0);
    transition: transform 0.1s ease;
}

.filter-btn.active {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.filter-btn.active:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
}

[data-theme="dark"] .filter-btn {
    background: rgba(51, 65, 85, 0.9);
    color: #cbd5e1;
    border-color: rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .filter-btn:hover {
    background: rgba(71, 85, 105, 1);
    border-color: #f59e0b;
}

/* Leaderboard Table */
.leaderboard-table {
    width: 100%;
}

a.leaderboard-row {
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}

.leaderboard-row {
    display: flex;
    align-items: center;
    padding: 16px;
    border-radius: 16px;
    margin-bottom: 8px;
    background: rgba(255, 255, 255, 0.5);
    transition: all 0.2s;
}

.leaderboard-row:hover {
    background: rgba(255, 255, 255, 0.8);
    transform: translateX(4px);
}

.leaderboard-row.top-1 {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.3), rgba(255, 215, 0, 0.1));
    border: 2px solid rgba(255, 215, 0, 0.5);
}

.leaderboard-row.top-2 {
    background: linear-gradient(135deg, rgba(192, 192, 192, 0.3), rgba(192, 192, 192, 0.1));
    border: 2px solid rgba(192, 192, 192, 0.5);
}

.leaderboard-row.top-3 {
    background: linear-gradient(135deg, rgba(205, 127, 50, 0.3), rgba(205, 127, 50, 0.1));
    border: 2px solid rgba(205, 127, 50, 0.5);
}

.leaderboard-row.current-user {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.1));
    border: 2px solid rgba(99, 102, 241, 0.5);
}

[data-theme="dark"] .leaderboard-row {
    background: rgba(51, 65, 85, 0.5);
}

.rank-col {
    width: 60px;
    font-size: 1.5rem;
    font-weight: 700;
    color: #94a3b8;
    text-align: center;
}

.rank-col .medal {
    font-size: 1.75rem;
}

.user-col {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.user-info h4 {
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.user-info .level-badge {
    font-size: 0.75rem;
    color: #64748b;
    background: rgba(99, 102, 241, 0.1);
    padding: 2px 8px;
    border-radius: 8px;
    display: inline-block;
    margin-top: 4px;
}

[data-theme="dark"] .user-info h4 {
    color: #f1f5f9;
}

.score-col {
    text-align: right;
    min-width: 120px;
}

.score-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f59e0b;
}

.score-label {
    font-size: 0.75rem;
    color: #94a3b8;
}

/* User Stats Card */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.6);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
}

[data-theme="dark"] .stat-card {
    background: rgba(51, 65, 85, 0.6);
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

[data-theme="dark"] .stat-value {
    color: #f1f5f9;
}

.stat-label {
    font-size: 0.875rem;
    color: #64748b;
}

/* XP Progress */
.xp-progress-container {
    margin-top: 8px;
}

.xp-progress-bar {
    height: 8px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.xp-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #f59e0b, #eab308);
    border-radius: 4px;
    transition: width 0.5s ease;
}

.xp-progress-text {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 4px;
}

/* Streak Display */
.streak-display {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(249, 115, 22, 0.1));
    border-radius: 12px;
    margin-bottom: 16px;
}

.streak-icon {
    font-size: 1.5rem;
}

.streak-count {
    font-size: 1.25rem;
    font-weight: 700;
    color: #ef4444;
}

.streak-label {
    color: #64748b;
    font-size: 0.875rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #64748b;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .leaderboard-container {
        padding: 100px 16px 24px 16px;
    }

    .leaderboard-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .leaderboard-row {
        flex-wrap: wrap;
        gap: 12px;
    }

    .rank-col {
        width: 40px;
    }

    .user-col {
        min-width: 0;
    }

    .score-col {
        width: 100%;
        text-align: left;
        padding-left: 52px;
    }
}
</style>

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
