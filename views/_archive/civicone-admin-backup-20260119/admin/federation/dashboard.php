<?php
/**
 * Federation Admin Dashboard
 * Overview of federation activity, partnerships, and user adoption
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Dashboard';
$adminPageSubtitle = 'Overview & Activity';
$adminPageIcon = 'fa-gauge-high';

require __DIR__ . '/../partials/admin-header.php';

// Extract data
$federationEnabled = $federationEnabled ?? false;
$tenantSettings = $tenantSettings ?? [];
$partnerships = $partnerships ?? [];
$activePartnerships = $activePartnerships ?? [];
$pendingPartnerships = $pendingPartnerships ?? [];
$stats = $stats ?? [];
$auditLogs = $auditLogs ?? [];
$optedInUsers = $optedInUsers ?? ['total' => 0, 'opted_in' => 0, 'percentage' => 0];
$activityTrends = $activityTrends ?? [];
$topUsers = $topUsers ?? [];
?>

<div class="fed-admin-dashboard">

    <!-- Status Header -->
    <div class="fed-status-header">
        <div class="fed-status-info">
            <div class="fed-status-icon <?= $federationEnabled ? 'enabled' : 'disabled' ?>">
                <i class="fa-solid <?= $federationEnabled ? 'fa-check' : 'fa-pause' ?>"></i>
            </div>
            <div class="fed-status-text">
                <h2>Federation is <?= $federationEnabled ? 'Active' : 'Inactive' ?></h2>
                <p><?= $federationEnabled
                    ? 'Your timebank is connected to ' . count($activePartnerships) . ' partner' . (count($activePartnerships) !== 1 ? 's' : '')
                    : 'Enable federation to connect with partner timebanks' ?></p>
            </div>
        </div>
        <button class="fed-status-toggle <?= $federationEnabled ? 'disable' : 'enable' ?>" id="federationToggle"
            onclick="toggleFederation(<?= $federationEnabled ? 'false' : 'true' ?>)">
            <?= $federationEnabled ? 'Disable Federation' : 'Enable Federation' ?>
        </button>
    </div>

    <!-- Quick Actions -->
    <div class="fed-quick-actions">
        <a href="<?= $basePath ?>/admin-legacy/federation" class="fed-quick-action">
            <i class="fa-solid fa-sliders"></i>
            Settings
        </a>
        <a href="<?= $basePath ?>/admin-legacy/federation/partnerships" class="fed-quick-action">
            <i class="fa-solid fa-handshake"></i>
            Partnerships
        </a>
        <a href="<?= $basePath ?>/admin-legacy/federation/analytics" class="fed-quick-action">
            <i class="fa-solid fa-chart-line"></i>
            Analytics
        </a>
        <a href="<?= $basePath ?>/admin-legacy/federation/directory" class="fed-quick-action">
            <i class="fa-solid fa-compass"></i>
            Directory
        </a>
        <a href="<?= $basePath ?>/federation" class="fed-quick-action" target="_blank">
            <i class="fa-solid fa-external-link"></i>
            User Hub
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="fed-stats-grid">
        <div class="fed-stat-card">
            <div class="fed-stat-icon purple">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="fed-stat-content">
                <div class="fed-stat-value"><?= number_format($stats['total_users_opted_in'] ?? 0) ?></div>
                <div class="fed-stat-label">Users Opted In</div>
            </div>
        </div>

        <div class="fed-stat-card">
            <div class="fed-stat-icon blue">
                <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="fed-stat-content">
                <div class="fed-stat-value"><?= number_format(($stats['total_messages_sent'] ?? 0) + ($stats['total_messages_received'] ?? 0)) ?></div>
                <div class="fed-stat-label">Messages Exchanged</div>
            </div>
        </div>

        <div class="fed-stat-card">
            <div class="fed-stat-icon green">
                <i class="fa-solid fa-arrow-right-arrow-left"></i>
            </div>
            <div class="fed-stat-content">
                <div class="fed-stat-value"><?= number_format($stats['total_transactions'] ?? 0) ?></div>
                <div class="fed-stat-label">Transactions</div>
            </div>
        </div>

        <div class="fed-stat-card">
            <div class="fed-stat-icon teal">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="fed-stat-content">
                <div class="fed-stat-value"><?= number_format(($stats['total_hours_sent'] ?? 0) + ($stats['total_hours_received'] ?? 0), 1) ?></div>
                <div class="fed-stat-label">Hours Exchanged</div>
            </div>
        </div>

        <div class="fed-stat-card">
            <div class="fed-stat-icon amber">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <div class="fed-stat-content">
                <div class="fed-stat-value"><?= number_format($stats['active_partnerships'] ?? 0) ?></div>
                <div class="fed-stat-label">Active Partners</div>
            </div>
        </div>

        <div class="fed-stat-card">
            <div class="fed-stat-icon pink">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="fed-stat-content">
                <div class="fed-stat-value"><?= number_format($stats['pending_partnerships'] ?? 0) ?></div>
                <div class="fed-stat-label">Pending Requests</div>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="fed-grid-2">

        <!-- User Adoption -->
        <div class="fed-admin-card">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-user-check"></i>
                    User Adoption
                </h3>
            </div>
            <div class="fed-admin-card-body">
                <div class="fed-adoption-bar">
                    <div class="fed-adoption-fill" style="width: <?= $optedInUsers['percentage'] ?>%;"></div>
                </div>
                <div class="fed-adoption-stats">
                    <span><strong><?= $optedInUsers['opted_in'] ?></strong> of <?= $optedInUsers['total'] ?> users opted in</span>
                    <span><strong><?= $optedInUsers['percentage'] ?>%</strong> adoption</span>
                </div>
            </div>
        </div>

        <!-- Partnerships -->
        <div class="fed-admin-card">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-handshake"></i>
                    Partnerships
                </h3>
                <a href="<?= $basePath ?>/admin-legacy/federation/partnerships" class="fed-quick-action">
                    View All <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="fed-admin-card-body">
                <?php if (empty($partnerships)): ?>
                <div class="fed-empty-state">
                    <i class="fa-solid fa-handshake-slash"></i>
                    <p>No partnerships yet</p>
                </div>
                <?php else: ?>
                <div class="fed-partnership-grid">
                    <?php foreach (array_slice($partnerships, 0, 6) as $p): ?>
                    <div class="fed-partnership-card">
                        <div class="fed-partnership-logo">
                            <?= strtoupper(substr($p['partner_name'] ?? $p['tenant_name'] ?? 'P', 0, 2)) ?>
                        </div>
                        <div class="fed-partnership-name"><?= htmlspecialchars($p['partner_name'] ?? $p['tenant_name'] ?? 'Unknown') ?></div>
                        <span class="fed-partnership-status <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Two Column Layout -->
    <div class="fed-grid-2">

        <!-- Top Users -->
        <div class="fed-admin-card">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-trophy"></i>
                    Top Federation Users
                </h3>
            </div>
            <div class="fed-admin-card-body">
                <?php if (empty($topUsers)): ?>
                <div class="fed-empty-state">
                    <i class="fa-solid fa-user-slash"></i>
                    <p>No active federation users yet</p>
                </div>
                <?php else: ?>
                <div class="fed-top-users">
                    <?php foreach (array_slice($topUsers, 0, 5) as $i => $user): ?>
                    <div class="fed-top-user">
                        <div class="fed-top-user-rank"><?= $i + 1 ?></div>
                        <div class="fed-top-user-avatar">
                            <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="">
                            <?php else: ?>
                            <?= strtoupper(substr($user['first_name'] ?? $user['name'] ?? 'U', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="fed-top-user-info">
                            <div class="fed-top-user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                            <div class="fed-top-user-stats">
                                <?= $user['messages_sent'] ?? 0 ?> messages, <?= $user['transactions_sent'] ?? 0 ?> transactions
                            </div>
                        </div>
                        <span class="fed-top-user-badge <?= $user['privacy_level'] ?? 'discovery' ?>">
                            <?= ucfirst($user['privacy_level'] ?? 'Discovery') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="fed-admin-card">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Recent Activity
                </h3>
            </div>
            <div class="fed-admin-card-body">
                <?php if (empty($auditLogs)): ?>
                <div class="fed-empty-state">
                    <i class="fa-solid fa-list"></i>
                    <p>No recent activity</p>
                </div>
                <?php else: ?>
                <div class="fed-activity-list">
                    <?php foreach (array_slice($auditLogs, 0, 8) as $log): ?>
                    <div class="fed-activity-item <?= ($log['level'] ?? '') === 'critical' ? 'critical' : (($log['level'] ?? '') === 'warning' ? 'warning' : '') ?>">
                        <div class="fed-activity-icon">
                            <i class="fa-solid fa-circle"></i>
                        </div>
                        <div class="fed-activity-content">
                            <div class="fed-activity-action"><?= htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $log['action_type'] ?? ''))) ?></div>
                            <div class="fed-activity-meta">
                                <?php if (!empty($log['actor_name'])): ?>
                                by <?= htmlspecialchars($log['actor_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="fed-activity-time">
                            <?php
                            if (!empty($log['created_at'])) {
                                $diff = time() - strtotime($log['created_at']);
                                if ($diff < 60) echo 'just now';
                                elseif ($diff < 3600) echo floor($diff / 60) . 'm ago';
                                elseif ($diff < 86400) echo floor($diff / 3600) . 'h ago';
                                elseif ($diff < 604800) echo floor($diff / 86400) . 'd ago';
                                else echo date('M j', strtotime($log['created_at']));
                            }
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script src="/assets/js/admin-federation.js?v=<?= time() ?>"></script>
<script>
    initFederationSettings('<?= $basePath ?>', '<?= Csrf::token() ?>');
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
