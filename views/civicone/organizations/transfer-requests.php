<?php
/**
 * Organization Transfer Requests - GOV.UK Design System
 * WCAG 2.1 AA Compliant
 */

$pageTitle = $org['name'] . ' - Transfer Requests';
\Nexus\Core\SEO::setTitle($org['name'] . ' - Transfer Requests');
\Nexus\Core\SEO::setDescription('View and manage transfer requests for ' . $org['name']);

$activeTab = 'requests';
$isMember = $isMember ?? true;
$isOwner = $isOwner ?? false;
$role = $role ?? ($isAdmin ? 'admin' : 'member');
$pendingCount = $pendingCount ?? count(array_filter($requests ?? [], fn($r) => $r['status'] === 'pending'));

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">
        <!-- Shared Organization Utility Bar -->
        <?php include __DIR__ . '/_org-utility-bar.php'; ?>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-full">
                <h2 class="govuk-heading-l">
                    <i class="fa-solid fa-inbox govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Transfer Requests
                </h2>

                <!-- Filter Tabs -->
                <div class="govuk-!-margin-bottom-4">
                    <div class="govuk-button-group">
                        <button type="button" class="govuk-button filter-tab active" data-filter="all" onclick="filterRequests('all')">
                            All <strong class="govuk-tag govuk-!-margin-left-2" style="background: #505a5f;"><?= count($requests) ?></strong>
                        </button>
                        <button type="button" class="govuk-button govuk-button--secondary filter-tab" data-filter="pending" onclick="filterRequests('pending')">
                            Pending <strong class="govuk-tag govuk-!-margin-left-2" style="background: #1d70b8;"><?= count(array_filter($requests, fn($r) => $r['status'] === 'pending')) ?></strong>
                        </button>
                        <button type="button" class="govuk-button govuk-button--secondary filter-tab" data-filter="approved" onclick="filterRequests('approved')">
                            Approved <strong class="govuk-tag govuk-!-margin-left-2" style="background: #00703c;"><?= count(array_filter($requests, fn($r) => $r['status'] === 'approved')) ?></strong>
                        </button>
                        <button type="button" class="govuk-button govuk-button--secondary filter-tab" data-filter="rejected" onclick="filterRequests('rejected')">
                            Rejected <strong class="govuk-tag govuk-!-margin-left-2" style="background: #d4351c;"><?= count(array_filter($requests, fn($r) => $r['status'] === 'rejected')) ?></strong>
                        </button>
                    </div>
                </div>

                <?php if (empty($requests)): ?>
                    <div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #1d70b8;">
                        <p class="govuk-body govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-inbox fa-3x" style="color: #1d70b8;" aria-hidden="true"></i>
                        </p>
                        <h3 class="govuk-heading-m">No transfer requests yet</h3>
                        <p class="govuk-body govuk-!-margin-bottom-0">
                            Transfer requests will appear here when members request credits.
                        </p>
                    </div>
                <?php else: ?>
                    <table class="govuk-table">
                        <thead class="govuk-table__head">
                            <tr class="govuk-table__row">
                                <th scope="col" class="govuk-table__header">Requester</th>
                                <th scope="col" class="govuk-table__header">Recipient</th>
                                <th scope="col" class="govuk-table__header govuk-table__header--numeric">Amount</th>
                                <th scope="col" class="govuk-table__header">Description</th>
                                <th scope="col" class="govuk-table__header">Status</th>
                                <th scope="col" class="govuk-table__header">Date</th>
                                <th scope="col" class="govuk-table__header">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="govuk-table__body" id="requestsBody">
                            <?php foreach ($requests as $request): ?>
                                <tr class="govuk-table__row" data-status="<?= $request['status'] ?>">
                                    <td class="govuk-table__cell">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #1d70b8; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                <?= strtoupper(substr($request['requester_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <span><?= htmlspecialchars($request['requester_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #00703c; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                <?= strtoupper(substr($request['recipient_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <span><?= htmlspecialchars($request['recipient_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="govuk-table__cell govuk-table__cell--numeric">
                                        <strong class="govuk-tag" style="background: #1d70b8;">
                                            <?= number_format($request['amount'], 1) ?> HRS
                                        </strong>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <span title="<?= htmlspecialchars($request['description'] ?? '') ?>">
                                            <?= htmlspecialchars(substr($request['description'] ?? '-', 0, 50)) ?><?= strlen($request['description'] ?? '') > 50 ? '...' : '' ?>
                                        </span>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <?php
                                        $statusColors = [
                                            'pending' => ['bg' => '#1d70b8', 'icon' => 'fa-clock'],
                                            'approved' => ['bg' => '#00703c', 'icon' => 'fa-check'],
                                            'rejected' => ['bg' => '#d4351c', 'icon' => 'fa-times'],
                                            'cancelled' => ['bg' => '#505a5f', 'icon' => 'fa-ban']
                                        ];
                                        $statusInfo = $statusColors[$request['status']] ?? $statusColors['pending'];
                                        ?>
                                        <strong class="govuk-tag" style="background: <?= $statusInfo['bg'] ?>;">
                                            <i class="fa-solid <?= $statusInfo['icon'] ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                                            <?= ucfirst($request['status']) ?>
                                        </strong>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <?= date('M d, Y', strtotime($request['created_at'])) ?>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <div class="govuk-button-group">
                                                <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/approve/<?= $request['id'] ?>" method="POST" style="display: inline;">
                                                    <?= \Nexus\Core\Csrf::input() ?>
                                                    <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" style="background: #00703c;">
                                                        <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                                                        Approve
                                                    </button>
                                                </form>
                                                <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/reject/<?= $request['id'] ?>" method="POST" style="display: inline;"
                                                      onsubmit="return promptRejectReason(this, <?= $request['id'] ?>);">
                                                    <?= \Nexus\Core\Csrf::input() ?>
                                                    <input type="hidden" name="reason" id="rejectReason_<?= $request['id'] ?>">
                                                    <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">
                                                        <i class="fa-solid fa-times govuk-!-margin-right-1" aria-hidden="true"></i>
                                                        Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="govuk-hint govuk-!-margin-bottom-0">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($page > 1 || count($requests) >= 25): ?>
                        <nav class="govuk-pagination" role="navigation" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <div class="govuk-pagination__prev">
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page - 1 ?>" rel="prev">
                                        <i class="fa-solid fa-chevron-left govuk-!-margin-right-1" aria-hidden="true"></i>
                                        Previous
                                    </a>
                                </div>
                            <?php endif; ?>

                            <ul class="govuk-pagination__list">
                                <li class="govuk-pagination__item govuk-pagination__item--current">
                                    <span class="govuk-pagination__link" aria-current="page"><?= $page ?></span>
                                </li>
                            </ul>

                            <?php if (count($requests) >= 25): ?>
                                <div class="govuk-pagination__next">
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page + 1 ?>" rel="next">
                                        Next
                                        <i class="fa-solid fa-chevron-right govuk-!-margin-left-1" aria-hidden="true"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function filterRequests(status) {
    // Update button states
    document.querySelectorAll('.filter-tab').forEach(function(btn) {
        if (btn.dataset.filter === status) {
            btn.classList.remove('govuk-button--secondary');
            btn.classList.add('active');
        } else {
            btn.classList.add('govuk-button--secondary');
            btn.classList.remove('active');
        }
    });

    // Filter rows
    document.querySelectorAll('#requestsBody tr').forEach(function(row) {
        if (status === 'all' || row.dataset.status === status) {
            row.classList.remove('govuk-!-display-none');
        } else {
            row.classList.add('govuk-!-display-none');
        }
    });
}

function promptRejectReason(form, requestId) {
    var reason = prompt('Please provide a reason for rejecting this request (optional):');
    if (reason === null) {
        return false; // Cancelled
    }
    document.getElementById('rejectReason_' + requestId).value = reason;
    return true;
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
