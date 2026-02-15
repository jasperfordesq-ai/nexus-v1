<?php
/**
 * Smart Match Monitoring Dashboard - Admin Interface
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

$tenantId = TenantContext::getId();
$basePath = TenantContext::getBasePath();

// Get statistics
$stats = [
    'total_users' => 0,
    'users_in_groups' => 0,
    'users_without_groups' => 0,
    'avg_groups_per_user' => 0,
    'total_hub_groups' => 0,
    'groups_with_members' => 0,
    'groups_without_members' => 0,
    'last_matching_run' => null,
];

try {
    // Total active users
    $stats['total_users'] = Database::query("
        SELECT COUNT(*) as c FROM users WHERE tenant_id = ? AND status = 'active'
    ", [$tenantId])->fetch()['c'] ?? 0;

    // Users in hub groups
    $stats['users_in_groups'] = Database::query("
        SELECT COUNT(DISTINCT gm.user_id) as c
        FROM group_members gm
        JOIN `groups` g ON g.id = gm.group_id
        WHERE g.tenant_id = ? AND g.type_id = 26
    ", [$tenantId])->fetch()['c'] ?? 0;

    $stats['users_without_groups'] = $stats['total_users'] - $stats['users_in_groups'];

    // Average groups per user
    $avgResult = Database::query("
        SELECT AVG(group_count) as avg_groups
        FROM (
            SELECT COUNT(*) as group_count
            FROM group_members gm
            JOIN `groups` g ON g.id = gm.group_id
            WHERE g.tenant_id = ? AND g.type_id = 26
            GROUP BY gm.user_id
        ) as counts
    ", [$tenantId])->fetch();
    $stats['avg_groups_per_user'] = round($avgResult['avg_groups'] ?? 0, 1);

    // Hub groups stats
    $stats['total_hub_groups'] = Database::query("
        SELECT COUNT(*) as c FROM `groups` WHERE tenant_id = ? AND type_id = 26
    ", [$tenantId])->fetch()['c'] ?? 0;

    $stats['groups_with_members'] = Database::query("
        SELECT COUNT(DISTINCT g.id) as c
        FROM `groups` g
        JOIN group_members gm ON gm.group_id = g.id
        WHERE g.tenant_id = ? AND g.type_id = 26
    ", [$tenantId])->fetch()['c'] ?? 0;

    $stats['groups_without_members'] = $stats['total_hub_groups'] - $stats['groups_with_members'];

} catch (Exception $e) {
    // Handle errors gracefully
}

// Get distribution data
$distribution = [];
try {
    $distResult = Database::query("
        SELECT
            g.name as group_name,
            COUNT(gm.user_id) as member_count
        FROM `groups` g
        LEFT JOIN group_members gm ON gm.group_id = g.id
        WHERE g.tenant_id = ? AND g.type_id = 26
        GROUP BY g.id, g.name
        ORDER BY member_count DESC
        LIMIT 20
    ", [$tenantId])->fetchAll();
    $distribution = $distResult;
} catch (Exception $e) {
    // Handle errors
}

// Admin header configuration
$adminPageTitle = 'Smart Match Monitoring';
$adminPageSubtitle = 'Community';
$adminPageIcon = 'fa-chart-line';

require __DIR__ . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Smart Match Monitoring
        </h1>
        <p class="admin-page-subtitle">Track user-to-group assignment success and distribution</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/smart-match-users" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            Run Smart Matching
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card stat-primary">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
            <div class="stat-label">Total Active Users</div>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['users_in_groups']) ?></div>
            <div class="stat-label">Users in Groups</div>
            <div class="stat-sublabel"><?= $stats['total_users'] > 0 ? round(($stats['users_in_groups'] / $stats['total_users']) * 100, 1) : 0 ?>% coverage</div>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fa-solid fa-user-xmark"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['users_without_groups']) ?></div>
            <div class="stat-label">Users Without Groups</div>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon"><i class="fa-solid fa-chart-bar"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['avg_groups_per_user'] ?></div>
            <div class="stat-label">Avg Groups Per User</div>
        </div>
    </div>

    <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['total_hub_groups']) ?></div>
            <div class="stat-label">Total Hub Groups</div>
        </div>
    </div>

    <div class="stat-card stat-cyan">
        <div class="stat-icon"><i class="fa-solid fa-users-rectangle"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['groups_with_members']) ?></div>
            <div class="stat-label">Groups with Members</div>
            <div class="stat-sublabel"><?= $stats['groups_without_members'] ?> empty</div>
        </div>
    </div>
</div>

<!-- Group Distribution -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-chart-column"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Member Distribution by Group</h3>
            <p class="admin-card-subtitle">Top 20 groups by member count</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($distribution)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon"><i class="fa-solid fa-chart-column"></i></div>
            <h3 class="admin-empty-title">No data yet</h3>
            <p class="admin-empty-text">Run smart matching to assign users to groups</p>
            <a href="<?= $basePath ?>/admin-legacy/smart-match-users" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                Run Smart Matching
            </a>
        </div>
        <?php else: ?>
        <div class="distribution-table">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th style="text-align: center;">Members</th>
                        <th>Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $maxMembers = max(1, max(array_column($distribution, 'member_count')));
                    foreach ($distribution as $group):
                        $percent = ($group['member_count'] / $maxMembers) * 100;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($group['group_name']) ?></strong></td>
                        <td style="text-align: center;"><span class="member-count"><?= number_format($group['member_count']) ?></span></td>
                        <td>
                            <div class="bar-wrapper">
                                <div class="bar" style="width: <?= $percent ?>%;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Health Indicators -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-green">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">System Health</h3>
            <p class="admin-card-subtitle">Key indicators for smart matching effectiveness</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="health-grid">
            <div class="health-item <?= $stats['users_in_groups'] / max(1, $stats['total_users']) >= 0.8 ? 'health-good' : 'health-warning' ?>">
                <div class="health-icon">
                    <i class="fa-solid fa-<?= $stats['users_in_groups'] / max(1, $stats['total_users']) >= 0.8 ? 'check' : 'exclamation' ?>-circle"></i>
                </div>
                <div class="health-content">
                    <div class="health-label">User Coverage</div>
                    <div class="health-value"><?= $stats['total_users'] > 0 ? round(($stats['users_in_groups'] / $stats['total_users']) * 100, 1) : 0 ?>%</div>
                    <div class="health-desc"><?= $stats['users_in_groups'] / max(1, $stats['total_users']) >= 0.8 ? 'Excellent' : 'Needs improvement' ?></div>
                </div>
            </div>

            <div class="health-item <?= $stats['groups_without_members'] == 0 ? 'health-good' : 'health-info' ?>">
                <div class="health-icon">
                    <i class="fa-solid fa-<?= $stats['groups_without_members'] == 0 ? 'check' : 'info' ?>-circle"></i>
                </div>
                <div class="health-content">
                    <div class="health-label">Empty Groups</div>
                    <div class="health-value"><?= $stats['groups_without_members'] ?></div>
                    <div class="health-desc"><?= $stats['groups_without_members'] == 0 ? 'All groups populated' : 'Some groups empty' ?></div>
                </div>
            </div>

            <div class="health-item <?= $stats['avg_groups_per_user'] >= 1.5 ? 'health-good' : 'health-info' ?>">
                <div class="health-icon">
                    <i class="fa-solid fa-<?= $stats['avg_groups_per_user'] >= 1.5 ? 'check' : 'info' ?>-circle"></i>
                </div>
                <div class="health-content">
                    <div class="health-label">Parent Assignment</div>
                    <div class="health-value"><?= $stats['avg_groups_per_user'] ?></div>
                    <div class="health-desc"><?= $stats['avg_groups_per_user'] >= 1.5 ? 'Parent cascade working' : 'Check parent groups' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Gold Standard FDS Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

@keyframes slideIn {
    from {
        transform: scaleX(0);
        opacity: 0;
    }
    to {
        transform: scaleX(1);
        opacity: 1;
    }
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: fadeInUp 0.5s ease-out backwards;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2), 0 0 0 1px rgba(99, 102, 241, 0.3);
    border-color: rgba(99, 102, 241, 0.3);
}

.stat-card:hover::before {
    left: 100%;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.15s; }
.stat-card:nth-child(3) { animation-delay: 0.2s; }
.stat-card:nth-child(4) { animation-delay: 0.25s; }
.stat-card:nth-child(5) { animation-delay: 0.3s; }
.stat-card:nth-child(6) { animation-delay: 0.35s; }

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
}

.stat-primary .stat-icon { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.stat-success .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
.stat-warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-info .stat-icon { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.stat-purple .stat-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.stat-cyan .stat-icon { background: linear-gradient(135deg, #06b6d4, #14b8a6); }

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    transition: all 0.3s ease;
}

.stat-card:hover .stat-value {
    transform: scale(1.05);
}

.stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    transition: color 0.3s ease;
}

.stat-card:hover .stat-label {
    color: rgba(255, 255, 255, 0.8);
}

.stat-sublabel {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.25rem;
    transition: color 0.3s ease;
}

.stat-card:hover .stat-sublabel {
    color: rgba(255, 255, 255, 0.6);
}

/* Distribution Table */
.distribution-table {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s ease;
}

.admin-table tbody tr {
    transition: all 0.2s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
    transform: scale(1.01);
}

.admin-table tbody tr:hover td {
    color: #fff;
}

.member-count {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    font-weight: 700;
    color: #818cf8;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
}

.admin-table tbody tr:hover .member-count {
    background: rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.bar-wrapper {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
}

.bar {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    border-radius: 4px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    animation: slideIn 0.8s ease-out;
    position: relative;
    overflow: hidden;
}

.bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    background-size: 200% 100%;
    animation: shimmer 2s linear infinite;
}

.admin-table tbody tr:hover .bar {
    background: linear-gradient(90deg, #818cf8, #a78bfa);
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.5);
}

/* Health Grid */
.health-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.health-item {
    padding: 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 3px solid;
    animation: fadeInUp 0.5s ease-out backwards;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.health-item:nth-child(1) { animation-delay: 0.5s; }
.health-item:nth-child(2) { animation-delay: 0.55s; }
.health-item:nth-child(3) { animation-delay: 0.6s; }

.health-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.health-good {
    border-left-color: #10b981;
}

.health-good:hover {
    background: rgba(16, 185, 129, 0.05);
    border-left-color: #34d399;
}

.health-warning {
    border-left-color: #f59e0b;
}

.health-warning:hover {
    background: rgba(245, 158, 11, 0.05);
    border-left-color: #fbbf24;
}

.health-info {
    border-left-color: #3b82f6;
}

.health-info:hover {
    background: rgba(59, 130, 246, 0.05);
    border-left-color: #60a5fa;
}

.health-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.health-item:hover .health-icon {
    transform: scale(1.1) rotate(5deg);
}

.health-good .health-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.health-good:hover .health-icon {
    background: rgba(16, 185, 129, 0.3);
    color: #34d399;
}

.health-warning .health-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.health-warning:hover .health-icon {
    background: rgba(245, 158, 11, 0.3);
    color: #fbbf24;
}

.health-info .health-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.health-info:hover .health-icon {
    background: rgba(59, 130, 246, 0.3);
    color: #60a5fa;
}

.health-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: color 0.3s ease;
}

.health-item:hover .health-label {
    color: rgba(255, 255, 255, 0.8);
}

.health-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0.25rem 0;
    transition: all 0.3s ease;
}

.health-item:hover .health-value {
    transform: scale(1.05);
}

.health-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    transition: color 0.3s ease;
}

.health-item:hover .health-desc {
    color: rgba(255, 255, 255, 0.7);
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 3rem 2rem;
    animation: fadeInUp 0.5s ease-out;
}

.admin-empty-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    border-radius: 16px;
    background: rgba(139, 92, 246, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    animation: pulse 2s ease-in-out infinite;
}

.admin-empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
    font-size: 0.9rem;
}

/* Card Headers */
.admin-card-header-icon-green {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

.admin-glass-card {
    animation: fadeInUp 0.5s ease-out backwards;
}

.admin-glass-card:nth-of-type(2) { animation-delay: 0.4s; }
.admin-glass-card:nth-of-type(3) { animation-delay: 0.45s; }

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .health-grid { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
