<?php
/**
 * Risk Tags List - CivicOne Theme (GOV.UK)
 * View and manage risk-tagged listings
 * Path: views/civicone/admin-legacy/broker-controls/risk-tags/index.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$listings = $listings ?? [];
$riskLevel = $risk_level ?? 'all';
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

        <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="govuk-back-link">Back to Broker Controls</a>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Risk Tags</h1>
                <p class="govuk-body-l">Manage risk assessments for listings.</p>
            </div>
            <div class="govuk-grid-column-one-third" style="text-align: right;">
                <a href="<?= $basePath ?>/admin-legacy/listings" class="govuk-button govuk-button--secondary">
                    Browse listings
                </a>
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

        <!-- Risk Level Filter -->
        <nav class="govuk-tabs" data-module="govuk-tabs">
            <ul class="govuk-tabs__list">
                <li class="govuk-tabs__list-item <?= $riskLevel === 'all' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?risk_level=all">All</a>
                </li>
                <li class="govuk-tabs__list-item <?= $riskLevel === 'critical' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?risk_level=critical">Critical</a>
                </li>
                <li class="govuk-tabs__list-item <?= $riskLevel === 'high' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?risk_level=high">High</a>
                </li>
                <li class="govuk-tabs__list-item <?= $riskLevel === 'medium' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?risk_level=medium">Medium</a>
                </li>
                <li class="govuk-tabs__list-item <?= $riskLevel === 'low' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?risk_level=low">Low</a>
                </li>
            </ul>
        </nav>

        <?php if (empty($listings)): ?>
        <div class="govuk-panel" style="background: #f3f2f1; color: #0b0c0c;">
            <h2 class="govuk-panel__title" style="color: #0b0c0c;">No tagged listings</h2>
            <div class="govuk-panel__body" style="color: #505a5f;">
                No listings have been tagged with this risk level.
            </div>
        </div>
        <?php else: ?>

        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--m">
                <?= $totalCount ?> tagged listing<?= $totalCount !== 1 ? 's' : '' ?>
            </caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">Listing</th>
                    <th scope="col" class="govuk-table__header">Owner</th>
                    <th scope="col" class="govuk-table__header">Risk Level</th>
                    <th scope="col" class="govuk-table__header">Category</th>
                    <th scope="col" class="govuk-table__header">Tagged By</th>
                    <th scope="col" class="govuk-table__header">Actions</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                <?php foreach ($listings as $listing): ?>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell">
                        <a href="<?= $basePath ?>/listings/<?= $listing['listing_id'] ?>" class="govuk-link">
                            <?= htmlspecialchars($listing['listing_title'] ?? 'Unknown') ?>
                        </a>
                        <br>
                        <strong class="govuk-tag govuk-tag--<?= ($listing['listing_type'] ?? '') === 'offer' ? 'green' : 'blue' ?>">
                            <?= ucfirst($listing['listing_type'] ?? '') ?>
                        </strong>
                    </td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?></td>
                    <td class="govuk-table__cell">
                        <?php
                        $riskColour = match($listing['risk_level'] ?? '') {
                            'critical' => 'red',
                            'high' => 'orange',
                            'medium' => 'yellow',
                            default => 'grey'
                        };
                        ?>
                        <strong class="govuk-tag govuk-tag--<?= $riskColour ?>"><?= ucfirst($listing['risk_level'] ?? 'Unknown') ?></strong>
                    </td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($listing['risk_category'] ?? '-') ?></td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($listing['tagged_by_name'] ?? 'Unknown') ?></td>
                    <td class="govuk-table__cell">
                        <a href="<?= $basePath ?>/admin-legacy/broker-controls/risk-tags/<?= $listing['listing_id'] ?>" class="govuk-link">
                            Edit
                        </a>
                        <form action="<?= $basePath ?>/admin-legacy/broker-controls/risk-tags/<?= $listing['listing_id'] ?>/remove" method="POST" style="display:inline;"
                              onsubmit="return confirm('Remove this risk tag?');">
                            <?= Csrf::input() ?>
                            <button type="submit" class="govuk-link" style="background: none; border: none; color: #d4351c; cursor: pointer; margin-left: 10px;">
                                Remove
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav class="govuk-pagination" role="navigation" aria-label="results">
            <?php if ($page > 1): ?>
            <div class="govuk-pagination__prev">
                <a class="govuk-link govuk-pagination__link" href="?risk_level=<?= $riskLevel ?>&page=<?= $page - 1 ?>">
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
                <a class="govuk-link govuk-pagination__link" href="?risk_level=<?= $riskLevel ?>&page=<?= $page + 1 ?>">
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
