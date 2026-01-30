<?php
/**
 * CivicOne View: Achievements Dashboard
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'My Achievements';
$basePath = \Nexus\Core\TenantContext::getBasePath();

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

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

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Achievements']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-trophy govuk-!-margin-right-2" aria-hidden="true"></i>
            My Achievements
        </h1>
        <p class="govuk-body-l">Track your progress and unlock rewards.</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/achievements/badges" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-medal govuk-!-margin-right-1" aria-hidden="true"></i> View All Badges
        </a>
    </div>
</div>

<!-- Achievement Navigation -->
<nav class="govuk-!-margin-bottom-6" aria-label="Achievement sections">
    <ul class="govuk-list civicone-nav-button-list">
        <li><a href="<?= $basePath ?>/achievements" class="govuk-button" data-module="govuk-button">Dashboard</a></li>
        <li><a href="<?= $basePath ?>/achievements/badges" class="govuk-button govuk-button--secondary" data-module="govuk-button">All Badges</a></li>
        <li><a href="<?= $basePath ?>/achievements/challenges" class="govuk-button govuk-button--secondary" data-module="govuk-button">Challenges</a></li>
        <li><a href="<?= $basePath ?>/achievements/collections" class="govuk-button govuk-button--secondary" data-module="govuk-button">Collections</a></li>
        <li><a href="<?= $basePath ?>/achievements/shop" class="govuk-button govuk-button--secondary" data-module="govuk-button">XP Shop</a></li>
    </ul>
</nav>

<div class="achievements-wrapper">
    <div class="achievements-grid">

        <!-- Level & XP Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon" aria-hidden="true">⭐</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Level & Experience</h2>
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
                <span class="icon" aria-hidden="true">🔥</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Streaks</h2>
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
                <span class="icon" aria-hidden="true">🏆</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Leaderboard Rankings</h2>
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

            <a href="<?= $basePath ?>/leaderboard" class="govuk-link govuk-!-font-weight-bold">
                View Leaderboards <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <!-- Badge Progress Card -->
        <div class="achievement-card two-thirds">
            <div class="card-header">
                <span class="icon" aria-hidden="true">🎯</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Next Badges to Unlock</h2>
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
            <p class="govuk-body govuk-!-text-align-centre govuk-!-padding-5" class="civicone-text-success">You've unlocked all available badges! Amazing!</p>
            <?php else: ?>
            <p class="govuk-body govuk-!-text-align-centre govuk-!-padding-5" class="civicone-secondary-text">Keep participating to unlock your next badge!</p>
            <?php endif; ?>
            <?php endif; ?>

            <a href="<?= $basePath ?>/achievements/badges" class="govuk-link govuk-!-font-weight-bold">
                View All Badges <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <!-- Earned Badges Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon" aria-hidden="true">🏅</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Earned Badges (<?= $badges['total_earned'] ?>)</h2>
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
            <a href="<?= $basePath ?>/achievements/badges" class="govuk-link govuk-!-font-weight-bold">
                +<?= count($badges['earned']) - 8 ?> more badges <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
            <?php endif; ?>
            <?php else: ?>
            <p class="govuk-body govuk-!-text-align-centre govuk-!-padding-5" class="civicone-secondary-text">Start participating to earn your first badge!</p>
            <?php endif; ?>
        </div>

        <!-- Recent XP Card -->
        <div class="achievement-card">
            <div class="card-header">
                <span class="icon" aria-hidden="true">📈</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Recent XP Activity</h2>
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
            <p class="govuk-body govuk-!-text-align-centre govuk-!-padding-5" class="civicone-secondary-text">No XP activity yet. Start participating!</p>
            <?php endif; ?>
        </div>

        <!-- Stats Summary Card -->
        <div class="achievement-card full-width">
            <div class="card-header">
                <span class="icon" aria-hidden="true">📊</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Your Activity Stats</h2>
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

<!-- Badge modal JS moved to /assets/js/civicone-achievements.js (2026-01-19) -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
