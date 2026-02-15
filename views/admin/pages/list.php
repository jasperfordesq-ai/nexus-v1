<?php
// $pageTitle is set in Controller/View logic, but usually we dynamic it
$tenantName = Nexus\Core\TenantContext::get()['name'] ?? 'Nexus TimeBank';
$pageTitle = "Page Builder - $tenantName";
$isSocial = false;

?>

<div class="container htb-page-wrapper" style="padding-top: 40px;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h1>Custom Pages</h1>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages/create?confirm=1" class="htb-btn htb-btn-primary">Create New Page</a>
    </div>

    <div class="glass-panel">
        <table class="htb-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pages)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 20px;">No pages found. Create one to get started!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?= htmlspecialchars($page['title']) ?></td>
                            <td><code><?= Nexus\Core\TenantContext::getBasePath() ?>/page/<?= htmlspecialchars($page['slug']) ?></code></td>
                            <td>
                                <?php if ($page['is_published']): ?>
                                    <span style="color: green; font-weight: bold;">Published</span>
                                <?php else: ?>
                                    <span style="color: orange;">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $page['menu_order'] ?></td>
                            <td>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages/builder?id=<?= $page['id'] ?>" class="htb-btn htb-btn-sm">Edit</a>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/page/<?= $page['slug'] ?>" target="_blank" class="htb-btn htb-btn-sm htb-btn-secondary">View</a>
                                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages/delete" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                    <input type="hidden" name="id" value="<?= $page['id'] ?>">
                                    <button type="submit" class="htb-btn htb-btn-sm" style="background:var(--del-color, #ef4444); padding:4px 8px; font-size:0.8rem;">Del</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php  ?>