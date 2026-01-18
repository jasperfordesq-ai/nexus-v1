<?php
$hTitle = 'My Achievements';
$hSubtitle = 'Track your progress and unlock rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Achievements styles are defined inline below - no external CSS needed
$cssVersion = time();

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

// Due to EXTR_SKIP in View::render(), $data remains the full array passed to render()
// The dashboard data is nested under $data['data'] key
$dashboardData = $data['data'] ?? $data;
$xp = $dashboardData['xp'] ?? ['total' => 0, 'level' => 1, 'progress' => 0, 'xp_for_next' => 100, 'xp_in_level' => 0];
$badges = $dashboardData['badges'] ?? ['earned' => [], 'total_earned' => 0, 'total_available' => 0, 'progress' => []];
$streaks = $dashboardData['streaks'] ?? [];
$rankings = $dashboardData['rankings'] ?? [];
$stats = $dashboardData['stats'] ?? [];
$recentXP = $dashboardData['recent_xp'] ?? [];
?>

<div class="achievements-wrapper">
    <div class="achievements-grid">

        <!-- Level & XP Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon">⭐</span>
                <h3>Level & Experience</h3>
            </div>

            <div class="level-display">
                <div class="level-circle">
                    <span class="level-num"><?= $xp['level'] ?? 1 ?></span>
                    <span class="level-label">Level</span>
                </div>
                <div class="level-info">
                    <h4>Experience Points</h4>
                    <div class="xp-display">
                        <?= number_format($xp['total'] ?? 0) ?> <span>XP</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?= $xp['progress'] ?? 0 ?>%"></div>
                    </div>
                    <div class="progress-text">
                        <?php $currentLevel = $xp['level'] ?? 1; ?>
                        <?php if ($currentLevel < 10): ?>
                            <?= number_format($xp['xp_in_level'] ?? 0) ?> / <?= number_format(($xp['xp_for_next'] ?? 100) - ($levelThresholds[$currentLevel] ?? 0)) ?> XP to Level <?= $currentLevel + 1 ?>
                        <?php else: ?>
                            Maximum Level Reached!
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Streaks Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon">🔥</span>
                <h3>Streaks</h3>
            </div>

            <div class="streaks-grid">
                <?php
                $streakTypes = [
                    'login' => ['icon' => '📅', 'label' => 'Login'],
                    'activity' => ['icon' => '⚡', 'label' => 'Activity'],
                    'giving' => ['icon' => '🎁', 'label' => 'Giving'],
                    'volunteer' => ['icon' => '🤝', 'label' => 'Volunteer'],
                ];
                foreach ($streakTypes as $type => $info):
                    $streak = $streaks[$type] ?? ['current' => 0, 'longest' => 0];
                ?>
                <div class="streak-item">
                    <div class="streak-icon"><?= $info['icon'] ?></div>
                    <div class="streak-count"><?= $streak['current'] ?></div>
                    <div class="streak-label"><?= $info['label'] ?> Streak</div>
                    <div class="streak-best">Best: <?= $streak['longest'] ?> days</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Rankings Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon">🏆</span>
                <h3>Leaderboard Rankings</h3>
            </div>

            <div class="rankings-grid">
                <?php
                $rankLabels = [
                    'xp' => 'XP Rank',
                    'badges' => 'Badges Rank',
                    'vol_hours' => 'Volunteer Rank',
                    'credits_earned' => 'Earner Rank',
                ];
                foreach ($rankLabels as $key => $label):
                    $rank = $rankings[$key] ?? '-';
                ?>
                <div class="rank-item">
                    <div class="rank-position">#<?= $rank ?></div>
                    <div class="rank-label"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <a href="<?= $basePath ?>/leaderboard" class="view-all-link">
                View Leaderboards <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <!-- Badge Progress Card -->
        <div class="achievement-card two-thirds">
            <div class="card-header">
                <span class="icon">🎯</span>
                <h3>Next Badges to Unlock</h3>
            </div>

            <?php if (!empty($badges['progress'])): ?>
            <div class="badge-progress-list">
                <?php foreach ($badges['progress'] as $prog): ?>
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
            <?php if ($badges['total_earned'] >= $badges['total_available']): ?>
            <p style="color: #10b981; text-align: center; padding: 20px;">You've unlocked all available badges! Amazing!</p>
            <?php else: ?>
            <p style="color: #6b7280; text-align: center; padding: 20px;">Keep participating to unlock your next badge!</p>
            <?php endif; ?>
            <?php endif; ?>

            <a href="<?= $basePath ?>/achievements/badges" class="view-all-link">
                View All Badges <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <!-- Earned Badges Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon">🏅</span>
                <h3>Earned Badges (<?= $badges['total_earned'] ?>)</h3>
            </div>

            <?php if (!empty($badges['earned'])): ?>
            <div class="badges-earned-grid">
                <?php foreach (array_slice($badges['earned'], 0, 8) as $badge):
                    // Determine rarity based on badge type or default to common
                    $rarity = $badge['rarity'] ?? 'common';
                    $rarityPercent = match($rarity) {
                        'legendary' => 1,
                        'epic' => 5,
                        'rare' => 15,
                        'uncommon' => 35,
                        default => 60
                    };
                ?>
                <div class="badge-earned"
                     onclick="openBadgeModal(this)"
                     data-badge-name="<?= htmlspecialchars($badge['name']) ?>"
                     data-badge-icon="<?= htmlspecialchars($badge['icon']) ?>"
                     data-badge-desc="<?= htmlspecialchars($badge['description'] ?? 'Earning this achievement') ?>"
                     data-badge-date="<?= date('F j, Y', strtotime($badge['awarded_at'])) ?>"
                     data-badge-rarity="<?= ucfirst($rarity) ?>"
                     data-badge-percent="<?= $rarityPercent ?>"
                     tabindex="0"
                     role="button"
                     aria-label="View details for <?= htmlspecialchars($badge['name']) ?> badge">
                    <span class="badge-icon"><?= $badge['icon'] ?></span>
                    <span class="badge-name"><?= htmlspecialchars($badge['name']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($badges['earned']) > 8): ?>
            <a href="<?= $basePath ?>/achievements/badges" class="view-all-link">
                +<?= count($badges['earned']) - 8 ?> more badges <i class="fa-solid fa-arrow-right"></i>
            </a>
            <?php endif; ?>
            <?php else: ?>
            <p style="color: #6b7280; text-align: center; padding: 20px;">Start participating to earn your first badge!</p>
            <?php endif; ?>
        </div>

        <!-- Recent XP Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon">📈</span>
                <h3>Recent XP Activity</h3>
            </div>

            <?php if (!empty($recentXP)): ?>
            <div class="xp-log">
                <?php foreach ($recentXP as $log): ?>
                <div class="xp-log-item">
                    <div>
                        <div class="xp-action"><?= htmlspecialchars($log['description'] ?: ucwords(str_replace('_', ' ', $log['action']))) ?></div>
                        <div class="xp-date"><?= date('M j, g:i a', strtotime($log['created_at'])) ?></div>
                    </div>
                    <div class="xp-amount">+<?= $log['xp_amount'] ?> XP</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color: #6b7280; text-align: center; padding: 20px;">No XP activity yet. Start participating!</p>
            <?php endif; ?>
        </div>

        <!-- Stats Summary Card -->
        <div class="achievement-card full-width">
            <div class="card-header">
                <span class="icon">📊</span>
                <h3>Your Activity Stats</h3>
            </div>

            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['vol'] ?? 0) ?></div>
                    <div class="stat-label">Volunteer Hours</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['earn'] ?? 0) ?></div>
                    <div class="stat-label">Credits Earned</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['spend'] ?? 0) ?></div>
                    <div class="stat-label">Credits Spent</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['transaction'] ?? 0) ?></div>
                    <div class="stat-label">Transactions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['connection'] ?? 0) ?></div>
                    <div class="stat-label">Connections</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['review_given'] ?? 0) ?></div>
                    <div class="stat-label">Reviews Given</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['event_attend'] ?? 0) ?></div>
                    <div class="stat-label">Events Attended</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['post'] ?? 0) ?></div>
                    <div class="stat-label">Posts Created</div>
                </div>
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
    const header = document.getElementById('badgeModalHeader');
    const icon = document.getElementById('badgeModalIcon');
    const name = document.getElementById('badgeModalName');
    const desc = document.getElementById('badgeModalDesc');
    const date = document.getElementById('badgeModalDate');
    const rarityTag = document.getElementById('badgeModalRarity');
    const rarityText = document.getElementById('badgeModalRarityText');
    const rarityBar = document.getElementById('badgeModalRarityBar');

    // Get data from clicked element
    const badgeName = element.dataset.badgeName || 'Badge';
    const badgeIcon = element.dataset.badgeIcon || '🏆';
    const badgeDesc = element.dataset.badgeDesc || 'earning this achievement';
    const badgeDate = element.dataset.badgeDate || 'Unknown';
    const badgeRarity = element.dataset.badgeRarity || 'Common';
    const badgePercent = parseFloat(element.dataset.badgePercent) || 100;

    // Populate modal
    icon.textContent = badgeIcon;
    name.textContent = badgeName;
    desc.textContent = badgeDesc.charAt(0).toUpperCase() + badgeDesc.slice(1);
    date.textContent = badgeDate;

    // Set rarity tag
    const rarityLower = badgeRarity.toLowerCase();
    rarityTag.className = 'badge-rarity-tag ' + rarityLower;
    rarityTag.innerHTML = getRarityIcon(rarityLower) + ' ' + badgeRarity;

    // Set rarity text
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

    // Set rarity bar
    rarityBar.className = 'badge-rarity-fill ' + rarityLower;
    rarityBar.style.width = '0%';

    // Show modal and hide navbar
    modal.classList.add('visible');
    document.body.style.overflow = 'hidden';

    // Hide navbar and bottom tab bar while drawer is open
    const navbar = document.querySelector('.nexus-navbar');
    if (navbar) {
        navbar.style.display = 'none';
    }
    const mobileTabBar = document.querySelector('.mobile-tab-bar');
    if (mobileTabBar) {
        mobileTabBar.style.display = 'none';
    }

    // Animate rarity bar
    setTimeout(() => {
        // Invert percentage for visual (rarer = less fill = more impressive)
        const fillWidth = Math.max(5, 100 - badgePercent);
        rarityBar.style.width = fillWidth + '%';
    }, 100);

    // Haptic feedback on mobile
    if (navigator.vibrate) {
        navigator.vibrate(10);
    }
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

    // Restore navbar and bottom tab bar visibility
    const navbar = document.querySelector('.nexus-navbar');
    if (navbar) {
        navbar.style.display = '';
    }
    const mobileTabBar = document.querySelector('.mobile-tab-bar');
    if (mobileTabBar) {
        mobileTabBar.style.display = '';
    }

    // On mobile, animate drawer closing
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
    if (event.target === event.currentTarget) {
        closeBadgeModal();
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const badgeModal = document.getElementById('badgeModal');
        if (badgeModal && badgeModal.classList.contains('visible')) {
            closeBadgeModal();
        }
    }
});

// Handle keyboard activation for badges
document.querySelectorAll('.badge-earned').forEach(badge => {
    badge.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openBadgeModal(this);
        }
    });
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
