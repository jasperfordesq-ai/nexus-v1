<?php
$hTitle = 'My Achievements';
$hSubtitle = 'Track your progress and unlock rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Load achievements CSS
$additionalCSS = '<link rel="stylesheet" href="/assets/css/achievements.min.css?v=' . time() . '">';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

// Due to EXTR_SKIP in View::render(), $data remains the full array passed to render()
$dashboardData = $data['data'] ?? $data;
$xp = $dashboardData['xp'] ?? ['total' => 0, 'level' => 1, 'progress' => 0, 'xp_for_next' => 100, 'xp_in_level' => 0];
$badges = $dashboardData['badges'] ?? ['earned' => [], 'total_earned' => 0, 'total_available' => 0, 'progress' => []];
$streaks = $dashboardData['streaks'] ?? [];
$rankings = $dashboardData['rankings'] ?? [];
$stats = $dashboardData['stats'] ?? [];
$recentXP = $dashboardData['recent_xp'] ?? [];

// Calculate totals for hero banner
$totalBadges = $badges['total_earned'] ?? 0;
$currentStreak = $streaks['login']['current'] ?? 0;
$xpRank = $rankings['xp'] ?? '-';
?>

<div class="achievements-wrapper" role="main" aria-label="Achievements Dashboard">
    <!-- Navigation Pills -->
    <nav class="achievements-nav" aria-label="Achievement sections">
        <a href="<?= $basePath ?>/achievements" class="nav-pill active">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill">
            <i class="fa-solid fa-medal"></i> Badges
        </a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill">
            <i class="fa-solid fa-bullseye"></i> Challenges
        </a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill">
            <i class="fa-solid fa-layer-group"></i> Collections
        </a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill">
            <i class="fa-solid fa-store"></i> Shop
        </a>
        <a href="<?= $basePath ?>/achievements/seasons" class="nav-pill">
            <i class="fa-solid fa-trophy"></i> Seasons
        </a>
    </nav>

    <!-- Hero Stats Banner -->
    <div class="hero-stats-banner">
        <div class="hero-stat-card level-card">
            <div class="hero-stat-icon">
                <div class="level-ring" style="--progress: <?= $xp['progress'] ?? 0 ?>">
                    <span class="level-number"><?= $xp['level'] ?? 1 ?></span>
                </div>
            </div>
            <div class="hero-stat-info">
                <div class="hero-stat-value"><?= number_format($xp['total'] ?? 0) ?></div>
                <div class="hero-stat-label">Total XP</div>
            </div>
        </div>

        <div class="hero-stat-card">
            <div class="hero-stat-icon badges-icon">
                <i class="fa-solid fa-award"></i>
            </div>
            <div class="hero-stat-info">
                <div class="hero-stat-value"><?= $totalBadges ?></div>
                <div class="hero-stat-label">Badges Earned</div>
            </div>
        </div>

        <div class="hero-stat-card">
            <div class="hero-stat-icon streak-icon">
                <i class="fa-solid fa-fire"></i>
            </div>
            <div class="hero-stat-info">
                <div class="hero-stat-value"><?= $currentStreak ?></div>
                <div class="hero-stat-label">Day Streak</div>
            </div>
        </div>

        <div class="hero-stat-card">
            <div class="hero-stat-icon rank-icon">
                <i class="fa-solid fa-ranking-star"></i>
            </div>
            <div class="hero-stat-info">
                <div class="hero-stat-value">#<?= $xpRank ?></div>
                <div class="hero-stat-label">Global Rank</div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="achievements-grid-v2">

        <!-- Left Column -->
        <div class="achievements-column-main">

            <!-- XP Progress Card -->
            <div class="achievement-card xp-card">
                <div class="card-header">
                    <span class="icon">⭐</span>
                    <h3>Level Progress</h3>
                    <span class="level-badge">Lvl <?= $xp['level'] ?? 1 ?></span>
                </div>
                <div class="xp-progress-section">
                    <div class="xp-bar-wrapper">
                        <div class="xp-bar-track">
                            <div class="xp-bar-fill" style="width: <?= $xp['progress'] ?? 0 ?>%">
                                <div class="xp-bar-glow"></div>
                            </div>
                        </div>
                        <div class="xp-bar-labels">
                            <span>Lvl <?= $xp['level'] ?? 1 ?></span>
                            <span class="xp-current"><?= number_format($xp['xp_in_level'] ?? 0) ?> / <?= number_format($xp['xp_for_next'] ?? 100) ?> XP</span>
                            <span>Lvl <?= ($xp['level'] ?? 1) + 1 ?></span>
                        </div>
                    </div>
                    <?php if (($xp['level'] ?? 1) < 10): ?>
                    <p class="xp-hint">Earn <?= number_format(($xp['xp_for_next'] ?? 100) - ($xp['xp_in_level'] ?? 0)) ?> more XP to reach Level <?= ($xp['level'] ?? 1) + 1 ?></p>
                    <?php else: ?>
                    <p class="xp-hint xp-max">Maximum Level Achieved!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Badge Progress Card -->
            <div class="achievement-card">
                <div class="card-header">
                    <span class="icon">🎯</span>
                    <h3>Next Badges to Unlock</h3>
                </div>

                <?php if (!empty($badges['progress'])): ?>
                <div class="badge-progress-list">
                    <?php foreach (array_slice($badges['progress'], 0, 4) as $prog): ?>
                    <div class="badge-progress-item">
                        <div class="badge-icon"><?= $prog['badge']['icon'] ?></div>
                        <div class="badge-info">
                            <div class="badge-name"><?= htmlspecialchars($prog['badge']['name']) ?></div>
                            <div class="badge-desc"><?= ucfirst($prog['badge']['msg'] ?? '') ?></div>
                            <div class="progress-mini">
                                <div class="progress-mini-fill" style="width: <?= $prog['percent'] ?>%"></div>
                            </div>
                            <div class="progress-label"><?= $prog['current'] ?> / <?= $prog['target'] ?> (<?= $prog['percent'] ?>%)</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state compact">
                    <?php if ($badges['total_earned'] >= $badges['total_available']): ?>
                    <div class="empty-icon">🎉</div>
                    <p>You've unlocked all available badges!</p>
                    <a href="<?= $basePath ?>/achievements/badges" class="cta-btn">View Collection <i class="fa-solid fa-arrow-right"></i></a>
                    <?php else: ?>
                    <div class="empty-icon">🎯</div>
                    <p>Keep participating to unlock your next badge!</p>
                    <a href="<?= $basePath ?>/achievements/challenges" class="cta-btn">View Challenges <i class="fa-solid fa-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <a href="<?= $basePath ?>/achievements/badges" class="view-all-link">
                    View All Badges <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <!-- Stats Summary Card -->
            <div class="achievement-card">
                <div class="card-header">
                    <span class="icon">📊</span>
                    <h3>Activity Stats</h3>
                </div>

                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fa-solid fa-hands-helping"></i></div>
                        <div class="stat-value"><?= number_format($stats['vol'] ?? 0) ?></div>
                        <div class="stat-label">Volunteer Hours</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
                        <div class="stat-value"><?= number_format($stats['earn'] ?? 0) ?></div>
                        <div class="stat-label">Credits Earned</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fa-solid fa-shopping-cart"></i></div>
                        <div class="stat-value"><?= number_format($stats['spend'] ?? 0) ?></div>
                        <div class="stat-label">Credits Spent</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fa-solid fa-exchange-alt"></i></div>
                        <div class="stat-value"><?= number_format($stats['transaction'] ?? 0) ?></div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fa-solid fa-user-friends"></i></div>
                        <div class="stat-value"><?= number_format($stats['connection'] ?? 0) ?></div>
                        <div class="stat-label">Connections</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
                        <div class="stat-value"><?= number_format($stats['review_given'] ?? 0) ?></div>
                        <div class="stat-label">Reviews</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="achievements-column-side">

            <!-- Streaks Card -->
            <div class="achievement-card streaks-card">
                <div class="card-header">
                    <span class="icon">🔥</span>
                    <h3>Streaks</h3>
                </div>

                <div class="streaks-list">
                    <?php
                    $streakTypes = [
                        'login' => ['icon' => '📅', 'label' => 'Login', 'color' => '#6366f1'],
                        'activity' => ['icon' => '⚡', 'label' => 'Activity', 'color' => '#f59e0b'],
                        'giving' => ['icon' => '🎁', 'label' => 'Giving', 'color' => '#10b981'],
                        'volunteer' => ['icon' => '🤝', 'label' => 'Volunteer', 'color' => '#ec4899'],
                    ];
                    foreach ($streakTypes as $type => $info):
                        $streak = $streaks[$type] ?? ['current' => 0, 'longest' => 0];
                        $isActive = $streak['current'] > 0;
                    ?>
                    <div class="streak-row <?= $isActive ? 'active' : '' ?>">
                        <div class="streak-icon-wrap" style="--streak-color: <?= $info['color'] ?>">
                            <?= $info['icon'] ?>
                        </div>
                        <div class="streak-details">
                            <div class="streak-name"><?= $info['label'] ?></div>
                            <div class="streak-meta">Best: <?= $streak['longest'] ?> days</div>
                        </div>
                        <div class="streak-value <?= $isActive ? 'active' : '' ?>">
                            <?= $streak['current'] ?>
                            <span>days</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Rankings Card -->
            <div class="achievement-card rankings-card">
                <div class="card-header">
                    <span class="icon">🏆</span>
                    <h3>Your Rankings</h3>
                </div>

                <div class="rankings-list">
                    <?php
                    $rankData = [
                        'xp' => ['icon' => 'fa-bolt', 'label' => 'XP', 'color' => '#6366f1'],
                        'badges' => ['icon' => 'fa-medal', 'label' => 'Badges', 'color' => '#f59e0b'],
                        'vol_hours' => ['icon' => 'fa-hands-helping', 'label' => 'Volunteer', 'color' => '#10b981'],
                        'credits_earned' => ['icon' => 'fa-coins', 'label' => 'Earnings', 'color' => '#ec4899'],
                    ];
                    foreach ($rankData as $key => $info):
                        $rank = $rankings[$key] ?? '-';
                    ?>
                    <div class="rank-row">
                        <div class="rank-icon-wrap" style="--rank-color: <?= $info['color'] ?>">
                            <i class="fa-solid <?= $info['icon'] ?>"></i>
                        </div>
                        <div class="rank-label"><?= $info['label'] ?></div>
                        <div class="rank-value">#<?= $rank ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <a href="<?= $basePath ?>/leaderboard" class="view-all-link">
                    View Leaderboards <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <!-- Earned Badges Card -->
            <div class="achievement-card">
                <div class="card-header">
                    <span class="icon">🏅</span>
                    <h3>Recent Badges</h3>
                    <span class="badge-count"><?= $badges['total_earned'] ?></span>
                </div>

                <?php if (!empty($badges['earned'])): ?>
                <div class="badges-showcase">
                    <?php foreach (array_slice($badges['earned'], 0, 6) as $badge):
                        $rarity = $badge['rarity'] ?? 'common';
                        $rarityPercent = match($rarity) {
                            'legendary' => 1,
                            'epic' => 5,
                            'rare' => 15,
                            'uncommon' => 35,
                            default => 60
                        };
                    ?>
                    <div class="badge-showcase-item rarity-<?= $rarity ?>"
                         onclick="openBadgeModal(this)"
                         data-badge-name="<?= htmlspecialchars($badge['name']) ?>"
                         data-badge-icon="<?= htmlspecialchars($badge['icon']) ?>"
                         data-badge-desc="<?= htmlspecialchars($badge['description'] ?? 'Earning this achievement') ?>"
                         data-badge-date="<?= date('F j, Y', strtotime($badge['awarded_at'])) ?>"
                         data-badge-rarity="<?= ucfirst($rarity) ?>"
                         data-badge-percent="<?= $rarityPercent ?>"
                         tabindex="0"
                         role="button"
                         aria-label="View <?= htmlspecialchars($badge['name']) ?>">
                        <span class="badge-emoji"><?= $badge['icon'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($badges['earned']) > 6): ?>
                <a href="<?= $basePath ?>/achievements/badges" class="view-all-link">
                    +<?= count($badges['earned']) - 6 ?> more <i class="fa-solid fa-arrow-right"></i>
                </a>
                <?php endif; ?>
                <?php else: ?>
                <div class="empty-state compact">
                    <div class="empty-icon">🎖️</div>
                    <p>Earn your first badge!</p>
                    <a href="<?= $basePath ?>/achievements/challenges" class="cta-btn">Start a Challenge <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent XP Card -->
            <div class="achievement-card">
                <div class="card-header">
                    <span class="icon">📈</span>
                    <h3>Recent Activity</h3>
                </div>

                <?php if (!empty($recentXP)): ?>
                <div class="activity-feed">
                    <?php foreach (array_slice($recentXP, 0, 5) as $log): ?>
                    <div class="activity-item">
                        <div class="activity-dot"></div>
                        <div class="activity-content">
                            <div class="activity-text"><?= htmlspecialchars($log['description'] ?: ucwords(str_replace('_', ' ', $log['action']))) ?></div>
                            <div class="activity-time"><?= date('M j, g:i a', strtotime($log['created_at'])) ?></div>
                        </div>
                        <div class="activity-xp">+<?= $log['xp_amount'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state compact">
                    <div class="empty-icon">📈</div>
                    <p>No recent activity</p>
                    <a href="<?= $basePath ?>/listings" class="cta-btn">Browse Listings <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Badge Detail Modal/Drawer -->
<div id="badgeModal" class="badge-modal-overlay" onclick="closeBadgeModalOnBackdrop(event)">
    <div class="badge-modal-content">
        <div class="badge-modal-handle"></div>
        <div class="badge-modal-header" id="badgeModalHeader">
            <button type="button" class="badge-modal-close" onclick="closeBadgeModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="badge-modal-icon" id="badgeModalIcon"></div>
            <h3 class="badge-modal-name" id="badgeModalName"></h3>
            <div class="badge-rarity-tag" id="badgeModalRarity"></div>
        </div>
        <div class="badge-modal-body">
            <div class="badge-modal-section">
                <div class="badge-modal-label">
                    <i class="fa-solid fa-trophy"></i> Achievement Unlocked For
                </div>
                <div class="badge-modal-text description" id="badgeModalDesc"></div>
            </div>
            <div class="badge-modal-section">
                <div class="badge-modal-label">
                    <i class="fa-solid fa-calendar"></i> Awarded On
                </div>
                <div class="badge-modal-text" id="badgeModalDate"></div>
            </div>
            <div class="badge-modal-section">
                <div class="badge-modal-label">
                    <i class="fa-solid fa-gem"></i> Rarity
                </div>
                <div class="badge-modal-text" id="badgeModalRarityText"></div>
                <div class="badge-rarity-bar">
                    <div class="badge-rarity-fill" id="badgeModalRarityBar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Badge Modal Functions
function openBadgeModal(element) {
    const modal = document.getElementById('badgeModal');
    const icon = document.getElementById('badgeModalIcon');
    const name = document.getElementById('badgeModalName');
    const desc = document.getElementById('badgeModalDesc');
    const date = document.getElementById('badgeModalDate');
    const rarityTag = document.getElementById('badgeModalRarity');
    const rarityText = document.getElementById('badgeModalRarityText');
    const rarityBar = document.getElementById('badgeModalRarityBar');

    const badgeName = element.dataset.badgeName || 'Badge';
    const badgeIcon = element.dataset.badgeIcon || '🏆';
    const badgeDesc = element.dataset.badgeDesc || 'earning this achievement';
    const badgeDate = element.dataset.badgeDate || 'Unknown';
    const badgeRarity = element.dataset.badgeRarity || 'Common';
    const badgePercent = parseFloat(element.dataset.badgePercent) || 100;

    icon.textContent = badgeIcon;
    name.textContent = badgeName;
    desc.textContent = badgeDesc.charAt(0).toUpperCase() + badgeDesc.slice(1);
    date.textContent = badgeDate;

    const rarityLower = badgeRarity.toLowerCase();
    rarityTag.className = 'badge-rarity-tag ' + rarityLower;
    rarityTag.innerHTML = getRarityIcon(rarityLower) + ' ' + badgeRarity;

    if (badgePercent <= 1) {
        rarityText.textContent = `Only ${badgePercent.toFixed(1)}% of members have this badge`;
    } else if (badgePercent <= 5) {
        rarityText.textContent = `Top ${badgePercent.toFixed(1)}% of members`;
    } else if (badgePercent <= 15) {
        rarityText.textContent = `${badgePercent.toFixed(1)}% of members have earned this`;
    } else if (badgePercent <= 40) {
        rarityText.textContent = `Earned by ${badgePercent.toFixed(0)}% of active members`;
    } else {
        rarityText.textContent = `A common achievement (${badgePercent.toFixed(0)}% have it)`;
    }

    rarityBar.className = 'badge-rarity-fill ' + rarityLower;
    rarityBar.style.width = '0%';

    modal.classList.add('visible');
    document.body.style.overflow = 'hidden';

    const navbar = document.querySelector('.nexus-navbar');
    if (navbar) navbar.style.display = 'none';
    const mobileTabBar = document.querySelector('.mobile-tab-bar');
    if (mobileTabBar) mobileTabBar.style.display = 'none';

    setTimeout(() => {
        const fillWidth = Math.max(5, 100 - badgePercent);
        rarityBar.style.width = fillWidth + '%';
    }, 100);

    if (navigator.vibrate) navigator.vibrate(10);
}

function getRarityIcon(rarity) {
    switch(rarity) {
        case 'legendary': return '<i class="fa-solid fa-crown"></i>';
        case 'epic': return '<i class="fa-solid fa-gem"></i>';
        case 'rare': return '<i class="fa-solid fa-star"></i>';
        case 'uncommon': return '<i class="fa-solid fa-circle-up"></i>';
        default: return '<i class="fa-solid fa-circle"></i>';
    }
}

function closeBadgeModal() {
    const modal = document.getElementById('badgeModal');
    const content = modal.querySelector('.badge-modal-content');

    const navbar = document.querySelector('.nexus-navbar');
    if (navbar) navbar.style.display = '';
    const mobileTabBar = document.querySelector('.mobile-tab-bar');
    if (mobileTabBar) mobileTabBar.style.display = '';

    if (window.innerWidth <= 640) {
        content.classList.add('closing');
        setTimeout(() => {
            modal.classList.remove('visible');
            content.classList.remove('closing');
            document.body.style.overflow = '';
        }, 200);
    } else {
        modal.classList.remove('visible');
        document.body.style.overflow = '';
    }
}

function closeBadgeModalOnBackdrop(event) {
    if (event.target === event.currentTarget) closeBadgeModal();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const badgeModal = document.getElementById('badgeModal');
        if (badgeModal && badgeModal.classList.contains('visible')) closeBadgeModal();
    }
});

document.querySelectorAll('.badge-showcase-item').forEach(badge => {
    badge.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openBadgeModal(this);
        }
    });
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
