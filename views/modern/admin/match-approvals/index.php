<?php
/**
 * Match Approvals Dashboard
 * Broker workflow for approving/rejecting matches
 * Path: views/modern/admin/match-approvals/index.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Match Approvals';
$adminPageSubtitle = 'Broker Workflow';
$adminPageIcon = 'fa-user-check';

require dirname(__DIR__) . '/partials/admin-header.php';

$pending_requests = $pending_requests ?? [];
$stats = $stats ?? [];
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$total_pending = $total_pending ?? 0;
$csrf_token = $csrf_token ?? Csrf::token();
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-check" style="color: #a855f7;"></i>
            Match Approvals
        </h1>
        <p class="admin-page-subtitle">Review and approve member matches before they connect</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/match-approvals/history" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-history"></i> History
        </a>
        <a href="<?= $basePath ?>/admin/smart-matching" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-bolt"></i> Smart Matching
        </a>
    </div>
</div>

<!-- Approval Stats -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['pending_count'] ?? 0) ?></div>
            <div class="admin-stat-label">Pending Approval</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-check"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['approved_count'] ?? 0) ?></div>
            <div class="admin-stat-label">Approved (30 days)</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-red">
        <div class="admin-stat-icon"><i class="fa-solid fa-times"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['rejected_count'] ?? 0) ?></div>
            <div class="admin-stat-label">Rejected (30 days)</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $stats['avg_approval_time'] ?? 0 ?>h</div>
            <div class="admin-stat-label">Avg Review Time</div>
        </div>
    </div>
</div>

<!-- Bulk Actions -->
<?php if (!empty($pending_requests)): ?>
<div class="admin-bulk-actions" style="margin-bottom: 1rem;">
    <button type="button" id="selectAll" class="admin-btn admin-btn-sm admin-btn-secondary">
        <i class="fa-solid fa-check-double"></i> Select All
    </button>
    <button type="button" id="bulkApprove" class="admin-btn admin-btn-sm admin-btn-success" disabled>
        <i class="fa-solid fa-check"></i> Approve Selected
    </button>
    <button type="button" id="bulkReject" class="admin-btn admin-btn-sm admin-btn-danger" disabled>
        <i class="fa-solid fa-times"></i> Reject Selected
    </button>
    <span id="selectedCount" class="admin-badge admin-badge-secondary" style="margin-left: 0.5rem;">0 selected</span>
</div>
<?php endif; ?>

<!-- Pending Approvals -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-hourglass-half"></i> Pending Approvals</h3>
        <span class="admin-badge admin-badge-warning"><?= $total_pending ?></span>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($pending_requests)): ?>
            <div class="match-approval-list">
                <?php foreach ($pending_requests as $request): ?>
                    <div class="match-approval-item" data-request-id="<?= $request['id'] ?>">
                        <div class="match-approval-checkbox">
                            <input type="checkbox" class="match-checkbox" value="<?= $request['id'] ?>">
                        </div>

                        <div class="match-approval-content">
                            <div class="match-approval-header">
                                <div class="match-parties">
                                    <!-- User receiving the match -->
                                    <div class="match-party">
                                        <div class="match-avatar">
                                            <?php if ($request['user_avatar']): ?>
                                                <img src="<?= htmlspecialchars($request['user_avatar']) ?>" alt="">
                                            <?php else: ?>
                                                <i class="fa-solid fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="match-party-info">
                                            <span class="match-party-name"><?= htmlspecialchars($request['user_name']) ?></span>
                                            <span class="match-party-role">Would receive match</span>
                                        </div>
                                    </div>

                                    <div class="match-arrow">
                                        <i class="fa-solid fa-arrows-left-right"></i>
                                    </div>

                                    <!-- Listing owner -->
                                    <div class="match-party">
                                        <div class="match-avatar">
                                            <?php if ($request['owner_avatar']): ?>
                                                <img src="<?= htmlspecialchars($request['owner_avatar']) ?>" alt="">
                                            <?php else: ?>
                                                <i class="fa-solid fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="match-party-info">
                                            <span class="match-party-name"><?= htmlspecialchars($request['owner_name']) ?></span>
                                            <span class="match-party-role">Listing owner</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="match-score-badge">
                                    <span class="match-score <?= $request['match_score'] >= 80 ? 'hot' : ($request['match_score'] >= 60 ? 'good' : 'moderate') ?>">
                                        <?= round($request['match_score']) ?>%
                                    </span>
                                    <?php if ($request['match_type'] === 'mutual'): ?>
                                        <span class="match-type-badge mutual">Mutual</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="match-listing-info">
                                <h4>
                                    <i class="fa-solid fa-<?= $request['listing_type'] === 'offer' ? 'hand-holding-heart' : 'hand-holding-dollar' ?>"></i>
                                    <?= htmlspecialchars($request['listing_title']) ?>
                                </h4>
                                <p><?= htmlspecialchars(substr($request['listing_description'] ?? '', 0, 150)) ?><?= strlen($request['listing_description'] ?? '') > 150 ? '...' : '' ?></p>
                                <div class="match-meta">
                                    <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($request['category_name'] ?? 'Uncategorized') ?></span>
                                    <?php if ($request['distance_km']): ?>
                                        <span><i class="fa-solid fa-location-dot"></i> <?= round($request['distance_km'], 1) ?> km apart</span>
                                    <?php endif; ?>
                                    <span><i class="fa-solid fa-calendar"></i> <?= date('M j, Y g:ia', strtotime($request['submitted_at'])) ?></span>
                                </div>
                            </div>

                            <?php
                            $reasons = json_decode($request['match_reasons'] ?? '[]', true);
                            if (!empty($reasons)):
                            ?>
                            <div class="match-reasons">
                                <strong>Match Reasons:</strong>
                                <ul>
                                    <?php foreach ($reasons as $reason): ?>
                                        <li><?= htmlspecialchars($reason) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <div class="match-approval-actions">
                                <div class="match-action-notes">
                                    <textarea id="notes-<?= $request['id'] ?>" class="admin-form-control" placeholder="Notes (optional for approval, required for rejection)..." rows="2"></textarea>
                                </div>
                                <div class="match-action-buttons">
                                    <button type="button" class="admin-btn admin-btn-success btn-approve" data-id="<?= $request['id'] ?>">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </button>
                                    <button type="button" class="admin-btn admin-btn-danger btn-reject" data-id="<?= $request['id'] ?>">
                                        <i class="fa-solid fa-times"></i> Reject
                                    </button>
                                    <a href="<?= $basePath ?>/admin/match-approvals/<?= $request['id'] ?>" class="admin-btn admin-btn-secondary">
                                        <i class="fa-solid fa-eye"></i> Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="admin-btn admin-btn-sm admin-btn-secondary">&laquo; Prev</a>
                <?php endif; ?>
                <span class="admin-pagination-info">Page <?= $page ?> of <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-check-circle"></i>
                <h3>All Caught Up!</h3>
                <p>No pending match approvals. Great job!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Match Approvals CSS loaded from external file (CLAUDE.md compliant) -->
<link rel="stylesheet" href="/assets/css/admin/match-approvals.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= $csrf_token ?>';
    const basePath = '<?= $basePath ?>';

    // Checkbox handling
    const selectAllBtn = document.getElementById('selectAll');
    const bulkApproveBtn = document.getElementById('bulkApprove');
    const bulkRejectBtn = document.getElementById('bulkReject');
    const selectedCountEl = document.getElementById('selectedCount');
    const checkboxes = document.querySelectorAll('.match-checkbox');

    function updateBulkButtons() {
        const selected = document.querySelectorAll('.match-checkbox:checked');
        const count = selected.length;

        if (selectedCountEl) selectedCountEl.textContent = count + ' selected';
        if (bulkApproveBtn) bulkApproveBtn.disabled = count === 0;
        if (bulkRejectBtn) bulkRejectBtn.disabled = count === 0;

        // Visual feedback
        checkboxes.forEach(cb => {
            const item = cb.closest('.match-approval-item');
            if (cb.checked) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateBulkButtons));

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            updateBulkButtons();
        });
    }

    // Single approve
    document.querySelectorAll('.btn-approve').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const notes = document.getElementById('notes-' + id)?.value || '';

            if (confirm('Approve this match? The user will be notified.')) {
                submitAction('approve', [id], notes);
            }
        });
    });

    // Single reject
    document.querySelectorAll('.btn-reject').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const notes = document.getElementById('notes-' + id)?.value || '';

            if (!notes.trim()) {
                alert('Please provide a reason for rejection. This will be shown to the user.');
                document.getElementById('notes-' + id)?.focus();
                return;
            }

            if (confirm('Reject this match? The user will be notified with your reason.')) {
                submitAction('reject', [id], notes);
            }
        });
    });

    // Bulk approve
    if (bulkApproveBtn) {
        bulkApproveBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.match-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) return;

            const notes = prompt('Optional notes for all approved matches:') || '';
            if (confirm('Approve ' + selected.length + ' match(es)? Users will be notified.')) {
                submitAction('approve', selected, notes);
            }
        });
    }

    // Bulk reject
    if (bulkRejectBtn) {
        bulkRejectBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.match-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) return;

            const reason = prompt('Reason for rejection (required - will be shown to users):');
            if (!reason || !reason.trim()) {
                alert('Rejection reason is required.');
                return;
            }

            if (confirm('Reject ' + selected.length + ' match(es)? Users will be notified with your reason.')) {
                submitAction('reject', selected, reason);
            }
        });
    }

    function submitAction(action, ids, notes) {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        if (ids.length === 1) {
            formData.append('request_id', ids[0]);
        } else {
            ids.forEach(id => formData.append('request_ids[]', id));
        }

        if (action === 'approve') {
            formData.append('notes', notes);
        } else {
            formData.append('reason', notes);
        }

        fetch(basePath + '/admin/match-approvals/' + action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
