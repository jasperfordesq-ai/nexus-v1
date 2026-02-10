<?php
/**
 * Match Approvals Dashboard - CivicOne Theme (GOV.UK)
 * Broker workflow for approving/rejecting matches
 * Path: views/civicone/admin/match-approvals/index.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$pending_requests = $pending_requests ?? [];
$stats = $stats ?? [];
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$total_pending = $total_pending ?? 0;
$csrf_token = $csrf_token ?? Csrf::token();

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Match Approvals</h1>
                <p class="govuk-body-l">Review and approve member matches before they connect.</p>
            </div>
            <div class="govuk-grid-column-one-third" style="text-align: right;">
                <a href="<?= $basePath ?>/admin/match-approvals/history" class="govuk-button govuk-button--secondary">
                    View history
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel govuk-panel--confirmation" style="background: #f47738; padding: 15px;">
                    <div class="govuk-panel__body" style="font-size: 36px; font-weight: bold;">
                        <?= number_format($stats['pending_count'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Pending</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel govuk-panel--confirmation" style="background: #00703c; padding: 15px;">
                    <div class="govuk-panel__body" style="font-size: 36px; font-weight: bold;">
                        <?= number_format($stats['approved_count'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Approved</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel govuk-panel--confirmation" style="background: #d4351c; padding: 15px;">
                    <div class="govuk-panel__body" style="font-size: 36px; font-weight: bold;">
                        <?= number_format($stats['rejected_count'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Rejected</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel govuk-panel--confirmation" style="background: #1d70b8; padding: 15px;">
                    <div class="govuk-panel__body" style="font-size: 36px; font-weight: bold;">
                        <?= $stats['avg_approval_time'] ?? 0 ?>h
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Avg Time</p>
                </div>
            </div>
        </div>

        <!-- Pending Approvals -->
        <?php if (!empty($pending_requests)): ?>

            <h2 class="govuk-heading-l">Pending approvals (<?= $total_pending ?>)</h2>

            <?php foreach ($pending_requests as $request): ?>
            <div class="govuk-summary-card govuk-!-margin-bottom-4">
                <div class="govuk-summary-card__title-wrapper">
                    <h2 class="govuk-summary-card__title">
                        <?= htmlspecialchars($request['listing_title']) ?>
                    </h2>
                    <div class="govuk-summary-card__actions">
                        <strong class="govuk-tag govuk-tag--<?= $request['match_score'] >= 80 ? 'red' : ($request['match_score'] >= 60 ? 'green' : 'grey') ?>">
                            <?= round($request['match_score']) ?>% match
                        </strong>
                    </div>
                </div>
                <div class="govuk-summary-card__content">
                    <dl class="govuk-summary-list">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Member</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['user_name']) ?></dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Listing owner</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['owner_name']) ?></dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Category</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['category_name'] ?? 'Uncategorized') ?></dd>
                        </div>
                        <?php if ($request['distance_km']): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Distance</dt>
                            <dd class="govuk-summary-list__value"><?= round($request['distance_km'], 1) ?> km</dd>
                        </div>
                        <?php endif; ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Submitted</dt>
                            <dd class="govuk-summary-list__value"><?= date('j F Y, g:ia', strtotime($request['submitted_at'])) ?></dd>
                        </div>
                    </dl>

                    <form method="POST" action="<?= $basePath ?>/admin/match-approvals/approve" class="govuk-!-margin-top-4" id="form-approve-<?= $request['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">

                        <div class="govuk-form-group">
                            <label class="govuk-label" for="notes-<?= $request['id'] ?>">
                                Notes (optional for approval, required for rejection)
                            </label>
                            <textarea class="govuk-textarea" id="notes-<?= $request['id'] ?>" name="notes" rows="2"></textarea>
                        </div>

                        <div class="govuk-button-group">
                            <button type="submit" class="govuk-button" data-module="govuk-button">
                                Approve
                            </button>
                            <button type="button" class="govuk-button govuk-button--warning" onclick="rejectMatch(<?= $request['id'] ?>)">
                                Reject
                            </button>
                            <a href="<?= $basePath ?>/admin/match-approvals/<?= $request['id'] ?>" class="govuk-button govuk-button--secondary">
                                View details
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="govuk-pagination" role="navigation" aria-label="results">
                <?php if ($page > 1): ?>
                <div class="govuk-pagination__prev">
                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page - 1 ?>">
                        <span class="govuk-pagination__link-title">Previous</span>
                    </a>
                </div>
                <?php endif; ?>

                <ul class="govuk-pagination__list">
                    <li class="govuk-pagination__item govuk-pagination__item--current">
                        <span class="govuk-visually-hidden">Page </span><?= $page ?><span class="govuk-visually-hidden"> of <?= $total_pages ?></span>
                    </li>
                </ul>

                <?php if ($page < $total_pages): ?>
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page + 1 ?>">
                        <span class="govuk-pagination__link-title">Next</span>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="govuk-panel govuk-panel--confirmation">
                <h1 class="govuk-panel__title">All caught up!</h1>
                <div class="govuk-panel__body">
                    No pending match approvals
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
function rejectMatch(id) {
    const notes = document.getElementById('notes-' + id).value;
    if (!notes.trim()) {
        alert('Please provide a reason for rejection. This will be shown to the user.');
        document.getElementById('notes-' + id).focus();
        return;
    }

    if (confirm('Reject this match? The user will be notified with your reason.')) {
        const form = document.getElementById('form-approve-' + id);
        form.action = '<?= $basePath ?>/admin/match-approvals/reject';
        form.querySelector('[name="notes"]').name = 'reason';
        form.submit();
    }
}
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
