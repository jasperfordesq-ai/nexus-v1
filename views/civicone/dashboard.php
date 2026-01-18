<?php require __DIR__ . '/../layouts/civicone/header.php'; ?>

<style>
    /* Dashboard mobile responsive fixes */
    .civic-dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 30px;
        gap: 16px;
        flex-wrap: wrap;
    }
    @media (max-width: 600px) {
        .civic-dashboard-header {
            flex-direction: column;
            align-items: stretch;
        }
        .civic-dashboard-header .civic-btn {
            text-align: center;
        }
        .civic-dashboard-activity {
            grid-column: span 1 !important;
        }
    }
    @media (min-width: 601px) {
        .civic-dashboard-activity {
            grid-column: span 2;
        }
    }
</style>

<div class="civic-dashboard-header">
    <h1>Your Dashboard</h1>
    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" class="civic-btn">Transfer Credits</a>
</div>

<div class="civic-grid">

    <!-- Balance -->
    <div class="civic-card">
        <h2><?= $user['balance'] ?> Hours</h2>
        <p style="color: var(--civic-text-secondary);">Current Balance</p>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/create" style="display: block; margin-top: 15px;">Post a new listing ></a>
    </div>

    <!-- Recent Activity -->
    <div class="civic-card civic-dashboard-activity">
        <h3>Recent Activity</h3>
        <?php if (empty($activity_feed)): ?>
            <p>No recent activity.</p>
        <?php else: ?>
            <ul style="padding-left: 20px;">
                <?php foreach ($activity_feed as $log): ?>
                    <li style="margin-bottom: 10px;">
                        <strong><?= htmlspecialchars($log['user_name']) ?></strong>
                        <?= htmlspecialchars($log['action']) ?>
                        <span style="color: var(--civic-text-secondary); font-size: 0.9em; margin-left: 10px;">
                            <?= date('M d', strtotime($log['created_at'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

</div>

<h2>Your Active Listings</h2>
<div class="civic-grid">
    <?php foreach ($my_listings as $listing): ?>
        <div class="civic-card">
            <span style="background: #DEE0E2; padding: 4px 8px; font-weight: bold; font-size: 0.8em; text-transform: uppercase;">
                <?= $listing['type'] ?>
            </span>
            <h3 style="margin-top: 15px; font-size: 22px;"><?= htmlspecialchars($listing['title']) ?></h3>
            <p><?= htmlspecialchars(substr($listing['description'], 0, 80)) ?>...</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/<?= $listing['id'] ?>">Manage Listing</a>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../layouts/civicone/footer.php'; ?>