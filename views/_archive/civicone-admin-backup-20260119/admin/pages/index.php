<?php
// CivicOne View: Admin Page Manager
// Path: views/civicone/admin-legacy/pages/index.php

$hTitle = 'Page Manager';
$hSubtitle = 'CMS & Content';

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="civic-container" style="padding-top: 40px; padding-bottom: 60px;">
    <!-- Centered Container -->
    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 40px;">

        <div style="border: 1px solid #e5e7eb; border-radius: 8px; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <header style="border-bottom: 1px solid #e5e7eb; padding: 20px 25px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="background:#00796B; color:white; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">📝</div>
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color: #111827;">Custom Pages</h3>
                        <div style="font-size:0.85rem; color: #6b7280;">Manage static content pages</div>
                    </div>
                </div>
                <!-- Action Button styled for CivicOne -->
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages/create?confirm=1" style="background:var(--civic-brand, #00796B); color:white; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600;">
                    + New Page
                </a>
            </header>

            <div style="padding: 30px;">
                <!-- Inner Box for Table -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                    <table style="width:100%; border-collapse: collapse;" aria-label="Custom pages management">
                        <caption class="visually-hidden">List of custom static content pages</caption>
                        <thead style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                            <tr>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Title</th>
                                <th scope="col" style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Slug / URL</th>
                                <th scope="col" style="padding:15px 20px; text-align:right; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pages)): ?>
                                <tr>
                                    <td colspan="3" style="padding:40px; text-align:center; color:#94a3b8;">
                                        No custom pages found. Create your first one!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pages as $p): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0; background: #fff; transition: background 0.1s;">
                                        <td style="padding:15px 20px; font-weight:600; color:#1e293b;">
                                            <?= htmlspecialchars($p['title']) ?>
                                        </td>
                                        <td style="padding:15px 20px; color:#64748b; font-family:monospace;">
                                            /page/<?= htmlspecialchars($p['slug']) ?>
                                        </td>
                                        <td style="padding:15px 20px; text-align:right;">
                                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages/builder/<?= $p['id'] ?>" style="display:inline-block; margin-right:5px; padding:4px 10px; background:#e0e7ff; color:#4338ca; border-radius:4px; text-decoration:none; font-size:0.85rem; font-weight:600;">Design</a>
                                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/page/<?= $p['slug'] ?>" target="_blank" style="display:inline-block; margin-right:5px; padding:4px 10px; background:#f3f4f6; color:#374151; border-radius:4px; text-decoration:none; font-size:0.85rem; font-weight:600;">View</a>

                                            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages/delete" method="POST" style="display:inline;" onsubmit="return confirm('Delete this page?');">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
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