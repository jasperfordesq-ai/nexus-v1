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

/**
 * Generate initials from a name
 */
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

// Include the main layout header
require_once __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Holographic Background -->
<div class="nexus-score-page-bg"></div>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 nexus-score-page-header">
            <h1>My Nexus Score</h1>
            <p>Track your community engagement, contributions, and achievements</p>
        </div>
    </div>

    <!-- Score Dashboard Component -->
    <div class="row mb-4">
        <div class="col-12">
            <?php require __DIR__ . '/../components/nexus-score-dashboard.php'; ?>
        </div>
    </div>

    <!-- Tabs for Achievements and Leaderboard -->
    <div class="row">
        <div class="col-12 nexus-score-tabs">
            <ul class="nav nav-tabs mb-4" id="scoreTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="achievements-tab" data-bs-toggle="tab" data-bs-target="#achievements" type="button" role="tab" aria-controls="achievements" aria-selected="true">
                        <span class="tab-icon">🏆</span>Achievements
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="leaderboard-tab" data-bs-toggle="tab" data-bs-target="#leaderboard" type="button" role="tab" aria-controls="leaderboard" aria-selected="false">
                        <span class="tab-icon">📊</span>Leaderboard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="badges-tab" data-bs-toggle="tab" data-bs-target="#badges" type="button" role="tab" aria-controls="badges" aria-selected="false">
                        <span class="tab-icon">🏅</span>My Badges
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="scoreTabsContent">
                <!-- Achievements Tab -->
                <div class="tab-pane fade show active" id="achievements" role="tabpanel" aria-labelledby="achievements-tab">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <h3 class="mb-3">Recent Achievements</h3>
                            <?php if (!empty($recentAchievements)): ?>
                                <div class="achievement-grid">
                                    <?php foreach ($recentAchievements as $achievement): ?>
                                        <div class="achievement-card">
                                            <div class="achievement-card-icon"><?php echo $achievement['icon'] ?? '🏆'; ?></div>
                                            <div class="achievement-card-content">
                                                <div class="achievement-card-name"><?php echo htmlspecialchars($achievement['name']); ?></div>
                                                <div class="achievement-card-date">
                                                    Earned on <?php echo date('M j, Y', strtotime($achievement['date'])); ?>
                                                </div>
                                            </div>
                                            <div class="achievement-card-points">+<?php echo $achievement['points']; ?> pts</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="nexus-score-empty">
                                    <span class="empty-state-icon">🏆</span>
                                    <h4 class="empty-state-title">No Achievements Yet</h4>
                                    <p>Your first achievement is waiting! Engage with the community to start unlocking rewards.</p>
                                    <a href="/community" class="empty-state-cta">
                                        Explore Community
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <h3 class="mb-3">Milestones</h3>
                            <?php if (!empty($milestones)): ?>
                                <div class="milestone-grid">
                                    <?php foreach ($milestones as $milestone): ?>
                                        <div class="milestone-item">
                                            <div class="d-flex align-items-start mb-2">
                                                <span class="milestone-item-icon"><?php echo $milestone['icon'] ?? '🎯'; ?></span>
                                                <div>
                                                    <div class="milestone-item-title"><?php echo htmlspecialchars($milestone['name']); ?></div>
                                                    <p class="milestone-item-desc">
                                                        <?php echo htmlspecialchars($milestone['description']); ?>
                                                    </p>
                                                    <span class="badge bg-success"><?php echo $milestone['date']; ?></span>
                                                </div>
                                            </div>
                                            <?php if (isset($milestone['reward'])): ?>
                                                <div class="milestone-item-reward">
                                                    <strong>Reward:</strong> <?php echo htmlspecialchars($milestone['reward']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="nexus-score-empty">
                                    <span class="empty-state-icon">🎯</span>
                                    <h4 class="empty-state-title">Milestones Await</h4>
                                    <p>Complete activities to reach your first milestone and earn exclusive rewards!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Tab -->
                <div class="tab-pane fade" id="leaderboard" role="tabpanel" aria-labelledby="leaderboard-tab">
                    <div class="row">
                        <div class="col-12">
                            <h3 class="mb-3">Community Leaderboard</h3>
                            <?php if ($currentUserData): ?>
                                <div class="your-rank-alert alert mb-4">
                                    <strong>Your Rank:</strong> #<?php echo $currentUserData['rank']; ?>
                                    out of <?php echo $currentUserData['total_users']; ?> members
                                    with <?php echo number_format($currentUserData['score'], 1); ?> points
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($leaderboardData['top_users'])): ?>
                                <?php
                                // Extract top 3 for podium
                                $topThree = array_slice($leaderboardData['top_users'], 0, 3);
                                $remaining = array_slice($leaderboardData['top_users'], 3);
                                ?>

                                <!-- Podium for Top 3 -->
                                <?php if (count($topThree) >= 3): ?>
                                <div class="leaderboard-podium">
                                    <?php
                                    $podiumOrder = [1, 0, 2]; // 2nd, 1st, 3rd for visual layout
                                    $placeClasses = ['first', 'second', 'third'];
                                    foreach ($podiumOrder as $pos):
                                        $user = $topThree[$pos];
                                        $tierData = is_array($user['tier'] ?? null)
                                            ? $user['tier']
                                            : ['name' => $user['tier'] ?? 'Novice', 'color' => '#6366f1', 'icon' => '🎯'];
                                    ?>
                                    <div class="podium-place <?php echo $placeClasses[$pos]; ?>">
                                        <div class="podium-avatar-container">
                                            <?php if ($pos === 0): ?>
                                                <span class="podium-crown">👑</span>
                                            <?php endif; ?>
                                            <?php if (!empty($user['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="podium-avatar">
                                            <?php else: ?>
                                                <div class="podium-avatar-placeholder"><?php echo getInitials($user['name']); ?></div>
                                            <?php endif; ?>
                                            <span class="podium-rank"><?php echo $pos + 1; ?></span>
                                        </div>
                                        <div class="podium-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <div class="podium-score"><?php echo number_format($user['score'], 1); ?></div>
                                        <div class="podium-tier">
                                            <span><?php echo $tierData['icon'] ?? ''; ?></span>
                                            <span><?php echo $tierData['name'] ?? 'Novice'; ?></span>
                                        </div>
                                        <div class="podium-base"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Table for remaining users (4th place onwards) -->
                                <?php if (!empty($remaining)): ?>
                                <div class="leaderboard-table">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Member</th>
                                                    <th>Tier</th>
                                                    <th>Score</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($remaining as $index => $user): ?>
                                                    <?php $actualRank = $index + 4; ?>
                                                    <tr class="<?php echo isset($_SESSION['user_id']) && $user['user_id'] == $_SESSION['user_id'] ? 'table-primary' : ''; ?>">
                                                        <td>
                                                            <span class="leaderboard-rank-badge">#<?php echo $actualRank; ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($user['avatar_url'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>"
                                                                         alt="Avatar"
                                                                         class="leaderboard-user-avatar">
                                                                <?php else: ?>
                                                                    <div class="avatar-placeholder">
                                                                        <?php echo getInitials($user['name']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <span><?php echo htmlspecialchars($user['name']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $tierData = is_array($user['tier'] ?? null)
                                                                ? $user['tier']
                                                                : ['name' => $user['tier'] ?? 'Novice', 'color' => '#6366f1', 'icon' => '🎯'];
                                                            ?>
                                                            <span class="leaderboard-tier-badge" style="background-color: <?php echo $tierData['color'] ?? '#6366f1'; ?>20; color: <?php echo $tierData['color'] ?? '#6366f1'; ?>;">
                                                                <?php echo $tierData['icon'] ?? ''; ?>
                                                                <?php echo $tierData['name'] ?? 'Novice'; ?>
                                                            </span>
                                                        </td>
                                                        <td><strong><?php echo number_format($user['score'], 1); ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php elseif (count($topThree) < 3): ?>
                                <!-- Show table if less than 3 users (no podium) -->
                                <div class="leaderboard-table">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Member</th>
                                                    <th>Tier</th>
                                                    <th>Score</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leaderboardData['top_users'] as $index => $user): ?>
                                                    <tr class="<?php echo isset($_SESSION['user_id']) && $user['user_id'] == $_SESSION['user_id'] ? 'table-primary' : ''; ?>">
                                                        <td>
                                                            <span class="leaderboard-rank-badge">
                                                                <?php if ($index < 3): ?>
                                                                    <?php echo ['🥇', '🥈', '🥉'][$index]; ?>
                                                                <?php else: ?>
                                                                    #<?php echo $index + 1; ?>
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($user['avatar_url'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>"
                                                                         alt="Avatar"
                                                                         class="leaderboard-user-avatar">
                                                                <?php else: ?>
                                                                    <div class="avatar-placeholder">
                                                                        <?php echo getInitials($user['name']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <span><?php echo htmlspecialchars($user['name']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $tierData = is_array($user['tier'] ?? null)
                                                                ? $user['tier']
                                                                : ['name' => $user['tier'] ?? 'Novice', 'color' => '#6366f1', 'icon' => '🎯'];
                                                            ?>
                                                            <span class="leaderboard-tier-badge" style="background-color: <?php echo $tierData['color'] ?? '#6366f1'; ?>20; color: <?php echo $tierData['color'] ?? '#6366f1'; ?>;">
                                                                <?php echo $tierData['icon'] ?? ''; ?>
                                                                <?php echo $tierData['name'] ?? 'Novice'; ?>
                                                            </span>
                                                        </td>
                                                        <td><strong><?php echo number_format($user['score'], 1); ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (isset($leaderboardData['community_average'])): ?>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            Community Average: <?php echo number_format($leaderboardData['community_average'], 1); ?> points
                                        </small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="nexus-score-empty">
                                    <span class="empty-state-icon">📊</span>
                                    <h4 class="empty-state-title">Leaderboard Coming Soon</h4>
                                    <p>Be among the first to climb the ranks! Start earning points to appear on the leaderboard.</p>
                                    <a href="/offers" class="empty-state-cta">
                                        Browse Offers
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Badges Tab -->
                <div class="tab-pane fade" id="badges" role="tabpanel" aria-labelledby="badges-tab">
                    <div class="row">
                        <div class="col-12">
                            <h3 class="mb-3">My Badges</h3>
                            <?php if (!empty($badges)): ?>
                                <div class="badge-grid">
                                    <?php foreach ($badges as $badge): ?>
                                        <div class="badge-item">
                                            <div class="badge-item-icon">
                                                <?php echo $badge['icon'] ?? '🏅'; ?>
                                            </div>
                                            <div class="badge-item-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                                            <?php if (isset($badge['description'])): ?>
                                                <p class="badge-item-desc">
                                                    <?php echo htmlspecialchars($badge['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (isset($badge['awarded_at'])): ?>
                                                <div class="badge-item-date">
                                                    <?php echo date('M j, Y', strtotime($badge['awarded_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="nexus-score-empty">
                                    <span class="empty-state-icon">🏅</span>
                                    <h4 class="empty-state-title">Your Badge Collection</h4>
                                    <p>Badges showcase your accomplishments. Complete challenges and milestones to earn your first badge!</p>
                                    <a href="/achievements" class="empty-state-cta">
                                        View All Badges
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include the main layout footer
if (file_exists(__DIR__ . '/../../layouts/modern/footer.php')) {
    require_once __DIR__ . '/../../layouts/modern/footer.php';
}
?>
