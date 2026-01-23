<?php
$hTitle = 'Leaderboard Seasons';
$hSubtitle = 'Compete monthly for exclusive rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$season = $seasonData['season'] ?? null;
$userRank = $seasonData['user_rank'] ?? null;
$leaderboard = $seasonData['leaderboard'] ?? [];
$rewards = $seasonData['rewards'] ?? [];
$daysRemaining = $seasonData['days_remaining'] ?? 0;
$isEndingSoon = $seasonData['is_ending_soon'] ?? false;
?>
<!-- CSS moved to /assets/css/civicone-achievements.css (2026-01-19) -->

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
                <i class="fa-solid fa-trophy civic-icon-trophy"></i>
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
                    <i class="fa-solid fa-star civic-icon-star"></i>
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
                        <div class="user-avatar civic-avatar-placeholder">
                            <?= strtoupper(substr($entry['first_name'] ?? '?', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="user-details">
                        <div class="user-name">
                            <?= htmlspecialchars(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?>
                            <?php if ($isCurrentUser): ?>
                                <span class="civic-you-label">(You)</span>
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
                    <div class="tier-icon"><i class="fa-solid fa-crown civic-icon-crown"></i></div>
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
                    <div class="tier-icon"><i class="fa-solid fa-medal civic-icon-medal-silver"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">2nd Place</div>
                        <div class="tier-reward">+<?= $rewards[2]['xp'] ?? 300 ?> XP</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($rewards[3])): ?>
                <div class="reward-tier bronze">
                    <div class="tier-icon"><i class="fa-solid fa-medal civic-icon-medal-bronze"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">3rd Place</div>
                        <div class="tier-reward">+<?= $rewards[3]['xp'] ?? 200 ?> XP</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($rewards['top10'])): ?>
                <div class="reward-tier">
                    <div class="tier-icon"><i class="fa-solid fa-award civic-icon-award"></i></div>
                    <div class="tier-details">
                        <div class="tier-name">Top 10</div>
                        <div class="tier-reward">+<?= $rewards['top10']['xp'] ?? 100 ?> XP</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($rewards['participant'])): ?>
                <div class="reward-tier">
                    <div class="tier-icon"><i class="fa-solid fa-star civic-icon-medal-silver"></i></div>
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

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
