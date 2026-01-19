<?php
/**
 * Tenant Admin Federation Settings Dashboard
 * Allows tenant admins to manage their own federation settings
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Settings';
$adminPageSubtitle = 'Manage cross-tenant collaboration';
$adminPageIcon = 'fa-network-wired';

require __DIR__ . '/../partials/admin-header.php';
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-network-wired"></i>
            Federation Settings
        </h1>
        <p class="admin-page-subtitle">Manage how your timebank interacts with other timebanks</p>
    </div>
    <div class="admin-page-header-actions">
        <?php if ($systemEnabled && $isWhitelisted): ?>
        <a href="<?= $basePath ?>/admin/federation/directory" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-compass"></i>
            Directory
        </a>
        <a href="<?= $basePath ?>/admin/federation/analytics" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-line"></i>
            Analytics
        </a>
        <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-handshake"></i>
            Partnerships
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Status Banner -->
<?php if (!$systemEnabled): ?>
<div class="admin-alert admin-alert-info">
    <i class="fa-solid fa-info-circle"></i>
    <div>
        <strong>Federation is not yet available</strong>
        <p>The platform administrator has not enabled federation features yet. Check back later.</p>
    </div>
</div>
<?php elseif (!$isWhitelisted): ?>
<div class="admin-alert admin-alert-warning">
    <i class="fa-solid fa-clock"></i>
    <div>
        <strong>Pending Approval</strong>
        <p>Your timebank is not yet approved for federation. Contact the platform administrator to request access.</p>
    </div>
</div>
<?php endif; ?>

<!-- Status Summary Cards -->
<div class="fed-stats-grid">
    <div class="fed-stat-card">
        <div class="fed-stat-icon <?= ($statusSummary['canFederate'] ?? false) ? 'green' : 'purple' ?>">
            <i class="fa-solid fa-power-off"></i>
        </div>
        <div class="fed-stat-content">
            <div class="fed-stat-value"><?= ($statusSummary['canFederate'] ?? false) ? 'Active' : 'Inactive' ?></div>
            <div class="fed-stat-label">Federation Status</div>
        </div>
    </div>

    <div class="fed-stat-card">
        <div class="fed-stat-icon blue">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="fed-stat-content">
            <div class="fed-stat-value"><?= count(array_filter($partnerships ?? [], fn($p) => $p['status'] === 'active')) ?></div>
            <div class="fed-stat-label">Active Partnerships</div>
        </div>
    </div>

    <div class="fed-stat-card">
        <div class="fed-stat-icon amber">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="fed-stat-content">
            <div class="fed-stat-value"><?= count($pendingRequests ?? []) ?></div>
            <div class="fed-stat-label">Pending Requests</div>
        </div>
    </div>
</div>

<?php if ($systemEnabled && $isWhitelisted): ?>
<!-- Feature Toggles -->
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-toggle-on"></i>
            Federation Features
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <p class="admin-text-muted">
            Control which federation features are enabled for your timebank. You can enable features
            here, but actual functionality depends on your partnership agreements.
        </p>

        <div class="admin-toggle-list">
            <?php
            $featureList = [
                'tenant_federation_enabled' => ['Enable Federation', 'Turn on federation for your timebank', 'fa-power-off'],
                'tenant_appear_in_directory' => ['Appear in Directory', 'Let other timebanks discover you', 'fa-eye'],
                'tenant_profiles_enabled' => ['Share Profiles', 'Allow partners to view member profiles', 'fa-user'],
                'tenant_messaging_enabled' => ['Cross-Tenant Messaging', 'Allow messages between timebanks', 'fa-envelope'],
                'tenant_transactions_enabled' => ['Cross-Tenant Transactions', 'Allow time credit exchanges', 'fa-exchange-alt'],
                'tenant_listings_enabled' => ['Share Listings', 'Make listings visible to partners', 'fa-list'],
                'tenant_events_enabled' => ['Share Events', 'Make events visible to partners', 'fa-calendar'],
                'tenant_groups_enabled' => ['Federated Groups', 'Allow cross-tenant group membership', 'fa-users'],
            ];
            foreach ($featureList as $key => $info):
                $enabled = !empty($features[$key]['enabled']);
            ?>
            <div class="admin-toggle-item">
                <div class="admin-toggle-info">
                    <i class="fa-solid <?= $info[2] ?> admin-toggle-icon"></i>
                    <div>
                        <div class="admin-toggle-title"><?= $info[0] ?></div>
                        <div class="admin-toggle-desc"><?= $info[1] ?></div>
                    </div>
                </div>
                <label class="admin-switch">
                    <input type="checkbox" data-feature="<?= $key ?>" <?= $enabled ? 'checked' : '' ?>
                        onchange="updateFeature('<?= $key ?>', this.checked)">
                    <span class="admin-switch-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Pending Partnership Requests -->
<?php if (!empty($pendingRequests)): ?>
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-inbox"></i>
            Partnership Requests
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <?php foreach ($pendingRequests as $request): ?>
        <div class="partnership-request-card">
            <div class="partnership-request-info">
                <strong><?= htmlspecialchars($request['tenant_name'] ?? 'Unknown Timebank') ?></strong>
                <span class="admin-badge admin-badge-info">Level <?= $request['federation_level'] ?></span>
                <p class="admin-text-muted">
                    Requested <?= date('M j, Y', strtotime($request['requested_at'])) ?>
                </p>
            </div>
            <div class="partnership-request-actions">
                <button onclick="approvePartnership(<?= $request['id'] ?>)" class="admin-btn admin-btn-success admin-btn-sm">
                    <i class="fa-solid fa-check"></i> Approve
                </button>
                <button onclick="rejectPartnership(<?= $request['id'] ?>)" class="admin-btn admin-btn-danger admin-btn-sm">
                    <i class="fa-solid fa-times"></i> Reject
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Active Partnerships Summary -->
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-handshake"></i>
            Active Partnerships
        </h3>
        <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-secondary admin-btn-sm">
            View All <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Partner Timebank</th>
                <th>Level</th>
                <th>Features</th>
                <th>Since</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $activePartnerships = array_filter($partnerships ?? [], fn($p) => $p['status'] === 'active');
            if (empty($activePartnerships)):
            ?>
            <tr>
                <td colspan="4" class="admin-empty-state">
                    <i class="fa-solid fa-handshake"></i>
                    <p>No active partnerships yet</p>
                    <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-primary">
                        Find Partners
                    </a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach (array_slice($activePartnerships, 0, 5) as $p): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($p['partner_name'] ?? $p['tenant_name'] ?? 'Unknown') ?></strong>
                </td>
                <td>
                    <?php
                    $levelNames = ['', 'Discovery', 'Social', 'Economic', 'Integrated'];
                    ?>
                    <span class="admin-badge admin-badge-info">
                        L<?= $p['federation_level'] ?> - <?= $levelNames[$p['federation_level']] ?? '' ?>
                    </span>
                </td>
                <td>
                    <?php if ($p['profiles_enabled']): ?><span class="admin-badge" title="Profiles"><i class="fa-solid fa-user"></i></span><?php endif; ?>
                    <?php if ($p['messaging_enabled']): ?><span class="admin-badge" title="Messaging"><i class="fa-solid fa-envelope"></i></span><?php endif; ?>
                    <?php if ($p['transactions_enabled']): ?><span class="admin-badge" title="Transactions"><i class="fa-solid fa-exchange-alt"></i></span><?php endif; ?>
                    <?php if ($p['listings_enabled']): ?><span class="admin-badge" title="Listings"><i class="fa-solid fa-list"></i></span><?php endif; ?>
                </td>
                <td class="admin-text-muted">
                    <?= date('M j, Y', strtotime($p['approved_at'] ?? $p['created_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<script src="/assets/js/admin-federation.js?v=<?= time() ?>"></script>
<script>
    initFederationSettings('<?= $basePath ?>', '<?= Csrf::token() ?>');
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
