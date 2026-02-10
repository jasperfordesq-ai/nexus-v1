<?php
/**
 * CivicOne View: Exchange Detail
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Exchange #' . $exchange['id'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

// Status labels for display
$statusLabels = [
    'pending_provider' => 'Awaiting provider',
    'pending_broker' => 'Under broker review',
    'accepted' => 'Accepted',
    'in_progress' => 'In progress',
    'pending_confirmation' => 'Awaiting confirmation',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'disputed' => 'Disputed',
    'expired' => 'Expired',
];

// GOV.UK tag colours for statuses
$statusColours = [
    'pending_provider' => 'govuk-tag--yellow',
    'pending_broker' => 'govuk-tag--yellow',
    'accepted' => 'govuk-tag--blue',
    'in_progress' => 'govuk-tag--purple',
    'pending_confirmation' => 'govuk-tag--pink',
    'completed' => 'govuk-tag--green',
    'cancelled' => 'govuk-tag--red',
    'disputed' => 'govuk-tag--red',
    'expired' => 'govuk-tag--grey',
];

$statusColour = $statusColours[$exchange['status']] ?? 'govuk-tag--grey';
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'My Exchanges', 'href' => $basePath . '/exchanges'],
        ['text' => 'Exchange #' . $exchange['id']]
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/exchanges" class="govuk-back-link govuk-!-margin-bottom-6">Back to exchanges</a>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="success-title">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="success-title">Success</h2>
        </div>
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading"><?= htmlspecialchars($_SESSION['flash_success']) ?></p>
        </div>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="govuk-error-summary" data-module="govuk-error-summary">
        <h2 class="govuk-error-summary__title">There is a problem</h2>
        <div class="govuk-error-summary__body">
            <p><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
        </div>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">
            Exchange #<?= $exchange['id'] ?>
        </h1>
        <p class="govuk-body-l">
            <strong class="govuk-tag <?= $statusColour ?>">
                <?= $statusLabels[$exchange['status']] ?? ucfirst(str_replace('_', ' ', $exchange['status'])) ?>
            </strong>
        </p>
    </div>
</div>

<!-- Risk Warning -->
<?php if (!empty($exchange['risk_level']) && in_array($exchange['risk_level'], ['high', 'critical'])): ?>
<div class="govuk-warning-text govuk-!-margin-bottom-6">
    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
    <strong class="govuk-warning-text__text">
        <span class="govuk-visually-hidden">Warning</span>
        This listing has been flagged as <?= ucfirst($exchange['risk_level']) ?> risk. Please take appropriate precautions.
    </strong>
</div>
<?php endif; ?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <!-- Exchange Details -->
        <h2 class="govuk-heading-l">Exchange details</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Listing</dt>
                <dd class="govuk-summary-list__value">
                    <a href="<?= $basePath ?>/listings/<?= $exchange['listing_id'] ?>" class="govuk-link">
                        <?= htmlspecialchars($exchange['listing_title']) ?>
                    </a>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Type</dt>
                <dd class="govuk-summary-list__value"><?= ucfirst($exchange['listing_type']) ?></dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Requester</dt>
                <dd class="govuk-summary-list__value">
                    <a href="<?= $basePath ?>/profile/<?= $exchange['requester_id'] ?>" class="govuk-link">
                        <?= htmlspecialchars($exchange['requester_name']) ?>
                    </a>
                    <?php if ($isRequester): ?>
                        <strong class="govuk-tag govuk-tag--grey">You</strong>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Provider</dt>
                <dd class="govuk-summary-list__value">
                    <a href="<?= $basePath ?>/profile/<?= $exchange['provider_id'] ?>" class="govuk-link">
                        <?= htmlspecialchars($exchange['provider_name']) ?>
                    </a>
                    <?php if ($isProvider): ?>
                        <strong class="govuk-tag govuk-tag--grey">You</strong>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Proposed hours</dt>
                <dd class="govuk-summary-list__value"><?= number_format($exchange['proposed_hours'], 1) ?> hours</dd>
            </div>
            <?php if (!empty($exchange['final_hours'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Final hours</dt>
                <dd class="govuk-summary-list__value"><?= number_format($exchange['final_hours'], 1) ?> hours</dd>
            </div>
            <?php endif; ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Created</dt>
                <dd class="govuk-summary-list__value"><?= date('j F Y, g:i A', strtotime($exchange['created_at'])) ?></dd>
            </div>
            <?php if (!empty($exchange['broker_name'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Reviewed by</dt>
                <dd class="govuk-summary-list__value"><?= htmlspecialchars($exchange['broker_name']) ?></dd>
            </div>
            <?php endif; ?>
        </dl>

        <!-- Confirmation Status -->
        <?php if (in_array($exchange['status'], ['in_progress', 'pending_confirmation', 'completed'])): ?>
        <h2 class="govuk-heading-l">Confirmations</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Requester confirmed</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($exchange['requester_confirmed_at'])): ?>
                        <strong class="govuk-tag govuk-tag--green">Yes</strong>
                        - <?= number_format($exchange['requester_confirmed_hours'], 1) ?> hours
                        (<?= date('j M Y', strtotime($exchange['requester_confirmed_at'])) ?>)
                    <?php else: ?>
                        <strong class="govuk-tag govuk-tag--yellow">Pending</strong>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Provider confirmed</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($exchange['provider_confirmed_at'])): ?>
                        <strong class="govuk-tag govuk-tag--green">Yes</strong>
                        - <?= number_format($exchange['provider_confirmed_hours'], 1) ?> hours
                        (<?= date('j M Y', strtotime($exchange['provider_confirmed_at'])) ?>)
                    <?php else: ?>
                        <strong class="govuk-tag govuk-tag--yellow">Pending</strong>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <?php endif; ?>

        <!-- Actions -->
        <?php if (!empty($actions)): ?>
        <h2 class="govuk-heading-l">Actions</h2>
        <?php foreach ($actions as $action): ?>
            <?php if (!empty($action['needsHours'])): ?>
                <!-- Hours Confirmation Form -->
                <form action="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>/confirm" method="POST" class="govuk-!-margin-bottom-4">
                    <?= Nexus\Core\Csrf::input() ?>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="hours">Hours to confirm</label>
                        <div class="govuk-hint">Enter the number of hours worked</div>
                        <input class="govuk-input govuk-input--width-4"
                               id="hours"
                               name="hours"
                               type="number"
                               value="<?= number_format($exchange['proposed_hours'], 1) ?>"
                               min="0.25"
                               max="24"
                               step="0.25"
                               required>
                    </div>
                    <button type="submit" class="govuk-button" data-module="govuk-button">
                        <?= $action['label'] ?>
                    </button>
                </form>
            <?php elseif (!empty($action['confirm']) && ($action['action'] === 'decline' || $action['action'] === 'cancel')): ?>
                <!-- Actions with reason -->
                <details class="govuk-details govuk-!-margin-bottom-4" data-module="govuk-details">
                    <summary class="govuk-details__summary">
                        <span class="govuk-details__summary-text"><?= $action['label'] ?></span>
                    </summary>
                    <div class="govuk-details__text">
                        <form action="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>/<?= $action['action'] ?>" method="POST">
                            <?= Nexus\Core\Csrf::input() ?>
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="reason-<?= $action['action'] ?>">Reason (optional)</label>
                                <textarea class="govuk-textarea"
                                          id="reason-<?= $action['action'] ?>"
                                          name="reason"
                                          rows="3"></textarea>
                            </div>
                            <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                                Confirm <?= strtolower($action['label']) ?>
                            </button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <!-- Simple form actions -->
                <form action="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>/<?= $action['action'] ?>" method="POST" class="govuk-!-margin-bottom-4" style="display: inline;">
                    <?= Nexus\Core\Csrf::input() ?>
                    <button type="submit" class="govuk-button <?= $action['style'] === 'secondary' ? 'govuk-button--secondary' : '' ?>" data-module="govuk-button">
                        <?= $action['label'] ?>
                    </button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Message Link -->
        <?php
        $otherUserId = $isRequester ? $exchange['provider_id'] : $exchange['requester_id'];
        $otherUserName = $isRequester ? $exchange['provider_name'] : $exchange['requester_name'];
        ?>
        <p class="govuk-body govuk-!-margin-top-6">
            <a href="<?= $basePath ?>/messages/<?= $otherUserId ?>" class="govuk-link">
                Message <?= htmlspecialchars($otherUserName) ?>
            </a>
        </p>
    </div>

    <div class="govuk-grid-column-one-third">
        <!-- History Timeline -->
        <?php if (!empty($history)): ?>
        <h2 class="govuk-heading-m">History</h2>
        <ol class="govuk-list">
            <?php foreach ($history as $entry): ?>
            <li class="govuk-!-margin-bottom-4">
                <p class="govuk-body-s govuk-!-margin-bottom-1" style="color: #505a5f;">
                    <?= date('j M Y, g:i A', strtotime($entry['created_at'])) ?>
                </p>
                <p class="govuk-body govuk-!-margin-bottom-1">
                    <?php
                    $actionText = match($entry['action']) {
                        'request_created' => 'Exchange requested',
                        'status_changed' => 'Status changed to ' . ($statusLabels[$entry['new_status']] ?? $entry['new_status']),
                        'requester_confirmed' => 'Requester confirmed hours',
                        'provider_confirmed' => 'Provider confirmed hours',
                        default => ucfirst(str_replace('_', ' ', $entry['action']))
                    };
                    echo $actionText;
                    if (!empty($entry['actor_name'])) {
                        echo ' by ' . htmlspecialchars($entry['actor_name']);
                    }
                    ?>
                </p>
                <?php if (!empty($entry['notes'])): ?>
                <p class="govuk-body-s" style="color: #505a5f; font-style: italic;">
                    <?= htmlspecialchars($entry['notes']) ?>
                </p>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
