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

<style>
.achievement-showcase-container {
    display: grid;
    gap: 2rem;
}

.showcase-section {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.9));
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.showcase-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.showcase-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.showcase-count {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 600;
}

.featured-badges {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.featured-badge-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
    border: 2px solid rgba(99, 102, 241, 0.4);
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.featured-badge-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 60px rgba(99, 102, 241, 0.4);
    border-color: rgba(99, 102, 241, 0.6);
}

.featured-badge-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, transparent 70%);
    animation: pulse-rotate 6s ease-in-out infinite;
}

@keyframes pulse-rotate {
    0%, 100% {
        transform: rotate(0deg) scale(1);
        opacity: 0.5;
    }
    50% {
        transform: rotate(180deg) scale(1.2);
        opacity: 0.8;
    }
}

.featured-badge-content {
    position: relative;
    z-index: 1;
}

.badge-icon-large {
    font-size: 5rem;
    margin-bottom: 1rem;
    display: block;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
}

.badge-name-large {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 0.5rem;
}

.badge-description {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.5;
    margin-bottom: 1rem;
}

.badge-earned-date {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.featured-indicator {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #000;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    z-index: 2;
}

.badge-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 1rem;
}

.badge-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.badge-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.3);
    border-color: rgba(99, 102, 241, 0.5);
}

.badge-card.rare {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(245, 158, 11, 0.15));
    border-color: rgba(251, 191, 36, 0.4);
}

.badge-card.legendary {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(217, 70, 239, 0.15));
    border-color: rgba(168, 85, 247, 0.4);
    animation: shimmer 3s ease-in-out infinite;
}

@keyframes shimmer {
    0%, 100% {
        box-shadow: 0 0 20px rgba(168, 85, 247, 0.3);
    }
    50% {
        box-shadow: 0 0 40px rgba(168, 85, 247, 0.6);
    }
}

.badge-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    display: block;
}

.badge-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    line-height: 1.3;
}

.badge-rarity {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.milestone-timeline {
    position: relative;
    padding-left: 3rem;
}

.milestone-timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
}

.milestone-item {
    position: relative;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border-left: 3px solid #6366f1;
}

.milestone-item::before {
    content: '';
    position: absolute;
    left: -3.5rem;
    top: 1.5rem;
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: 3px solid #0f1629;
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.6);
}

.milestone-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.milestone-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.milestone-date {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.5);
}

.milestone-description {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.5;
}

.milestone-reward {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 182, 212, 0.1));
    border-radius: 8px;
    font-size: 0.875rem;
    color: #10b981;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.recent-achievements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.recent-achievement-item {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(6, 182, 212, 0.15));
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 1rem;
    align-items: center;
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.achievement-icon-circle {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #10b981, #06b6d4);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
}

.achievement-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.achievement-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: #f1f5f9;
}

.achievement-earned-text {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
}

.achievement-points {
    font-size: 1.5rem;
    font-weight: 700;
    color: #10b981;
    text-align: center;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: rgba(255, 255, 255, 0.5);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-text {
    font-size: 1.125rem;
}

.category-filter {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.category-tag {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 50px;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-tag:hover,
.category-tag.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: white;
}

@media (max-width: 768px) {
    .featured-badges {
        grid-template-columns: 1fr;
    }

    .badge-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }

    .recent-achievement-item {
        grid-template-columns: auto 1fr;
    }

    .achievement-points {
        grid-column: 2;
        text-align: left;
        margin-top: 0.5rem;
    }
}
</style>

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

<script>
// Category filter functionality
document.querySelectorAll('.category-tag').forEach(tag => {
    tag.addEventListener('click', function() {
        const category = this.dataset.category;

        // Update active state
        document.querySelectorAll('.category-tag').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        // Filter badges (implement based on badge data attributes)
        console.log('Filter by category:', category);
    });
});
</script>
