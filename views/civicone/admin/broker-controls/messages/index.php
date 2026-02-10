<?php
/**
 * Message Review Queue - CivicOne Theme (GOV.UK)
 * Review messages copied for broker visibility
 * Path: views/civicone/admin/broker-controls/messages/index.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$messages = $messages ?? [];
$filter = $filter ?? 'unreviewed';
$page = $page ?? 1;
$totalCount = $total_count ?? 0;
$totalPages = $total_pages ?? 1;
$unreviewed_count = $unreviewed_count ?? 0;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <a href="<?= $basePath ?>/admin/broker-controls" class="govuk-back-link">Back to Broker Controls</a>

        <h1 class="govuk-heading-xl">Message Review</h1>
        <p class="govuk-body-l">Review messages copied for broker visibility.</p>

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

        <!-- Filter Tabs -->
        <nav class="govuk-tabs" data-module="govuk-tabs">
            <ul class="govuk-tabs__list">
                <li class="govuk-tabs__list-item <?= $filter === 'unreviewed' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=unreviewed">
                        Unreviewed
                        <?php if ($unreviewed_count > 0): ?>
                        <strong class="govuk-tag govuk-tag--red"><?= $unreviewed_count ?></strong>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="govuk-tabs__list-item <?= $filter === 'flagged' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=flagged">Flagged</a>
                </li>
                <li class="govuk-tabs__list-item <?= $filter === 'reviewed' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=reviewed">Reviewed</a>
                </li>
                <li class="govuk-tabs__list-item <?= $filter === 'all' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=all">All</a>
                </li>
            </ul>
        </nav>

        <?php if (empty($messages)): ?>
        <div class="govuk-panel" style="background: #f3f2f1; color: #0b0c0c;">
            <h2 class="govuk-panel__title" style="color: #0b0c0c;">No messages to review</h2>
            <div class="govuk-panel__body" style="color: #505a5f;">
                <?php if ($filter === 'unreviewed'): ?>
                All messages have been reviewed. Great job!
                <?php elseif ($filter === 'flagged'): ?>
                No messages have been flagged for concern.
                <?php else: ?>
                No messages match this filter.
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>

        <?php foreach ($messages as $message): ?>
        <div class="govuk-summary-card govuk-!-margin-bottom-4" <?= $message['flagged'] ? 'style="border-color: #d4351c;"' : '' ?>>
            <div class="govuk-summary-card__title-wrapper">
                <h2 class="govuk-summary-card__title">
                    <?= htmlspecialchars($message['sender_name'] ?? 'Unknown') ?> → <?= htmlspecialchars($message['receiver_name'] ?? 'Unknown') ?>
                </h2>
                <div class="govuk-summary-card__actions">
                    <?php
                    $reasonLabels = [
                        'first_contact' => ['First Contact', 'blue'],
                        'high_risk_listing' => ['High Risk', 'orange'],
                        'new_member' => ['New Member', 'grey'],
                        'flagged_user' => ['Flagged User', 'red'],
                        'monitoring' => ['Monitoring', 'purple'],
                    ];
                    $reason = $message['copy_reason'] ?? 'monitoring';
                    $reasonInfo = $reasonLabels[$reason] ?? ['Unknown', 'grey'];
                    ?>
                    <strong class="govuk-tag govuk-tag--<?= $reasonInfo[1] ?>"><?= $reasonInfo[0] ?></strong>
                    <?php if ($message['flagged']): ?>
                    <strong class="govuk-tag govuk-tag--red">Flagged</strong>
                    <?php endif; ?>
                </div>
            </div>
            <div class="govuk-summary-card__content">
                <p class="govuk-body" style="white-space: pre-wrap;"><?= htmlspecialchars($message['message_body'] ?? '') ?></p>
                <p class="govuk-body-s" style="color: #505a5f;">
                    Sent: <?= date('j F Y \a\t g:i A', strtotime($message['sent_at'])) ?>
                    <?php if (!empty($message['reviewed_at'])): ?>
                    | Reviewed by <?= htmlspecialchars($message['reviewer_name'] ?? 'Unknown') ?>
                    on <?= date('j M Y', strtotime($message['reviewed_at'])) ?>
                    <?php endif; ?>
                </p>

                <div class="govuk-button-group">
                    <?php if (empty($message['reviewed_at'])): ?>
                    <form action="<?= $basePath ?>/admin/broker-controls/messages/<?= $message['id'] ?>/review" method="POST" style="display:inline;">
                        <?= Csrf::input() ?>
                        <button type="submit" class="govuk-button govuk-button--secondary">
                            Mark as reviewed
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if (!$message['flagged']): ?>
                    <form action="<?= $basePath ?>/admin/broker-controls/messages/<?= $message['id'] ?>/flag" method="POST" style="display:inline;"
                          onsubmit="return confirm('Flag this message for concern?');">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="reason" value="Flagged by broker">
                        <button type="submit" class="govuk-button govuk-button--warning">
                            Flag message
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
        <nav class="govuk-pagination" role="navigation" aria-label="results">
            <?php if ($page > 1): ?>
            <div class="govuk-pagination__prev">
                <a class="govuk-link govuk-pagination__link" href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>">
                    <span class="govuk-pagination__link-title">Previous</span>
                </a>
            </div>
            <?php endif; ?>
            <ul class="govuk-pagination__list">
                <li class="govuk-pagination__item">
                    <span class="govuk-pagination__link-label">Page <?= $page ?> of <?= $totalPages ?></span>
                </li>
            </ul>
            <?php if ($page < $totalPages): ?>
            <div class="govuk-pagination__next">
                <a class="govuk-link govuk-pagination__link" href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>">
                    <span class="govuk-pagination__link-title">Next</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>
