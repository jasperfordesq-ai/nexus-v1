<?php
// CivicOne View: Admin Attribute Manager
// Path: views/civicone/admin-legacy/attributes/index.php

$hTitle = 'Service Attributes';
$hSubtitle = 'Tags & Requirements for Listings';

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="civic-container" style="padding-top: 40px; padding-bottom: 60px;">
    <!-- Centered Container -->
    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 40px;">

        <div style="border: 1px solid #e5e7eb; border-radius: 8px; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <header style="border-bottom: 1px solid #e5e7eb; padding: 20px 25px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="background:#059669; color:white; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">🏷️</div>
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color: #111827;">Attribute Manager</h3>
                        <div style="font-size:0.85rem; color: #6b7280;">Manage tags like "Garda Vetted" or "Wheelchair Access"</div>
                    </div>
                </div>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/attributes/create" style="background:var(--civic-brand, #00796B); color:white; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600;">
                    + New Attribute
                </a>
            </header>

            <div style="padding: 30px;">
                <!-- Inner Box for Table -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                    <table style="width:100%; border-collapse: collapse;" aria-label="Service attributes management">
                        <caption class="visually-hidden">List of service attributes like tags and requirements for listings</caption>
                        <thead style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                            <tr>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Attribute Name</th>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Scope / Category</th>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Input Type</th>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Status</th>
                                <th scope="col" style="padding:15px 20px; text-align:right; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attributes)): ?>
                                <tr>
                                    <td colspan="5" style="padding:40px; text-align:center; color:#94a3b8;">
                                        No attributes defined yet. Create your first one!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attributes as $attr): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0; background: #fff; transition: background 0.1s;">
                                        <td style="padding:15px 20px;">
                                            <span style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($attr['name']) ?></span>
                                        </td>
                                        <td style="padding:15px 20px;">
                                            <?php if (!empty($attr['category_name'])): ?>
                                                <span style="background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight:600;">
                                                    <?= htmlspecialchars($attr['category_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #f3f4f6; color: #4b5563; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight:600;">
                                                    Global
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:15px 20px;">
                                            <span style="font-family: monospace; font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                                <?= strtoupper($attr['input_type']) ?>
                                            </span>
                                        </td>
                                        <td style="padding:15px 20px;">
                                            <?php if ($attr['is_active']): ?>
                                                <span style="color:#16a34a; font-size:0.85rem; font-weight:600;">● Active</span>
                                            <?php else: ?>
                                                <span style="color:#ef4444; font-size:0.85rem; font-weight:600;">○ Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:15px 20px; text-align:right;">
                                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/attributes/edit/<?= $attr['id'] ?>" style="display:inline-block; margin-right:5px; padding:4px 10px; background:#f3f4f6; color:#374151; border-radius:4px; text-decoration:none; font-size:0.85rem; font-weight:600;">Edit</a>

                                            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/attributes/delete" method="POST" onsubmit="return confirm('Delete this attribute?');" style="display:inline;">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="id" value="<?= $attr['id'] ?>">
                                                <button type="submit" style="background:none; border:none; padding:0 5px; color:#ef4444; font-weight:bold; cursor:pointer; font-size:1.1rem;">&times;</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>