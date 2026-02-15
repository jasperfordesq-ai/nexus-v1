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

<style>
/* Federation Admin Dashboard Styles */
.fed-admin-dashboard {
    display: grid;
    gap: 1.5rem;
}

/* Status Header */
.fed-status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(99, 102, 241, 0.1));
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: 16px;
    margin-bottom: 0.5rem;
}

.fed-status-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.fed-status-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.fed-status-icon.enabled {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.fed-status-icon.disabled {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
}

.fed-status-text h2 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 4px;
    color: var(--admin-text, #fff);
}

.fed-status-text p {
    font-size: 0.9rem;
    color: var(--admin-text-muted, rgba(255,255,255,0.6));
    margin: 0;
}

.fed-status-toggle {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.fed-status-toggle.disable {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.fed-status-toggle.disable:hover {
    background: rgba(239, 68, 68, 0.25);
}

.fed-status-toggle.enable {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.fed-status-toggle.enable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Stats Grid */
.fed-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.fed-stat-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(139, 92, 246, 0.15);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.fed-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.fed-stat-icon.purple { background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1)); color: #8b5cf6; }
.fed-stat-icon.green { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1)); color: #10b981; }
.fed-stat-icon.blue { background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.1)); color: #3b82f6; }
.fed-stat-icon.amber { background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.1)); color: #f59e0b; }
.fed-stat-icon.pink { background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(219, 39, 119, 0.1)); color: #ec4899; }
.fed-stat-icon.teal { background: linear-gradient(135deg, rgba(20, 184, 166, 0.2), rgba(13, 148, 136, 0.1)); color: #14b8a6; }

.fed-stat-content {
    flex: 1;
}

.fed-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--admin-text, #fff);
    line-height: 1.2;
}

.fed-stat-label {
    font-size: 0.85rem;
    color: var(--admin-text-muted, rgba(255,255,255,0.6));
    margin-top: 2px;
}

.fed-stat-change {
    font-size: 0.75rem;
    margin-top: 4px;
}

.fed-stat-change.positive { color: #10b981; }
.fed-stat-change.negative { color: #ef4444; }

/* Cards */
.fed-admin-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(139, 92, 246, 0.1);
    border-radius: 16px;
    overflow: hidden;
}

.fed-admin-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(139, 92, 246, 0.1);
}

.fed-admin-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text, #fff);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.fed-admin-card-title i {
    color: #8b5cf6;
}

.fed-admin-card-body {
    padding: 1.25rem;
}

/* User Adoption Progress */
.fed-adoption-bar {
    height: 12px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 6px;
    overflow: hidden;
    margin: 1rem 0;
}

.fed-adoption-fill {
    height: 100%;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 6px;
    transition: width 0.5s ease;
}

.fed-adoption-stats {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    color: var(--admin-text-muted, rgba(255,255,255,0.6));
}

.fed-adoption-stats strong {
    color: #8b5cf6;
}

/* Activity Log */
.fed-activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 350px;
    overflow-y: auto;
}

.fed-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    border-left: 3px solid #8b5cf6;
}

.fed-activity-item.warning { border-left-color: #f59e0b; }
.fed-activity-item.critical { border-left-color: #ef4444; }

.fed-activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(139, 92, 246, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    flex-shrink: 0;
}

.fed-activity-content {
    flex: 1;
    min-width: 0;
}

.fed-activity-action {
    font-weight: 600;
    color: var(--admin-text, #fff);
    font-size: 0.9rem;
}

.fed-activity-meta {
    font-size: 0.8rem;
    color: var(--admin-text-muted, rgba(255,255,255,0.5));
    margin-top: 2px;
}

.fed-activity-time {
    font-size: 0.75rem;
    color: var(--admin-text-muted, rgba(255,255,255,0.4));
    white-space: nowrap;
}

/* Top Users */
.fed-top-users {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.fed-top-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
}

.fed-top-user-rank {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: #8b5cf6;
}

.fed-top-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    object-fit: cover;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}

.fed-top-user-info {
    flex: 1;
    min-width: 0;
}

.fed-top-user-name {
    font-weight: 600;
    color: var(--admin-text, #fff);
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fed-top-user-stats {
    font-size: 0.8rem;
    color: var(--admin-text-muted, rgba(255,255,255,0.5));
}

.fed-top-user-badge {
    padding: 4px 10px;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 100px;
    text-transform: uppercase;
}

.fed-top-user-badge.discovery { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.fed-top-user-badge.social { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.fed-top-user-badge.economic { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }

/* Partnership Summary */
.fed-partnership-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}

.fed-partnership-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(139, 92, 246, 0.1);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    transition: all 0.2s ease;
}

.fed-partnership-card:hover {
    border-color: rgba(139, 92, 246, 0.3);
    background: rgba(255, 255, 255, 0.05);
}

.fed-partnership-logo {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    margin: 0 auto 10px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #8b5cf6;
}

.fed-partnership-name {
    font-weight: 600;
    color: var(--admin-text, #fff);
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.fed-partnership-status {
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 100px;
    display: inline-block;
}

.fed-partnership-status.active {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.fed-partnership-status.pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

/* Empty State */
.fed-empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--admin-text-muted, rgba(255,255,255,0.5));
}

.fed-empty-state i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: rgba(139, 92, 246, 0.3);
}

/* Quick Actions */
.fed-quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.fed-quick-action {
    padding: 10px 16px;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: 10px;
    color: #a78bfa;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.fed-quick-action:hover {
    background: rgba(139, 92, 246, 0.2);
    border-color: rgba(139, 92, 246, 0.4);
    color: #c4b5fd;
}

/* Grid Layout */
.fed-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 900px) {
    .fed-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .fed-status-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .fed-status-info {
        flex-direction: column;
    }

    .fed-stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

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
        <button class="fed-status-toggle <?= $federationEnabled ? 'disable' : 'enable' ?>" id="federationToggle">
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
                <a href="<?= $basePath ?>/admin-legacy/federation/partnerships" class="fed-quick-action" style="padding: 6px 12px; font-size: 0.8rem;">
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
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
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
                            <i class="fa-solid fa-<?= $this->getActivityIcon($log['action_type'] ?? '') ?>"></i>
                        </div>
                        <div class="fed-activity-content">
                            <div class="fed-activity-action"><?= htmlspecialchars($this->formatActionType($log['action_type'] ?? '')) ?></div>
                            <div class="fed-activity-meta">
                                <?php if (!empty($log['actor_name'])): ?>
                                by <?= htmlspecialchars($log['actor_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="fed-activity-time">
                            <?= $this->timeAgo($log['created_at'] ?? '') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script>
const csrfToken = '<?= Csrf::token() ?>';
const basePath = '<?= $basePath ?>';
let federationEnabled = <?= $federationEnabled ? 'true' : 'false' ?>;

document.getElementById('federationToggle').addEventListener('click', function() {
    const action = federationEnabled ? 'disable' : 'enable';
    const confirmMsg = federationEnabled
        ? 'Are you sure you want to disable federation? Your timebank will be hidden from all partners.'
        : 'Enable federation for your timebank?';

    if (!confirm(confirmMsg)) return;

    this.disabled = true;
    this.textContent = 'Processing...';

    fetch(basePath + '/admin-legacy/federation/dashboard/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ enabled: !federationEnabled })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to update');
            this.disabled = false;
            this.textContent = federationEnabled ? 'Disable Federation' : 'Enable Federation';
        }
    })
    .catch(() => {
        alert('Network error');
        this.disabled = false;
        this.textContent = federationEnabled ? 'Disable Federation' : 'Enable Federation';
    });
});
</script>

<?php
// Helper functions for the view
function getActivityIcon($actionType) {
    $icons = [
        'message' => 'envelope',
        'transaction' => 'exchange-alt',
        'partnership' => 'handshake',
        'profile' => 'user',
        'listing' => 'list',
        'event' => 'calendar',
        'group' => 'users',
        'settings' => 'cog',
        'user_opted_in' => 'user-plus',
        'user_opted_out' => 'user-minus',
    ];

    foreach ($icons as $key => $icon) {
        if (stripos($actionType, $key) !== false) {
            return $icon;
        }
    }
    return 'circle';
}

function formatActionType($actionType) {
    return ucwords(str_replace(['_', '-'], ' ', $actionType));
}

function timeAgo($datetime) {
    if (empty($datetime)) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}
?>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
