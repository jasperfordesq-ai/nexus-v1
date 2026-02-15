<?php
/**
 * User Monitoring Dashboard
 * Manage user messaging restrictions and monitoring flags
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'User Monitoring';
$adminPageSubtitle = 'Manage user messaging restrictions';
$adminPageIcon = 'fa-user-shield';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$users = $users ?? [];
$filter = $filter ?? 'all';
$page = $page ?? 1;
$totalCount = $total_count ?? 0;
$totalPages = $total_pages ?? 1;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            User Monitoring
        </h1>
        <p class="admin-page-subtitle">Manage user messaging restrictions and monitoring</p>
    </div>
</div>

<?php if ($flashSuccess): ?>
<div class="config-flash config-flash-success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="config-flash config-flash-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="admin-tabs">
    <a href="?filter=all" class="admin-tab <?= $filter === 'all' ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i> All Users
    </a>
    <a href="?filter=restricted" class="admin-tab admin-tab-danger <?= $filter === 'restricted' ? 'active' : '' ?>">
        <i class="fa-solid fa-ban"></i> Restricted
    </a>
    <a href="?filter=monitored" class="admin-tab admin-tab-warning <?= $filter === 'monitored' ? 'active' : '' ?>">
        <i class="fa-solid fa-eye"></i> Monitored
    </a>
    <a href="?filter=new_members" class="admin-tab <?= $filter === 'new_members' ? 'active' : '' ?>">
        <i class="fa-solid fa-user-plus"></i> New Members
    </a>
</div>

<!-- Quick Stats -->
<div class="monitoring-stats">
    <div class="stat-card">
        <div class="stat-icon stat-icon-danger">
            <i class="fa-solid fa-ban"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['restricted'] ?? 0 ?></div>
            <div class="stat-label">Messaging Disabled</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-warning">
            <i class="fa-solid fa-eye"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['monitored'] ?? 0 ?></div>
            <div class="stat-label">Under Monitoring</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-info">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['new_members'] ?? 0 ?></div>
            <div class="stat-label">New Members (30 days)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-success">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['first_contacts_today'] ?? 0 ?></div>
            <div class="stat-label">First Contacts Today</div>
        </div>
    </div>
</div>

<div class="admin-glass-card">
    <div class="admin-card-body">
        <?php if (empty($users)): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-user-check"></i>
            <h3>No Users Found</h3>
            <p>No users match the current filter.</p>
        </div>
        <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>First Contacts</th>
                        <th>Restrictions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar']) ?>" class="user-avatar" alt="">
                                <?php else: ?>
                                <div class="user-avatar user-avatar-placeholder">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <?php endif; ?>
                                <div class="user-info">
                                    <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'Unknown') ?></span>
                                    <span class="user-email"><?= htmlspecialchars($user['email'] ?? '') ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="join-date"><?= isset($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '-' ?></span>
                            <?php
                            $daysAgo = isset($user['created_at']) ? floor((time() - strtotime($user['created_at'])) / 86400) : 999;
                            if ($daysAgo <= 30):
                            ?>
                            <span class="admin-badge admin-badge-info admin-badge-sm">New</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($user['messaging_disabled'])): ?>
                            <span class="admin-badge admin-badge-danger">Messaging Disabled</span>
                            <?php elseif (!empty($user['under_monitoring'])): ?>
                            <span class="admin-badge admin-badge-warning">Monitored</span>
                            <?php else: ?>
                            <span class="admin-badge admin-badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="first-contact-count"><?= $user['first_contact_count'] ?? 0 ?></span>
                            <?php if (($user['first_contacts_today'] ?? 0) > 0): ?>
                            <span class="admin-badge admin-badge-info admin-badge-sm">+<?= $user['first_contacts_today'] ?> today</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($user['restriction_reason'])): ?>
                            <span class="restriction-reason" title="<?= htmlspecialchars($user['restriction_reason']) ?>">
                                <?= htmlspecialchars(substr($user['restriction_reason'], 0, 30)) ?>...
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="<?= $basePath ?>/admin-legacy/users/<?= $user['id'] ?>"
                                   class="admin-btn admin-btn-secondary admin-btn-sm" title="View Profile">
                                    <i class="fa-solid fa-user"></i>
                                </a>
                                <button type="button" class="admin-btn admin-btn-primary admin-btn-sm"
                                        onclick="showMonitoringModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES) ?>', <?= !empty($user['messaging_disabled']) ? 'true' : 'false' ?>, <?= !empty($user['under_monitoring']) ? 'true' : 'false' ?>)"
                                        title="Set Monitoring">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="admin-pagination">
            <?php if ($page > 1): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Monitoring Modal -->
<div id="monitoringModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-shield-halved"></i> User Monitoring Settings</h3>
            <button type="button" class="modal-close" onclick="closeMonitoringModal()">&times;</button>
        </div>
        <form id="monitoringForm" method="POST">
            <?= Csrf::input() ?>
            <div class="modal-body">
                <p class="modal-user-name">User: <strong id="modalUserName"></strong></p>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="messaging_disabled" id="messaging_disabled" value="1">
                        <span class="checkbox-custom"></span>
                        <div class="checkbox-content">
                            <span class="checkbox-title">Disable Direct Messaging</span>
                            <span class="checkbox-desc">User cannot send or receive direct messages</span>
                        </div>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="under_monitoring" id="under_monitoring" value="1">
                        <span class="checkbox-custom"></span>
                        <div class="checkbox-content">
                            <span class="checkbox-title">Enhanced Monitoring</span>
                            <span class="checkbox-desc">All messages from this user are copied for broker review</span>
                        </div>
                    </label>
                </div>

                <div class="form-group">
                    <label for="restriction_reason" class="form-label">Reason (optional)</label>
                    <textarea name="reason" id="restriction_reason" class="admin-input" rows="3"
                              placeholder="Document the reason for these restrictions..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="closeMonitoringModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showMonitoringModal(userId, userName, messagingDisabled, underMonitoring) {
    const modal = document.getElementById('monitoringModal');
    const form = document.getElementById('monitoringForm');
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('messaging_disabled').checked = messagingDisabled;
    document.getElementById('under_monitoring').checked = underMonitoring;
    document.getElementById('restriction_reason').value = '';
    form.action = '<?= $basePath ?>/admin-legacy/broker-controls/monitoring/' + userId;
    modal.style.display = 'flex';
}

function closeMonitoringModal() {
    document.getElementById('monitoringModal').style.display = 'none';
}

document.getElementById('monitoringModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMonitoringModal();
    }
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
