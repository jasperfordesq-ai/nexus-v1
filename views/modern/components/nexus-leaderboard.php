<?php
/**
 * Nexus Score Leaderboard Component
 * Community ranking and comparison features
 *
 * @var array $leaderboardData - Top users/organizations by score
 * @var array $currentUserData - Current user's rank and score
 * @var string $timeframe - 'weekly', 'monthly', 'all-time'
 * @var string $category - 'overall', 'engagement', 'volunteer', etc.
 */

$timeframe = $timeframe ?? 'all-time';
$category = $category ?? 'overall';
$leaders = $leaderboardData['top_users'] ?? [];
$userRank = $currentUserData['rank'] ?? null;
$userScore = $currentUserData['score'] ?? 0;
$communityAverage = $leaderboardData['community_average'] ?? 450;
?>

<div class="leaderboard-container">
    <!-- Header with Filters -->
    <div class="leaderboard-header">
        <div class="leaderboard-title">
            🏆 Community Leaderboard
        </div>

        <div class="leaderboard-filters">
            <button class="filter-button <?php echo $timeframe === 'weekly' ? 'active' : ''; ?>" data-timeframe="weekly">
                This Week
            </button>
            <button class="filter-button <?php echo $timeframe === 'monthly' ? 'active' : ''; ?>" data-timeframe="monthly">
                This Month
            </button>
            <button class="filter-button <?php echo $timeframe === 'all-time' ? 'active' : ''; ?>" data-timeframe="all-time">
                All Time
            </button>
        </div>
    </div>

    <!-- Current User Rank Card -->
    <?php if ($userRank): ?>
    <div class="user-rank-card">
        <div class="rank-badge">
            <div class="rank-number">#<?php echo number_format($userRank); ?></div>
            <div class="rank-label">Your Rank</div>
        </div>

        <div class="rank-details">
            <div class="rank-name">Your Position</div>
            <div class="rank-score-bar">
                <div class="rank-progress">
                    <div class="rank-progress-fill" style="width: <?php echo ($userScore / 1000) * 100; ?>%"></div>
                </div>
                <div class="rank-score-value"><?php echo number_format($userScore); ?></div>
            </div>
        </div>

        <div class="comparison-stat">
            <div class="comparison-value">
                <?php
                $diff = $userScore - $communityAverage;
                echo ($diff >= 0 ? '+' : '') . number_format($diff);
                ?>
            </div>
            <div class="comparison-label">vs Average</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Leaders List -->
    <div class="leaderboard-list">
        <?php
        // Show real leaderboard data or empty state
        if (empty($leaders)):
        ?>
            <div style="text-align: center; padding: 3rem 1rem; color: rgba(255, 255, 255, 0.6);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🏆</div>
                <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">
                    No Rankings Yet
                </div>
                <div style="font-size: 0.95rem;">
                    Be the first to earn points and climb the leaderboard!
                </div>
            </div>
        <?php
        else:
            $rank = 1;
            foreach ($leaders as $leader):
                $rankClass = 'rank-' . $rank;
                $medal = '';
                if ($rank === 1) $medal = '🥇';
                elseif ($rank === 2) $medal = '🥈';
                elseif ($rank === 3) $medal = '🥉';
        ?>
        <div class="leader-item <?php echo $rankClass; ?>">
            <div class="leader-rank-display">
                <?php echo $medal ?: $rank; ?>
            </div>

            <div class="leader-info">
                <div class="leader-name"><?php echo htmlspecialchars($leader['name']); ?></div>
                <div class="leader-tier">
                    <span><?php echo $leader['icon'] ?? '🎯'; ?></span>
                    <span><?php echo htmlspecialchars($leader['tier'] ?? 'Member'); ?></span>
                </div>
            </div>

            <div class="leader-score-display">
                <div class="leader-score-value"><?php echo number_format($leader['score']); ?></div>
                <div class="leader-score-label">points</div>
            </div>
        </div>
        <?php
                $rank++;
            endforeach;
        endif;
        ?>
    </div>

    <!-- Community Average Indicator -->
    <div class="average-line">
        <span class="average-icon">📊</span>
        <span class="average-text">Community Average Score</span>
        <span class="average-score"><?php echo number_format($communityAverage); ?></span>
    </div>

    <!-- Load More Button -->
    <button class="load-more-button" onclick="loadMoreLeaders()">
        Load More Rankings ↓
    </button>
</div>

<script>
function loadMoreLeaders() {
    // Implement AJAX call to load more leaderboard entries
    console.log('Loading more leaders...');
}

// Filter button handlers
document.querySelectorAll('.filter-button').forEach(button => {
    button.addEventListener('click', function() {
        const timeframe = this.dataset.timeframe;
        // Implement AJAX call to filter leaderboard
        console.log('Filter by timeframe:', timeframe);

        // Update active state
        document.querySelectorAll('.filter-button').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>
