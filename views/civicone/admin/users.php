<?php
// CivicOne View: Admin Users
$pageTitle = 'Manage Users';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; border-bottom: 4px solid var(--skin-primary); padding-bottom: 15px;">
        <div>
            <h1 style="margin: 0; text-transform: uppercase; color: var(--skin-primary);">Manage Users</h1>
            <div style="margin-top: 5px;">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin" style="color: var(--civic-text-secondary, #4B5563); text-decoration: none;">&larr; Back to Dashboard</a>
            </div>
        </div>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/users/export" class="civic-btn" style="background: #555; font-size: 0.9rem;">Export CSV</a>
    </div>

    <div class="civic-card">
        <table style="width: 100%; border-collapse: collapse;" aria-label="User management table">
            <caption class="visually-hidden">List of registered users with their details and management actions</caption>
            <thead>
                <tr style="background: #f5f5f5; text-align: left;">
                    <th scope="col" style="padding: 12px; border-bottom: 2px solid #ddd;">Name</th>
                    <th scope="col" style="padding: 12px; border-bottom: 2px solid #ddd;">Email</th>
                    <th scope="col" style="padding: 12px; border-bottom: 2px solid #ddd;">Role</th>
                    <th scope="col" style="padding: 12px; border-bottom: 2px solid #ddd;">Status</th>
                    <th scope="col" style="padding: 12px; border-bottom: 2px solid #ddd;">Joined</th>
                    <th scope="col" style="padding: 12px; border-bottom: 2px solid #ddd; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px;">
                            <strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                        </td>
                        <td style="padding: 12px; color: #555;"><?= htmlspecialchars($u['email']) ?></td>
                        <td style="padding: 12px;">
                            <span style="background: <?= $u['role'] === 'admin' ? '#e0f2fe' : '#f3f4f6' ?>; color: <?= $u['role'] === 'admin' ? '#0369a1' : '#374151' ?>; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td style="padding: 12px;">
                            <?php if ($u['is_approved']): ?>
                                <span style="color: #166534; font-weight: bold; font-size: 0.9rem;">Active</span>
                            <?php else: ?>
                                <span style="color: #dc2626; font-weight: bold; font-size: 0.9rem;">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; color: var(--civic-text-secondary, #4B5563); font-size: 0.9rem;">
                            <?= date('M j, Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/users/<?= $u['id'] ?>/edit" style="color: var(--skin-primary); font-weight: bold; text-decoration: none; margin-right: 10px;">Edit</a>
                            <?php if (!$u['is_approved']): ?>
                                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/approve-user" method="POST" style="display: inline;">
                                    <?= Nexus\Core\Csrf::input() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" style="background: none; border: none; color: #166534; font-weight: bold; cursor: pointer; text-decoration: underline;">Approve</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>