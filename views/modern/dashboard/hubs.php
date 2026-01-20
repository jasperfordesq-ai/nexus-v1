<?php
/**
 * Modern Dashboard - My Hubs Page
 * Dedicated route version (replaces tab-based approach)
 */

$hero_title = "My Hubs";
$hero_subtitle = "Your community connections";
$hero_gradient = 'htb-hero-gradient-wallet';
$hero_type = 'Wallet';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
?>

<div class="dashboard-glass-bg"></div>

<div class="dashboard-container">

    <!-- Glass Navigation -->
    <div class="dash-tabs-glass">
        <a href="<?= $basePath ?>/dashboard" class="dash-tab-glass">
            <i class="fa-solid fa-house"></i> Overview
        </a>
        <a href="<?= $basePath ?>/dashboard/notifications" class="dash-tab-glass">
            <i class="fa-solid fa-bell"></i> Notifications
            <?php
            $uCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
            if ($uCount > 0): ?>
                <span class="dash-notif-badge"><?= $uCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/dashboard/hubs" class="dash-tab-glass active">
            <i class="fa-solid fa-users"></i> My Hubs
        </a>
        <a href="<?= $basePath ?>/dashboard/listings" class="dash-tab-glass">
            <i class="fa-solid fa-list"></i> My Listings
        </a>
        <a href="<?= $basePath ?>/dashboard/wallet" class="dash-tab-glass">
            <i class="fa-solid fa-wallet"></i> Wallet
        </a>
        <a href="<?= $basePath ?>/dashboard/events" class="dash-tab-glass">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
    </div>

    <div class="htb-card">
        <div class="htb-card-header dash-hubs-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2rem;">My Hubs</h3>
            <a href="<?= $basePath ?>/groups" class="htb-btn htb-btn-primary"><i class="fa-solid fa-compass"></i> Browse All Hubs</a>
        </div>
        <div class="dash-hubs-grid" style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php if (empty($myGroups)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #94a3b8;">
                    <div style="font-size: 3rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-user-group"></i></div>
                    <p>You haven't joined any hubs yet.</p>
                    <a href="<?= $basePath ?>/groups" class="htb-btn htb-btn-primary" style="margin-top: 10px;">Join a Hub</a>
                </div>
            <?php else: ?>
                <?php foreach ($myGroups as $grp): ?>
                    <div class="htb-card" style="border: 1px solid #e2e8f0;">
                        <div class="htb-card-body">
                            <h4><?= htmlspecialchars($grp['name']) ?></h4>
                            <p style="color: #64748b; font-size: 0.9rem;"><?= htmlspecialchars($grp['description'] ?? '') ?></p>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                                <span style="color: #64748b; font-size: 0.85rem;"><i class="fa-solid fa-users" style="margin-right: 6px; color: #db2777;"></i><?= $grp['member_count'] ?? 0 ?> members</span>
                                <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="htb-btn htb-btn-primary htb-btn-sm">Enter Hub</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
