<?php
// Layout: Default (Admin)
$layout = 'default';
?>

<div class="nexus-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px;">
        <h1>Manage News</h1>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/blog/create" class="nexus-btn-primary">
            + Write Article
        </a>
    </div>

    <div class="nexus-card" style="padding: 0; overflow: hidden;">
        <table class="nexus-table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                <tr>
                    <th style="padding: 15px; text-align: left; font-size: 0.85rem; text-transform: uppercase; color: #6b7280;">Title</th>
                    <th style="padding: 15px; text-align: left; font-size: 0.85rem; text-transform: uppercase; color: #6b7280;">Author</th>
                    <th style="padding: 15px; text-align: left; font-size: 0.85rem; text-transform: uppercase; color: #6b7280;">Status</th>
                    <th style="padding: 15px; text-align: left; font-size: 0.85rem; text-transform: uppercase; color: #6b7280;">Date</th>
                    <th style="padding: 15px; text-align: right; font-size: 0.85rem; text-transform: uppercase; color: #6b7280;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 15px;">
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/blog/edit/<?= $post['id'] ?>" style="font-weight: 600; color: #111827; text-decoration: none;">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                        </td>
                        <td style="padding: 15px; color: #4b5563;">
                            <?= htmlspecialchars($post['author_name']) ?>
                        </td>
                        <td style="padding: 15px;">
                            <?php if ($post['status'] === 'published'): ?>
                                <span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 600;">Published</span>
                            <?php else: ?>
                                <span style="background: #f3f4f6; color: #374151; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 600;">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px; color: #6b7280; font-size: 0.9rem;">
                            <?= date('M j, Y', strtotime($post['created_at'])) ?>
                        </td>
                        <td style="padding: 15px; text-align: right;">
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/blog/edit/<?= $post['id'] ?>" class="nexus-btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">Edit</a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/blog/builder/<?= $post['id'] ?>" class="nexus-btn-primary" style="padding: 6px 12px; font-size: 0.8rem; margin-left:5px;">Builder</a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>" target="_blank" class="nexus-link" style="margin-left: 10px; font-size: 0.8rem;">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($posts)): ?>
            <div style="padding: 40px; text-align: center; color: #6b7280;">
                No articles found. Start writing today!
            </div>
        <?php endif; ?>
    </div>
</div>