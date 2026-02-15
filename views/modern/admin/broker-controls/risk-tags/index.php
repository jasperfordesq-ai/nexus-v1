<?php
/**
 * Risk Tags List
 * View and manage risk-tagged listings
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Risk Tags';
$adminPageSubtitle = 'Manage listing risk assessments';
$adminPageIcon = 'fa-tags';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$listings = $listings ?? [];
$riskLevel = $risk_level ?? 'all';
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
            <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Risk Tags
        </h1>
        <p class="admin-page-subtitle">Manage risk assessments for listings</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/listings" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-list"></i> Browse Listings
        </a>
    </div>
</div>

<?php if ($flashSuccess): ?>
<div class="config-flash config-flash-success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="config-flash config-flash-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<!-- Risk Level Filters -->
<div class="admin-tabs">
    <a href="?risk_level=all" class="admin-tab <?= $riskLevel === 'all' ? 'active' : '' ?>">
        <i class="fa-solid fa-tags"></i> All
    </a>
    <a href="?risk_level=critical" class="admin-tab admin-tab-danger <?= $riskLevel === 'critical' ? 'active' : '' ?>">
        <i class="fa-solid fa-skull-crossbones"></i> Critical
    </a>
    <a href="?risk_level=high" class="admin-tab admin-tab-warning <?= $riskLevel === 'high' ? 'active' : '' ?>">
        <i class="fa-solid fa-exclamation-triangle"></i> High
    </a>
    <a href="?risk_level=medium" class="admin-tab admin-tab-info <?= $riskLevel === 'medium' ? 'active' : '' ?>">
        <i class="fa-solid fa-exclamation-circle"></i> Medium
    </a>
    <a href="?risk_level=low" class="admin-tab admin-tab-secondary <?= $riskLevel === 'low' ? 'active' : '' ?>">
        <i class="fa-solid fa-info-circle"></i> Low
    </a>
</div>

<div class="admin-glass-card">
    <div class="admin-card-body">
        <?php if (empty($listings)): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-shield-check"></i>
            <h3>No Tagged Listings</h3>
            <p>No listings have been tagged with this risk level.</p>
            <a href="<?= $basePath ?>/admin-legacy/listings" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-list"></i> Browse Listings to Tag
            </a>
        </div>
        <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Listing</th>
                        <th>Owner</th>
                        <th>Risk Level</th>
                        <th>Category</th>
                        <th>Tagged By</th>
                        <th>Tagged Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $listing): ?>
                    <tr>
                        <td>
                            <div class="listing-cell">
                                <a href="<?= $basePath ?>/listings/<?= $listing['listing_id'] ?>" target="_blank" class="listing-title">
                                    <?= htmlspecialchars($listing['listing_title'] ?? 'Unknown') ?>
                                </a>
                                <span class="admin-badge admin-badge-<?= ($listing['listing_type'] ?? '') === 'offer' ? 'success' : 'info' ?> admin-badge-sm">
                                    <?= ucfirst($listing['listing_type'] ?? '') ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="user-cell">
                                <?php if (!empty($listing['owner_avatar'])): ?>
                                <img src="<?= htmlspecialchars($listing['owner_avatar']) ?>" class="user-avatar" alt="">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?></span>
                            </div>
                        </td>
                        <td>
                            <?php
                            $riskClass = match($listing['risk_level'] ?? '') {
                                'critical' => 'danger',
                                'high' => 'warning',
                                'medium' => 'info',
                                default => 'secondary'
                            };
                            ?>
                            <span class="admin-badge admin-badge-<?= $riskClass ?>">
                                <?= ucfirst($listing['risk_level'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($listing['risk_category'] ?? '-') ?></td>
                        <td>
                            <span class="text-muted"><?= htmlspecialchars($listing['tagged_by_name'] ?? 'Unknown') ?></span>
                        </td>
                        <td><?= isset($listing['created_at']) ? date('M j, Y', strtotime($listing['created_at'])) : '-' ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="<?= $basePath ?>/admin-legacy/broker-controls/risk-tags/<?= $listing['listing_id'] ?>"
                                   class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit Tag">
                                    <i class="fa-solid fa-edit"></i>
                                </a>
                                <form action="<?= $basePath ?>/admin-legacy/broker-controls/risk-tags/<?= $listing['listing_id'] ?>/remove" method="POST" style="display:inline;"
                                      onsubmit="return confirm('Remove risk tag from this listing?');">
                                    <?= Csrf::input() ?>
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" title="Remove Tag">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="admin-pagination">
            <?php if ($page > 1): ?>
            <a href="?risk_level=<?= $riskLevel ?>&page=<?= $page - 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="?risk_level=<?= $riskLevel ?>&page=<?= $page + 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
