<?php
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = htmlspecialchars($deliverable['title'] ?? 'View Deliverable');
$adminPageSubtitle = 'Deliverability Tracking';
$adminPageIcon = 'fa-eye';

require dirname(dirname(__DIR__)) . '/admin-legacy/partials/admin-header.php';

$deliverable = $deliverable ?? [];
$milestones = $milestones ?? [];
$milestoneStats = $milestoneStats ?? [];
$comments = $comments ?? [];
$history = $history ?? [];
$users = $users ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-eye"></i>
            <?= htmlspecialchars($deliverable['title'] ?? 'Deliverable') ?>
        </h1>
        <p class="admin-page-subtitle">
            Created by <?= htmlspecialchars($deliverable['owner_first_name'] . ' ' . $deliverable['owner_last_name']) ?>
            on <?= date('M j, Y', strtotime($deliverable['created_at'])) ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/deliverability/list" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-list"></i> All Deliverables
        </a>
        <a href="<?= $basePath ?>/admin-legacy/deliverability/edit/<?= $deliverable['id'] ?>" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-pen"></i> Edit
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

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 1fr 350px; gap: 24px; margin-bottom: 30px;">

    <!-- Left Column: Details & Content -->
    <div>
        <!-- Overview Card -->
        <div class="admin-glass-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-cyan">
                    <i class="fa-solid fa-info-circle"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Overview</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <!-- Status and Priority Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display: block; font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 6px; text-transform: uppercase;">Status</label>
                        <?php
                        $statusColors = [
                            'draft' => 'secondary', 'ready' => 'info', 'in_progress' => 'active',
                            'blocked' => 'inactive', 'review' => 'pending', 'completed' => 'active',
                            'cancelled' => 'inactive', 'on_hold' => 'pending'
                        ];
                        ?>
                        <span class="admin-status-badge admin-status-<?= $statusColors[$deliverable['status']] ?? 'secondary' ?>">
                            <span class="admin-status-dot"></span> <?= ucwords(str_replace('_', ' ', $deliverable['status'])) ?>
                        </span>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 6px; text-transform: uppercase;">Priority</label>
                        <?php
                        $priorityColors = [
                            'urgent' => '#ef4444', 'high' => '#f59e0b', 'medium' => '#06b6d4', 'low' => '#10b981'
                        ];
                        ?>
                        <span style="padding: 6px 12px; border-radius: 4px; background: <?= $priorityColors[$deliverable['priority']] ?>; color: #fff; font-weight: 600; text-transform: uppercase; font-size: 12px;">
                            <?= $deliverable['priority'] ?>
                        </span>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 6px; text-transform: uppercase;">Progress</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="flex: 1; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; width: <?= min(100, $deliverable['progress_percentage'] ?? 0) ?>%; background: linear-gradient(135deg, #06b6d4, #6366f1);"></div>
                            </div>
                            <span style="font-weight: 700; color: #06b6d4; min-width: 45px;"><?= number_format($deliverable['progress_percentage'] ?? 0, 0) ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($deliverable['description'])): ?>
                <div style="padding: 16px; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid #6366f1; margin-bottom: 16px;">
                    <p style="margin: 0; line-height: 1.6; color: rgba(255,255,255,0.8);">
                        <?= nl2br(htmlspecialchars($deliverable['description'])) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Details Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <strong style="color: rgba(255,255,255,0.6); display: block; margin-bottom: 4px;">Category</strong>
                        <span><?= htmlspecialchars($deliverable['category'] ?? 'general') ?></span>
                    </div>
                    <div>
                        <strong style="color: rgba(255,255,255,0.6); display: block; margin-bottom: 4px;">Assigned To</strong>
                        <?php if ($deliverable['assigned_to']): ?>
                            <?= htmlspecialchars($deliverable['assigned_first_name'] . ' ' . $deliverable['assigned_last_name']) ?>
                        <?php elseif ($deliverable['assigned_group_name']): ?>
                            <i class="fa-solid fa-users"></i> <?= htmlspecialchars($deliverable['assigned_group_name']) ?>
                        <?php else: ?>
                            <span style="color: rgba(255,255,255,0.4);">Unassigned</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong style="color: rgba(255,255,255,0.6); display: block; margin-bottom: 4px;">Start Date</strong>
                        <?= $deliverable['start_date'] ? date('M j, Y', strtotime($deliverable['start_date'])) : 'Not set' ?>
                    </div>
                    <div>
                        <strong style="color: rgba(255,255,255,0.6); display: block; margin-bottom: 4px;">Due Date</strong>
                        <?php if ($deliverable['due_date']): ?>
                            <?php $isOverdue = strtotime($deliverable['due_date']) < time() && !in_array($deliverable['status'], ['completed', 'cancelled']); ?>
                            <span style="color: <?= $isOverdue ? '#ef4444' : '#fff' ?>;">
                                <?= date('M j, Y', strtotime($deliverable['due_date'])) ?>
                                <?php if ($isOverdue): ?><i class="fa-solid fa-exclamation-triangle"></i> Overdue<?php endif; ?>
                            </span>
                        <?php else: ?>
                            Not set
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($deliverable['estimated_hours'])): ?>
                    <div>
                        <strong style="color: rgba(255,255,255,0.6); display: block; margin-bottom: 4px;">Estimated Hours</strong>
                        <?= number_format($deliverable['estimated_hours'], 1) ?> hrs
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($deliverable['actual_hours'])): ?>
                    <div>
                        <strong style="color: rgba(255,255,255,0.6); display: block; margin-bottom: 4px;">Actual Hours</strong>
                        <?= number_format($deliverable['actual_hours'], 1) ?> hrs
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($deliverable['tags'])): ?>
                <div style="margin-top: 16px;">
                    <strong style="color: rgba(255,255,255,0.6); display: block; margin-bottom: 8px;">Tags</strong>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <?php foreach ($deliverable['tags'] as $tag): ?>
                        <span style="background: rgba(99,102,241,0.2); color: #a5b4fc; padding: 4px 10px; border-radius: 4px; font-size: 12px;">
                            <?= htmlspecialchars($tag) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Milestones Card -->
        <div class="admin-glass-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Milestones</h3>
                    <p class="admin-card-subtitle">
                        <?= $milestoneStats['completed'] ?? 0 ?> of <?= $milestoneStats['total'] ?? 0 ?> completed
                    </p>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (empty($milestones)): ?>
                <div class="admin-empty-state" style="padding: 40px 20px;">
                    <div class="admin-empty-icon"><i class="fa-solid fa-list-check"></i></div>
                    <p class="admin-empty-title">No Milestones Yet</p>
                    <p class="admin-empty-text">Break this deliverable into milestones to track progress.</p>
                </div>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($milestones as $milestone): ?>
                    <div style="padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                        <input type="checkbox"
                               <?= $milestone['status'] === 'completed' ? 'checked' : '' ?>
                               onchange="completeMilestone(<?= $milestone['id'] ?>)"
                               style="width: 18px; height: 18px; cursor: pointer;">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; color: <?= $milestone['status'] === 'completed' ? 'rgba(255,255,255,0.5)' : '#fff' ?>; <?= $milestone['status'] === 'completed' ? 'text-decoration: line-through;' : '' ?>">
                                <?= htmlspecialchars($milestone['title']) ?>
                            </div>
                            <?php if (!empty($milestone['description'])): ?>
                            <div style="font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px;">
                                <?= htmlspecialchars($milestone['description']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($milestone['status'] === 'completed'): ?>
                        <span style="font-size: 11px; color: #10b981;">
                            <i class="fa-solid fa-check-circle"></i> Done
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-green">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Comments</h3>
                    <p class="admin-card-subtitle"><?= count($comments) ?> comment<?= count($comments) != 1 ? 's' : '' ?></p>
                </div>
            </div>
            <div class="admin-card-body">
                <!-- Add Comment Form -->
                <form id="comment-form" style="margin-bottom: 24px;">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <input type="hidden" name="deliverable_id" value="<?= $deliverable['id'] ?>">
                    <div class="form-group" style="margin: 0;">
                        <textarea id="comment_text" name="comment_text" rows="3" placeholder="Add a comment..." required></textarea>
                    </div>
                    <div style="margin-top: 12px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                            <i class="fa-solid fa-paper-plane"></i> Add Comment
                        </button>
                    </div>
                </form>

                <!-- Comments List -->
                <?php if (empty($comments)): ?>
                <div style="text-align: center; padding: 20px; color: rgba(255,255,255,0.5);">
                    <i class="fa-solid fa-comments" style="font-size: 32px; opacity: 0.3; margin-bottom: 8px;"></i>
                    <p style="margin: 0;">No comments yet. Be the first to comment!</p>
                </div>
                <?php else: ?>
                <div id="comments-list" style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($comments as $comment): ?>
                    <div style="padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="color: #06b6d4;">
                                <?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?>
                            </strong>
                            <span style="font-size: 11px; color: rgba(255,255,255,0.4);">
                                <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                            </span>
                        </div>
                        <p style="margin: 0; color: rgba(255,255,255,0.8); line-height: 1.5;">
                            <?= nl2br(htmlspecialchars($comment['comment_text'])) ?>
                        </p>
                        <?php if ($comment['comment_type'] !== 'general'): ?>
                        <span style="display: inline-block; margin-top: 8px; padding: 2px 6px; background: rgba(99,102,241,0.2); color: #a5b4fc; border-radius: 3px; font-size: 11px;">
                            <?= ucfirst($comment['comment_type']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Sidebar -->
    <div>
        <!-- Risk Assessment -->
        <?php if ($deliverable['risk_level'] !== 'low' || !empty($deliverable['risk_notes'])): ?>
        <div class="admin-glass-card" style="margin-bottom: 24px; border: 1px solid rgba(239, 68, 68, 0.3);">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: rgba(239, 68, 68, 0.2);">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Risk Assessment</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <div style="margin-bottom: 12px;">
                    <strong style="display: block; font-size: 12px; color: rgba(255,255,255,0.6); margin-bottom: 4px;">Risk Level</strong>
                    <span style="padding: 4px 10px; border-radius: 4px; background: <?= $deliverable['risk_level'] === 'critical' ? '#dc2626' : ($deliverable['risk_level'] === 'high' ? '#ef4444' : '#f59e0b') ?>; color: #fff; font-weight: 600; text-transform: uppercase; font-size: 11px;">
                        <?= $deliverable['risk_level'] ?>
                    </span>
                </div>
                <div style="margin-bottom: 12px;">
                    <strong style="display: block; font-size: 12px; color: rgba(255,255,255,0.6); margin-bottom: 4px;">Delivery Confidence</strong>
                    <span><?= ucfirst($deliverable['delivery_confidence']) ?></span>
                </div>
                <?php if (!empty($deliverable['risk_notes'])): ?>
                <div>
                    <strong style="display: block; font-size: 12px; color: rgba(255,255,255,0.6); margin-bottom: 4px;">Notes</strong>
                    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: rgba(255,255,255,0.7);">
                        <?= nl2br(htmlspecialchars($deliverable['risk_notes'])) ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent History -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-indigo">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Recent Activity</h3>
                    <p class="admin-card-subtitle">Last <?= min(10, count($history)) ?> changes</p>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (empty($history)): ?>
                <p style="text-align: center; color: rgba(255,255,255,0.5); margin: 0;">No activity yet</p>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach (array_slice($history, 0, 10) as $entry): ?>
                    <div style="padding-left: 12px; border-left: 2px solid rgba(99,102,241,0.3);">
                        <div style="font-size: 12px; color: rgba(255,255,255,0.8);">
                            <?= htmlspecialchars($entry['change_description'] ?? $entry['action_type']) ?>
                        </div>
                        <div style="font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 2px;">
                            <?= htmlspecialchars($entry['first_name'] ?? 'Unknown') ?> •
                            <?= date('M j, g:i A', strtotime($entry['action_timestamp'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Complete milestone via AJAX
async function completeMilestone(milestoneId) {
    const response = await fetch('<?= $basePath ?>/admin-legacy/deliverability/ajax/complete-milestone', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            csrf_token: '<?= Csrf::generate() ?>',
            id: milestoneId
        })
    });

    const result = await response.json();
    if (result.success) {
        AdminToast.success('Success', result.message);
        setTimeout(() => location.reload(), 1000);
    } else {
        AdminToast.error('Error', result.message);
    }
}

// Add comment via AJAX
document.getElementById('comment-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const response = await fetch('<?= $basePath ?>/admin-legacy/deliverability/ajax/add-comment', {
        method: 'POST',
        body: new URLSearchParams(formData)
    });

    const result = await response.json();
    if (result.success) {
        AdminToast.success('Success', result.message);
        setTimeout(() => location.reload(), 1000);
    } else {
        AdminToast.error('Error', result.message);
    }
});
</script>

<?php require dirname(dirname(__DIR__)) . '/admin-legacy/partials/admin-footer.php'; ?>
