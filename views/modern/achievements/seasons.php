<?php
$hTitle = 'Leaderboard Seasons';
$hSubtitle = 'Compete monthly for exclusive rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$season = $seasonData['season'] ?? null;
$userRank = $seasonData['user_rank'] ?? null;
$leaderboard = $seasonData['leaderboard'] ?? [];
$rewards = $seasonData['rewards'] ?? [];
$daysRemaining = $seasonData['days_remaining'] ?? 0;
$isEndingSoon = $seasonData['is_ending_soon'] ?? false;
?>

<style>
.seasons-wrapper {
    margin-top: 120px;
    padding: 0 20px 60px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.collections-nav {
    display: flex;
    gap: 12px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.nav-pill {
    padding: 10px 20px;
    border-radius: 25px;
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-pill:hover, .nav-pill.active {
    background: white;
    color: #1e1e2e;
}

.season-header {
    background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.9));
    border-radius: 24px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.season-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.season-name {
    font-size: 28px;
    font-weight: 800;
    color: #1e1e2e;
    display: flex;
    align-items: center;
    gap: 12px;
}

.season-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.season-badge.active {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}

.season-badge.ending-soon {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.time-remaining {
    text-align: right;
}

.time-remaining-label {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 4px;
}

.time-remaining-value {
    font-size: 32px;
    font-weight: 800;
    color: #4f46e5;
}

.time-remaining-unit {
    font-size: 16px;
    color: #6b7280;
    font-weight: 500;
}

.user-rank-card {
    display: flex;
    align-items: center;
    gap: 24px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: 16px;
    padding: 20px 28px;
    color: white;
}

.user-rank-position {
    font-size: 48px;
    font-weight: 900;
    line-height: 1;
}

.user-rank-position sup {
    font-size: 20px;
    font-weight: 600;
}

.user-rank-info h4 {
    font-size: 14px;
    font-weight: 500;
    opacity: 0.9;
    margin-bottom: 4px;
}

.user-rank-xp {
    font-size: 24px;
    font-weight: 700;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 24px;
}

.leaderboard-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.leaderboard-card h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.leaderboard-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.leaderboard-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    border-radius: 12px;
    background: #f9fafb;
    transition: all 0.2s;
}

.leaderboard-item:hover {
    background: #f3f4f6;
}

.leaderboard-item.top-3 {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
}

.leaderboard-item.current-user {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border: 2px solid #3b82f6;
}

.rank-badge {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    background: #e5e7eb;
    color: #6b7280;
}

.leaderboard-item.top-3:nth-child(1) .rank-badge {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
}

.leaderboard-item.top-3:nth-child(2) .rank-badge {
    background: linear-gradient(135deg, #9ca3af, #6b7280);
    color: white;
}

.leaderboard-item.top-3:nth-child(3) .rank-badge {
    background: linear-gradient(135deg, #d97706, #b45309);
    color: white;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background: #e5e7eb;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: #1e1e2e;
}

.user-level {
    font-size: 12px;
    color: #6b7280;
}

.user-xp {
    font-weight: 700;
    color: #4f46e5;
}

.rewards-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.rewards-card h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.reward-tier {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 10px;
    background: #f9fafb;
}

.reward-tier.gold {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
}

.reward-tier.silver {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
}

.reward-tier.bronze {
    background: linear-gradient(135deg, #fed7aa, #fdba74);
}

.tier-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.tier-details {
    flex: 1;
}

.tier-name {
    font-weight: 700;
    color: #1e1e2e;
    font-size: 14px;
}

.tier-reward {
    font-size: 12px;
    color: #6b7280;
}

.past-seasons {
    margin-top: 32px;
}

.past-seasons h3 {
    color: white;
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 16px;
}

.past-seasons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 16px;
}

.past-season-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.past-season-name {
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 8px;
}

.past-season-dates {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 12px;
}

.past-season-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.past-season-status.completed {
    background: #d1fae5;
    color: #059669;
}

.past-season-status.active {
    background: #dbeafe;
    color: #2563eb;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: white;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

@media (max-width: 968px) {
    .content-grid {
        grid-template-columns: 1fr;
    }

    .season-title-row {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }

    .time-remaining {
        text-align: center;
    }

    .user-rank-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="seasons-wrapper">
    <div class="collections-nav">
        <a href="<?= $basePath ?>/achievements" class="nav-pill">Dashboard</a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill">All Badges</a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill">Challenges</a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill">Collections</a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill">XP Shop</a>
        <a href="<?= $basePath ?>/achievements/seasons" class="nav-pill active">Seasons</a>
    </div>

    <?php if (!$season): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-trophy"></i></div>
        <h3>No Active Season</h3>
        <p>Leaderboard seasons will appear here once they're set up.</p>
    </div>
    <?php else: ?>

    <div class="season-header">
        <div class="season-title-row">
            <div class="season-name">
                <i class="fa-solid fa-trophy" style="color: #fbbf24;"></i>
                <?= htmlspecialchars($season['name']) ?>
                <?php if ($isEndingSoon): ?>
                    <span class="season-badge ending-soon">
                        <i class="fa-solid fa-clock"></i> Ending Soon!
                    </span>
                <?php else: ?>
                    <span class="season-badge active">
                        <i class="fa-solid fa-circle"></i> Active
                    </span>
                <?php endif; ?>
            </div>
            <div class="time-remaining">
                <div class="time-remaining-label">Time Remaining</div>
                <div class="time-remaining-value">
                    <?= $daysRemaining ?>
                    <span class="time-remaining-unit"><?= $daysRemaining === 1 ? 'day' : 'days' ?></span>
                </div>
            </div>
        </div>

        <?php if ($userRank): ?>
        <div class="user-rank-card">
            <div class="user-rank-position">
                #<?= $userRank['position'] ?><?= $userRank['position'] == 1 ? '<sup>st</sup>' : ($userRank['position'] == 2 ? '<sup>nd</sup>' : ($userRank['position'] == 3 ? '<sup>rd</sup>' : '<sup>th</sup>')) ?>
            </div>
            <div class="user-rank-info">
                <h4>Your Current Position</h4>
                <div class="user-rank-xp">
                    <i class="fa-solid fa-star" style="color: #fbbf24;"></i>
                    <?= number_format($userRank['season_xp']) ?> XP this season
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="content-grid">
        <div class="leaderboard-card">
            <h3><i class="fa-solid fa-ranking-star"></i> Season Leaderboard</h3>
            <div class="leaderboard-list">
                <?php foreach ($leaderboard as $index => $entry): ?>
                <?php
                    $position = $index + 1;
                    $isCurrentUser = isset($_SESSION['user_id']) && $entry['user_id'] == $_SESSION['user_id'];
                    $isTop3 = $position <= 3;
                ?>
                <div class="leaderboard-item <?= $isTop3 ? 'top-3' : '' ?> <?= $isCurrentUser ? 'current-user' : '' ?>">
                    <div class="rank-badge">
                        <?php if ($position === 1): ?>
                            <i class="fa-solid fa-crown"></i>
                        <?php else: ?>
                            <?= $position ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($entry['photo'])): ?>
                        <img src="<?= htmlspecialchars($entry['photo']) ?>" loading="lazy" alt="" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar" style="display: flex; align-items: center; justify-content: center; background: #4f46e5; color: white; font-weight: 600;">
                            <?= strtoupper(substr($entry['first_name'] ?? '?', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="user-details">
                        <div class="user-name">
                            <?= htmlspecialchars(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?>
                            <?php if ($isCurrentUser): ?>
                                <span style="color: #3b82f6; font-size: 12px;">(You)</span>
                            <?php endif; ?>
                        </div>
                        <div class="user-level">Level <?= $entry['level'] ?? 1 ?></div>
                    </div>
                    <div class="user-xp"><?= number_format($entry['season_xp'] ?? 0) ?> XP</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <div class="rewards-card">
                <h3><i class="fa-solid fa-gift"></i> Season Rewards</h3>

                <?php if (isset($rewards[1])): ?>
                <div class="reward-tier gold">
                    <div class="tier-icon"><i class="fa-solid fa-crown" style="color: #fbbf24;"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">1st Place</div>
                        <div class="tier-reward">
                            +<?= $rewards[1]['xp'] ?? 500 ?> XP
                            <?php if (!empty($rewards[1]['badge'])): ?>
                                + Exclusive Badge
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($rewards[2])): ?>
                <div class="reward-tier silver">
                    <div class="tier-icon"><i class="fa-solid fa-medal" style="color: #9ca3af;"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">2nd Place</div>
                        <div class="tier-reward">+<?= $rewards[2]['xp'] ?? 300 ?> XP</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($rewards[3])): ?>
                <div class="reward-tier bronze">
                    <div class="tier-icon"><i class="fa-solid fa-medal" style="color: #d97706;"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">3rd Place</div>
                        <div class="tier-reward">+<?= $rewards[3]['xp'] ?? 200 ?> XP</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($rewards['top10'])): ?>
                <div class="reward-tier">
                    <div class="tier-icon"><i class="fa-solid fa-award" style="color: #6b7280;"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">Top 10</div>
                        <div class="tier-reward">+<?= $rewards['top10']['xp'] ?? 100 ?> XP</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($rewards['participant'])): ?>
                <div class="reward-tier">
                    <div class="tier-icon"><i class="fa-solid fa-star" style="color: #9ca3af;"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">All Participants</div>
                        <div class="tier-reward">+<?= $rewards['participant']['xp'] ?? 25 ?> XP</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($allSeasons) && count($allSeasons) > 1): ?>
    <div class="past-seasons">
        <h3><i class="fa-solid fa-history"></i> Past Seasons</h3>
        <div class="past-seasons-grid">
            <?php foreach ($allSeasons as $pastSeason): ?>
                <?php if ($pastSeason['id'] === $season['id']) continue; ?>
                <div class="past-season-card">
                    <div class="past-season-name"><?= htmlspecialchars($pastSeason['name']) ?></div>
                    <div class="past-season-dates">
                        <?= date('M j', strtotime($pastSeason['start_date'])) ?> - <?= date('M j, Y', strtotime($pastSeason['end_date'])) ?>
                    </div>
                    <span class="past-season-status <?= $pastSeason['status'] ?>">
                        <?php if ($pastSeason['status'] === 'completed'): ?>
                            <i class="fa-solid fa-check-circle"></i> Completed
                        <?php else: ?>
                            <i class="fa-solid fa-circle"></i> <?= ucfirst($pastSeason['status']) ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
