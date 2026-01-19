<?php
// CivicOne View: Goals Index - WCAG 2.1 AA Compliant
// CSS extracted to civicone-mini-modules.css
$heroTitle = "Goal Buddy";
$heroSub = "Track and share your personal goals.";
$heroType = 'Self Improvement';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-module-header">
        <h2>Your Goals</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/create" class="civic-btn">+ New Goal</a>
    </div>

    <?php if (empty($goals)): ?>
        <div class="civic-card civic-module-empty">
            <p class="civic-module-empty-icon" aria-hidden="true">🎯</p>
            <p class="civic-module-empty-title">No goals set yet.</p>
            <p class="civic-module-empty-text">Set a goal to get started!</p>
        </div>
    <?php else: ?>
        <div class="civic-module-grid" role="list">
            <?php foreach ($goals as $goal): ?>
                <article class="civic-card civic-goal-card" role="listitem">
                    <h3><?= htmlspecialchars($goal['title']) ?></h3>
                    <div class="civic-goal-progress-bar" role="progressbar" aria-valuenow="<?= $goal['progress'] ?? 0 ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="civic-goal-progress-fill" style="width: <?= $goal['progress'] ?? 0 ?>%;"></div>
                    </div>
                    <p class="civic-goal-percent"><?= $goal['progress'] ?? 0 ?>% Complete</p>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>"
                       class="civic-btn civic-goal-btn-outline"
                       aria-label="Update progress for: <?= htmlspecialchars($goal['title']) ?>">Update Progress</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>