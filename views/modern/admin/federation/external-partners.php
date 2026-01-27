<?php
/**
 * External Federation Partners - List View
 * Admin interface for managing external federation server connections
 *
 * Styles: /httpdocs/assets/css/admin/federation-external-partners.css
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'External Partners';
$adminPageSubtitle = 'External Federation Connections';
$adminPageIcon = 'fa-globe';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../partials/admin-header.php';

$partners = $partners ?? [];

// Status badge colors
$statusColors = [
    'pending' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
    'active' => ['bg' => 'rgba(16, 185, 129, 0.15)', 'color' => '#10b981', 'icon' => 'fa-circle-check'],
    'suspended' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444', 'icon' => 'fa-pause'],
    'failed' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444', 'icon' => 'fa-triangle-exclamation'],
];
?>

<div class="federation-partners-page">

<?php if ($flashSuccess): ?>
<div class="flash-message success">
    <i class="fa-solid fa-circle-check"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="flash-message error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<div class="external-partners-dashboard">
    <div class="info-box">
        <h4><i class="fa-solid fa-circle-info"></i> External Federation Partners</h4>
        <p>
            Connect to timebanks running on different servers. Add their API URL and credentials to enable
            cross-platform member search, messaging, and time credit transfers.
        </p>
    </div>

    <div class="page-header">
        <h2><i class="fa-solid fa-globe"></i> External Partners</h2>
        <a href="/admin/federation/external-partners/create" class="add-partner-btn">
            <i class="fa-solid fa-plus"></i> Add External Partner
        </a>
    </div>

    <?php if (empty($partners)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-globe"></i>
        <h3>No External Partners Yet</h3>
        <p>
            Connect to other timebank platforms to expand your federation network.
            You'll need their API URL and an API key they provide.
        </p>
        <a href="/admin/federation/external-partners/create" class="add-partner-btn">
            <i class="fa-solid fa-plus"></i> Add Your First Partner
        </a>
    </div>
    <?php else: ?>
    <div class="partners-grid">
        <?php foreach ($partners as $partner): ?>
        <?php $status = $statusColors[$partner['status']] ?? $statusColors['pending']; ?>
        <div class="partner-card">
            <div class="partner-header">
                <div class="partner-info">
                    <h3><?= htmlspecialchars($partner['name']) ?></h3>
                    <div class="partner-url"><?= htmlspecialchars($partner['base_url']) ?></div>
                </div>
                <span class="partner-status" style="background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>;">
                    <i class="fa-solid <?= $status['icon'] ?>"></i>
                    <?= ucfirst($partner['status']) ?>
                </span>
            </div>

            <div class="partner-meta">
                <div class="meta-item">
                    <span class="meta-label">Auth Method</span>
                    <span class="meta-value"><?= strtoupper($partner['auth_method'] ?? 'API Key') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Last Verified</span>
                    <span class="meta-value">
                        <?= $partner['verified_at'] ? date('M j, Y', strtotime($partner['verified_at'])) : 'Never' ?>
                    </span>
                </div>
                <?php if ($partner['partner_name']): ?>
                <div class="meta-item">
                    <span class="meta-label">Partner Name</span>
                    <span class="meta-value"><?= htmlspecialchars($partner['partner_name']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($partner['partner_version']): ?>
                <div class="meta-item">
                    <span class="meta-label">API Version</span>
                    <span class="meta-value"><?= htmlspecialchars($partner['partner_version']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="partner-permissions">
                <span class="perm-badge <?= $partner['allow_member_search'] ? '' : 'disabled' ?>">Members</span>
                <span class="perm-badge <?= $partner['allow_listing_search'] ? '' : 'disabled' ?>">Listings</span>
                <span class="perm-badge <?= $partner['allow_messaging'] ? '' : 'disabled' ?>">Messaging</span>
                <span class="perm-badge <?= $partner['allow_transactions'] ? '' : 'disabled' ?>">Transactions</span>
                <span class="perm-badge <?= $partner['allow_events'] ? '' : 'disabled' ?>">Events</span>
                <span class="perm-badge <?= $partner['allow_groups'] ? '' : 'disabled' ?>">Groups</span>
            </div>

            <div class="partner-actions">
                <a href="/admin/federation/external-partners/<?= $partner['id'] ?>" class="partner-action-btn view">
                    <i class="fa-solid fa-eye"></i> View Details
                </a>
                <form method="POST" action="/admin/federation/external-partners/<?= $partner['id'] ?>/test" class="test-connection-form">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit" class="partner-action-btn test">
                        <i class="fa-solid fa-plug"></i> Test
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</div><!-- /.federation-partners-page -->

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
