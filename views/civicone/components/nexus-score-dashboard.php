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

<!-- Nexus Score Dashboard CSS -->
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-nexus-score-dashboard.min.css">

<div class="nexus-score-container" style="--score-percentage: <?php echo $percentage; ?>; --score-primary: <?php echo $tier['color']; ?>; --score-glow: <?php echo $tier['color']; ?>80;">
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
                <p class="score-hint-text">
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
        <h3 class="score-section-heading">💡 Your Insights</h3>
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
        <h3 class="score-section-heading">📊 Score Breakdown</h3>
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

<!-- Nexus Score Dashboard JavaScript -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-nexus-score-dashboard.min.js" defer></script>
