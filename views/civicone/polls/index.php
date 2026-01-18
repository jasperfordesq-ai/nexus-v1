<?php
// CivicOne View: Polls Index
$heroTitle = "Community Polls";
$heroSub = "Vote on important community decisions.";
$heroType = 'Governance';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="border-bottom: 4px solid #000; padding-bottom: 10px; margin-bottom: 30px;">
        <h2 style="margin: 0; text-transform: uppercase; letter-spacing: 1px;">Active Polls</h2>
    </div>

    <?php if (empty($polls)): ?>
        <div class="civic-card" style="text-align: center; padding: 40px;">
            <p style="font-size: 1.5rem;">🗳️ No active polls.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 20px;">
            <?php foreach ($polls as $poll): ?>
                <div class="civic-card">
                    <h3 style="margin-top: 0;"><?= htmlspecialchars($poll['question']) ?></h3>
                    <p>Status: <strong><?= ucfirst($poll['status']) ?></strong></p>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls/<?= $poll['id'] ?>" class="civic-btn">View & Vote</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>