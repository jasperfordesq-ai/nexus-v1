<?php
/**
 * Group Detail View
 * Path: views/modern/admin-legacy/groups/view.php
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$group = $group ?? null;

if (!$group) {
    header("Location: $basePath/admin-legacy/groups");
    exit;
}

// Admin header configuration
$adminPageTitle = 'Group Details';
$adminPageSubtitle = htmlspecialchars($group['name']);
$adminPageIcon = 'fa-users-rectangle';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-users-rectangle"></i>
            <?= htmlspecialchars($group['name']) ?>
        </h1>
        <p class="admin-page-subtitle">Group Details & Management</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/groups" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>/analytics" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-line"></i> View Analytics
        </a>
        <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="admin-btn admin-btn-primary" target="_blank">
            <i class="fa-solid fa-external-link"></i> View Public Page
        </a>
    </div>
</div>

<!-- Group Info Card -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-info-circle"></i> Group Information</h3>
    </div>
    <div class="admin-card-body">
        <div class="admin-detail-grid">
            <div class="admin-detail-item">
                <span class="admin-detail-label">Name:</span>
                <span class="admin-detail-value"><?= htmlspecialchars($group['name']) ?></span>
            </div>
            <div class="admin-detail-item">
                <span class="admin-detail-label">Type:</span>
                <span class="admin-detail-value">
                    <span class="admin-badge admin-badge-primary"><?= htmlspecialchars($groupDetails['type_name'] ?? 'N/A') ?></span>
                </span>
            </div>
            <div class="admin-detail-item">
                <span class="admin-detail-label">Members:</span>
                <span class="admin-detail-value"><?= number_format($groupDetails['member_count'] ?? 0) ?></span>
            </div>
            <div class="admin-detail-item">
                <span class="admin-detail-label">Pending Requests:</span>
                <span class="admin-detail-value"><?= number_format($groupDetails['pending_requests'] ?? 0) ?></span>
            </div>
            <div class="admin-detail-item">
                <span class="admin-detail-label">Discussions:</span>
                <span class="admin-detail-value"><?= number_format($groupDetails['discussion_count'] ?? 0) ?></span>
            </div>
            <div class="admin-detail-item">
                <span class="admin-detail-label">Created:</span>
                <span class="admin-detail-value"><?= date('M j, Y', strtotime($group['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Members List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-users"></i> Members (<?= count($members ?? []) ?>)</h3>
    </div>
    <div class="admin-card-body admin-table-responsive">
        <?php if (!empty($members)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?= htmlspecialchars($member['name']) ?></td>
                            <td><span class="admin-badge admin-badge-<?= $member['role'] === 'owner' ? 'danger' : 'secondary' ?>"><?= htmlspecialchars($member['role']) ?></span></td>
                            <td><?= date('M j, Y', strtotime($member['joined_at'])) ?></td>
                            <td>
                                <a href="<?= $basePath ?>/admin-legacy/users/view?id=<?= $member['user_id'] ?>" class="admin-btn admin-btn-sm admin-btn-secondary">
                                    <i class="fa-solid fa-user"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-users"></i>
                <p>No members yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Audit Log -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-history"></i> Recent Activity</h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($auditLog)): ?>
            <div class="admin-activity-list">
                <?php foreach ($auditLog as $log): ?>
                    <div class="admin-activity-item">
                        <div class="admin-activity-icon"><i class="fa-solid fa-circle"></i></div>
                        <div class="admin-activity-content">
                            <div class="admin-activity-text"><?= htmlspecialchars($log['action']) ?></div>
                            <div class="admin-activity-time"><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-history"></i>
                <p>No activity yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.admin-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
.admin-detail-item { display: flex; flex-direction: column; gap: 4px; }
.admin-detail-label { font-size: 0.875rem; color: rgba(255,255,255,0.5); }
.admin-detail-value { font-weight: 600; color: #fff; }
.admin-activity-list { display: flex; flex-direction: column; gap: 16px; }
.admin-activity-item { display: flex; gap: 12px; }
.admin-activity-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: rgba(99, 102, 241, 0.15); border-radius: 8px; color: #818cf8; }
.admin-activity-content { flex: 1; }
.admin-activity-text { color: #fff; }
.admin-activity-time { font-size: 0.875rem; color: rgba(255,255,255,0.5); }
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
