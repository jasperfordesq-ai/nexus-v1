<?php
/**
 * Federation Directory - Partner Profile
 * View details of a specific timebank
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = $tenant['name'] ?? 'Timebank Profile';
$adminPageSubtitle = 'Partner Details';
$adminPageIcon = 'fa-building';

require __DIR__ . '/../partials/admin-header.php';

$tenant = $tenant ?? [];
$partnership = $partnership ?? null;
$stats = $stats ?? [];
?>

<a href="<?= $basePath ?>/admin-legacy/federation/directory" class="admin-back-link">
    <i class="fa-solid fa-arrow-left"></i> Back to Directory
</a>

<div class="fed-grid-2" style="margin-top: 1rem;">
    <!-- Profile Card -->
    <div class="fed-admin-card">
        <div class="fed-admin-card-body">
            <div class="fed-directory-header" style="margin-bottom: 1.5rem;">
                <div class="fed-directory-logo" style="width: 80px; height: 80px; font-size: 2rem;">
                    <?php if (!empty($tenant['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($tenant['logo_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                    <?php else: ?>
                    <?= strtoupper(substr($tenant['name'] ?? 'T', 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <div class="fed-directory-info">
                    <h3 style="font-size: 1.5rem;"><?= htmlspecialchars($tenant['name'] ?? 'Unknown') ?></h3>
                    <?php if (!empty($tenant['location'])): ?>
                    <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($tenant['location']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($tenant['domain'])): ?>
                    <p><i class="fa-solid fa-globe"></i> <?= htmlspecialchars($tenant['domain']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($tenant['description'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <h4 class="admin-text-muted" style="margin-bottom: 0.5rem;">About</h4>
                <p><?= nl2br(htmlspecialchars($tenant['description'])) ?></p>
            </div>
            <?php endif; ?>

            <div class="fed-directory-stats" style="margin-bottom: 1.5rem;">
                <div class="fed-directory-stat">
                    <div class="fed-directory-stat-value"><?= number_format($stats['member_count'] ?? $tenant['member_count'] ?? 0) ?></div>
                    <div class="fed-directory-stat-label">Members</div>
                </div>
                <div class="fed-directory-stat">
                    <div class="fed-directory-stat-value"><?= number_format($stats['listing_count'] ?? $tenant['listing_count'] ?? 0) ?></div>
                    <div class="fed-directory-stat-label">Listings</div>
                </div>
                <div class="fed-directory-stat">
                    <div class="fed-directory-stat-value"><?= number_format($stats['hours_exchanged'] ?? 0, 0) ?></div>
                    <div class="fed-directory-stat-label">Hours Exchanged</div>
                </div>
            </div>

            <?php if ($partnership): ?>
                <?php if ($partnership['status'] === 'active'): ?>
                <div class="admin-alert admin-alert-success">
                    <i class="fa-solid fa-check-circle"></i>
                    <div>
                        <strong>Active Partnership</strong>
                        <p>You are partnered with this timebank at Level <?= $partnership['federation_level'] ?>.</p>
                    </div>
                </div>
                <?php elseif ($partnership['status'] === 'pending'): ?>
                <div class="admin-alert admin-alert-warning">
                    <i class="fa-solid fa-clock"></i>
                    <div>
                        <strong>Partnership Pending</strong>
                        <p>A partnership request is awaiting approval.</p>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <a href="<?= $basePath ?>/admin-legacy/federation/partnerships?request=<?= $tenant['id'] ?>" class="admin-btn admin-btn-primary admin-btn-block">
                <i class="fa-solid fa-paper-plane"></i>
                Request Partnership
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Features Available -->
    <div class="fed-admin-card">
        <div class="fed-admin-card-header">
            <h3 class="fed-admin-card-title">
                <i class="fa-solid fa-puzzle-piece"></i>
                Features Available
            </h3>
        </div>
        <div class="fed-admin-card-body">
            <div class="admin-toggle-list">
                <?php
                $features = [
                    'profiles' => ['View Profiles', 'fa-user'],
                    'messaging' => ['Messaging', 'fa-envelope'],
                    'transactions' => ['Transactions', 'fa-exchange-alt'],
                    'listings' => ['Listings', 'fa-list'],
                    'events' => ['Events', 'fa-calendar'],
                    'groups' => ['Groups', 'fa-users'],
                ];
                foreach ($features as $key => $info):
                    $enabled = !empty($tenant['features'][$key]);
                ?>
                <div class="admin-toggle-item">
                    <div class="admin-toggle-info">
                        <i class="fa-solid <?= $info[1] ?> admin-toggle-icon"></i>
                        <span><?= $info[0] ?></span>
                    </div>
                    <span class="admin-badge admin-badge-<?= $enabled ? 'success' : 'secondary' ?>">
                        <?= $enabled ? 'Available' : 'Not Available' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
