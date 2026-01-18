<?php
// CivicOne View: Admin Category Manager
// Path: views/civicone/admin/categories/index.php

$hTitle = 'Category Manager';
$hSubtitle = 'Organize Listings & Opportunities';

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="civic-container" style="padding-top: 40px; padding-bottom: 60px;">
    <!-- Centered Container -->
    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 40px;">

        <div style="border: 1px solid #e5e7eb; border-radius: 8px; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <header style="border-bottom: 1px solid #e5e7eb; padding: 20px 25px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="background:#8b5cf6; color:white; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">📂</div>
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color: #111827;">Category Manager</h3>
                        <div style="font-size:0.85rem; color: #6b7280;">Defined taxonomies for this community</div>
                    </div>
                </div>
                <!-- Action Button styled for CivicOne -->
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/categories/create" style="background:var(--civic-brand, #00796B); color:white; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600;">
                    + New Category
                </a>
            </header>

            <div style="padding: 30px;">
                <!-- Inner Box for Table -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                    <table style="width:100%; border-collapse: collapse;" aria-label="Category management">
                        <caption class="visually-hidden">List of categories for organizing listings and opportunities</caption>
                        <thead style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                            <tr>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Name</th>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Modules</th>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Color Tag</th>
                                <th scope="col" style="padding:15px 20px; text-align:right; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="4" style="padding:40px; text-align:center; color:#94a3b8;">
                                        No categories defined yet. Create your first one!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0; background: #fff; transition: background 0.1s;">
                                        <td style="padding:15px 20px;">
                                            <div style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($cat['name']) ?></div>
                                            <div style="font-size:0.8rem; color:#94a3b8; font-family:monospace;">/<?= htmlspecialchars($cat['slug']) ?></div>
                                        </td>
                                        <td style="padding:15px 20px;">
                                            <span style="background:<?= $cat['type'] === 'vol_opportunity' ? '#ecfdf5' : '#eff6ff' ?>; color:<?= $cat['type'] === 'vol_opportunity' ? '#059669' : '#1d4ed8' ?>; padding: 4px 10px; border-radius: 6px; font-size:0.8rem; font-weight:600; border:1px solid transparent;">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $cat['type']))) ?>
                                            </span>
                                        </td>
                                        <td style="padding:15px 20px;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="width:16px; height:16px; border-radius:50%; background-color: var(--nexus-<?= $cat['color'] ?>-500, <?= $cat['color'] ?>); border:1px solid rgba(0,0,0,0.1);"></span>
                                                <span style="font-size:0.85rem; color:#475569; text-transform:capitalize;"><?= htmlspecialchars($cat['color']) ?></span>
                                            </div>
                                        </td>
                                        <td style="padding:15px 20px; text-align:right;">
                                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/categories/edit/<?= $cat['id'] ?>" style="display:inline-block; margin-right:5px; padding:4px 10px; background:#f3f4f6; color:#374151; border-radius:4px; text-decoration:none; font-size:0.85rem; font-weight:600;">Edit</a>

                                            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/categories/delete" method="POST" onsubmit="return confirm('Delete this category?');" style="display:inline;">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
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