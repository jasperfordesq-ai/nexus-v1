<?php
// Admin Listings Index View
$isPending = ($currentStatus ?? '') === 'pending';
?>

<div class="fds-feed-container" style="max-width: 1200px; margin: 20px auto;">

    <!-- Title -->
    <div class="fds-surface fb-card p-4 mb-4" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; margin: 0;"><?= htmlspecialchars($pageTitle ?? 'Global Content Directory') ?></h1>
            <p style="color: var(--text-muted); margin: 5px 0 0;">
                <?= $isPending ? 'Review and approve pending listings.' : 'Manage marketplace, events, polls, and more across all tenants.' ?>
            </p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- Status Filter Tabs -->
            <div style="display: flex; gap: 4px; background: rgba(99, 102, 241, 0.1); padding: 4px; border-radius: 8px;">
                <a href="/admin-legacy/listings" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; <?= !$isPending ? 'background: #6366f1; color: white;' : 'color: var(--text-muted);' ?>">
                    All Content
                </a>
                <a href="/admin-legacy/listings?status=pending" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; <?= $isPending ? 'background: #f59e0b; color: white;' : 'color: var(--text-muted);' ?>">
                    <i class="fa-solid fa-clock" style="margin-right: 4px;"></i> Pending Review
                </a>
            </div>
            <!-- Tenant Filter -->
            <form method="GET" action="" style="display: inline-block;">
                <?php if ($isPending): ?>
                    <input type="hidden" name="status" value="pending">
                <?php endif; ?>
                <select name="tenant_id" onchange="this.form.submit()" style="padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--surface); color: var(--text-main); font-size: 13px;">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>" <?= (isset($currentTenantId) && $currentTenantId == $tenant['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Listings Table -->
    <div class="fds-surface fb-card p-0" style="overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-family: 'Inter', sans-serif;">
            <thead style="background: var(--bg-hover); border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-muted);">ID</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-muted);">Tenant</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-muted);">Listing</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-muted);">Author</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-muted);">Type</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-muted);">Created</th>
                    <th style="padding: 12px 16px; text-align: right; font-size: 13px; font-weight: 600; color: var(--text-muted);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($listings)): ?>
                    <tr>
                        <td colspan="7" style="padding: 30px; text-align: center; color: var(--text-muted);">No listings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($listings as $row): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 16px; color: var(--text-muted); font-family: monospace;">#<?= $row['id'] ?></td>
                            <td style="padding: 12px 16px;">
                                <span class="nexus-badge" style="background: #e4e6eb; color: #050505; font-weight: 600;">
                                    <?= htmlspecialchars($row['tenant_name'] ?? 'Unknown') ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: var(--text-main); font-size: 15px;"><?= htmlspecialchars($row['title']) ?></div>
                                <div style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars(substr($row['description'], 0, 50)) ?>...</div>
                            </td>
                            <td style="padding: 12px 16px; font-size: 14px;">
                                <?= htmlspecialchars($row['author_name'] ?? 'Unknown') ?>
                            </td>
                            <td style="padding: 12px 16px;">
                                <?php
                                $typeColor = '#6b7280'; // Default Gray
                                $typeLabel = $row['content_type']; // Default Label
                                $editUrl = '#';

                                switch ($row['content_type']) {
                                    case 'listing':
                                        $typeColor = ($row['type'] ?? '') === 'offer' ? '#e41e3f' : '#f97316';
                                        $typeLabel = ($row['type'] ?? 'listing');
                                        $editUrl = "/listings/edit/{$row['id']}";
                                        break;
                                    case 'event':
                                        $typeColor = '#3b82f6'; // Blue
                                        $editUrl = "/events/edit/{$row['id']}";
                                        break;
                                    case 'poll':
                                        $typeColor = '#8b5cf6'; // Purple
                                        $editUrl = "/polls/edit/{$row['id']}";
                                        break;
                                    case 'goal':
                                        $typeColor = '#10b981'; // Emerald
                                        $editUrl = "/goals/edit/{$row['id']}";
                                        break;
                                    case 'resource':
                                        $typeColor = '#06b6d4'; // Cyan
                                        $editUrl = "/resources/edit/{$row['id']}";
                                        break;
                                    case 'volunteer':
                                        $typeColor = '#ec4899'; // Pink
                                        $editUrl = "/volunteering/edit/{$row['id']}";
                                        break;
                                }
                                ?>
                                <span class="nexus-badge" style="background-color: <?= $typeColor ?>15; color: <?= $typeColor ?>; font-weight: 700; text-transform: uppercase; font-size: 11px; padding: 2px 8px; border-radius: 4px;">
                                    <?= htmlspecialchars($typeLabel) ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; font-size: 13px; color: var(--text-muted);">
                                <?= date('M j, Y', strtotime($row['created_at'])) ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <?php if ($isPending && $row['content_type'] === 'listing'): ?>
                                        <form method="POST" action="/admin-legacy/listings/approve/<?= $row['id'] ?>" style="margin: 0;">
                                            <button type="submit" class="fds-btn-primary" style="background: #10b981; border-color: #10b981; padding: 4px 10px; font-size: 13px;">
                                                <i class="fa-solid fa-check" style="margin-right: 4px;"></i> Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= $editUrl ?>" class="fds-btn-secondary" style="padding: 4px 10px; font-size: 13px; text-decoration: none;">Edit</a>
                                    <form method="POST" action="/admin-legacy/listings/delete/<?= $row['id'] ?>?type=<?= $row['content_type'] ?>" onsubmit="return confirm('Are you sure you want to delete this item?');" style="margin: 0;">
                                        <button type="submit" class="fds-btn-primary" style="background: #ef4444; border-color: #ef4444; padding: 4px 10px; font-size: 13px;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        $paginationParams = [];
        if ($isPending) $paginationParams['status'] = 'pending';
        if (!empty($currentTenantId)) $paginationParams['tenant_id'] = $currentTenantId;
        $baseQuery = !empty($paginationParams) ? '&' . http_build_query($paginationParams) : '';
        ?>
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?><?= $baseQuery ?>" class="fds-btn-secondary" style="<?= $i == $currentPage ? 'background: var(--primary-color); color: white;' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</div>