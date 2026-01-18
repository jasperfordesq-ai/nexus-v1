<?php
/**
 * Nexus Score Dashboard Component
 * Visual display of user's 1000-point Nexus Score with glassmorphism design
 *
 * @var array $scoreData - Score data from NexusScoreService
 * @var bool $isPublic - Whether this is public profile view or private dashboard
 */

$total = $scoreData['total_score'] ?? 0;
$percentage = $scoreData['percentage'] ?? 0;
$tier = $scoreData['tier'] ?? ['name' => 'Novice', 'color' => '#94a3b8', 'icon' => '🎯'];
$percentile = $scoreData['percentile'] ?? 0;
$breakdown = $scoreData['breakdown'] ?? [];
$insights = $scoreData['insights'] ?? [];
$nextMilestone = $scoreData['next_milestone'] ?? null;
?>

<style>
.nexus-score-container {
    --score-primary: <?php echo $tier['color']; ?>;
    --score-glow: <?php echo $tier['color']; ?>80;
    padding: 0;
    margin: 0;
}

.score-hero-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.9));
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 24px;
    padding: 3rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.score-hero-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, var(--score-glow) 0%, transparent 70%);
    animation: pulse-glow 3s ease-in-out infinite;
    pointer-events: none;
}

@keyframes pulse-glow {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.6; }
}

.score-hero-content {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 2rem;
    align-items: center;
}

.score-circle-container {
    position: relative;
    width: 200px;
    height: 200px;
}

.score-circle {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.score-circle-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 12;
}

.score-circle-fill {
    fill: none;
    stroke: var(--score-primary);
    stroke-width: 12;
    stroke-linecap: round;
    stroke-dasharray: 565.48; /* 2 * PI * 90 */
    stroke-dashoffset: 565.48;
    animation: fillScore 2s ease-out forwards;
    filter: drop-shadow(0 0 10px var(--score-glow));
}

@keyframes fillScore {
    to {
        stroke-dashoffset: calc(565.48 - (565.48 * var(--score-percentage) / 100));
    }
}

.score-circle-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.score-value {
    font-size: 3rem;
    font-weight: 700;
    color: var(--score-primary);
    line-height: 1;
    text-shadow: 0 0 20px var(--score-glow);
}

.score-max {
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.5);
    font-weight: 300;
}

.score-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tier-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 0.75rem 1.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--score-primary);
    width: fit-content;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.tier-icon {
    font-size: 2rem;
}

.percentile-stat {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.7);
    margin-top: 0.5rem;
}

.percentile-value {
    color: #10b981;
    font-weight: 700;
    font-size: 1.25rem;
}

.score-breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.category-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.8));
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.category-card:hover {
    transform: translateY(-4px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.category-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #f1f5f9;
}

.category-score {
    font-size: 1.5rem;
    font-weight: 700;
    color: #6366f1;
}

.category-score-max {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.5);
    margin-left: 0.25rem;
}

.category-progress {
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.category-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    border-radius: 4px;
    transition: width 1s ease-out;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
}

.category-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    font-size: 0.875rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    color: rgba(255, 255, 255, 0.7);
}

.detail-value {
    color: #06b6d4;
    font-weight: 600;
}

.insights-section {
    margin-bottom: 2rem;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.insight-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    gap: 1rem;
}

.insight-card.improvement {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1));
    border-color: rgba(245, 158, 11, 0.3);
}

.insight-card.suggestion {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 182, 212, 0.1));
    border-color: rgba(16, 185, 129, 0.3);
}

.insight-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.insight-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #f1f5f9;
    margin: 0 0 0.5rem 0;
}

.insight-content p {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
}

.milestone-card {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
    border: 1px solid rgba(16, 185, 129, 0.4);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.milestone-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.milestone-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #10b981;
}

.milestone-remaining {
    font-size: 1.5rem;
    font-weight: 700;
    color: #10b981;
}

.milestone-progress-bar {
    width: 100%;
    height: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 0.75rem;
}

.milestone-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #06b6d4);
    border-radius: 6px;
    transition: width 1s ease-out;
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.6);
}

.milestone-reward {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.875rem;
}

.milestone-reward-icon {
    color: #fbbf24;
    margin-right: 0.5rem;
}

@media (max-width: 768px) {
    .score-hero-content {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .score-circle-container {
        margin: 0 auto;
    }

    .tier-badge {
        margin: 0 auto;
    }

    .score-breakdown-grid,
    .insights-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="nexus-score-container" style="--score-percentage: <?php echo $percentage; ?>">
    <!-- Hero Score Card -->
    <div class="score-hero-card">
        <div class="score-hero-content">
            <div class="score-circle-container">
                <svg class="score-circle" viewBox="0 0 200 200">
                    <circle class="score-circle-bg" cx="100" cy="100" r="90"></circle>
                    <circle class="score-circle-fill" cx="100" cy="100" r="90"></circle>
                </svg>
                <div class="score-circle-text">
                    <div class="score-value"><?php echo number_format($total, 0); ?></div>
                    <div class="score-max">/1000</div>
                </div>
            </div>

            <div class="score-details">
                <div class="tier-badge">
                    <span class="tier-icon"><?php echo $tier['icon']; ?></span>
                    <span><?php echo $tier['name']; ?> Tier</span>
                </div>

                <div class="percentile-stat">
                    You're in the top
                    <span class="percentile-value"><?php echo $percentile; ?>%</span>
                    of the community!
                </div>

                <?php if (!$isPublic): ?>
                <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem; margin-top: 0.5rem;">
                    Keep engaging with the community to increase your score and unlock rewards.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($nextMilestone && !$isPublic): ?>
    <!-- Next Milestone -->
    <div class="milestone-card">
        <div class="milestone-header">
            <div class="milestone-title">
                Next: <?php echo htmlspecialchars($nextMilestone['name']); ?>
            </div>
            <div class="milestone-remaining">
                <?php echo number_format($nextMilestone['points_remaining'], 0); ?> pts
            </div>
        </div>
        <div class="milestone-progress-bar">
            <div class="milestone-progress-fill" style="width: <?php echo $nextMilestone['progress_percentage']; ?>%"></div>
        </div>
        <div class="milestone-reward">
            <span class="milestone-reward-icon">🎁</span>
            <?php echo htmlspecialchars($nextMilestone['reward']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($insights && !$isPublic): ?>
    <!-- Insights Section -->
    <div class="insights-section">
        <h3 style="color: #f1f5f9; font-size: 1.5rem; margin-bottom: 1rem;">💡 Your Insights</h3>
        <div class="insights-grid">
            <?php foreach ($insights as $insight): ?>
            <div class="insight-card <?php echo $insight['type']; ?>">
                <div class="insight-icon"><?php echo $insight['icon']; ?></div>
                <div class="insight-content">
                    <h4><?php echo htmlspecialchars($insight['title']); ?></h4>
                    <p><?php echo htmlspecialchars($insight['message']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Score Breakdown -->
    <div>
        <h3 style="color: #f1f5f9; font-size: 1.5rem; margin-bottom: 1rem;">📊 Score Breakdown</h3>
        <div class="score-breakdown-grid">
            <?php
            $categories = [
                'engagement' => ['name' => '🤝 Community Engagement', 'color' => '#6366f1'],
                'quality' => ['name' => '⭐ Contribution Quality', 'color' => '#8b5cf6'],
                'volunteer' => ['name' => '💪 Volunteer Hours', 'color' => '#06b6d4'],
                'activity' => ['name' => '🚀 Platform Activity', 'color' => '#10b981'],
                'badges' => ['name' => '🏆 Badges & Achievements', 'color' => '#f59e0b'],
                'impact' => ['name' => '🌟 Social Impact', 'color' => '#ec4899']
            ];

            foreach ($categories as $key => $cat):
                $data = $breakdown[$key] ?? ['score' => 0, 'max' => 100, 'percentage' => 0, 'details' => []];
            ?>
            <div class="category-card">
                <div class="category-header">
                    <div class="category-name"><?php echo $cat['name']; ?></div>
                    <div class="category-score">
                        <?php echo number_format($data['score'], 1); ?>
                        <span class="category-score-max">/<?php echo $data['max']; ?></span>
                    </div>
                </div>

                <div class="category-progress">
                    <div class="category-progress-fill" style="width: <?php echo isset($data['percentage']) ? $data['percentage'] : round(($data['score'] / $data['max']) * 100, 1); ?>%; background: linear-gradient(90deg, <?php echo $cat['color']; ?>, <?php echo $cat['color']; ?>cc);"></div>
                </div>

                <?php if (!$isPublic && !empty($data['details'])): ?>
                <div class="category-details">
                    <?php
                    // Display first 4 details
                    $count = 0;
                    foreach ($data['details'] as $detailKey => $detailValue):
                        if ($count >= 4) break;
                        if (strpos($detailKey, '_score') !== false) continue; // Skip score sub-values
                        if (is_array($detailValue)) continue; // Skip array values
                        $count++;
                        $label = ucwords(str_replace('_', ' ', $detailKey));
                    ?>
                    <div class="detail-item">
                        <span><?php echo $label; ?>:</span>
                        <span class="detail-value"><?php echo is_numeric($detailValue) ? number_format($detailValue, 1) : htmlspecialchars($detailValue); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Animate progress bars on load
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.category-progress-fill, .milestone-progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
});
</script>
