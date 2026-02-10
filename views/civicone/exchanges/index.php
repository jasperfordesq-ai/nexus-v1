<?php
/**
 * CivicOne View: Exchanges List
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'My Exchanges';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
$status = $_GET['status'] ?? 'active';

// Status labels for display
$statusLabels = [
    'pending_provider' => 'Pending',
    'pending_broker' => 'Under review',
    'accepted' => 'Accepted',
    'in_progress' => 'In progress',
    'pending_confirmation' => 'Confirming',
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
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'My Exchanges']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<h1 class="govuk-heading-xl">My Exchanges</h1>

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

<!-- Filter Tabs -->
<nav class="govuk-tabs" data-module="govuk-tabs">
    <ul class="govuk-tabs__list">
        <li class="govuk-tabs__list-item <?= $status === 'active' ? 'govuk-tabs__list-item--selected' : '' ?>">
            <a class="govuk-tabs__tab" href="<?= $basePath ?>/exchanges?status=active">Active</a>
        </li>
        <li class="govuk-tabs__list-item <?= $status === 'pending' ? 'govuk-tabs__list-item--selected' : '' ?>">
            <a class="govuk-tabs__tab" href="<?= $basePath ?>/exchanges?status=pending">Pending</a>
        </li>
        <li class="govuk-tabs__list-item <?= $status === 'completed' ? 'govuk-tabs__list-item--selected' : '' ?>">
            <a class="govuk-tabs__tab" href="<?= $basePath ?>/exchanges?status=completed">Completed</a>
        </li>
        <li class="govuk-tabs__list-item <?= $status === 'all' ? 'govuk-tabs__list-item--selected' : '' ?>">
            <a class="govuk-tabs__tab" href="<?= $basePath ?>/exchanges?status=all">All</a>
        </li>
    </ul>
</nav>

<?php if (empty($exchanges)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body">
            <?php if ($status === 'active'): ?>
                You don't have any active exchanges. <a href="<?= $basePath ?>/listings" class="govuk-link">Browse listings</a> to request an exchange.
            <?php elseif ($status === 'pending'): ?>
                You don't have any pending exchanges.
            <?php elseif ($status === 'completed'): ?>
                You haven't completed any exchanges yet.
            <?php else: ?>
                You don't have any exchanges yet. <a href="<?= $basePath ?>/listings" class="govuk-link">Browse listings</a> to get started.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <!-- Exchange List as Task List -->
    <ul class="govuk-task-list">
        <?php foreach ($exchanges as $exchange):
            $isRequester = $exchange['requester_id'] === $currentUserId;
            $statusColour = $statusColours[$exchange['status']] ?? 'govuk-tag--grey';
        ?>
        <li class="govuk-task-list__item govuk-task-list__item--with-link">
            <div class="govuk-task-list__name-and-hint">
                <a class="govuk-link govuk-task-list__link" href="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>">
                    <?= htmlspecialchars($exchange['listing_title']) ?>
                </a>
                <div class="govuk-task-list__hint">
                    <?= $isRequester ? 'You requested from ' . htmlspecialchars($exchange['provider_name']) : htmlspecialchars($exchange['requester_name']) . ' requested from you' ?>
                    · <?= number_format($exchange['proposed_hours'], 1) ?> hours
                    · <?= date('j M Y', strtotime($exchange['created_at'])) ?>
                </div>
            </div>
            <div class="govuk-task-list__status">
                <strong class="govuk-tag <?= $statusColour ?>">
                    <?= $statusLabels[$exchange['status']] ?? ucfirst(str_replace('_', ' ', $exchange['status'])) ?>
                </strong>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="govuk-pagination" aria-label="Pagination">
            <ul class="govuk-pagination__list">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === (int)($_GET['page'] ?? 1)): ?>
                        <li class="govuk-pagination__item govuk-pagination__item--current">
                            <span class="govuk-pagination__link-title"><?= $i ?></span>
                        </li>
                    <?php else: ?>
                        <li class="govuk-pagination__item">
                            <a class="govuk-link govuk-pagination__link" href="<?= $basePath ?>/exchanges?status=<?= $status ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
