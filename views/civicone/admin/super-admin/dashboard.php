<?php
/**
 * Super Admin Dashboard - Gold Standard
 * Platform Master Control Center
 * Path: views/modern/admin/super-admin/dashboard.php
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Env;

$basePath = TenantContext::getBasePath();

// Fetch all tenants
$tenants = Database::query("SELECT * FROM tenants ORDER BY created_at DESC")->fetchAll();

// Queue Stats
$qPending = Database::query("SELECT COUNT(*) FROM notification_queue WHERE status='pending'")->fetchColumn();
$qFailed = Database::query("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")->fetchColumn();
$qProcessed = Database::query("SELECT COUNT(*) FROM notification_queue WHERE status='sent'")->fetchColumn();

// Total users across all tenants
$totalUsers = Database::query("SELECT COUNT(*) FROM users")->fetchColumn();

// Cron key
$cronKey = Env::get('CRON_KEY') ?? 'Not Set';
$appUrl = Env::get('APP_URL') ?? '';

// Flash messages
$successMsg = $_GET['msg'] ?? null;
$errorMsg = $_GET['error'] ?? null;

// Header config
$superAdminPageTitle = 'Platform Dashboard';
$superAdminPageSubtitle = 'Platform Master';
$superAdminPageIcon = 'fa-satellite-dish';

require dirname(__DIR__) . '/partials/super-admin-header.php';
?>

<?php if ($successMsg): ?>
<div class="super-admin-flash-message" data-type="success" style="display:none;">
    <?php
    switch($successMsg) {
        case 'tenant_created': echo 'New community deployed successfully!'; break;
        case 'tenant_updated': echo 'Community configuration saved.'; break;
        case 'admin_added': echo 'Administrator access granted.'; break;
        default: echo htmlspecialchars($successMsg);
    }
    ?>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="super-admin-flash-message" data-type="error" style="display:none;">
    <?= htmlspecialchars($errorMsg) ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="super-admin-page-header">
    <div class="super-admin-page-header-content">
        <div class="super-admin-page-header-icon">
            <i class="fa-solid fa-satellite-dish"></i>
        </div>
        <div>
            <h1 class="super-admin-page-title">Platform Command Center</h1>
            <p class="super-admin-page-subtitle">Orchestrate communities, monitor health, and deploy new instances</p>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="super-admin-stats-grid" id="queue">
    <!-- Total Tenants -->
    <div class="super-admin-stat-card" style="--stat-color: linear-gradient(135deg, #9333ea, #7c3aed);">
        <div class="super-admin-stat-icon" style="background: linear-gradient(135deg, #9333ea, #7c3aed); color: white;">
            <i class="fa-solid fa-city"></i>
        </div>
        <div class="super-admin-stat-value"><?= count($tenants) ?></div>
        <div class="super-admin-stat-label">Communities</div>
    </div>

    <!-- Total Users -->
    <div class="super-admin-stat-card" style="--stat-color: linear-gradient(135deg, #06b6d4, #0891b2);">
        <div class="super-admin-stat-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2); color: white;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="super-admin-stat-value"><?= number_format($totalUsers) ?></div>
        <div class="super-admin-stat-label">Total Users</div>
    </div>

    <!-- Queue Pending -->
    <div class="super-admin-stat-card" style="--stat-color: linear-gradient(135deg, #f59e0b, #d97706);">
        <div class="super-admin-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="super-admin-stat-value"><?= number_format($qPending) ?></div>
        <div class="super-admin-stat-label">Pending Jobs</div>
    </div>

    <!-- Queue Failed -->
    <div class="super-admin-stat-card" style="--stat-color: <?= $qFailed > 0 ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 'linear-gradient(135deg, #10b981, #059669)' ?>;">
        <div class="super-admin-stat-icon" style="background: <?= $qFailed > 0 ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 'linear-gradient(135deg, #10b981, #059669)' ?>; color: white;">
            <i class="fa-solid <?= $qFailed > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
        </div>
        <div class="super-admin-stat-value"><?= $qFailed > 0 ? number_format($qFailed) : 'OK' ?></div>
        <div class="super-admin-stat-label"><?= $qFailed > 0 ? 'Failed Jobs' : 'Queue Health' ?></div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="super-admin-two-col">
    <!-- Main Column -->
    <div>
        <!-- Managed Communities -->
        <div class="super-admin-glass-card">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-purple">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Managed Communities</h3>
                    <p class="super-admin-card-subtitle"><?= count($tenants) ?> active instances across the platform</p>
                </div>
            </div>
            <div class="super-admin-card-body" style="padding: 0;">
                <?php if (!empty($tenants)): ?>
                <div class="super-admin-table-wrapper">
                    <table class="super-admin-table">
                        <thead>
                            <tr>
                                <th>Community</th>
                                <th>URL Slug</th>
                                <th>Active Modules</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tenants as $t): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #9333ea, #ec4899); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;">
                                            <?= strtoupper(substr($t['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($t['name']) ?></div>
                                            <?php if (!empty($t['tagline'])): ?>
                                            <div style="font-size: 0.75rem; color: rgba(255,255,255,0.5);"><?= htmlspecialchars(substr($t['tagline'], 0, 40)) ?><?= strlen($t['tagline']) > 40 ? '...' : '' ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: rgba(147, 51, 234, 0.15); padding: 4px 10px; border-radius: 6px; font-family: monospace; font-size: 0.8rem; color: #c084fc; border: 1px solid rgba(147, 51, 234, 0.2);">
                                        /<?= htmlspecialchars($t['slug']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $f = json_decode($t['features'] ?? '{}', true);
                                    $active = [];
                                    if (!empty($f['listings'])) $active[] = '<span style="color:#60a5fa">Listings</span>';
                                    if (!empty($f['groups'])) $active[] = '<span style="color:#c084fc">Hubs</span>';
                                    if (!empty($f['volunteering'])) $active[] = '<span style="color:#34d399">Vols</span>';
                                    if (!empty($f['events'])) $active[] = '<span style="color:#f472b6">Events</span>';
                                    if (!empty($f['wallet'])) $active[] = '<span style="color:#fbbf24">Wallet</span>';

                                    if (empty($active)) {
                                        echo '<span style="opacity:0.5; font-style:italic; font-size: 0.8rem;">None configured</span>';
                                    } else {
                                        echo '<span style="font-size: 0.8rem;">' . implode(' <span style="opacity:0.3">|</span> ', array_slice($active, 0, 3)) . '</span>';
                                        if (count($active) > 3) echo ' <span style="opacity:0.5; font-size:0.75rem;">+' . (count($active) - 3) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="/super-admin/tenant/edit?id=<?= $t['id'] ?>" class="super-admin-btn super-admin-btn-secondary super-admin-btn-sm">
                                            <i class="fa-solid fa-cog"></i> Configure
                                        </a>
                                        <a href="<?= $basePath ?>/<?= htmlspecialchars($t['slug']) ?>" target="_blank" class="super-admin-btn super-admin-btn-secondary super-admin-btn-sm" style="opacity: 0.7;">
                                            <i class="fa-solid fa-external-link"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 60px 20px; text-align: center;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(147, 51, 234, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="fa-solid fa-city" style="font-size: 1.5rem; color: #c084fc;"></i>
                    </div>
                    <h3 style="color: #fff; font-size: 1.1rem; margin: 0 0 8px;">No Communities Yet</h3>
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.9rem; margin: 0;">Deploy your first community instance below.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deploy New Instance -->
        <div class="super-admin-glass-card" id="deploy">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-pink">
                    <i class="fa-solid fa-rocket"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Deploy New Community</h3>
                    <p class="super-admin-card-subtitle">Launch a new timebank instance on the platform</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <form action="<?= $basePath ?>/super-admin/tenant/create" method="POST">
                    <!-- Instance Details -->
                    <div style="background: rgba(147, 51, 234, 0.1); border: 1px solid rgba(147, 51, 234, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #c084fc; font-weight: 700;">Instance Details</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="super-admin-form-group">
                                <label class="super-admin-label">Community Name</label>
                                <input type="text" name="name" class="super-admin-input" placeholder="e.g. Cork City Exchange" required>
                            </div>
                            <div class="super-admin-form-group">
                                <label class="super-admin-label">URL Slug (unique)</label>
                                <div style="display: flex;">
                                    <span style="background: rgba(15, 10, 26, 0.8); padding: 0.65rem 0.85rem; border: 1px solid rgba(147, 51, 234, 0.2); border-right: none; border-radius: 8px 0 0 8px; color: rgba(255,255,255,0.4); font-family: monospace; font-size: 0.85rem;">platform.url/</span>
                                    <input type="text" name="slug" class="super-admin-input" placeholder="cork-city" required style="border-radius: 0 8px 8px 0;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Admin -->
                    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #34d399; font-weight: 700;">Primary Administrator</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                            <div class="super-admin-form-group">
                                <label class="super-admin-label">Full Name</label>
                                <input type="text" name="admin_name" class="super-admin-input" placeholder="Admin Name" required>
                            </div>
                            <div class="super-admin-form-group">
                                <label class="super-admin-label">Email Address</label>
                                <input type="email" name="admin_email" class="super-admin-input" placeholder="admin@email.com" required>
                            </div>
                            <div class="super-admin-form-group">
                                <label class="super-admin-label">Password</label>
                                <input type="password" name="admin_password" class="super-admin-input" placeholder="Create Password" required>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="super-admin-btn super-admin-btn-primary" style="padding: 12px 24px;">
                            <i class="fa-solid fa-rocket"></i>
                            Launch New Instance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Quick Actions -->
        <div class="super-admin-glass-card">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-cyan">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Quick Actions</h3>
                    <p class="super-admin-card-subtitle">Common platform tasks</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?= $basePath ?>/super-admin/users" class="super-admin-btn super-admin-btn-secondary" style="width: 100%; justify-content: flex-start;">
                        <i class="fa-solid fa-users"></i>
                        Global User Directory
                    </a>
                    <button onclick="window.open('/cron/process-queue?key=<?= htmlspecialchars($cronKey) ?>', '_blank')" class="super-admin-btn super-admin-btn-secondary" style="width: 100%; justify-content: flex-start;">
                        <i class="fa-solid fa-play"></i>
                        Run Queue Worker
                    </button>
                    <a href="#deploy" class="super-admin-btn super-admin-btn-secondary" style="width: 100%; justify-content: flex-start;">
                        <i class="fa-solid fa-rocket"></i>
                        Deploy New Instance
                    </a>
                    <a href="<?= $basePath ?>/help" target="_blank" class="super-admin-btn super-admin-btn-secondary" style="width: 100%; justify-content: flex-start;">
                        <i class="fa-solid fa-book"></i>
                        Documentation
                    </a>
                </div>
            </div>
        </div>

        <!-- Cron Configuration -->
        <div class="super-admin-glass-card" id="cron">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-amber">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Cron Configuration</h3>
                    <p class="super-admin-card-subtitle">Server automation setup</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <p style="font-size: 0.8rem; color: rgba(255,255,255,0.6); margin: 0 0 12px;">Add these to your server's crontab:</p>

                <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 0.7rem; color: #34d399; overflow-x: auto; margin-bottom: 16px;">
                    <div style="color: rgba(255,255,255,0.4); margin-bottom: 8px;"># Every Minute (Instant Emails)</div>
                    <div style="word-break: break-all;">* * * * * curl -s "<?= $appUrl ?>/cron/process-queue?key=<?= $cronKey ?>"</div>
                    <div style="color: rgba(255,255,255,0.4); margin: 12px 0 8px;"># Daily 5PM (Digest)</div>
                    <div style="word-break: break-all;">0 17 * * * curl -s "<?= $appUrl ?>/cron/daily-digest?key=<?= $cronKey ?>"</div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button onclick="window.open('/cron/process-queue?key=<?= htmlspecialchars($cronKey) ?>', '_blank')" class="super-admin-btn super-admin-btn-secondary super-admin-btn-sm" style="flex: 1;">
                        <i class="fa-solid fa-bolt"></i> Queue
                    </button>
                    <button onclick="window.open('/cron/daily-digest?key=<?= htmlspecialchars($cronKey) ?>', '_blank')" class="super-admin-btn super-admin-btn-secondary super-admin-btn-sm" style="flex: 1;">
                        <i class="fa-solid fa-envelope"></i> Digest
                    </button>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="super-admin-glass-card">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-emerald">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">System Status</h3>
                    <p class="super-admin-card-subtitle">Platform health overview</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                        <span style="font-size: 0.85rem; color: rgba(255,255,255,0.8);">
                            <i class="fa-solid fa-database" style="margin-right: 8px; color: #34d399;"></i>
                            Database
                        </span>
                        <span class="super-admin-status-badge super-admin-status-active">
                            <span class="super-admin-status-dot"></span> Connected
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: <?= $qFailed > 0 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)' ?>; border-radius: 8px; border: 1px solid <?= $qFailed > 0 ? 'rgba(239, 68, 68, 0.2)' : 'rgba(16, 185, 129, 0.2)' ?>;">
                        <span style="font-size: 0.85rem; color: rgba(255,255,255,0.8);">
                            <i class="fa-solid fa-layer-group" style="margin-right: 8px; color: <?= $qFailed > 0 ? '#f87171' : '#34d399' ?>;"></i>
                            Queue
                        </span>
                        <span class="super-admin-status-badge <?= $qFailed > 0 ? 'super-admin-status-inactive' : 'super-admin-status-active' ?>">
                            <span class="super-admin-status-dot"></span> <?= $qFailed > 0 ? $qFailed . ' Failed' : 'Healthy' ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(147, 51, 234, 0.1); border-radius: 8px; border: 1px solid rgba(147, 51, 234, 0.2);">
                        <span style="font-size: 0.85rem; color: rgba(255,255,255,0.8);">
                            <i class="fa-solid fa-key" style="margin-right: 8px; color: #c084fc;"></i>
                            Cron Key
                        </span>
                        <span style="font-size: 0.7rem; color: rgba(255,255,255,0.5); font-family: monospace;">
                            <?= $cronKey !== 'Not Set' ? substr($cronKey, 0, 8) . '...' : 'Not Set' ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(6, 182, 212, 0.1); border-radius: 8px; border: 1px solid rgba(6, 182, 212, 0.2);">
                        <span style="font-size: 0.85rem; color: rgba(255,255,255,0.8);">
                            <i class="fa-solid fa-envelope" style="margin-right: 8px; color: #22d3ee;"></i>
                            Processed
                        </span>
                        <span style="font-size: 0.85rem; color: #22d3ee; font-weight: 600;">
                            <?= number_format($qProcessed) ?> emails
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional Dashboard-specific styles */
@media (max-width: 768px) {
    .super-admin-card-body form [style*="grid-template-columns: 1fr 1fr 1fr"],
    .super-admin-card-body form [style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/super-admin-footer.php'; ?>
