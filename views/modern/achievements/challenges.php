<?php
$hTitle = 'Challenges';
$hSubtitle = 'Complete challenges to earn bonus XP';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Load achievements CSS
$additionalCSS = '<link rel="stylesheet" href="/assets/css/achievements.min.css?v=' . time() . '">';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<div class="challenges-wrapper" role="main" aria-label="Challenges">
    <nav class="challenges-nav" aria-label="Achievement sections">
        <a href="<?= $basePath ?>/achievements" class="nav-pill">Dashboard</a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill">All Badges</a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill active">Challenges</a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill">Collections</a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill">XP Shop</a>
        <a href="<?= $basePath ?>/achievements/seasons" class="nav-pill">Seasons</a>
    </nav>

    <?php if (empty($challenges)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">🎯</div>
        <h3>No Active Challenges</h3>
        <p>Check back soon for new challenges to complete!</p>
        <a href="<?= $basePath ?>/achievements" class="cta-btn">Back to Dashboard <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <?php else: ?>
    <div class="challenges-grid">
        <?php foreach ($challenges as $challenge): ?>
        <div class="challenge-card <?= $challenge['is_completed'] ? 'completed' : '' ?>">
            <div class="challenge-header">
                <span class="challenge-type <?= $challenge['challenge_type'] ?>">
                    <?= ucfirst($challenge['challenge_type']) ?>
                </span>
                <span class="challenge-time">
                    <i class="fa-solid fa-clock"></i>
                    <?php if ($challenge['hours_remaining'] < 24): ?>
                        <?= round($challenge['hours_remaining']) ?> hours left
                    <?php else: ?>
                        <?= round($challenge['days_remaining']) ?> days left
                    <?php endif; ?>
                </span>
            </div>

            <h3 class="challenge-title"><?= htmlspecialchars($challenge['title']) ?></h3>
            <p class="challenge-desc"><?= htmlspecialchars($challenge['description']) ?></p>

            <div class="challenge-progress">
                <div class="progress-bar" role="progressbar" aria-valuenow="<?= $challenge['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Challenge progress">
                    <div class="progress-fill" style="width: <?= $challenge['progress_percent'] ?>%"></div>
                </div>
                <div class="progress-text">
                    <span><?= $challenge['user_progress'] ?> / <?= $challenge['target_count'] ?></span>
                    <span><?= $challenge['progress_percent'] ?>%</span>
                </div>
            </div>

            <div class="challenge-reward">
                <div class="reward-xp">
                    <i class="fa-solid fa-bolt"></i>
                    +<?= $challenge['xp_reward'] ?> XP
                </div>
                <?php if ($challenge['is_completed']): ?>
                <div class="completed-badge">
                    <i class="fa-solid fa-check-circle"></i> Completed!
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
