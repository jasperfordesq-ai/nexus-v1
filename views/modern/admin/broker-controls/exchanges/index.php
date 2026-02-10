<?php
/**
 * Exchange Requests List
 * View and manage exchange requests pending broker action
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Exchange Requests';
$adminPageSubtitle = 'Manage exchange workflow requests';
$adminPageIcon = 'fa-handshake';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$exchanges = $exchanges ?? [];
$status = $status ?? 'pending';
$page = $page ?? 1;
$totalCount = $total_count ?? 0;
$totalPages = $total_pages ?? 1;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/broker-controls" class="broker-back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Exchange Requests
        </h1>
        <p class="admin-page-subtitle">Review and manage exchange requests</p>
    </div>
</div>

<?php if ($flashSuccess): ?>
<div class="broker-flash broker-flash--success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="broker-flash broker-flash--error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<!-- Status Tabs -->
<div class="broker-tabs">
    <a href="?status=pending" class="broker-tab <?= $status === 'pending' ? 'active' : '' ?>">
        <i class="fa-solid fa-clock"></i> Pending
    </a>
    <a href="?status=active" class="broker-tab <?= $status === 'active' ? 'active' : '' ?>">
        <i class="fa-solid fa-spinner"></i> Active
    </a>
    <a href="?status=completed" class="broker-tab <?= $status === 'completed' ? 'active' : '' ?>">
        <i class="fa-solid fa-check"></i> Completed
    </a>
    <a href="?status=cancelled" class="broker-tab <?= $status === 'cancelled' ? 'active' : '' ?>">
        <i class="fa-solid fa-times"></i> Cancelled
    </a>
</div>

<div class="admin-glass-card">
    <div class="admin-card-body">
        <?php if (empty($exchanges)): ?>
        <div class="broker-empty-state">
            <i class="fa-solid fa-handshake"></i>
            <h3>No Exchange Requests</h3>
            <p>There are no exchange requests matching this filter.</p>
        </div>
        <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Requester</th>
                        <th>Provider</th>
                        <th>Listing</th>
                        <th>Hours</th>
                        <th>Risk</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exchanges as $exchange): ?>
                    <tr>
                        <td>#<?= $exchange['id'] ?></td>
                        <td>
                            <div class="broker-user-cell">
                                <?php if (!empty($exchange['requester_avatar'])): ?>
                                <img src="<?= htmlspecialchars($exchange['requester_avatar']) ?>" class="broker-user-avatar" alt="">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($exchange['requester_name']) ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="broker-user-cell">
                                <?php if (!empty($exchange['provider_avatar'])): ?>
                                <img src="<?= htmlspecialchars($exchange['provider_avatar']) ?>" class="broker-user-avatar" alt="">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($exchange['provider_name']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="broker-listing-title"><?= htmlspecialchars($exchange['listing_title']) ?></span>
                            <span class="broker-listing-type admin-badge admin-badge-<?= $exchange['listing_type'] === 'offer' ? 'success' : 'info' ?>">
                                <?= ucfirst($exchange['listing_type']) ?>
                            </span>
                        </td>
                        <td><?= number_format($exchange['proposed_hours'], 1) ?>h</td>
                        <td>
                            <?php if (!empty($exchange['risk_level'])): ?>
                            <?php
                            $riskClass = match($exchange['risk_level']) {
                                'critical' => 'danger',
                                'high' => 'warning',
                                'medium' => 'info',
                                default => 'secondary'
                            };
                            ?>
                            <span class="admin-badge admin-badge-<?= $riskClass ?>"><?= ucfirst($exchange['risk_level']) ?></span>
                            <?php else: ?>
                            <span class="broker-text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match($exchange['status']) {
                                'completed' => 'success',
                                'cancelled', 'expired' => 'danger',
                                'disputed' => 'danger',
                                'pending_broker' => 'warning',
                                'pending_confirmation' => 'info',
                                default => 'secondary'
                            };
                            ?>
                            <span class="admin-badge admin-badge-<?= $statusClass ?>">
                                <?= ucwords(str_replace('_', ' ', $exchange['status'])) ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($exchange['created_at'])) ?></td>
                        <td>
                            <div class="broker-action-buttons">
                                <a href="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>"
                                   class="admin-btn admin-btn-secondary admin-btn-sm" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php if ($exchange['status'] === 'pending_broker'): ?>
                                <form action="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>/approve" method="POST" style="display:inline;">
                                    <?= Csrf::input() ?>
                                    <button type="submit" class="admin-btn admin-btn-success admin-btn-sm" title="Approve">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="broker-pagination">
            <?php if ($page > 1): ?>
            <a href="?status=<?= $status ?>&page=<?= $page - 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            <span class="broker-pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="?status=<?= $status ?>&page=<?= $page + 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
