<?php
// CivicOne View: Admin User Management
// Path: views/civicone/admin/users/index.php

$hTitle = 'Platform Members';
$hSubtitle = 'View and manage platform members';

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="civic-container" style="padding-top: 40px; padding-bottom: 60px;">
    <!-- Centered Container -->
    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 40px;">

        <!-- Main Card -->
        <div style="border: 1px solid #e5e7eb; border-radius: 8px; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid #eee;">
                <h2 style="font-size: 1.1rem; font-weight: 600; color: #111827; margin: 0;">User Directory</h2>
                <div style="background:#f3f4f6; color:#374151; padding:4px 12px; border-radius:20px; font-size:0.85rem; font-weight:600;"><?= count($users) ?> Members</div>
            </div>

            <div style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table style="width:100%; border-collapse: collapse;" aria-label="Platform members directory">
                        <caption class="visually-hidden">List of platform members with their profiles, roles, status, and management actions</caption>
                        <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                            <tr>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#6b7280; font-weight:700; width: 40%;">User Profile</th>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#6b7280; font-weight:700; width: 20%;">Role</th>
                                <th scope="col" style="padding:15px 20px; text-align:center; font-size:0.8rem; text-transform:uppercase; color:#6b7280; font-weight:700; width: 20%;">Status</th>
                                <th scope="col" style="padding:15px 20px; text-align:right; font-size:0.8rem; text-transform:uppercase; color:#6b7280; font-weight:700; width: 20%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 15px 20px;">
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <?php if ($user['avatar_url']): ?>
                                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border: 1px solid #e5e7eb;">
                                            <?php else: ?>
                                                <div style="width:42px; height:42px; border-radius:50%; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size: 1.2rem;">
                                                    👤
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 600; color: #111827; font-size: 0.95rem;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                                <div style="font-size: 0.85rem; color: #6b7280;"><?= htmlspecialchars($user['email']) ?></div>
                                                <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 2px;">Joined <?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td style="padding: 15px 20px;">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span style="background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:4px; font-size:0.85rem; font-weight:bold;">Administrator</span>
                                        <?php else: ?>
                                            <span style="background:#dbeafe; color:#1e40af; padding:4px 8px; border-radius:4px; font-size:0.85rem; font-weight:bold;">Member</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding: 15px 20px; text-align: center;">
                                        <?php if ($user['is_approved']): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 5px; color: #16a34a; font-weight: 600; font-size: 0.9rem;">
                                                <span style="display:block; width:8px; height:8px; background:#16a34a; border-radius:50%;"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 5px; color: #d97706; font-weight: 600; font-size: 0.9rem;">
                                                <span style="display:block; width:8px; height:8px; background:#d97706; border-radius:50%;"></span> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding: 15px 20px; text-align: right;">
                                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/users/edit/<?= $user['id'] ?>" style="background:#f3f4f6; color:#1f2937; padding:4px 10px; border-radius:4px; text-decoration:none; font-size:0.85rem; font-weight:600;">Edit</a>

                                            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/users/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');" style="display:inline;">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" style="background:#fee2e2; color:#991b1b; border:none; padding:4px 10px; border-radius:4px; font-size:0.85rem; font-weight:600; cursor:pointer;">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($users)): ?>
                    <div style="padding: 60px; text-align: center; color: #6b7280;">
                        <div style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;">👥</div>
                        <div style="font-size: 1.1rem; font-weight: 600;">No members found</div>
                        <p style="font-size: 0.9rem;">Your community is waiting to grow.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align: center; margin-top: 20px; color: #9ca3af; font-size: 0.85rem;">
            Member management helps you maintain a safe and active community.
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>