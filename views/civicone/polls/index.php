<?php
// CivicOne View: Polls Index - WCAG 2.1 AA Compliant
// CSS extracted to civicone-mini-modules.css
$heroTitle = "Community Polls";
$heroSub = "Vote on important community decisions.";
$heroType = 'Governance';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-module-header">
        <h2>Active Polls</h2>
    </div>

    <?php if (empty($polls)): ?>
        <div class="civic-card civic-module-empty">
            <p class="civic-module-empty-icon" aria-hidden="true">🗳️</p>
            <p class="civic-module-empty-title">No active polls.</p>
        </div>
    <?php else: ?>
        <div class="civic-module-grid" role="list">
            <?php foreach ($polls as $poll): ?>
                <article class="civic-card civic-poll-card" role="listitem">
                    <h3><?= htmlspecialchars($poll['question']) ?></h3>
                    <p class="civic-poll-status">Status: <strong><?= ucfirst($poll['status']) ?></strong></p>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls/<?= $poll['id'] ?>"
                       class="civic-btn"
                       aria-label="View and vote on: <?= htmlspecialchars($poll['question']) ?>">View & Vote</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>