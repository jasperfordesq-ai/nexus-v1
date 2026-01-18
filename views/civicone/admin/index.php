<?php
// CivicOne View: Admin Dashboard
$pageTitle = 'Admin Dashboard';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; border-bottom: 4px solid var(--skin-primary); padding-bottom: 15px;">
        <h1 style="margin: 0; text-transform: uppercase; color: var(--skin-primary);">Admin Dashboard</h1>
        <div style="font-size: 0.9rem; color: var(--civic-text-secondary, #4B5563);">Overview of community activity</div>
    </div>

    <!-- Stats Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="civic-card" style="text-align: center; padding: 25px;">
            <div style="font-size: 2.5rem; font-weight: bold; color: var(--skin-primary);"><?= $total_users ?></div>
            <div style="text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; color: var(--civic-text-secondary, #4B5563);">Total Members</div>
        </div>
        <div class="civic-card" style="text-align: center; padding: 25px;">
            <div style="font-size: 2.5rem; font-weight: bold; color: var(--skin-primary);"><?= $total_listings ?></div>
            <div style="text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; color: var(--civic-text-secondary, #4B5563);">Listings</div>
        </div>
        <div class="civic-card" style="text-align: center; padding: 25px;">
            <div style="font-size: 2.5rem; font-weight: bold; color: var(--skin-primary);"><?= $total_transactions ?></div>
            <div style="text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; color: var(--civic-text-secondary, #4B5563);">Exchanges</div>
        </div>
        <div class="civic-card" style="text-align: center; padding: 25px;">
            <div style="font-size: 2.5rem; font-weight: bold; color: var(--skin-primary);"><?= number_format($total_volume) ?></div>
            <div style="text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; color: var(--civic-text-secondary, #4B5563);">Hours Exchanged</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">

        <!-- Pending Users -->
        <div class="civic-card" style="align-self: start;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h2 style="margin: 0; font-size: 1.2rem; color: #333;">Pending Approvals</h2>
                <?php if (count($pending_users) > 0): ?>
                    <span style="background: #e11d48; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;"><?= count($pending_users) ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($pending_users)): ?>
                <p style="color: var(--civic-text-secondary, #4B5563); font-style: italic;">No pending users.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($pending_users as $pUser): ?>
                        <li style="padding: 10px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= htmlspecialchars($pUser['name']) ?></strong><br>
                                <span style="font-size: 0.85rem; color: var(--civic-text-secondary, #4B5563);"><?= htmlspecialchars($pUser['email']) ?></span>
                            </div>
                            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/approve-user" method="POST" style="margin: 0;">
                                <?= Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="user_id" value="<?= $pUser['id'] ?>">
                                <button type="submit" class="civic-btn" style="padding: 5px 10px; font-size: 0.8rem;">Approve</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div style="margin-top: 15px; text-align: right;">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/users" style="font-size: 0.9rem; color: var(--skin-primary); font-weight: bold;">Manage All Users &rarr;</a>
            </div>
        </div>

        <!-- Quick Links / Recent Activity Placeholder -->
        <div class="civic-card" style="align-self: start;">
            <h2 style="margin: 0 0 20px 0; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px; color: #333;">Admin Actions</h2>
            <div style="display: grid; gap: 10px;">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/users" class="civic-btn" style="text-align: center; background: #555;">Manage Users</a>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/settings" class="civic-btn" style="text-align: center; background: #555;">System Settings</a>
            </div>
        </div>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>