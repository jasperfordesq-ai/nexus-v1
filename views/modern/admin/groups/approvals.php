<?php
/**
 * Groups Approval Workflow
 * Path: views/modern/admin/groups/approvals.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Group Approvals';
$adminPageSubtitle = 'Review and approve new groups';
$adminPageIcon = 'fa-clock';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <i class="fa-solid fa-clock" style="color: #a855f7;"></i>
            Group Approvals
        </h1>
        <p class="admin-page-subtitle">Review and approve new group creation requests</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/groups" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Approval Stats -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
            <div class="admin-stat-label">Pending Approval</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-check"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['approved'] ?? 0) ?></div>
            <div class="admin-stat-label">Approved</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-red">
        <div class="admin-stat-icon"><i class="fa-solid fa-times"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['rejected'] ?? 0) ?></div>
            <div class="admin-stat-label">Rejected</div>
        </div>
    </div>
</div>

<!-- Pending Approvals -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-hourglass-half"></i> Pending Approvals</h3>
        <span class="admin-badge admin-badge-warning"><?= count($pendingRequests ?? []) ?></span>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($pendingRequests)): ?>
            <div class="admin-approval-list">
                <?php foreach ($pendingRequests as $request): ?>
                    <div class="admin-approval-item">
                        <div class="admin-approval-header">
                            <div class="admin-approval-group">
                                <h4><?= htmlspecialchars($request['group_name']) ?></h4>
                                <div class="admin-approval-meta">
                                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($request['submitter_name']) ?></span>
                                    <span><i class="fa-solid fa-calendar"></i> <?= date('M j, Y', strtotime($request['created_at'])) ?></span>
                                    <?php if ($request['is_hub']): ?>
                                        <span class="admin-badge admin-badge-info"><i class="fa-solid fa-map-pin"></i> Hub</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($request['submission_notes']): ?>
                            <div class="admin-approval-notes">
                                <strong>Submitter Notes:</strong>
                                <p><?= nl2br(htmlspecialchars($request['submission_notes'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="admin-approval-details">
                            <p><strong>Description:</strong> <?= htmlspecialchars($request['group_description'] ?? 'N/A') ?></p>
                            <p><strong>Type:</strong> <?= htmlspecialchars($request['type_name'] ?? 'N/A') ?></p>
                            <?php if ($request['location']): ?>
                                <p><strong>Location:</strong> <?= htmlspecialchars($request['location']) ?></p>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="<?= $basePath ?>/admin/groups/process-approval" class="admin-approval-actions">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                            <textarea name="review_notes" class="admin-form-control admin-form-control-sm" placeholder="Review notes..." rows="2"></textarea>
                            <div class="admin-approval-buttons">
                                <button type="submit" name="action" value="approve" class="admin-btn admin-btn-success">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                                <button type="submit" name="action" value="changes_requested" class="admin-btn admin-btn-warning">
                                    <i class="fa-solid fa-edit"></i> Request Changes
                                </button>
                                <button type="submit" name="action" value="reject" class="admin-btn admin-btn-danger" onclick="return confirm('Reject this group?')">
                                    <i class="fa-solid fa-times"></i> Reject
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-check-circle"></i>
                <h3>All Caught Up!</h3>
                <p>No pending approval requests</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approval History -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-history"></i> Recent Decisions</h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($approvalHistory)): ?>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Submitter</th>
                            <th>Decision</th>
                            <th>Reviewed By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvalHistory as $history): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($history['group_name']) ?></strong></td>
                                <td><?= htmlspecialchars($history['submitter_name']) ?></td>
                                <td>
                                    <span class="admin-badge admin-badge-<?= ['approved' => 'success', 'rejected' => 'danger', 'changes_requested' => 'warning'][$history['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($history['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($history['reviewer_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($history['reviewed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-history"></i>
                <p>No approval history</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/groups-admin-gold-standard.min.css">

<style>
/* Approval List */
.admin-approval-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.admin-approval-item {
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-left: 4px solid #fbbf24;
    border-radius: 12px;
    transition: all 0.2s ease;
}

.admin-approval-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(99, 102, 241, 0.2);
    border-left-color: #fbbf24;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.admin-approval-header {
    margin-bottom: 1rem;
}

.admin-approval-group h4 {
    color: #fff;
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.admin-approval-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.admin-approval-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.admin-approval-meta i {
    opacity: 0.7;
}

.admin-approval-notes {
    padding: 1rem;
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.2);
    border-radius: 10px;
    margin-bottom: 1rem;
}

.admin-approval-notes strong {
    color: #fbbf24;
    font-weight: 600;
    font-size: 0.9rem;
}

.admin-approval-notes p {
    margin: 0.5rem 0 0 0;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.5;
    font-size: 0.875rem;
}

.admin-approval-details {
    margin-bottom: 1rem;
}

.admin-approval-details p {
    margin: 0.5rem 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.875rem;
    line-height: 1.5;
}

.admin-approval-details strong {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.admin-approval-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.admin-approval-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Empty State Enhancement */
.admin-empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: #22c55e;
}

.admin-empty-state h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
}

.admin-empty-state p {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

/* Responsive Approvals */
@media (max-width: 768px) {
    .admin-approval-meta {
        flex-direction: column;
        gap: 0.5rem;
    }

    .admin-approval-buttons {
        flex-direction: column;
    }

    .admin-approval-buttons .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .admin-approval-actions textarea {
        width: 100%;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
