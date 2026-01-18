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
require_once __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-2">My Nexus Score</h1>
            <p class="text-muted">Track your community engagement, contributions, and achievements</p>
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
        <div class="col-12">
            <ul class="nav nav-tabs mb-4" id="scoreTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="achievements-tab" data-bs-toggle="tab" data-bs-target="#achievements" type="button" role="tab" aria-controls="achievements" aria-selected="true">
                        Achievements & Milestones
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="leaderboard-tab" data-bs-toggle="tab" data-bs-target="#leaderboard" type="button" role="tab" aria-controls="leaderboard" aria-selected="false">
                        Leaderboard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="badges-tab" data-bs-toggle="tab" data-bs-target="#badges" type="button" role="tab" aria-controls="badges" aria-selected="false">
                        My Badges
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="scoreTabsContent">
                <!-- Achievements Tab -->
                <div class="tab-pane fade show active" id="achievements" role="tabpanel" aria-labelledby="achievements-tab">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <h3>Recent Achievements</h3>
                            <?php if (!empty($recentAchievements)): ?>
                                <div class="list-group">
                                    <?php foreach ($recentAchievements as $achievement): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="me-2" style="font-size: 1.5rem;"><?php echo $achievement['icon'] ?? '🏆'; ?></span>
                                                <strong><?php echo htmlspecialchars($achievement['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    Earned on <?php echo date('M j, Y', strtotime($achievement['date'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">+<?php echo $achievement['points']; ?> pts</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No recent achievements. Keep engaging with the community to unlock new achievements!
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <h3>Milestones</h3>
                            <?php if (!empty($milestones)): ?>
                                <div class="row row-cols-1 row-cols-md-2 g-3">
                                    <?php foreach ($milestones as $milestone): ?>
                                        <div class="col">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-start mb-2">
                                                        <span style="font-size: 2rem;" class="me-2"><?php echo $milestone['icon'] ?? '🎯'; ?></span>
                                                        <div>
                                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($milestone['name']); ?></h5>
                                                            <p class="card-text small text-muted mb-2">
                                                                <?php echo htmlspecialchars($milestone['description']); ?>
                                                            </p>
                                                            <span class="badge bg-success"><?php echo $milestone['date']; ?></span>
                                                        </div>
                                                    </div>
                                                    <?php if (isset($milestone['reward'])): ?>
                                                        <div class="mt-2 pt-2 border-top">
                                                            <small class="text-muted">
                                                                <strong>Reward:</strong> <?php echo htmlspecialchars($milestone['reward']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No milestones achieved yet. Start your journey to unlock your first milestone!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Tab -->
                <div class="tab-pane fade" id="leaderboard" role="tabpanel" aria-labelledby="leaderboard-tab">
                    <div class="row">
                        <div class="col-12">
                            <h3>Community Leaderboard</h3>
                            <?php if ($currentUserData): ?>
                                <div class="alert alert-info mb-4">
                                    <strong>Your Rank:</strong> #<?php echo $currentUserData['rank']; ?>
                                    out of <?php echo $currentUserData['total_users']; ?> members
                                    with <?php echo number_format($currentUserData['score'], 1); ?> points
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($leaderboardData['top_users'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
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
                                                        <?php if ($index < 3): ?>
                                                            <span style="font-size: 1.5rem;">
                                                                <?php echo ['🥇', '🥈', '🥉'][$index]; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            #<?php echo $index + 1; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($user['avatar_url'])): ?>
                                                                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>"
                                                                     alt="Avatar"
                                                                     class="rounded-circle me-2"
                                                                     style="width: 32px; height: 32px; object-fit: cover;">
                                                            <?php endif; ?>
                                                            <span><?php echo htmlspecialchars($user['name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge" style="background-color: <?php echo $user['tier']['color'] ?? '#6366f1'; ?>;">
                                                            <?php echo $user['tier']['icon'] ?? ''; ?>
                                                            <?php echo $user['tier']['name'] ?? 'Novice'; ?>
                                                        </span>
                                                    </td>
                                                    <td><strong><?php echo number_format($user['score'], 1); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if (isset($leaderboardData['community_average'])): ?>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            Community Average: <?php echo number_format($leaderboardData['community_average'], 1); ?> points
                                        </small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No leaderboard data available yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Badges Tab -->
                <div class="tab-pane fade" id="badges" role="tabpanel" aria-labelledby="badges-tab">
                    <div class="row">
                        <div class="col-12">
                            <h3>My Badges</h3>
                            <?php if (!empty($badges)): ?>
                                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
                                    <?php foreach ($badges as $badge): ?>
                                        <div class="col">
                                            <div class="card text-center h-100">
                                                <div class="card-body">
                                                    <div style="font-size: 3rem;" class="mb-2">
                                                        <?php echo $badge['icon'] ?? '🏅'; ?>
                                                    </div>
                                                    <h6 class="card-title"><?php echo htmlspecialchars($badge['name']); ?></h6>
                                                    <?php if (isset($badge['description'])): ?>
                                                        <p class="card-text small text-muted">
                                                            <?php echo htmlspecialchars($badge['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (isset($badge['awarded_at'])): ?>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y', strtotime($badge['awarded_at'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No badges earned yet. Participate in the community to unlock badges!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .nav-tabs .nav-link {
        color: #6c757d;
    }
    .nav-tabs .nav-link.active {
        color: #6366f1;
        border-bottom-color: #6366f1;
    }
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .table-primary {
        background-color: rgba(99, 102, 241, 0.1) !important;
    }
</style>

<?php
// Include the main layout footer
if (file_exists(__DIR__ . '/../../layouts/modern/footer.php')) {
    require_once __DIR__ . '/../../layouts/modern/footer.php';
}
?>
