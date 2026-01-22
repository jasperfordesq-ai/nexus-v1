<?php
// Phoenix View: Global Users (Super Admin)
$hTitle = 'Global User Directory';
$hSubtitle = 'Manage Users Across All Tenants';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Super Admin';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="super-admin-wrapper">
    <div class="master-users-container">

        <div class="nexus-card">
            <header class="nexus-card-header master-users-card-header">
                <h3>All Users</h3>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin" class="nexus-btn nexus-btn-secondary">
                    &larr; Back to Dashboard
                </a>
            </header>
            <div class="nexus-card-body master-users-card-body">
                <table class="master-users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Tenant</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="master-users-id">#<?= $u['id'] ?></td>
                                <td>
                                    <div class="master-users-name"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                    <div class="master-users-email"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td>
                                    <?php if ($u['tenant_id'] == 1): ?>
                                        <span class="master-users-badge-platform">Platform</span>
                                    <?php else: ?>
                                        <span class="master-users-badge-tenant">
                                            <?= htmlspecialchars($u['tenant_name'] ?? 'Unknown') ?>
                                        </span>
                                        <div class="master-users-tenant-slug">
                                            /<?= htmlspecialchars($u['tenant_slug'] ?? '') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="master-users-role">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($u['is_approved'])): ?>
                                        <span class="master-users-status-active">Active</span>
                                    <?php else: ?>
                                        <span class="master-users-status-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="master-users-date">
                                    <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="text-right">
                                    <?php if (empty($u['is_approved'])): ?>
                                        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/users/approve" method="POST" class="master-users-action-form">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="nexus-btn nexus-btn-sm nexus-btn-primary master-users-btn-approve">
                                                Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/users/delete" method="POST" onsubmit="return confirm('Are you sure you want to PERMANENTLY delete this user? This cannot be undone.');" class="master-users-action-form">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="nexus-btn nexus-btn-sm nexus-btn-danger master-users-btn-delete">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Master Users CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-master-users.min.css">
