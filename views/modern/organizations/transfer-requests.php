<?php
// Phoenix View: Organization Transfer Requests (Glassmorphism)
// Path: views/modern/organizations/transfer-requests.php

$hTitle = $org['name'] . ' - Transfer Requests';
$hSubtitle = 'All Transfer Requests';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Organization';
$hideHero = true;

// Set variables for the shared utility bar
$activeTab = 'requests';
$isMember = $isMember ?? true;
$isOwner = $isOwner ?? false;
$role = $role ?? ($isAdmin ? 'admin' : 'member');
$pendingCount = $pendingCount ?? count(array_filter($requests ?? [], fn($r) => $r['status'] === 'pending'));

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>


<div class="requests-bg"></div>

<div class="requests-container">
    <!-- Shared Organization Utility Bar -->
    <?php include __DIR__ . '/_org-utility-bar.php'; ?>

    <!-- Requests Table -->
    <div class="requests-glass-card">
        <!-- Filters -->
        <div class="requests-filters">
            <button class="filter-tab active" onclick="filterRequests('all')">
                All <span class="count"><?= count($requests) ?></span>
            </button>
            <button class="filter-tab" onclick="filterRequests('pending')">
                Pending <span class="count"><?= count(array_filter($requests, fn($r) => $r['status'] === 'pending')) ?></span>
            </button>
            <button class="filter-tab" onclick="filterRequests('approved')">
                Approved <span class="count"><?= count(array_filter($requests, fn($r) => $r['status'] === 'approved')) ?></span>
            </button>
            <button class="filter-tab" onclick="filterRequests('rejected')">
                Rejected <span class="count"><?= count(array_filter($requests, fn($r) => $r['status'] === 'rejected')) ?></span>
            </button>
        </div>

        <?php if (empty($requests)): ?>
        <div class="requests-empty">
            <div class="requests-empty-icon">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <p>No transfer requests yet.</p>
        </div>
        <?php else: ?>
        <table class="requests-table">
            <thead>
                <tr>
                    <th>Requester</th>
                    <th>Recipient</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="requestsBody">
                <?php foreach ($requests as $request): ?>
                <tr data-status="<?= $request['status'] ?>">
                    <td data-label="Requester">
                        <div class="request-user">
                            <div class="request-avatar">
                                <?= strtoupper(substr($request['requester_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <span class="request-name"><?= htmlspecialchars($request['requester_name']) ?></span>
                        </div>
                    </td>
                    <td data-label="Recipient">
                        <div class="request-user">
                            <div class="request-avatar" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <?= strtoupper(substr($request['recipient_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <span class="request-name"><?= htmlspecialchars($request['recipient_name']) ?></span>
                        </div>
                    </td>
                    <td data-label="Amount">
                        <span class="request-amount"><?= number_format($request['amount'], 1) ?> HRS</span>
                    </td>
                    <td data-label="Description">
                        <span class="request-desc" title="<?= htmlspecialchars($request['description'] ?? '') ?>">
                            <?= htmlspecialchars($request['description'] ?? '-') ?>
                        </span>
                    </td>
                    <td data-label="Status">
                        <span class="status-badge <?= $request['status'] ?>">
                            <?php if ($request['status'] === 'pending'): ?>
                                <i class="fa-solid fa-clock"></i>
                            <?php elseif ($request['status'] === 'approved'): ?>
                                <i class="fa-solid fa-check"></i>
                            <?php elseif ($request['status'] === 'rejected'): ?>
                                <i class="fa-solid fa-times"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-ban"></i>
                            <?php endif; ?>
                            <?= ucfirst($request['status']) ?>
                        </span>
                    </td>
                    <td data-label="Date">
                        <span class="request-date"><?= date('M d, Y', strtotime($request['created_at'])) ?></span>
                    </td>
                    <td data-label="Actions">
                        <?php if ($request['status'] === 'pending'): ?>
                        <div class="request-actions">
                            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/approve/<?= $request['id'] ?>" method="POST" style="display: inline;">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <button type="submit" class="request-btn approve">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                            </form>
                            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/reject/<?= $request['id'] ?>" method="POST" style="display: inline;"
                                  onsubmit="return promptRejectReason(this, <?= $request['id'] ?>);">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="reason" id="rejectReason_<?= $request['id'] ?>">
                                <button type="submit" class="request-btn reject">
                                    <i class="fa-solid fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span style="color: #9ca3af; font-size: 0.8rem;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($page > 1 || count($requests) >= 25): ?>
        <div class="requests-pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="pagination-btn">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>

            <span class="pagination-btn active"><?= $page ?></span>

            <?php if (count($requests) >= 25): ?>
            <a href="?page=<?= $page + 1 ?>" class="pagination-btn">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function filterRequests(status) {
    const tabs = document.querySelectorAll('.filter-tab');
    const rows = document.querySelectorAll('#requestsBody tr');

    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');

    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function promptRejectReason(form, requestId) {
    const reason = prompt('Please enter a reason for rejection (optional):');
    if (reason !== null) {
        document.getElementById('rejectReason_' + requestId).value = reason;
        return true;
    }
    return false;
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
