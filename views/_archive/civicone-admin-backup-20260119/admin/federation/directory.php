<?php
/**
 * Federation Directory
 * Browse and discover other timebanks
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Directory';
$adminPageSubtitle = 'Discover partner timebanks';
$adminPageIcon = 'fa-compass';

require __DIR__ . '/../partials/admin-header.php';

$tenants = $tenants ?? [];
$filters = $filters ?? [];
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-compass"></i>
            Federation Directory
        </h1>
        <p class="admin-page-subtitle">Browse timebanks available for partnership</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/federation/partnerships" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-handshake"></i>
            My Partnerships
        </a>
    </div>
</div>

<?php if (empty($tenants)): ?>
<div class="fed-admin-card">
    <div class="fed-admin-card-body">
        <div class="fed-empty-state">
            <i class="fa-solid fa-compass"></i>
            <h3>No Timebanks Available</h3>
            <p class="admin-text-muted">
                There are no timebanks currently visible in the directory.
                This may be because no other timebanks have enabled directory visibility.
            </p>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Directory Grid -->
<div class="fed-directory-grid">
    <?php foreach ($tenants as $tenant): ?>
    <div class="fed-directory-card">
        <div class="fed-directory-header">
            <div class="fed-directory-logo">
                <?php if (!empty($tenant['logo_url'])): ?>
                <img src="<?= htmlspecialchars($tenant['logo_url']) ?>" alt="">
                <?php else: ?>
                <?= strtoupper(substr($tenant['name'] ?? 'T', 0, 2)) ?>
                <?php endif; ?>
            </div>
            <div class="fed-directory-info">
                <h3><?= htmlspecialchars($tenant['name'] ?? 'Unknown') ?></h3>
                <?php if (!empty($tenant['location'])): ?>
                <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($tenant['location']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($tenant['description'])): ?>
        <p class="admin-text-muted admin-text-small">
            <?= htmlspecialchars(substr($tenant['description'], 0, 100)) ?>...
        </p>
        <?php endif; ?>

        <div class="fed-directory-stats">
            <div class="fed-directory-stat">
                <div class="fed-directory-stat-value"><?= number_format($tenant['member_count'] ?? 0) ?></div>
                <div class="fed-directory-stat-label">Members</div>
            </div>
            <div class="fed-directory-stat">
                <div class="fed-directory-stat-value"><?= number_format($tenant['listing_count'] ?? 0) ?></div>
                <div class="fed-directory-stat-label">Listings</div>
            </div>
        </div>

        <?php if (!empty($tenant['already_partnered'])): ?>
        <span class="admin-badge admin-badge-success" style="width: 100%; text-align: center;">
            <i class="fa-solid fa-check"></i> Already Partnered
        </span>
        <?php elseif (!empty($tenant['pending_request'])): ?>
        <span class="admin-badge admin-badge-warning" style="width: 100%; text-align: center;">
            <i class="fa-solid fa-clock"></i> Request Pending
        </span>
        <?php else: ?>
        <a href="<?= $basePath ?>/admin-legacy/federation/partnerships?request=<?= $tenant['id'] ?>" class="admin-btn admin-btn-primary admin-btn-block">
            <i class="fa-solid fa-paper-plane"></i>
            Request Partnership
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<script src="/assets/js/admin-federation.js?v=<?= time() ?>"></script>
<script>
    initFederationSettings('<?= $basePath ?>', '<?= Csrf::token() ?>');
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
