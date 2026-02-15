<?php
/**
 * Match Approval Detail View
 * Shows full details for a single match approval request
 * Path: views/modern/admin-legacy/match-approvals/show.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Match Details';
$adminPageSubtitle = 'Broker Workflow';
$adminPageIcon = 'fa-user-check';

require dirname(__DIR__) . '/partials/admin-header.php';

$request = $request ?? null;
$csrf_token = $csrf_token ?? Csrf::token();

if (!$request):
?>
<div class="admin-glass-card">
    <div class="admin-empty-state">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <h3>Request Not Found</h3>
        <p>This match approval request could not be found.</p>
        <a href="<?= $basePath ?>/admin-legacy/match-approvals" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-arrow-left"></i> Back to Approvals
        </a>
    </div>
</div>
<?php else: ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/match-approvals" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Match Approval #<?= $request['id'] ?>
        </h1>
        <p class="admin-page-subtitle">Review match details and make a decision</p>
    </div>
    <div class="admin-page-header-actions">
        <span class="admin-badge admin-badge-<?= $request['status'] === 'pending' ? 'warning' : ($request['status'] === 'approved' ? 'success' : 'danger') ?> match-status-badge-lg">
            <?= ucfirst($request['status']) ?>
        </span>
    </div>
</div>

<!-- Match Score Card -->
<div class="admin-glass-card match-detail-container">
    <div class="admin-card-header">
        <div class="admin-card-header-icon match-score-icon--<?= $request['match_score'] >= 80 ? 'hot' : ($request['match_score'] >= 60 ? 'good' : 'moderate') ?>">
            <i class="fa-solid fa-fire"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Match Score</h3>
            <p class="admin-card-subtitle"><?= $request['match_type'] ?> match</p>
        </div>
        <div class="match-score-display">
            <?= round($request['match_score']) ?>%
        </div>
    </div>
</div>

<!-- Parties Involved -->
<div class="match-parties-grid">
    <!-- User receiving match -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon match-header-icon--recipient">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Match Recipient</h3>
                <p class="admin-card-subtitle">Would receive this match</p>
            </div>
        </div>
        <div class="admin-card-body match-party-card-body">
            <div class="match-user-avatar-lg match-user-avatar-lg--recipient">
                <?php if (!empty($request['user_avatar'])): ?>
                    <img src="<?= htmlspecialchars($request['user_avatar']) ?>" alt="">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <h4 class="match-user-name"><?= htmlspecialchars($request['user_name'] ?? $request['user_first_name'] . ' ' . $request['user_last_name']) ?></h4>
            <p class="match-user-email"><?= htmlspecialchars($request['user_email'] ?? '') ?></p>
        </div>
    </div>

    <!-- Arrow -->
    <div class="match-arrow-connector">
        <div class="match-arrow-icon">
            <i class="fa-solid fa-arrows-left-right"></i>
        </div>
    </div>

    <!-- Listing owner -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon match-header-icon--owner">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Listing Owner</h3>
                <p class="admin-card-subtitle">Created the listing</p>
            </div>
        </div>
        <div class="admin-card-body match-party-card-body">
            <div class="match-user-avatar-lg match-user-avatar-lg--owner">
                <?php if (!empty($request['owner_avatar'])): ?>
                    <img src="<?= htmlspecialchars($request['owner_avatar']) ?>" alt="">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <h4 class="match-user-name"><?= htmlspecialchars($request['owner_name'] ?? $request['owner_first_name'] . ' ' . $request['owner_last_name']) ?></h4>
        </div>
    </div>
</div>

<!-- Listing Details -->
<div class="admin-glass-card match-detail-container">
    <div class="admin-card-header">
        <div class="admin-card-header-icon match-header-icon--listing">
            <i class="fa-solid fa-<?= ($request['listing_type'] ?? 'offer') === 'offer' ? 'hand-holding-heart' : 'hand-holding-dollar' ?>"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title"><?= htmlspecialchars($request['listing_title'] ?? 'Listing') ?></h3>
            <p class="admin-card-subtitle"><?= ucfirst($request['listing_type'] ?? 'offer') ?> • <?= htmlspecialchars($request['category_name'] ?? 'Uncategorized') ?></p>
        </div>
        <a href="<?= $basePath ?>/listings/<?= $request['listing_id'] ?>" class="admin-btn admin-btn-secondary match-view-listing-link" target="_blank">
            <i class="fa-solid fa-external-link"></i> View Listing
        </a>
    </div>
    <div class="admin-card-body">
        <p class="match-listing-description">
            <?= nl2br(htmlspecialchars($request['listing_description'] ?? 'No description available.')) ?>
        </p>

        <div class="match-listing-meta">
            <?php if (!empty($request['distance_km'])): ?>
            <div class="match-listing-meta-item">
                <i class="fa-solid fa-location-dot"></i>
                <span><?= round($request['distance_km'], 1) ?> km apart</span>
            </div>
            <?php endif; ?>
            <div class="match-listing-meta-item">
                <i class="fa-solid fa-calendar"></i>
                <span>Submitted <?= date('M j, Y g:ia', strtotime($request['submitted_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Match Reasons -->
<?php
$reasons = is_string($request['match_reasons'] ?? null) ? json_decode($request['match_reasons'], true) : ($request['match_reasons'] ?? []);
if (!empty($reasons)):
?>
<div class="admin-glass-card match-detail-container">
    <div class="admin-card-header">
        <div class="admin-card-header-icon match-header-icon--reasons">
            <i class="fa-solid fa-sparkles"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Match Reasons</h3>
            <p class="admin-card-subtitle">Why these members were matched</p>
        </div>
    </div>
    <div class="admin-card-body">
        <ul class="match-reasons-list">
            <?php foreach ($reasons as $reason): ?>
                <li><?= htmlspecialchars($reason) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Review History (if already reviewed) -->
<?php if ($request['status'] !== 'pending'): ?>
<div class="admin-glass-card match-detail-container">
    <div class="admin-card-header">
        <div class="admin-card-header-icon match-header-icon--<?= $request['status'] === 'approved' ? 'approved' : 'rejected' ?>">
            <i class="fa-solid fa-<?= $request['status'] === 'approved' ? 'check' : 'times' ?>"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title"><?= ucfirst($request['status']) ?></h3>
            <p class="admin-card-subtitle">by <?= htmlspecialchars($request['reviewer_name'] ?? 'Unknown') ?> on <?= date('M j, Y g:ia', strtotime($request['reviewed_at'])) ?></p>
        </div>
    </div>
    <?php if (!empty($request['review_notes'])): ?>
    <div class="admin-card-body">
        <p class="match-review-notes">
            <strong>Notes:</strong> <?= htmlspecialchars($request['review_notes']) ?>
        </p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Action Form (if pending) -->
<?php if ($request['status'] === 'pending'): ?>
<div class="admin-glass-card match-detail-container">
    <div class="admin-card-header">
        <div class="admin-card-header-icon match-header-icon--decision">
            <i class="fa-solid fa-gavel"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Make a Decision</h3>
            <p class="admin-card-subtitle">Approve or reject this match</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="match-decision-form-group">
            <label class="match-decision-label">Notes / Rejection Reason</label>
            <textarea id="review-notes" class="admin-form-control" rows="3" placeholder="Optional for approval, required for rejection..."></textarea>
        </div>
        <div class="match-decision-buttons">
            <button type="button" id="btn-approve" class="admin-btn admin-btn-success admin-btn-lg">
                <i class="fa-solid fa-check"></i> Approve Match
            </button>
            <button type="button" id="btn-reject" class="admin-btn admin-btn-danger admin-btn-lg">
                <i class="fa-solid fa-times"></i> Reject Match
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestId = <?= $request['id'] ?>;
    const csrfToken = '<?= $csrf_token ?>';
    const basePath = '<?= $basePath ?>';

    document.getElementById('btn-approve').addEventListener('click', function() {
        const notes = document.getElementById('review-notes').value;
        if (!confirm('Approve this match? The member will be notified.')) return;

        submitAction('approve', notes);
    });

    document.getElementById('btn-reject').addEventListener('click', function() {
        const notes = document.getElementById('review-notes').value;
        if (!notes.trim()) {
            alert('Please provide a reason for rejection.');
            document.getElementById('review-notes').focus();
            return;
        }
        if (!confirm('Reject this match? The member will be notified with your reason.')) return;

        submitAction('reject', notes);
    });

    function submitAction(action, notes) {
        fetch(basePath + '/admin-legacy/match-approvals/' + action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                request_ids: [requestId],
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = basePath + '/admin-legacy/match-approvals';
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
});
</script>
<?php endif; ?>

<!-- Match Approvals CSS loaded from external file (CLAUDE.md compliant) -->
<link rel="stylesheet" href="/assets/css/admin-legacy/match-approvals.css">

<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
