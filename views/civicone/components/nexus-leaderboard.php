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

<style>
.leaderboard-container {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.9));
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.leaderboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.leaderboard-title {
    font-size: 2rem;
    font-weight: 700;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.leaderboard-filters {
    display: flex;
    gap: 0.5rem;
}

.filter-button {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-button:hover,
.filter-button.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: white;
}

.user-rank-card {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
    border: 2px solid rgba(16, 185, 129, 0.4);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 1.5rem;
    align-items: center;
}

.rank-badge {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #10b981, #06b6d4);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
}

.rank-number {
    font-size: 2rem;
    font-weight: 800;
    color: white;
    line-height: 1;
}

.rank-label {
    font-size: 0.625rem;
    color: rgba(255, 255, 255, 0.9);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.rank-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.rank-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: #f1f5f9;
}

.rank-score-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.rank-progress {
    flex: 1;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.rank-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #06b6d4);
    border-radius: 4px;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
}

.rank-score-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #10b981;
}

.comparison-stat {
    text-align: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
}

.comparison-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #10b981;
}

.comparison-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.25rem;
}

.leaderboard-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.leader-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    display: grid;
    grid-template-columns: 60px 1fr auto;
    gap: 1.5rem;
    align-items: center;
    transition: all 0.3s ease;
}

.leader-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    transform: translateX(4px);
}

.leader-item.rank-1 {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.15), rgba(255, 193, 7, 0.15));
    border-color: rgba(255, 215, 0, 0.4);
}

.leader-item.rank-2 {
    background: linear-gradient(135deg, rgba(192, 192, 192, 0.15), rgba(169, 169, 169, 0.15));
    border-color: rgba(192, 192, 192, 0.4);
}

.leader-item.rank-3 {
    background: linear-gradient(135deg, rgba(205, 127, 50, 0.15), rgba(184, 115, 51, 0.15));
    border-color: rgba(205, 127, 50, 0.4);
}

.leader-rank-display {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border-radius: 12px;
    font-size: 1.5rem;
    font-weight: 700;
    color: #6366f1;
}

.leader-item.rank-1 .leader-rank-display {
    background: linear-gradient(135deg, #ffd700, #ffa500);
    color: #000;
    font-size: 2rem;
}

.leader-item.rank-2 .leader-rank-display {
    background: linear-gradient(135deg, #c0c0c0, #808080);
    color: #000;
    font-size: 1.75rem;
}

.leader-item.rank-3 .leader-rank-display {
    background: linear-gradient(135deg, #cd7f32, #8b4513);
    color: #fff;
    font-size: 1.75rem;
}

.leader-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.leader-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #f1f5f9;
}

.leader-tier {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.leader-score-display {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.leader-score-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #6366f1;
}

.leader-score-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.average-line {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(245, 158, 11, 0.1));
    border: 1px dashed rgba(251, 191, 36, 0.4);
    border-radius: 12px;
    margin: 1rem 0;
}

.average-icon {
    font-size: 1.5rem;
}

.average-text {
    flex: 1;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.8);
}

.average-score {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fbbf24;
}

.load-more-button {
    width: 100%;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: #6366f1;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 1rem;
}

.load-more-button:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: #6366f1;
}

@media (max-width: 768px) {
    .user-rank-card {
        grid-template-columns: auto 1fr;
    }

    .comparison-stat {
        grid-column: span 2;
    }

    .leader-item {
        grid-template-columns: 48px 1fr;
        gap: 1rem;
    }

    .leader-score-display {
        grid-column: 2;
        align-items: flex-start;
        margin-top: 0.5rem;
    }
}
</style>

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
