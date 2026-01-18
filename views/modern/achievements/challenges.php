<?php
$hTitle = 'Challenges';
$hSubtitle = 'Complete challenges to earn bonus XP';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<style>
.challenges-wrapper {
    margin-top: 120px;
    padding: 0 20px 60px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.challenges-nav {
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

.challenges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
}

.challenge-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s;
}

.challenge-card:hover {
    transform: translateY(-4px);
}

.challenge-card.completed {
    border: 2px solid #10b981;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(255,255,255,0.95));
}

.challenge-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.challenge-type {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.challenge-type.daily { background: #fef3c7; color: #92400e; }
.challenge-type.weekly { background: #dbeafe; color: #1e40af; }
.challenge-type.monthly { background: #f3e8ff; color: #7c3aed; }
.challenge-type.special { background: #fce7f3; color: #be185d; }

.challenge-time {
    font-size: 12px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 4px;
}

.challenge-title {
    font-size: 18px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 8px;
}

.challenge-desc {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 20px;
}

.challenge-progress {
    margin-bottom: 16px;
}

.progress-bar {
    height: 10px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5, #7c3aed);
    border-radius: 10px;
    transition: width 0.5s ease;
}

.challenge-card.completed .progress-fill {
    background: linear-gradient(90deg, #10b981, #34d399);
}

.progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #6b7280;
}

.challenge-reward {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

.reward-xp {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: #10b981;
    font-size: 18px;
}

.completed-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #10b981;
    font-weight: 600;
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

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 10px;
}

.empty-state p {
    opacity: 0.8;
}

@media (max-width: 768px) {
    .challenges-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="challenges-wrapper">
    <div class="challenges-nav">
        <a href="<?= $basePath ?>/achievements" class="nav-pill">Dashboard</a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill">All Badges</a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill active">Challenges</a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill">Collections</a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill">XP Shop</a>
    </div>

    <?php if (empty($challenges)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">🎯</div>
        <h3>No Active Challenges</h3>
        <p>Check back soon for new challenges to complete!</p>
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
                <div class="progress-bar">
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
