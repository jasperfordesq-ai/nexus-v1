<?php
/**
 * Nexus Score Dashboard Page
 * Full page wrapper for user's personal Nexus Score dashboard
 *
 * @var array $scoreData - Score data from NexusScoreService
 * @var array $badges - User's earned badges
 * @var array $recentAchievements - Recent achievements
 * @var array $communityStats - Community statistics
 * @var array $milestones - User's milestones
 * @var array $leaderboardData - Leaderboard data
 * @var array|null $currentUserData - Current user's rank data
 * @var bool $isPublic - Whether this is public view
 */

$pageTitle = "My Nexus Score";
$currentPage = 'nexus-score';

// Include the main layout header
require_once __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="nexus-score-page">
    <header class="nexus-score-header">
        <h1>My Nexus Score</h1>
        <p>Track your community engagement, contributions, and achievements</p>
    </header>

    <!-- Score Dashboard Component -->
    <section class="govuk-margin-bottom-6" aria-label="Score overview">
        <?php require __DIR__ . '/../components/nexus-score-dashboard.php'; ?>
    </section>

    <!-- GOV.UK Tabs for Achievements and Leaderboard -->
    <div class="govuk-tabs" data-module="govuk-tabs">
        <ul class="govuk-tabs__list" role="tablist">
            <li class="govuk-tabs__list-item" role="presentation">
                <button class="govuk-tabs__tab active" id="achievements-tab" type="button" role="tab" aria-controls="achievements" aria-selected="true">
                    Achievements & Milestones
                </button>
            </li>
            <li class="govuk-tabs__list-item" role="presentation">
                <button class="govuk-tabs__tab" id="leaderboard-tab" type="button" role="tab" aria-controls="leaderboard" aria-selected="false">
                    Leaderboard
                </button>
            </li>
            <li class="govuk-tabs__list-item" role="presentation">
                <button class="govuk-tabs__tab" id="badges-tab" type="button" role="tab" aria-controls="badges" aria-selected="false">
                    My Badges
                </button>
            </li>
        </ul>

        <!-- Achievements Tab Panel -->
        <section class="govuk-tabs__panel active" id="achievements" role="tabpanel" aria-labelledby="achievements-tab">
            <div class="govuk-margin-bottom-6">
                <h2 class="govuk-heading-m">Recent Achievements</h2>
                <?php if (!empty($recentAchievements)): ?>
                    <ul class="govuk-summary-list" role="list">
                        <?php foreach ($recentAchievements as $achievement): ?>
                            <li class="govuk-summary-list__row" role="listitem">
                                <div class="govuk-summary-list__content govuk-flex govuk-flex--align-center govuk-flex--gap-3">
                                    <span class="achievement-icon" aria-hidden="true"><?php echo $achievement['icon'] ?? '🏆'; ?></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($achievement['name']); ?></strong>
                                        <small>Earned on <?php echo date('M j, Y', strtotime($achievement['date'])); ?></small>
                                    </div>
                                </div>
                                <span class="govuk-tag govuk-tag--light-blue">+<?php echo $achievement['points']; ?> pts</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="govuk-inset-text govuk-inset-text--info">
                        No recent achievements. Keep engaging with the community to unlock new achievements!
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h2 class="govuk-heading-m">Milestones</h2>
                <?php if (!empty($milestones)): ?>
                    <div class="govuk-card-grid govuk-card-grid--2">
                        <?php foreach ($milestones as $milestone): ?>
                            <article class="govuk-card">
                                <div class="govuk-flex govuk-flex--gap-3">
                                    <span class="milestone-icon-lg" aria-hidden="true"><?php echo $milestone['icon'] ?? '🎯'; ?></span>
                                    <div>
                                        <h3 class="govuk-card__title"><?php echo htmlspecialchars($milestone['name']); ?></h3>
                                        <p class="govuk-card__text"><?php echo htmlspecialchars($milestone['description']); ?></p>
                                        <span class="govuk-tag govuk-tag--green"><?php echo $milestone['date']; ?></span>
                                        <?php if (isset($milestone['reward'])): ?>
                                            <p class="govuk-card__meta">
                                                <strong>Reward:</strong> <?php echo htmlspecialchars($milestone['reward']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="govuk-inset-text govuk-inset-text--info">
                        No milestones achieved yet. Start your journey to unlock your first milestone!
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Leaderboard Tab Panel -->
        <section class="govuk-tabs__panel" id="leaderboard" role="tabpanel" aria-labelledby="leaderboard-tab">
            <h2 class="govuk-heading-m">Community Leaderboard</h2>
            <?php if ($currentUserData): ?>
                <div class="govuk-inset-text">
                    <strong>Your Rank:</strong> #<?php echo $currentUserData['rank']; ?>
                    out of <?php echo $currentUserData['total_users']; ?> members
                    with <?php echo number_format($currentUserData['score'], 1); ?> points
                </div>
            <?php endif; ?>

            <?php if (!empty($leaderboardData['top_users'])): ?>
                <div class="table-responsive">
                    <table class="govuk-table" aria-label="Community leaderboard">
                        <thead>
                            <tr>
                                <th scope="col">Rank</th>
                                <th scope="col">Member</th>
                                <th scope="col">Tier</th>
                                <th scope="col">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboardData['top_users'] as $index => $user):
                                $isCurrentUser = isset($_SESSION['user_id']) && $user['user_id'] == $_SESSION['user_id'];
                            ?>
                                <tr class="<?php echo $isCurrentUser ? 'govuk-table__row--highlight' : ''; ?>">
                                    <td>
                                        <?php if ($index < 3): ?>
                                            <span class="leaderboard-medal-icon" aria-label="<?php echo ['Gold', 'Silver', 'Bronze'][$index]; ?> medal">
                                                <?php echo ['🥇', '🥈', '🥉'][$index]; ?>
                                            </span>
                                        <?php else: ?>
                                            #<?php echo $index + 1; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="govuk-flex govuk-flex--align-center govuk-flex--gap-2">
                                            <?php if (!empty($user['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>"
                                                     alt=""
                                                     class="avatar-thumbnail">
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($user['name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="govuk-tag govuk-tag--tier" style="--tier-color: <?php echo htmlspecialchars($user['tier']['color'] ?? 'var(--color-primary-500)'); ?>">
                                            <span aria-hidden="true"><?php echo $user['tier']['icon'] ?? ''; ?></span>
                                            <?php echo htmlspecialchars($user['tier']['name'] ?? 'Novice'); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($user['score'], 1); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (isset($leaderboardData['community_average'])): ?>
                    <p class="govuk-margin-top-4">
                        <small>Community Average: <?php echo number_format($leaderboardData['community_average'], 1); ?> points</small>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <div class="govuk-inset-text govuk-inset-text--info">
                    No leaderboard data available yet.
                </div>
            <?php endif; ?>
        </section>

        <!-- Badges Tab Panel -->
        <section class="govuk-tabs__panel" id="badges" role="tabpanel" aria-labelledby="badges-tab">
            <h2 class="govuk-heading-m">My Badges</h2>
            <?php if (!empty($badges)): ?>
                <div class="govuk-card-grid govuk-card-grid--4">
                    <?php foreach ($badges as $badge): ?>
                        <article class="govuk-card govuk-card--text-center">
                            <div class="badge-icon-xl govuk-margin-bottom-4" aria-hidden="true">
                                <?php echo $badge['icon'] ?? '🏅'; ?>
                            </div>
                            <h3 class="govuk-card__title"><?php echo htmlspecialchars($badge['name']); ?></h3>
                            <?php if (isset($badge['description'])): ?>
                                <p class="govuk-card__text">
                                    <?php echo htmlspecialchars($badge['description']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (isset($badge['awarded_at'])): ?>
                                <p class="govuk-card__meta">
                                    <?php echo date('M j, Y', strtotime($badge['awarded_at'])); ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="govuk-inset-text govuk-inset-text--info">
                    No badges earned yet. Participate in the community to unlock badges!
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- GOV.UK Tabs JavaScript -->
<script>
(function() {
    var tabs = document.querySelectorAll('.govuk-tabs__tab');
    var panels = document.querySelectorAll('.govuk-tabs__panel');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            // Deactivate all tabs
            tabs.forEach(function(t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });

            // Hide all panels
            panels.forEach(function(p) {
                p.classList.remove('active');
            });

            // Activate clicked tab
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');

            // Show corresponding panel
            var panelId = this.getAttribute('aria-controls');
            var panel = document.getElementById(panelId);
            if (panel) {
                panel.classList.add('active');
            }
        });

        // Keyboard navigation
        tab.addEventListener('keydown', function(e) {
            var tabArray = Array.from(tabs);
            var currentIndex = tabArray.indexOf(this);
            var newIndex = currentIndex;

            switch(e.key) {
                case 'ArrowLeft':
                    newIndex = currentIndex > 0 ? currentIndex - 1 : tabArray.length - 1;
                    e.preventDefault();
                    break;
                case 'ArrowRight':
                    newIndex = currentIndex < tabArray.length - 1 ? currentIndex + 1 : 0;
                    e.preventDefault();
                    break;
                case 'Home':
                    newIndex = 0;
                    e.preventDefault();
                    break;
                case 'End':
                    newIndex = tabArray.length - 1;
                    e.preventDefault();
                    break;
            }

            if (newIndex !== currentIndex) {
                tabArray[newIndex].focus();
                tabArray[newIndex].click();
            }
        });
    });
})();
</script>

<!-- Nexus Score Dashboard Page CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-dashboard-nexus-score.min.css">
