<?php
// CivicOne View: Goals Index
$heroTitle = "Goal Buddy";
$heroSub = "Track and share your personal goals.";
$heroType = 'Self Improvement';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #000; padding-bottom: 10px; margin-bottom: 30px;">
        <h2 style="margin: 0; text-transform: uppercase; letter-spacing: 1px;">Your Goals</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/create" class="civic-btn">+ New Goal</a>
    </div>

    <?php if (empty($goals)): ?>
        <div class="civic-card" style="text-align: center; padding: 40px;">
            <p style="font-size: 1.5rem;">🎯 No goals set yet.</p>
            <p>Set a goal to get started!</p>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 20px;">
            <?php foreach ($goals as $goal): ?>
                <div class="civic-card">
                    <h3 style="margin-top: 0;"><?= htmlspecialchars($goal['title']) ?></h3>
                    <div style="background: #eee; height: 10px; border-radius: 5px; margin: 15px 0; border: 1px solid #999;">
                        <div style="background: #000; height: 100%; width: <?= $goal['progress'] ?? 0 ?>%;"></div>
                    </div>
                    <p><?= $goal['progress'] ?? 0 ?>% Complete</p>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>" class="civic-btn" style="background: white; color: black; border: 2px solid black;">Update Progress</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>