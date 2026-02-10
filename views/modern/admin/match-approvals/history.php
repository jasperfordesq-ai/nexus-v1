<?php
/**
 * Match Approval History
 * Shows approved/rejected matches
 * Path: views/modern/admin/match-approvals/history.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Approval History';
$adminPageSubtitle = 'Broker Workflow';
$adminPageIcon = 'fa-history';

require dirname(__DIR__) . '/partials/admin-header.php';

$history = $history ?? [];
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$filter_status = $filter_status ?? '';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/match-approvals" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Approval History
        </h1>
        <p class="admin-page-subtitle">View past match approval decisions</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/match-approvals" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-clock"></i> Pending Approvals
        </a>
    </div>
</div>

<!-- Filters -->
<div class="admin-glass-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-body" style="padding: 1rem;">
        <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <label style="color: rgba(255,255,255,0.7); font-weight: 600;">Filter by status:</label>
            <select name="status" class="admin-form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Decisions</option>
                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved Only</option>
                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected Only</option>
            </select>
            <?php if ($filter_status): ?>
                <a href="<?= $basePath ?>/admin/match-approvals/history" class="admin-btn admin-btn-sm admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-history"></i> Decision History</h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($history)): ?>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Listing</th>
                            <th>Score</th>
                            <th>Decision</th>
                            <th>Reviewer</th>
                            <th>Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td>
                                    <a href="<?= $basePath ?>/admin/match-approvals/<?= $item['id'] ?>" class="admin-link">
                                        #<?= $item['id'] ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                            <?php if (!empty($item['user_avatar'])): ?>
                                                <img src="<?= htmlspecialchars($item['user_avatar']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fa-solid fa-user" style="font-size: 0.8rem; color: white;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <span><?= htmlspecialchars($item['user_name'] ?? $item['user_first_name'] . ' ' . $item['user_last_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span style="max-width: 200px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($item['listing_title'] ?? 'Deleted Listing') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-badge" style="background: <?= $item['match_score'] >= 80 ? '#ef4444' : ($item['match_score'] >= 60 ? '#10b981' : '#6366f1') ?>;">
                                        <?= round($item['match_score']) ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-badge admin-badge-<?= $item['status'] === 'approved' ? 'success' : 'danger' ?>">
                                        <i class="fa-solid fa-<?= $item['status'] === 'approved' ? 'check' : 'times' ?>"></i>
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($item['reviewer_name'] ?? 'Unknown') ?>
                                </td>
                                <td>
                                    <span title="<?= date('Y-m-d H:i:s', strtotime($item['reviewed_at'])) ?>">
                                        <?= date('M j, Y', strtotime($item['reviewed_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['review_notes'])): ?>
                                        <span title="<?= htmlspecialchars($item['review_notes']) ?>" style="cursor: help; max-width: 150px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars($item['review_notes']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.4);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination" style="margin-top: 1.5rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $filter_status ? '&status=' . $filter_status : '' ?>" class="admin-btn admin-btn-sm admin-btn-secondary">&laquo; Prev</a>
                <?php endif; ?>
                <span class="admin-pagination-info">Page <?= $page ?> of <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $filter_status ? '&status=' . $filter_status : '' ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-history"></i>
                <h3>No History Yet</h3>
                <p>Once you approve or reject matches, they'll appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Match Approvals CSS loaded from external file (CLAUDE.md compliant) -->
<link rel="stylesheet" href="/assets/css/admin/match-approvals.css">

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
