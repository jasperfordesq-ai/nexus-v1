<?php
$hTitle = 'Badge Collections';
$hSubtitle = 'Complete collections for bonus rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<style>
.collections-wrapper {
    margin-top: 120px;
    padding: 0 20px 60px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.collections-nav {
    display: flex;
    gap: 12px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.nav-pill {
    padding: 10px 20px;
    border-radius: 25px;
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-pill:hover, .nav-pill.active {
    background: white;
    color: #1e1e2e;
}

.collections-grid {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.collection-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.collection-card.completed {
    border: 2px solid #10b981;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(255,255,255,0.95));
}

.collection-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.collection-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.collection-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.collection-card.completed .collection-icon {
    background: linear-gradient(135deg, #10b981, #34d399);
}

.collection-title {
    font-size: 20px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 4px;
}

.collection-desc {
    color: #6b7280;
    font-size: 14px;
}

.collection-stats {
    text-align: right;
}

.collection-progress-text {
    font-size: 24px;
    font-weight: 700;
    color: #4f46e5;
}

.collection-card.completed .collection-progress-text {
    color: #10b981;
}

.collection-reward {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.collection-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.badge-item {
    width: 70px;
    text-align: center;
}

.badge-icon {
    width: 50px;
    height: 50px;
    background: #f3f4f6;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 6px;
    opacity: 0.4;
    filter: grayscale(1);
    transition: all 0.2s;
}

.badge-item.earned .badge-icon {
    opacity: 1;
    filter: none;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
}

.badge-name {
    font-size: 11px;
    color: #6b7280;
    line-height: 1.2;
}

.badge-item.earned .badge-name {
    color: #1e1e2e;
    font-weight: 500;
}

.progress-bar {
    height: 8px;
    background: #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    margin-top: 16px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5, #7c3aed);
    border-radius: 8px;
    transition: width 0.5s ease;
}

.collection-card.completed .progress-fill {
    background: linear-gradient(90deg, #10b981, #34d399);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: white;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .collection-header {
        flex-direction: column;
        gap: 16px;
    }

    .collection-stats {
        text-align: left;
    }
}
</style>

<div class="collections-wrapper">
    <div class="collections-nav">
        <a href="<?= $basePath ?>/achievements" class="nav-pill">Dashboard</a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill">All Badges</a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill">Challenges</a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill active">Collections</a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill">XP Shop</a>
    </div>

    <?php if (empty($collections)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📚</div>
        <h3>No Collections Available</h3>
        <p>Badge collections will appear here once they're set up.</p>
    </div>
    <?php else: ?>
    <div class="collections-grid">
        <?php foreach ($collections as $collection): ?>
        <div class="collection-card <?= $collection['is_completed'] ? 'completed' : '' ?>">
            <div class="collection-header">
                <div class="collection-info">
                    <div class="collection-icon">
                        <?= $collection['icon'] ?? '📚' ?>
                    </div>
                    <div>
                        <h3 class="collection-title"><?= htmlspecialchars($collection['name']) ?></h3>
                        <p class="collection-desc"><?= htmlspecialchars($collection['description']) ?></p>
                    </div>
                </div>
                <div class="collection-stats">
                    <div class="collection-progress-text">
                        <?= $collection['earned_count'] ?> / <?= $collection['total_count'] ?>
                    </div>
                    <div class="collection-reward">
                        <?php if ($collection['is_completed']): ?>
                            <i class="fa-solid fa-check-circle" style="color: #10b981;"></i> +<?= $collection['bonus_xp'] ?> XP Claimed
                        <?php else: ?>
                            <i class="fa-solid fa-gift"></i> +<?= $collection['bonus_xp'] ?> XP Bonus
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $collection['progress_percent'] ?>%"></div>
            </div>

            <div class="collection-badges">
                <?php foreach ($collection['badges'] as $badge): ?>
                <div class="badge-item <?= $badge['earned'] ? 'earned' : '' ?>">
                    <div class="badge-icon"><?= $badge['icon'] ?></div>
                    <div class="badge-name"><?= htmlspecialchars($badge['name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
