<?php
/**
 * Match Approval Detail View - CivicOne Theme (GOV.UK)
 * Shows full details for a single match approval request
 * Path: views/civicone/admin-legacy/match-approvals/show.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$request = $request ?? null;
$csrf_token = $csrf_token ?? Csrf::token();

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <a href="<?= $basePath ?>/admin-legacy/match-approvals" class="govuk-back-link">Back to approvals</a>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <?php if (!$request): ?>
            <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                <h2 class="govuk-error-summary__title" id="error-summary-title">Request not found</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">This match approval request could not be found.</p>
                </div>
            </div>
        <?php else: ?>

            <div class="govuk-grid-row">
                <div class="govuk-grid-column-two-thirds">
                    <h1 class="govuk-heading-xl">
                        Match Approval #<?= $request['id'] ?>
                        <span class="govuk-caption-xl">
                            <?php if ($request['status'] === 'pending'): ?>
                                <strong class="govuk-tag govuk-tag--yellow">Pending review</strong>
                            <?php elseif ($request['status'] === 'approved'): ?>
                                <strong class="govuk-tag govuk-tag--green">Approved</strong>
                            <?php else: ?>
                                <strong class="govuk-tag govuk-tag--red">Rejected</strong>
                            <?php endif; ?>
                        </span>
                    </h1>
                </div>
                <div class="govuk-grid-column-one-third" style="text-align: right;">
                    <div class="govuk-panel govuk-panel--confirmation" style="background: <?= $request['match_score'] >= 80 ? '#d4351c' : ($request['match_score'] >= 60 ? '#00703c' : '#1d70b8') ?>; padding: 20px;">
                        <div class="govuk-panel__body" style="font-size: 48px; font-weight: bold;">
                            <?= round($request['match_score']) ?>%
                        </div>
                        <p class="govuk-body" style="color: white; margin: 0;">Match score</p>
                    </div>
                </div>
            </div>

            <!-- Match Recipient -->
            <div class="govuk-summary-card govuk-!-margin-bottom-4">
                <div class="govuk-summary-card__title-wrapper">
                    <h2 class="govuk-summary-card__title">Match recipient</h2>
                    <p class="govuk-body govuk-!-margin-bottom-0">This member would receive the match</p>
                </div>
                <div class="govuk-summary-card__content">
                    <dl class="govuk-summary-list">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Name</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['user_name'] ?? $request['user_first_name'] . ' ' . $request['user_last_name']) ?></dd>
                        </div>
                        <?php if (!empty($request['user_email'])): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Email</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['user_email']) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Listing Owner -->
            <div class="govuk-summary-card govuk-!-margin-bottom-4">
                <div class="govuk-summary-card__title-wrapper">
                    <h2 class="govuk-summary-card__title">Listing owner</h2>
                    <p class="govuk-body govuk-!-margin-bottom-0">This member created the listing</p>
                </div>
                <div class="govuk-summary-card__content">
                    <dl class="govuk-summary-list">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Name</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['owner_name'] ?? $request['owner_first_name'] . ' ' . $request['owner_last_name']) ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Listing Details -->
            <div class="govuk-summary-card govuk-!-margin-bottom-4">
                <div class="govuk-summary-card__title-wrapper">
                    <h2 class="govuk-summary-card__title">Listing details</h2>
                    <ul class="govuk-summary-card__actions">
                        <li class="govuk-summary-card__action">
                            <a class="govuk-link" href="<?= $basePath ?>/listings/<?= $request['listing_id'] ?>" target="_blank">View listing</a>
                        </li>
                    </ul>
                </div>
                <div class="govuk-summary-card__content">
                    <dl class="govuk-summary-list">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Title</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['listing_title'] ?? 'Unknown') ?></dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Type</dt>
                            <dd class="govuk-summary-list__value"><?= ucfirst($request['listing_type'] ?? 'offer') ?></dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Category</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['category_name'] ?? 'Uncategorized') ?></dd>
                        </div>
                        <?php if (!empty($request['listing_description'])): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Description</dt>
                            <dd class="govuk-summary-list__value"><?= nl2br(htmlspecialchars($request['listing_description'])) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($request['distance_km'])): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Distance</dt>
                            <dd class="govuk-summary-list__value"><?= round($request['distance_km'], 1) ?> km apart</dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Match Information -->
            <div class="govuk-summary-card govuk-!-margin-bottom-4">
                <div class="govuk-summary-card__title-wrapper">
                    <h2 class="govuk-summary-card__title">Match information</h2>
                </div>
                <div class="govuk-summary-card__content">
                    <dl class="govuk-summary-list">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Match type</dt>
                            <dd class="govuk-summary-list__value"><?= ucfirst($request['match_type'] ?? 'one_way') ?></dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Match score</dt>
                            <dd class="govuk-summary-list__value">
                                <strong class="govuk-tag <?= $request['match_score'] >= 80 ? 'govuk-tag--red' : ($request['match_score'] >= 60 ? 'govuk-tag--green' : 'govuk-tag--blue') ?>">
                                    <?= round($request['match_score']) ?>%
                                </strong>
                            </dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Submitted</dt>
                            <dd class="govuk-summary-list__value"><?= date('j F Y \a\t g:ia', strtotime($request['submitted_at'])) ?></dd>
                        </div>
                        <?php
                        $reasons = is_string($request['match_reasons'] ?? null) ? json_decode($request['match_reasons'], true) : ($request['match_reasons'] ?? []);
                        if (!empty($reasons)):
                        ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Match reasons</dt>
                            <dd class="govuk-summary-list__value">
                                <ul class="govuk-list govuk-list--bullet">
                                    <?php foreach ($reasons as $reason): ?>
                                        <li><?= htmlspecialchars($reason) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Review Details (if already reviewed) -->
            <?php if ($request['status'] !== 'pending'): ?>
            <div class="govuk-summary-card govuk-!-margin-bottom-6">
                <div class="govuk-summary-card__title-wrapper">
                    <h2 class="govuk-summary-card__title">Review decision</h2>
                </div>
                <div class="govuk-summary-card__content">
                    <dl class="govuk-summary-list">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Decision</dt>
                            <dd class="govuk-summary-list__value">
                                <strong class="govuk-tag govuk-tag--<?= $request['status'] === 'approved' ? 'green' : 'red' ?>">
                                    <?= ucfirst($request['status']) ?>
                                </strong>
                            </dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Reviewed by</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['reviewer_name'] ?? 'Unknown') ?></dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Reviewed on</dt>
                            <dd class="govuk-summary-list__value"><?= date('j F Y \a\t g:ia', strtotime($request['reviewed_at'])) ?></dd>
                        </div>
                        <?php if (!empty($request['review_notes'])): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Notes</dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($request['review_notes']) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Form (if pending) -->
            <?php if ($request['status'] === 'pending'): ?>
            <div class="govuk-!-margin-bottom-6">
                <h2 class="govuk-heading-l">Make a decision</h2>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="review-notes">
                        Notes or rejection reason
                    </label>
                    <div class="govuk-hint">
                        Optional for approval, required for rejection
                    </div>
                    <textarea class="govuk-textarea" id="review-notes" name="notes" rows="3"></textarea>
                </div>

                <div class="govuk-button-group">
                    <button type="button" id="btn-approve" class="govuk-button" data-module="govuk-button">
                        Approve match
                    </button>
                    <button type="button" id="btn-reject" class="govuk-button govuk-button--warning" data-module="govuk-button">
                        Reject match
                    </button>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var requestId = <?= $request['id'] ?>;
                var csrfToken = '<?= $csrf_token ?>';
                var basePath = '<?= $basePath ?>';

                document.getElementById('btn-approve').addEventListener('click', function() {
                    var notes = document.getElementById('review-notes').value;
                    if (!confirm('Approve this match? The member will be notified.')) return;
                    submitAction('approve', notes);
                });

                document.getElementById('btn-reject').addEventListener('click', function() {
                    var notes = document.getElementById('review-notes').value;
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
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            window.location.href = basePath + '/admin-legacy/match-approvals';
                        } else {
                            alert('Error: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(function(error) {
                        alert('Error: ' + error.message);
                    });
                }
            });
            </script>
            <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
