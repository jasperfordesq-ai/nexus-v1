<?php
/**
 * Match Approval History - CivicOne Theme (GOV.UK)
 * Shows approved/rejected matches
 * Path: views/civicone/admin-legacy/match-approvals/history.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$history = $history ?? [];
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$filter_status = $filter_status ?? '';

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <a href="<?= $basePath ?>/admin-legacy/match-approvals" class="govuk-back-link">Back to pending approvals</a>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Approval history</h1>
                <p class="govuk-body-l">View past match approval decisions.</p>
            </div>
            <div class="govuk-grid-column-one-third" style="text-align: right;">
                <a href="<?= $basePath ?>/admin-legacy/match-approvals" class="govuk-button">
                    Pending approvals
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="govuk-!-margin-bottom-6">
            <form method="GET" class="govuk-form-group">
                <label class="govuk-label" for="status">Filter by status</label>
                <select class="govuk-select" id="status" name="status" onchange="this.form.submit()">
                    <option value="">All decisions</option>
                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved only</option>
                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected only</option>
                </select>
                <?php if ($filter_status): ?>
                    <a href="<?= $basePath ?>/admin-legacy/match-approvals/history" class="govuk-link govuk-!-margin-left-2">Clear filter</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- History Table -->
        <?php if (!empty($history)): ?>
            <table class="govuk-table">
                <caption class="govuk-table__caption govuk-table__caption--m">Decision history</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">ID</th>
                        <th scope="col" class="govuk-table__header">Member</th>
                        <th scope="col" class="govuk-table__header">Listing</th>
                        <th scope="col" class="govuk-table__header">Score</th>
                        <th scope="col" class="govuk-table__header">Decision</th>
                        <th scope="col" class="govuk-table__header">Reviewer</th>
                        <th scope="col" class="govuk-table__header">Date</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    <?php foreach ($history as $item): ?>
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">
                                <a href="<?= $basePath ?>/admin-legacy/match-approvals/<?= $item['id'] ?>" class="govuk-link">
                                    #<?= $item['id'] ?>
                                </a>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($item['user_name'] ?? $item['user_first_name'] . ' ' . $item['user_last_name']) ?>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars(substr($item['listing_title'] ?? 'Deleted Listing', 0, 30)) ?><?= strlen($item['listing_title'] ?? '') > 30 ? '...' : '' ?>
                            </td>
                            <td class="govuk-table__cell">
                                <strong class="govuk-tag govuk-tag--<?= $item['match_score'] >= 80 ? 'red' : ($item['match_score'] >= 60 ? 'green' : 'blue') ?>">
                                    <?= round($item['match_score']) ?>%
                                </strong>
                            </td>
                            <td class="govuk-table__cell">
                                <strong class="govuk-tag govuk-tag--<?= $item['status'] === 'approved' ? 'green' : 'red' ?>">
                                    <?= ucfirst($item['status']) ?>
                                </strong>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($item['reviewer_name'] ?? 'Unknown') ?>
                            </td>
                            <td class="govuk-table__cell">
                                <?= date('j M Y', strtotime($item['reviewed_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="govuk-pagination" role="navigation" aria-label="results">
                <?php if ($page > 1): ?>
                <div class="govuk-pagination__prev">
                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page - 1 ?><?= $filter_status ? '&status=' . $filter_status : '' ?>" rel="prev">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                        </svg>
                        <span class="govuk-pagination__link-title">Previous</span>
                    </a>
                </div>
                <?php endif; ?>

                <ul class="govuk-pagination__list">
                    <li class="govuk-pagination__item govuk-pagination__item--current">
                        <span class="govuk-pagination__link-title govuk-pagination__link-title--decorated">
                            Page <?= $page ?> of <?= $total_pages ?>
                        </span>
                    </li>
                </ul>

                <?php if ($page < $total_pages): ?>
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page + 1 ?><?= $filter_status ? '&status=' . $filter_status : '' ?>" rel="next">
                        <span class="govuk-pagination__link-title">Next</span>
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                        </svg>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="govuk-inset-text">
                No approval history yet. Once you approve or reject matches, they will appear here.
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
