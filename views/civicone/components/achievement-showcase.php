<?php
/**
 * Achievement Showcase Component
 * Visual display of badges, achievements, and milestones
 *
 * @var array $badges - User's earned badges
 * @var array $milestones - Completed milestones
 * @var array $recentAchievements - Recently earned achievements
 * @var bool $isPublic - Public profile view or private
 */

$isPublic = $isPublic ?? false;
$badges = $badges ?? [];
$milestones = $milestones ?? [];
$recentAchievements = $recentAchievements ?? [];
?>

<!-- Achievement Showcase CSS -->
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-achievement-showcase.min.css">

<div class="achievement-showcase-container">
    <!-- Featured/Showcased Badges -->
    <?php if (!empty($badges)):
        $featuredBadges = array_filter($badges, function($b) { return $b['is_showcased'] ?? false; });
        if (!empty($featuredBadges)):
    ?>
    <div class="showcase-section">
        <div class="showcase-header">
            <div class="showcase-title">
                ⭐ Featured Achievements
            </div>
        </div>

        <div class="featured-badges">
            <?php foreach ($featuredBadges as $badge): ?>
            <div class="featured-badge-card">
                <div class="featured-indicator">
                    ⭐ Featured
                </div>
                <div class="featured-badge-content">
                    <span class="badge-icon-large"><?php echo $badge['icon'] ?? '🏆'; ?></span>
                    <div class="badge-name-large"><?php echo htmlspecialchars($badge['name'] ?? 'Badge'); ?></div>
                    <div class="badge-description">
                        <?php echo htmlspecialchars($badge['description'] ?? 'Special achievement unlocked!'); ?>
                    </div>
                    <div class="badge-earned-date">
                        <span>🗓️</span>
                        <span>Earned <?php echo date('M j, Y', strtotime($badge['awarded_at'] ?? 'now')); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; endif; ?>

    <!-- All Badges Grid -->
    <div class="showcase-section">
        <div class="showcase-header">
            <div class="showcase-title">
                🏆 Badge Collection
            </div>
            <div class="showcase-count">
                <?php echo count($badges); ?> Earned
            </div>
        </div>

        <!-- Category Filter -->
        <div class="category-filter">
            <button class="category-tag active" data-category="all">All</button>
            <button class="category-tag" data-category="volunteer">Volunteer</button>
            <button class="category-tag" data-category="social">Social</button>
            <button class="category-tag" data-category="quality">Quality</button>
            <button class="category-tag" data-category="special">Special</button>
        </div>

        <?php if (!empty($badges)): ?>
        <div class="badge-grid">
            <?php
            // Categorize badges by rarity
            foreach ($badges as $badge):
                $rarity = 'common';
                $badgeKey = $badge['badge_key'] ?? '';

                // Determine rarity
                if (in_array($badgeKey, ['vol_500h', 'diversity_25', 'level_10'])) {
                    $rarity = 'legendary';
                } elseif (in_array($badgeKey, ['vol_250h', 'vol_100h', 'transaction_50'])) {
                    $rarity = 'rare';
                }
            ?>
            <div class="badge-card <?php echo $rarity; ?>" title="<?php echo htmlspecialchars($badge['description'] ?? ''); ?>">
                <span class="badge-icon"><?php echo $badge['icon'] ?? '🏆'; ?></span>
                <div class="badge-name"><?php echo htmlspecialchars($badge['name'] ?? 'Badge'); ?></div>
                <?php if ($rarity !== 'common'): ?>
                <div class="badge-rarity"><?php echo ucfirst($rarity); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <div class="empty-text">No badges earned yet. Start engaging to unlock achievements!</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Milestone Timeline -->
    <?php if (!empty($milestones)): ?>
    <div class="showcase-section">
        <div class="showcase-header">
            <div class="showcase-title">
                🎯 Milestone Journey
            </div>
        </div>

        <div class="milestone-timeline">
            <?php foreach ($milestones as $milestone): ?>
            <div class="milestone-item">
                <div class="milestone-header">
                    <div class="milestone-title">
                        <span><?php echo $milestone['icon'] ?? '✨'; ?></span>
                        <span><?php echo htmlspecialchars($milestone['name']); ?></span>
                    </div>
                    <div class="milestone-date">
                        <?php echo date('M Y', strtotime($milestone['date'] ?? 'now')); ?>
                    </div>
                </div>
                <div class="milestone-description">
                    <?php echo htmlspecialchars($milestone['description']); ?>
                </div>
                <?php if (!empty($milestone['reward'])): ?>
                <div class="milestone-reward">
                    <span>🎁</span>
                    <span><?php echo htmlspecialchars($milestone['reward']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Achievements -->
    <?php if (!empty($recentAchievements)): ?>
    <div class="showcase-section">
        <div class="showcase-header">
            <div class="showcase-title">
                ✨ Recent Achievements
            </div>
        </div>

        <div class="recent-achievements-list">
            <?php foreach ($recentAchievements as $achievement): ?>
            <div class="recent-achievement-item">
                <div class="achievement-icon-circle">
                    <?php echo $achievement['icon'] ?? '🏆'; ?>
                </div>

                <div class="achievement-info">
                    <div class="achievement-name"><?php echo htmlspecialchars($achievement['name']); ?></div>
                    <div class="achievement-earned-text">
                        Earned <?php echo date('M j, Y', strtotime($achievement['date'] ?? 'now')); ?>
                    </div>
                </div>

                <div class="achievement-points">
                    +<?php echo number_format($achievement['points'] ?? 0); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Achievement Showcase JavaScript -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-achievement-showcase.min.js" defer></script>
