<?php
$hTitle = 'Badge Collections';
$hSubtitle = 'Complete collections for bonus rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>
<!-- CSS moved to /assets/css/civicone-achievements.css (2026-01-19) -->

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

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
