<?php
/**
 * Exchange Requests List - CivicOne Theme (GOV.UK)
 * View and manage exchange requests pending broker action
 * Path: views/civicone/admin/broker-controls/exchanges/index.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$exchanges = $exchanges ?? [];
$status = $status ?? 'pending';
$page = $page ?? 1;
$totalCount = $total_count ?? 0;
$totalPages = $total_pages ?? 1;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <a href="<?= $basePath ?>/admin/broker-controls" class="govuk-back-link">Back to Broker Controls</a>

        <h1 class="govuk-heading-xl">Exchange Requests</h1>
        <p class="govuk-body-l">Review and manage exchange requests.</p>

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

        <!-- Status Tabs -->
        <nav class="govuk-tabs" data-module="govuk-tabs">
            <ul class="govuk-tabs__list">
                <li class="govuk-tabs__list-item <?= $status === 'pending' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?status=pending">Pending</a>
                </li>
                <li class="govuk-tabs__list-item <?= $status === 'active' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?status=active">Active</a>
                </li>
                <li class="govuk-tabs__list-item <?= $status === 'completed' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?status=completed">Completed</a>
                </li>
                <li class="govuk-tabs__list-item <?= $status === 'cancelled' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?status=cancelled">Cancelled</a>
                </li>
            </ul>
        </nav>

        <?php if (empty($exchanges)): ?>
        <div class="govuk-panel govuk-panel--confirmation" style="background: #f3f2f1; color: #0b0c0c;">
            <h2 class="govuk-panel__title" style="color: #0b0c0c;">No exchange requests</h2>
            <div class="govuk-panel__body" style="color: #505a5f;">
                There are no exchange requests matching this filter.
            </div>
        </div>
        <?php else: ?>

        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--m">
                <?= $totalCount ?> exchange request<?= $totalCount !== 1 ? 's' : '' ?>
            </caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">ID</th>
                    <th scope="col" class="govuk-table__header">Requester</th>
                    <th scope="col" class="govuk-table__header">Provider</th>
                    <th scope="col" class="govuk-table__header">Listing</th>
                    <th scope="col" class="govuk-table__header">Hours</th>
                    <th scope="col" class="govuk-table__header">Risk</th>
                    <th scope="col" class="govuk-table__header">Status</th>
                    <th scope="col" class="govuk-table__header">Actions</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                <?php foreach ($exchanges as $exchange): ?>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell">#<?= $exchange['id'] ?></td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($exchange['requester_name']) ?></td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($exchange['provider_name']) ?></td>
                    <td class="govuk-table__cell">
                        <?= htmlspecialchars($exchange['listing_title']) ?>
                        <br>
                        <strong class="govuk-tag govuk-tag--<?= $exchange['listing_type'] === 'offer' ? 'green' : 'blue' ?>">
                            <?= ucfirst($exchange['listing_type']) ?>
                        </strong>
                    </td>
                    <td class="govuk-table__cell"><?= number_format($exchange['proposed_hours'], 1) ?>h</td>
                    <td class="govuk-table__cell">
                        <?php if (!empty($exchange['risk_level'])): ?>
                        <?php
                        $riskColour = match($exchange['risk_level']) {
                            'critical' => 'red',
                            'high' => 'orange',
                            'medium' => 'yellow',
                            default => 'grey'
                        };
                        ?>
                        <strong class="govuk-tag govuk-tag--<?= $riskColour ?>"><?= ucfirst($exchange['risk_level']) ?></strong>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell">
                        <?php
                        $statusColour = match($exchange['status']) {
                            'completed' => 'green',
                            'cancelled', 'expired', 'disputed' => 'red',
                            'pending_broker' => 'orange',
                            'pending_provider', 'pending_confirmation' => 'yellow',
                            'accepted', 'in_progress' => 'blue',
                            default => 'grey'
                        };
                        ?>
                        <strong class="govuk-tag govuk-tag--<?= $statusColour ?>">
                            <?= ucwords(str_replace('_', ' ', $exchange['status'])) ?>
                        </strong>
                    </td>
                    <td class="govuk-table__cell">
                        <a href="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>" class="govuk-link">
                            View
                        </a>
                        <?php if ($exchange['status'] === 'pending_broker'): ?>
                        <form action="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>/approve" method="POST" style="display:inline;">
                            <?= Csrf::input() ?>
                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" style="margin-left: 10px;">
                                Approve
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav class="govuk-pagination" role="navigation" aria-label="results">
            <?php if ($page > 1): ?>
            <div class="govuk-pagination__prev">
                <a class="govuk-link govuk-pagination__link" href="?status=<?= $status ?>&page=<?= $page - 1 ?>" rel="prev">
                    <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                        <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                    </svg>
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
                <a class="govuk-link govuk-pagination__link" href="?status=<?= $status ?>&page=<?= $page + 1 ?>" rel="next">
                    <span class="govuk-pagination__link-title">Next</span>
                    <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                        <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                    </svg>
                </a>
            </div>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>
