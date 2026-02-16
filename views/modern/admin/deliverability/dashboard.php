<?php
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Deliverability Tracking';
$adminPageSubtitle = 'Projects & Tasks';
$adminPageIcon = 'fa-tasks-alt';

require dirname(dirname(dirname(__DIR__))) . '/layouts/admin-header.php';

$analytics = $analytics ?? [];
$userDashboard = $userDashboard ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-tasks-alt"></i>
            Deliverability Tracking
        </h1>
        <p class="admin-page-subtitle">Manage project deliverables, milestones, and track progress</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/deliverability/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Deliverable
        </a>
        <a href="<?= $basePath ?>/admin-legacy/deliverability/analytics" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-line"></i> Analytics
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

<!-- Stats Grid - Enhanced with hover effects and gradients -->
<div class="admin-stats-grid" style="margin-bottom: 30px;">
    <div class="admin-stat-card admin-stat-blue" style="transition: all 0.3s ease; cursor: pointer;"
         onclick="window.location.href='<?= $basePath ?>/admin-legacy/deliverability/list';">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #3b82f6, #6366f1); box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);">
            <i class="fa-solid fa-tasks"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value" style="font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #3b82f6, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= number_format($analytics['overview']['total'] ?? 0) ?></div>
            <div class="admin-stat-label">Total Deliverables</div>
            <div style="margin-top: 4px; font-size: 11px; color: rgba(255,255,255,0.4);">
                <i class="fa-solid fa-chart-line"></i> All items
            </div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-cyan" style="transition: all 0.3s ease; cursor: pointer;"
         onclick="window.location.href='<?= $basePath ?>/admin-legacy/deliverability/list?status=in_progress';">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.3);">
            <i class="fa-solid fa-spinner fa-spin"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value" style="font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #06b6d4, #22d3ee); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= number_format($analytics['overview']['in_progress'] ?? 0) ?></div>
            <div class="admin-stat-label">In Progress</div>
            <div style="margin-top: 4px; font-size: 11px; color: rgba(255,255,255,0.4);">
                <i class="fa-solid fa-clock"></i> Active now
            </div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-green" style="transition: all 0.3s ease; cursor: pointer;"
         onclick="window.location.href='<?= $basePath ?>/admin-legacy/deliverability/list?status=completed';">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value" style="font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= number_format($analytics['overview']['completed'] ?? 0) ?></div>
            <div class="admin-stat-label">Completed</div>
            <div style="margin-top: 4px; font-size: 11px; color: rgba(255,255,255,0.4);">
                <i class="fa-solid fa-trophy"></i> <?= number_format($analytics['completion_rate'] ?? 0, 1) ?>% rate
            </div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-orange" style="transition: all 0.3s ease; cursor: pointer;"
         onclick="window.location.href='<?= $basePath ?>/admin-legacy/deliverability/list?status=blocked';">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value" style="font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #f59e0b, #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= number_format($analytics['overview']['blocked'] ?? 0) ?></div>
            <div class="admin-stat-label">Blocked</div>
            <div style="margin-top: 4px; font-size: 11px; color: rgba(255,255,255,0.4);">
                <i class="fa-solid fa-ban"></i> Needs attention
            </div>
        </div>
    </div>
</div>

<!-- Add hover CSS for stat cards -->
<style>
.admin-stat-card:hover {
    transform: translateY(-6px) scale(1.02);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.25);
}
</style>

<!-- Two Column Layout -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 30px;">

    <!-- Left Column: My Deliverables -->
    <div>
        <!-- My Active Deliverables -->
        <div class="admin-glass-card" style="margin-bottom: 24px; border: 1px solid rgba(6, 182, 212, 0.2); box-shadow: 0 4px 20px rgba(6, 182, 212, 0.1);">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.3);">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title" style="font-size: 1.25rem; letter-spacing: -0.02em;">My Active Deliverables</h3>
                    <p class="admin-card-subtitle" style="display: flex; align-items: center; gap: 6px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; background: rgba(6, 182, 212, 0.2); border-radius: 50%; font-size: 11px; font-weight: 700; color: #06b6d4;">
                            <?= count($userDashboard['my_deliverables'] ?? []) ?>
                        </span>
                        assigned to you
                    </p>
                </div>
                <div class="admin-card-header-actions">
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/list?assigned_to=<?= $_SESSION['user_id'] ?>"
                       class="admin-btn admin-btn-secondary admin-btn-sm"
                       style="background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(34, 211, 238, 0.1)); border-color: rgba(6, 182, 212, 0.3);">
                        <i class="fa-solid fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (empty($userDashboard['my_deliverables'])): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                    <p class="admin-empty-title">No Active Deliverables</p>
                    <p class="admin-empty-text">You don't have any deliverables assigned to you.</p>
                </div>
                <?php else: ?>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th class="hide-mobile">Status</th>
                                <th class="hide-mobile">Progress</th>
                                <th class="hide-tablet" style="text-align: center;">Priority</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($userDashboard['my_deliverables'], 0, 5) as $deliverable): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($deliverable['title']) ?></strong>
                                    <?php if ($deliverable['due_date'] && strtotime($deliverable['due_date']) < time() && $deliverable['status'] != 'completed'): ?>
                                    <span class="admin-status-badge admin-status-inactive" style="margin-left: 8px;">
                                        <i class="fa-solid fa-clock"></i> Overdue
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-mobile">
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
                                <td class="hide-mobile">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex: 1; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                                            <div style="height: 100%; width: <?= min(100, max(0, $deliverable['progress_percentage'] ?? 0)) ?>%; background: linear-gradient(135deg, #06b6d4, #6366f1); transition: width 0.3s;"></div>
                                        </div>
                                        <span style="font-size: 12px; color: rgba(255,255,255,0.6); min-width: 40px;"><?= number_format($deliverable['progress_percentage'] ?? 0, 0) ?>%</span>
                                    </div>
                                </td>
                                <td class="hide-tablet" style="text-align: center;">
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
                                <td style="text-align: center;">
                                    <a href="<?= $basePath ?>/admin-legacy/deliverability/view/<?= $deliverable['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-calendar-clock"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Upcoming Deadlines</h3>
                    <p class="admin-card-subtitle">Due in the next 7 days</p>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (empty($userDashboard['upcoming_deadlines'])): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <p class="admin-empty-title">No Upcoming Deadlines</p>
                    <p class="admin-empty-text">All clear for the next week!</p>
                </div>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach (array_slice($userDashboard['upcoming_deadlines'], 0, 5) as $deliverable): ?>
                    <div style="padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid #f59e0b;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <strong style="color: #fff;"><?= htmlspecialchars($deliverable['title']) ?></strong>
                            <span style="font-size: 12px; color: rgba(255,255,255,0.6);">
                                <i class="fa-solid fa-clock"></i> <?= date('M j', strtotime($deliverable['due_date'])) ?>
                            </span>
                        </div>
                        <div style="font-size: 12px; color: rgba(255,255,255,0.5);">
                            <?= number_format($deliverable['progress_percentage'] ?? 0, 0) ?>% complete •
                            <?= ucwords(str_replace('_', ' ', $deliverable['status'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Quick Actions & Stats -->
    <div>
        <!-- Quick Actions - Enhanced with gradient cards -->
        <div class="admin-glass-card" style="margin-bottom: 24px; border: 1px solid rgba(139, 92, 246, 0.2); box-shadow: 0 4px 20px rgba(139, 92, 246, 0.1);">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #a855f7); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.3);">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title" style="font-size: 1.25rem; letter-spacing: -0.02em;">Quick Actions</h3>
                    <p class="admin-card-subtitle">Common tasks</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/create"
                       style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 10px; text-decoration: none; color: #fff; transition: all 0.25s ease;"
                       onmouseover="this.style.transform='translateX(4px)'; this.style.borderColor='rgba(99, 102, 241, 0.5)'; this.style.background='linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(139, 92, 246, 0.2)';"
                       onmouseout="this.style.transform='translateX(0)'; this.style.borderColor='rgba(99, 102, 241, 0.25)'; this.style.background='linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1)';">
                        <div style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 8px;">
                            <i class="fa-solid fa-plus-circle"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;">New Deliverable</div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);">Create a new task</div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color: rgba(255,255,255,0.3); font-size: 12px;"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/list?status=in_progress"
                       style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(34, 211, 238, 0.1)); border: 1px solid rgba(6, 182, 212, 0.25); border-radius: 10px; text-decoration: none; color: #fff; transition: all 0.25s ease;"
                       onmouseover="this.style.transform='translateX(4px)'; this.style.borderColor='rgba(6, 182, 212, 0.5)'; this.style.background='linear-gradient(135deg, rgba(6, 182, 212, 0.25), rgba(34, 211, 238, 0.2)';"
                       onmouseout="this.style.transform='translateX(0)'; this.style.borderColor='rgba(6, 182, 212, 0.25)'; this.style.background='linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(34, 211, 238, 0.1)';">
                        <div style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: linear-gradient(135deg, #06b6d4, #22d3ee); border-radius: 8px;">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;">In Progress</div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);"><?= count(array_filter($userDashboard['my_deliverables'] ?? [], fn($d) => $d['status'] === 'in_progress')) ?> active</div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color: rgba(255,255,255,0.3); font-size: 12px;"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/list?status=review"
                       style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1)); border: 1px solid rgba(139, 92, 246, 0.25); border-radius: 10px; text-decoration: none; color: #fff; transition: all 0.25s ease;"
                       onmouseover="this.style.transform='translateX(4px)'; this.style.borderColor='rgba(139, 92, 246, 0.5)'; this.style.background='linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(168, 85, 247, 0.2)';"
                       onmouseout="this.style.transform='translateX(0)'; this.style.borderColor='rgba(139, 92, 246, 0.25)'; this.style.background='linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1)';">
                        <div style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: linear-gradient(135deg, #8b5cf6, #a855f7); border-radius: 8px;">
                            <i class="fa-solid fa-eye"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;">Need Review</div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);">Awaiting approval</div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color: rgba(255,255,255,0.3); font-size: 12px;"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/list?status=blocked"
                       style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.1)); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 10px; text-decoration: none; color: #fff; transition: all 0.25s ease;"
                       onmouseover="this.style.transform='translateX(4px)'; this.style.borderColor='rgba(245, 158, 11, 0.5)'; this.style.background='linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(251, 191, 36, 0.2)';"
                       onmouseout="this.style.transform='translateX(0)'; this.style.borderColor='rgba(245, 158, 11, 0.25)'; this.style.background='linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.1)';">
                        <div style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 8px;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;">Blocked</div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);">Needs attention</div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color: rgba(255,255,255,0.3); font-size: 12px;"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/list?overdue=true"
                       style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(135deg, rgba(244, 63, 94, 0.15), rgba(251, 113, 133, 0.1)); border: 1px solid rgba(244, 63, 94, 0.25); border-radius: 10px; text-decoration: none; color: #fff; transition: all 0.25s ease;"
                       onmouseover="this.style.transform='translateX(4px)'; this.style.borderColor='rgba(244, 63, 94, 0.5)'; this.style.background='linear-gradient(135deg, rgba(244, 63, 94, 0.25), rgba(251, 113, 133, 0.2)';"
                       onmouseout="this.style.transform='translateX(0)'; this.style.borderColor='rgba(244, 63, 94, 0.25)'; this.style.background='linear-gradient(135deg, rgba(244, 63, 94, 0.15), rgba(251, 113, 133, 0.1)';">
                        <div style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: linear-gradient(135deg, #f43f5e, #fb7185); border-radius: 8px;">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;">Overdue Items</div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);">Past deadline</div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color: rgba(255,255,255,0.3); font-size: 12px;"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/analytics"
                       style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.1)); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 10px; text-decoration: none; color: #fff; transition: all 0.25s ease;"
                       onmouseover="this.style.transform='translateX(4px)'; this.style.borderColor='rgba(16, 185, 129, 0.5)'; this.style.background='linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(52, 211, 153, 0.2)';"
                       onmouseout="this.style.transform='translateX(0)'; this.style.borderColor='rgba(16, 185, 129, 0.25)'; this.style.background='linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.1)';">
                        <div style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: linear-gradient(135deg, #10b981, #34d399); border-radius: 8px;">
                            <i class="fa-solid fa-chart-bar"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;">View Analytics</div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);">Performance insights</div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color: rgba(255,255,255,0.3); font-size: 12px;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Delivery Metrics -->
        <div class="admin-glass-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-teal">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Delivery Metrics</h3>
                    <p class="admin-card-subtitle">Overall performance</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Completion Rate -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="font-size: 13px; color: rgba(255,255,255,0.7);">Completion Rate</span>
                            <span style="font-size: 13px; font-weight: 600; color: #10b981;"><?= number_format($analytics['completion_rate'] ?? 0, 1) ?>%</span>
                        </div>
                        <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?= min(100, $analytics['completion_rate'] ?? 0) ?>%; background: linear-gradient(135deg, #10b981, #22c55e);"></div>
                        </div>
                    </div>

                    <!-- Average Progress -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="font-size: 13px; color: rgba(255,255,255,0.7);">Average Progress</span>
                            <span style="font-size: 13px; font-weight: 600; color: #06b6d4;"><?= number_format($analytics['overview']['avg_progress'] ?? 0, 1) ?>%</span>
                        </div>
                        <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?= min(100, $analytics['overview']['avg_progress'] ?? 0) ?>%; background: linear-gradient(135deg, #06b6d4, #6366f1);"></div>
                        </div>
                    </div>

                    <!-- On-Time Rate -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="font-size: 13px; color: rgba(255,255,255,0.7);">On-Time Delivery</span>
                            <span style="font-size: 13px; font-weight: 600; color: #8b5cf6;"><?= number_format($analytics['on_time_rate'] ?? 0, 1) ?>%</span>
                        </div>
                        <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?= min(100, $analytics['on_time_rate'] ?? 0) ?>%; background: linear-gradient(135deg, #8b5cf6, #a855f7);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Priority Breakdown -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-indigo">
                    <i class="fa-solid fa-flag"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Priority Breakdown</h3>
                    <p class="admin-card-subtitle">Active deliverables</p>
                </div>
            </div>
            <div class="admin-card-body">
                <?php
                $priorityBreakdown = $analytics['priority_breakdown'] ?? ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
                $priorityIcons = ['urgent' => 'fa-circle-exclamation', 'high' => 'fa-arrow-up', 'medium' => 'fa-minus', 'low' => 'fa-arrow-down'];
                $priorityColors = ['urgent' => '#ef4444', 'high' => '#f59e0b', 'medium' => '#06b6d4', 'low' => '#10b981'];
                ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($priorityBreakdown as $priority => $count): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid <?= $priorityIcons[$priority] ?>" style="color: <?= $priorityColors[$priority] ?>; width: 16px;"></i>
                            <span style="text-transform: capitalize; font-size: 13px;"><?= $priority ?></span>
                        </div>
                        <span style="font-weight: 600; color: <?= $priorityColors[$priority] ?>;"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/admin-footer.php'; ?>
