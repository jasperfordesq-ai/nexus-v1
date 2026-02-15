<?php
/**
 * Deliverability View - Admin Panel
 *
 * CSS extracted to: httpdocs/assets/css/modern-template-extracts.css
 * Section: views/modern/admin-legacy/deliverability/view.php
 */

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

// Priority colors mapping
$priorityColors = [
    'urgent' => '#ef4444',
    'high' => '#f59e0b',
    'medium' => '#06b6d4',
    'low' => '#10b981'
];

// Risk colors mapping
$riskColors = [
    'critical' => '#dc2626',
    'high' => '#ef4444',
    'medium' => '#f59e0b',
    'low' => '#10b981'
];

// Status colors mapping
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
<div class="mte-deliverability--grid">

    <!-- Left Column: Details & Content -->
    <div>
        <!-- Overview Card -->
        <div class="admin-glass-card mte-deliverability--card">
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
                <div class="mte-deliverability--stats-row">
                    <div>
                        <label class="mte-deliverability--stat-label">Status</label>
                        <span class="admin-status-badge admin-status-<?= $statusColors[$deliverable['status']] ?? 'secondary' ?>">
                            <span class="admin-status-dot"></span> <?= ucwords(str_replace('_', ' ', $deliverable['status'])) ?>
                        </span>
                    </div>
                    <div>
                        <label class="mte-deliverability--stat-label">Priority</label>
                        <span class="mte-deliverability--priority-badge" style="--mte-priority-color: <?= $priorityColors[$deliverable['priority']] ?? '#06b6d4' ?>">
                            <?= $deliverable['priority'] ?>
                        </span>
                    </div>
                    <div>
                        <label class="mte-deliverability--stat-label">Progress</label>
                        <div class="mte-deliverability--progress-row">
                            <div class="mte-deliverability--progress-track">
                                <div class="mte-deliverability--progress-fill" style="--mte-progress: <?= min(100, $deliverable['progress_percentage'] ?? 0) ?>%"></div>
                            </div>
                            <span class="mte-deliverability--progress-value"><?= number_format($deliverable['progress_percentage'] ?? 0, 0) ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($deliverable['description'])): ?>
                <div class="mte-deliverability--description-box">
                    <p class="mte-deliverability--description-text">
                        <?= nl2br(htmlspecialchars($deliverable['description'])) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Details Grid -->
                <div class="mte-deliverability--details-grid">
                    <div>
                        <strong class="mte-deliverability--detail-label">Category</strong>
                        <span><?= htmlspecialchars($deliverable['category'] ?? 'general') ?></span>
                    </div>
                    <div>
                        <strong class="mte-deliverability--detail-label">Assigned To</strong>
                        <?php if ($deliverable['assigned_to']): ?>
                            <?= htmlspecialchars($deliverable['assigned_first_name'] . ' ' . $deliverable['assigned_last_name']) ?>
                        <?php elseif ($deliverable['assigned_group_name']): ?>
                            <i class="fa-solid fa-users"></i> <?= htmlspecialchars($deliverable['assigned_group_name']) ?>
                        <?php else: ?>
                            <span class="mte-deliverability--unassigned">Unassigned</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong class="mte-deliverability--detail-label">Start Date</strong>
                        <?= $deliverable['start_date'] ? date('M j, Y', strtotime($deliverable['start_date'])) : 'Not set' ?>
                    </div>
                    <div>
                        <strong class="mte-deliverability--detail-label">Due Date</strong>
                        <?php if ($deliverable['due_date']): ?>
                            <?php $isOverdue = strtotime($deliverable['due_date']) < time() && !in_array($deliverable['status'], ['completed', 'cancelled']); ?>
                            <span class="<?= $isOverdue ? 'mte-deliverability--overdue' : '' ?>">
                                <?= date('M j, Y', strtotime($deliverable['due_date'])) ?>
                                <?php if ($isOverdue): ?><i class="fa-solid fa-exclamation-triangle"></i> Overdue<?php endif; ?>
                            </span>
                        <?php else: ?>
                            Not set
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($deliverable['estimated_hours'])): ?>
                    <div>
                        <strong class="mte-deliverability--detail-label">Estimated Hours</strong>
                        <?= number_format($deliverable['estimated_hours'], 1) ?> hrs
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($deliverable['actual_hours'])): ?>
                    <div>
                        <strong class="mte-deliverability--detail-label">Actual Hours</strong>
                        <?= number_format($deliverable['actual_hours'], 1) ?> hrs
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($deliverable['tags'])): ?>
                <div class="mte-deliverability--tags-section">
                    <strong class="mte-deliverability--tags-label">Tags</strong>
                    <div class="mte-deliverability--tags-list">
                        <?php foreach ($deliverable['tags'] as $tag): ?>
                        <span class="mte-deliverability--tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Milestones Card -->
        <div class="admin-glass-card mte-deliverability--card">
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
                <div class="admin-empty-state">
                    <div class="admin-empty-icon"><i class="fa-solid fa-list-check"></i></div>
                    <p class="admin-empty-title">No Milestones Yet</p>
                    <p class="admin-empty-text">Break this deliverable into milestones to track progress.</p>
                </div>
                <?php else: ?>
                <div class="mte-deliverability--milestone-list">
                    <?php foreach ($milestones as $milestone): ?>
                    <div class="mte-deliverability--milestone-item">
                        <input type="checkbox"
                               <?= $milestone['status'] === 'completed' ? 'checked' : '' ?>
                               onchange="completeMilestone(<?= $milestone['id'] ?>)"
                               class="mte-deliverability--milestone-checkbox">
                        <div class="mte-deliverability--milestone-content">
                            <div class="mte-deliverability--milestone-title" data-completed="<?= $milestone['status'] === 'completed' ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($milestone['title']) ?>
                            </div>
                            <?php if (!empty($milestone['description'])): ?>
                            <div class="mte-deliverability--milestone-desc">
                                <?= htmlspecialchars($milestone['description']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($milestone['status'] === 'completed'): ?>
                        <span class="mte-deliverability--milestone-done">
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
                <form id="comment-form" class="mte-deliverability--comment-form">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <input type="hidden" name="deliverable_id" value="<?= $deliverable['id'] ?>">
                    <div class="form-group">
                        <textarea id="comment_text" name="comment_text" rows="3" placeholder="Add a comment..." required></textarea>
                    </div>
                    <div class="mte-deliverability--comment-submit-row">
                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                            <i class="fa-solid fa-paper-plane"></i> Add Comment
                        </button>
                    </div>
                </form>

                <!-- Comments List -->
                <?php if (empty($comments)): ?>
                <div class="mte-deliverability--comments-empty">
                    <i class="fa-solid fa-comments mte-deliverability--comments-empty-icon"></i>
                    <p class="mte-deliverability--comments-empty-text">No comments yet. Be the first to comment!</p>
                </div>
                <?php else: ?>
                <div id="comments-list" class="mte-deliverability--comments-list">
                    <?php foreach ($comments as $comment): ?>
                    <div class="mte-deliverability--comment-item">
                        <div class="mte-deliverability--comment-header">
                            <strong class="mte-deliverability--comment-author">
                                <?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?>
                            </strong>
                            <span class="mte-deliverability--comment-time">
                                <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                            </span>
                        </div>
                        <p class="mte-deliverability--comment-text">
                            <?= nl2br(htmlspecialchars($comment['comment_text'])) ?>
                        </p>
                        <?php if ($comment['comment_type'] !== 'general'): ?>
                        <span class="mte-deliverability--comment-type-badge">
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
        <div class="admin-glass-card mte-deliverability--card mte-deliverability--risk-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon mte-deliverability--risk-icon-box">
                    <i class="fa-solid fa-triangle-exclamation mte-deliverability--risk-icon"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Risk Assessment</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="mte-deliverability--risk-item">
                    <strong class="mte-deliverability--risk-label">Risk Level</strong>
                    <span class="mte-deliverability--risk-badge" style="--mte-risk-color: <?= $riskColors[$deliverable['risk_level']] ?? '#f59e0b' ?>">
                        <?= $deliverable['risk_level'] ?>
                    </span>
                </div>
                <div class="mte-deliverability--risk-item">
                    <strong class="mte-deliverability--risk-label">Delivery Confidence</strong>
                    <span><?= ucfirst($deliverable['delivery_confidence']) ?></span>
                </div>
                <?php if (!empty($deliverable['risk_notes'])): ?>
                <div>
                    <strong class="mte-deliverability--risk-label">Notes</strong>
                    <p class="mte-deliverability--risk-notes">
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
                <p class="mte-deliverability--history-empty">No activity yet</p>
                <?php else: ?>
                <div class="mte-deliverability--history-list">
                    <?php foreach (array_slice($history, 0, 10) as $entry): ?>
                    <div class="mte-deliverability--history-item">
                        <div class="mte-deliverability--history-desc">
                            <?= htmlspecialchars($entry['change_description'] ?? $entry['action_type']) ?>
                        </div>
                        <div class="mte-deliverability--history-meta">
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
