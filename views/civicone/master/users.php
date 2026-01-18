<?php
// Phoenix View: Global Users (Super Admin)
$hTitle = 'Global User Directory';
$hSubtitle = 'Manage Users Across All Tenants';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Super Admin';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="super-admin-wrapper">
    <div style="max-width: 1200px; margin: 0 auto;">

        <div class="nexus-card">
            <header class="nexus-card-header" style="padding: 20px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">All Users</h3>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin" class="nexus-btn nexus-btn-secondary">
                    &larr; Back to Dashboard
                </a>
            </header>
            <div class="nexus-card-body" style="padding: 0;">
                <table style="width:100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b;">ID</th>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b;">User</th>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b;">Tenant</th>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b;">Role</th>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b;">Status</th>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b;">Joined</th>
                            <th style="padding:15px 20px; text-align:right; font-size:0.8rem; text-transform:uppercase; color:#64748b;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding:15px 20px; color:#64748b;">#<?= $u['id'] ?></td>
                                <td style="padding:15px 20px;">
                                    <div style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                    <div style="font-size:0.85rem; color:#64748b;"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td style="padding:15px 20px;">
                                    <?php if ($u['tenant_id'] == 1): ?>
                                        <span style="background:#f1f5f9; color:#475569; padding:2px 6px; border-radius:4px; font-size:0.8rem;">Platform</span>
                                    <?php else: ?>
                                        <span style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-size:0.8rem;">
                                            <?= htmlspecialchars($u['tenant_name'] ?? 'Unknown') ?>
                                        </span>
                                        <div style="font-size:0.75rem; color:#94a3b8; margin-top:2px;">
                                            /<?= htmlspecialchars($u['tenant_slug'] ?? '') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:15px 20px;">
                                    <span style="text-transform:capitalize; color:#475569; font-weight:500;">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td style="padding:15px 20px;">
                                    <?php if (!empty($u['is_approved'])): ?>
                                        <span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:600;">Active</span>
                                    <?php else: ?>
                                        <span style="background:#fef9c3; color:#854d0e; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:600;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:15px 20px; color:#64748b; font-size:0.9rem;">
                                    <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td style="padding:15px 20px; text-align:right;">
                                    <?php if (empty($u['is_approved'])): ?>
                                        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/users/approve" method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="nexus-btn nexus-btn-sm nexus-btn-primary" style="padding:4px 10px; font-size:0.75rem; margin-right:5px; background:#22c55e; border-color:#16a34a;">
                                                Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/users/delete" method="POST" onsubmit="return confirm('Are you sure you want to PERMANENTLY delete this user? This cannot be undone.');" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="nexus-btn nexus-btn-sm nexus-btn-danger" style="padding:4px 10px; font-size:0.75rem; background:#ef4444; color:white; border:none; border-radius:4px; cursor:pointer;">
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

<style>
    .super-admin-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    /* Desktop spacing */
    @media (min-width: 601px) {
        .super-admin-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile responsiveness */
    @media (max-width: 600px) {
        .super-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }

        .super-admin-wrapper [style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
        }

        .super-admin-wrapper .nexus-card {
            border-radius: 12px;
        }

        .super-admin-wrapper table {
            font-size: 0.8rem;
        }
    }

    /* ========================================
       DARK MODE FOR MASTER USERS
       ======================================== */

    [data-theme="dark"] .nexus-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .nexus-card-header {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] .nexus-card-header h3 {
        color: #f1f5f9;
    }

    /* Table header */
    [data-theme="dark"] thead[style*="background: #f8fafc"] {
        background: rgba(30, 41, 59, 0.6) !important;
    }

    [data-theme="dark"] thead[style*="border-bottom: 1px solid #e2e8f0"] {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] table th[style*="color:#64748b"] {
        color: #94a3b8 !important;
    }

    /* Table rows */
    [data-theme="dark"] table tr[style*="border-bottom: 1px solid #e2e8f0"] {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] table td[style*="color:#64748b"] {
        color: #94a3b8 !important;
    }

    [data-theme="dark"] table td div[style*="color:#1e293b"] {
        color: #f1f5f9 !important;
    }

    /* Tenant badges */
    [data-theme="dark"] span[style*="background:#f1f5f9"][style*="color:#475569"] {
        background: rgba(51, 65, 85, 0.6) !important;
        color: #e2e8f0 !important;
    }

    [data-theme="dark"] span[style*="background:#e0f2fe"][style*="color:#0369a1"] {
        background: rgba(14, 165, 233, 0.2) !important;
        color: #7dd3fc !important;
    }

    [data-theme="dark"] div[style*="color:#94a3b8"] {
        color: #64748b !important;
    }

    /* Role text */
    [data-theme="dark"] span[style*="color:#475569"] {
        color: #e2e8f0 !important;
    }

    /* Status badges */
    [data-theme="dark"] span[style*="background:#dcfce7"][style*="color:#166534"] {
        background: rgba(22, 163, 74, 0.2) !important;
        color: #86efac !important;
    }

    [data-theme="dark"] span[style*="background:#fef9c3"][style*="color:#854d0e"] {
        background: rgba(234, 179, 8, 0.2) !important;
        color: #fde047 !important;
    }

    /* Buttons */
    [data-theme="dark"] .nexus-btn-secondary {
        background: rgba(51, 65, 85, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #e2e8f0 !important;
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>