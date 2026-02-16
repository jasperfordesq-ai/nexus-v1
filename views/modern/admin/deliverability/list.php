<?php
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'All Deliverables';
$adminPageSubtitle = 'Deliverability Tracking';
$adminPageIcon = 'fa-list';

require dirname(dirname(dirname(__DIR__))) . '/layouts/admin-header.php';

$deliverables = $deliverables ?? [];
$totalCount = $totalCount ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$filters = $filters ?? [];
$users = $users ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-list"></i>
            All Deliverables
        </h1>
        <p class="admin-page-subtitle">Manage and track project deliverables</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/deliverability" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="<?= $basePath ?>/admin-legacy/deliverability/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Deliverable
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_success'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-circle-check"></i>
    <?= htmlspecialchars($_SESSION['flash_success']) ?>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?= htmlspecialchars($_SESSION['flash_error']) ?>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- Filters - Enhanced with gradient styling -->
<div class="admin-glass-card" style="margin-bottom: 24px; border: 1px solid rgba(6, 182, 212, 0.25); box-shadow: 0 4px 20px rgba(6, 182, 212, 0.1);">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.3);">
            <i class="fa-solid fa-filter"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title" style="font-size: 1.125rem; letter-spacing: -0.02em;">Advanced Filters</h3>
            <p class="admin-card-subtitle">Narrow down your results • <?= $totalCount ?> total deliverables</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form method="GET" action="<?= $basePath ?>/admin-legacy/deliverability/list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">

            <div class="form-group" style="margin: 0;">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="ready" <?= ($filters['status'] ?? '') === 'ready' ? 'selected' : '' ?>>Ready</option>
                    <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="blocked" <?= ($filters['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    <option value="review" <?= ($filters['status'] ?? '') === 'review' ? 'selected' : '' ?>>Review</option>
                    <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="on_hold" <?= ($filters['status'] ?? '') === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?= ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    <option value="high" <?= ($filters['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="medium" <?= ($filters['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="low" <?= ($filters['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="assigned_to">Assigned To</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= ($filters['assigned_to'] ?? '') == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" placeholder="e.g., development"
                       value="<?= htmlspecialchars($filters['category'] ?? '') ?>">
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="overdue">Show Only</label>
                <select id="overdue" name="overdue">
                    <option value="">All Items</option>
                    <option value="true" <?= ($filters['overdue'] ?? '') === 'true' ? 'selected' : '' ?>>Overdue Only</option>
                </select>
            </div>

            <div style="display: flex; align-items: flex-end; gap: 8px;">
                <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1;">
                    <i class="fa-solid fa-search"></i> Apply Filters
                </button>
                <a href="<?= $basePath ?>/admin-legacy/deliverability/list" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-rotate-left"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stats Summary -->
<div class="admin-glass-card" style="margin-bottom: 24px;">
    <div class="admin-card-body">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 24px; font-weight: 700; color: #fff;"><?= number_format($totalCount) ?></span>
                <span style="margin-left: 8px; color: rgba(255,255,255,0.6);">
                    deliverable<?= $totalCount != 1 ? 's' : '' ?> found
                </span>
            </div>
            <div style="font-size: 13px; color: rgba(255,255,255,0.5);">
                Page <?= $page ?> of <?= max(1, $totalPages) ?>
            </div>
        </div>
    </div>
</div>

<!-- Deliverables Table -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-tasks"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Deliverables</h3>
            <p class="admin-card-subtitle"><?= number_format($totalCount) ?> total</p>
        </div>
    </div>
    <div class="admin-card-body">
        <?php if (empty($deliverables)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon"><i class="fa-solid fa-tasks"></i></div>
            <p class="admin-empty-title">No Deliverables Found</p>
            <p class="admin-empty-text">
                <?php if (!empty($filters)): ?>
                    Try adjusting your filters or clearing them.
                <?php else: ?>
                    Get started by creating your first deliverable.
                <?php endif; ?>
            </p>
            <a href="<?= $basePath ?>/admin-legacy/deliverability/create" class="admin-btn admin-btn-primary" style="margin-top: 16px;">
                <i class="fa-solid fa-plus"></i> Create Deliverable
            </a>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th class="hide-mobile">Assigned To</th>
                        <th class="hide-tablet">Status</th>
                        <th class="hide-tablet">Progress</th>
                        <th class="hide-mobile" style="text-align: center;">Priority</th>
                        <th class="hide-tablet">Due Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliverables as $deliverable): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($deliverable['title']) ?></strong>
                            <?php if (!empty($deliverable['tags'])): ?>
                            <div style="margin-top: 4px; display: flex; gap: 4px; flex-wrap: wrap;">
                                <?php foreach (array_slice($deliverable['tags'], 0, 3) as $tag): ?>
                                <span style="background: rgba(99,102,241,0.2); color: #a5b4fc; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?= htmlspecialchars($tag) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile">
                            <?php if ($deliverable['assigned_to']): ?>
                                <?= htmlspecialchars($deliverable['assigned_first_name'] . ' ' . $deliverable['assigned_last_name']) ?>
                            <?php elseif ($deliverable['assigned_group_name']): ?>
                                <i class="fa-solid fa-users"></i> <?= htmlspecialchars($deliverable['assigned_group_name']) ?>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.4);">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-tablet">
                            <?php
                            $statusColors = [
                                'draft' => 'secondary',
                                'ready' => 'info',
                                'in_progress' => 'active',
                                'blocked' => 'inactive',
                                'review' => 'pending',
                                'completed' => 'active',
                                'cancelled' => 'inactive',
                                'on_hold' => 'pending'
                            ];
                            $statusColor = $statusColors[$deliverable['status']] ?? 'secondary';
                            ?>
                            <span class="admin-status-badge admin-status-<?= $statusColor ?>">
                                <span class="admin-status-dot"></span> <?= ucwords(str_replace('_', ' ', $deliverable['status'])) ?>
                            </span>
                        </td>
                        <td class="hide-tablet">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; min-width: 80px;">
                                    <div style="height: 100%; width: <?= min(100, max(0, $deliverable['progress_percentage'] ?? 0)) ?>%; background: linear-gradient(135deg, #06b6d4, #6366f1);"></div>
                                </div>
                                <span style="font-size: 12px; min-width: 35px; text-align: right;"><?= number_format($deliverable['progress_percentage'] ?? 0, 0) ?>%</span>
                            </div>
                        </td>
                        <td class="hide-mobile" style="text-align: center;">
                            <?php
                            $priorityColors = [
                                'urgent' => 'background: #ef4444; color: #fff;',
                                'high' => 'background: #f59e0b; color: #fff;',
                                'medium' => 'background: #06b6d4; color: #fff;',
                                'low' => 'background: #10b981; color: #fff;'
                            ];
                            $priorityStyle = $priorityColors[$deliverable['priority']] ?? '';
                            ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; <?= $priorityStyle ?>">
                                <?= htmlspecialchars($deliverable['priority']) ?>
                            </span>
                        </td>
                        <td class="hide-tablet">
                            <?php if ($deliverable['due_date']): ?>
                                <?php
                                $dueTime = strtotime($deliverable['due_date']);
                                $isOverdue = $dueTime < time() && !in_array($deliverable['status'], ['completed', 'cancelled']);
                                ?>
                                <span style="color: <?= $isOverdue ? '#ef4444' : 'rgba(255,255,255,0.8)' ?>;">
                                    <?php if ($isOverdue): ?><i class="fa-solid fa-exclamation-triangle"></i> <?php endif; ?>
                                    <?= date('M j, Y', $dueTime) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.4);">No deadline</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/admin-legacy/deliverability/view/<?= $deliverable['id'] ?>"
                                   class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                                <a href="<?= $basePath ?>/admin-legacy/deliverability/edit/<?= $deliverable['id'] ?>"
                                   class="admin-btn admin-btn-info admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div style="margin-top: 24px; display: flex; justify-content: center; align-items: center; gap: 8px;">
            <?php if ($page > 1): ?>
            <a href="<?= $basePath ?>/admin-legacy/deliverability/list?page=<?= $page - 1 ?><?= !empty($filters) ? '&' . http_build_query($filters) : '' ?>"
               class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>

            <span style="color: rgba(255,255,255,0.6); font-size: 14px;">
                Page <?= $page ?> of <?= $totalPages ?>
            </span>

            <?php if ($page < $totalPages): ?>
            <a href="<?= $basePath ?>/admin-legacy/deliverability/list?page=<?= $page + 1 ?><?= !empty($filters) ? '&' . http_build_query($filters) : '' ?>"
               class="admin-btn admin-btn-secondary admin-btn-sm">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/admin-footer.php'; ?>
