<?php
/**
 * Exchange Request Detail - CivicOne Theme (GOV.UK)
 * View detailed information about a single exchange request
 * Path: views/civicone/admin/broker-controls/exchanges/show.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$exchange = $exchange ?? [];
$history = $history ?? [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$statusColour = match($exchange['status'] ?? '') {
    'completed' => 'green',
    'cancelled', 'expired', 'disputed' => 'red',
    'pending_broker' => 'orange',
    'pending_provider', 'pending_confirmation' => 'yellow',
    'accepted', 'in_progress' => 'blue',
    default => 'grey'
};

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <a href="<?= $basePath ?>/admin/broker-controls/exchanges" class="govuk-back-link">Back to Exchange Requests</a>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Exchange Request #<?= $exchange['id'] ?? '' ?></h1>
            </div>
            <div class="govuk-grid-column-one-third" style="text-align: right;">
                <strong class="govuk-tag govuk-tag--<?= $statusColour ?>" style="font-size: 1.2rem;">
                    <?= ucwords(str_replace('_', ' ', $exchange['status'] ?? 'Unknown')) ?>
                </strong>
            </div>
        </div>

        <?php if ($flashSuccess): ?>
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title">Success</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading"><?= htmlspecialchars($flashSuccess) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
        <div class="govuk-error-summary" role="alert">
            <h2 class="govuk-error-summary__title">There is a problem</h2>
            <div class="govuk-error-summary__body">
                <p><?= htmlspecialchars($flashError) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Exchange Details -->
        <div class="govuk-summary-card govuk-!-margin-bottom-6">
            <div class="govuk-summary-card__title-wrapper">
                <h2 class="govuk-summary-card__title">Exchange Details</h2>
            </div>
            <div class="govuk-summary-card__content">
                <dl class="govuk-summary-list">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Listing</dt>
                        <dd class="govuk-summary-list__value">
                            <a href="<?= $basePath ?>/listings/<?= $exchange['listing_id'] ?? '' ?>" class="govuk-link">
                                <?= htmlspecialchars($exchange['listing_title'] ?? 'Unknown') ?>
                            </a>
                            <strong class="govuk-tag govuk-tag--<?= ($exchange['listing_type'] ?? '') === 'offer' ? 'green' : 'blue' ?>">
                                <?= ucfirst($exchange['listing_type'] ?? '') ?>
                            </strong>
                        </dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Proposed Hours</dt>
                        <dd class="govuk-summary-list__value"><?= number_format($exchange['proposed_hours'] ?? 0, 1) ?> hours</dd>
                    </div>
                    <?php if (!empty($exchange['final_hours'])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Final Hours</dt>
                        <dd class="govuk-summary-list__value"><?= number_format($exchange['final_hours'], 1) ?> hours</dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($exchange['risk_level'])): ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Risk Level</dt>
                        <dd class="govuk-summary-list__value">
                            <?php
                            $riskColour = match($exchange['risk_level']) {
                                'critical' => 'red',
                                'high' => 'orange',
                                'medium' => 'yellow',
                                default => 'grey'
                            };
                            ?>
                            <strong class="govuk-tag govuk-tag--<?= $riskColour ?>"><?= ucfirst($exchange['risk_level']) ?></strong>
                        </dd>
                    </div>
                    <?php endif; ?>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Created</dt>
                        <dd class="govuk-summary-list__value"><?= isset($exchange['created_at']) ? date('j F Y \a\t g:i A', strtotime($exchange['created_at'])) : '' ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Participants -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">Requester</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <dl class="govuk-summary-list">
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">Name</dt>
                                <dd class="govuk-summary-list__value"><?= htmlspecialchars($exchange['requester_name'] ?? 'Unknown') ?></dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">Email</dt>
                                <dd class="govuk-summary-list__value"><?= htmlspecialchars($exchange['requester_email'] ?? '') ?></dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">Confirmed</dt>
                                <dd class="govuk-summary-list__value">
                                    <?php if (!empty($exchange['requester_confirmed_at'])): ?>
                                    <strong class="govuk-tag govuk-tag--green">Yes</strong>
                                    <?= number_format($exchange['requester_confirmed_hours'] ?? 0, 1) ?>h
                                    <?php else: ?>
                                    <strong class="govuk-tag govuk-tag--grey">Pending</strong>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">Provider</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <dl class="govuk-summary-list">
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">Name</dt>
                                <dd class="govuk-summary-list__value"><?= htmlspecialchars($exchange['provider_name'] ?? 'Unknown') ?></dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">Email</dt>
                                <dd class="govuk-summary-list__value"><?= htmlspecialchars($exchange['provider_email'] ?? '') ?></dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">Confirmed</dt>
                                <dd class="govuk-summary-list__value">
                                    <?php if (!empty($exchange['provider_confirmed_at'])): ?>
                                    <strong class="govuk-tag govuk-tag--green">Yes</strong>
                                    <?= number_format($exchange['provider_confirmed_hours'] ?? 0, 1) ?>h
                                    <?php else: ?>
                                    <strong class="govuk-tag govuk-tag--grey">Pending</strong>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Broker Action -->
        <?php if ($exchange['status'] === 'pending_broker'): ?>
        <div class="govuk-warning-text govuk-!-margin-bottom-6">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-warning-text__assistive">Warning</span>
                This exchange requires your approval before it can proceed.
            </strong>
        </div>

        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-half">
                <form action="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>/approve" method="POST">
                    <?= Csrf::input() ?>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="approve_notes">Approval notes (optional)</label>
                        <textarea class="govuk-textarea" id="approve_notes" name="notes" rows="3"></textarea>
                    </div>
                    <button type="submit" class="govuk-button">Approve exchange</button>
                </form>
            </div>
            <div class="govuk-grid-column-one-half">
                <form action="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>/reject" method="POST">
                    <?= Csrf::input() ?>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="reject_reason">Rejection reason</label>
                        <textarea class="govuk-textarea" id="reject_reason" name="reason" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="govuk-button govuk-button--warning">Reject exchange</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Broker Notes -->
        <?php if (!empty($exchange['broker_notes'])): ?>
        <div class="govuk-summary-card govuk-!-margin-bottom-6">
            <div class="govuk-summary-card__title-wrapper">
                <h2 class="govuk-summary-card__title">Broker Notes</h2>
            </div>
            <div class="govuk-summary-card__content">
                <?php if (!empty($exchange['broker_name'])): ?>
                <p class="govuk-body"><strong>Reviewed by:</strong> <?= htmlspecialchars($exchange['broker_name']) ?></p>
                <?php endif; ?>
                <p class="govuk-body"><?= nl2br(htmlspecialchars($exchange['broker_notes'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- History -->
        <h2 class="govuk-heading-l">Exchange History</h2>
        <?php if (empty($history)): ?>
        <p class="govuk-body">No history recorded yet.</p>
        <?php else: ?>
        <table class="govuk-table">
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">Date</th>
                    <th scope="col" class="govuk-table__header">Action</th>
                    <th scope="col" class="govuk-table__header">By</th>
                    <th scope="col" class="govuk-table__header">Status Change</th>
                    <th scope="col" class="govuk-table__header">Notes</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                <?php foreach ($history as $item): ?>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell"><?= date('j M Y, g:i A', strtotime($item['created_at'])) ?></td>
                    <td class="govuk-table__cell"><?= ucwords(str_replace('_', ' ', $item['action'])) ?></td>
                    <td class="govuk-table__cell">
                        <strong class="govuk-tag govuk-tag--grey"><?= ucfirst($item['actor_role']) ?></strong>
                    </td>
                    <td class="govuk-table__cell">
                        <?php if (!empty($item['old_status']) && !empty($item['new_status'])): ?>
                        <?= ucwords(str_replace('_', ' ', $item['old_status'])) ?> → <?= ucwords(str_replace('_', ' ', $item['new_status'])) ?>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($item['notes'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </main>
</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>
