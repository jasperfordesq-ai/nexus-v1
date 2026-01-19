<?php
// My Federated Groups - Glassmorphism 2025
$pageTitle = $pageTitle ?? "My Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('My Federated Groups');
Nexus\Core\SEO::setDescription('View and manage your group memberships from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$groups = $groups ?? [];
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="my-groups-wrapper">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fa-solid fa-user-group"></i>
                    My Federated Groups
                </h1>
                <p class="page-subtitle">Groups you've joined from partner timebanks</p>
            </div>
            <div class="header-actions">
                <a href="<?= $basePath ?>/federation/groups">
                    <i class="fa-solid fa-search"></i>
                    Browse Groups
                </a>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-circle"></i>
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (empty($groups)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-people-group"></i>
                </div>
                <h3 class="empty-title">No Federated Groups Yet</h3>
                <p class="empty-message">
                    You haven't joined any groups from partner timebanks.<br>
                    Browse available groups to connect with members across the network.
                </p>
                <a href="<?= $basePath ?>/federation/groups" class="empty-btn">
                    <i class="fa-solid fa-search"></i>
                    Browse Federated Groups
                </a>
            </div>
        <?php else: ?>
            <div class="groups-list">
                <?php foreach ($groups as $group): ?>
                    <div class="group-item">
                        <div class="group-icon">
                            <i class="fa-solid fa-people-group"></i>
                        </div>
                        <div class="group-info">
                            <h3 class="group-name">
                                <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>">
                                    <?= htmlspecialchars($group['name']) ?>
                                </a>
                            </h3>
                            <div class="group-meta">
                                <span class="group-meta-item">
                                    <i class="fa-solid fa-building"></i>
                                    <?= htmlspecialchars($group['tenant_name'] ?? 'Partner Timebank') ?>
                                </span>
                                <span class="group-meta-item">
                                    <i class="fa-solid fa-users"></i>
                                    <?= (int)($group['member_count'] ?? 0) ?> members
                                </span>
                                <?php if (!empty($group['joined_at'])): ?>
                                    <span class="group-meta-item">
                                        <i class="fa-solid fa-calendar"></i>
                                        Joined <?= date('M j, Y', strtotime($group['joined_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?= $group['membership_status'] ?? 'approved' ?>">
                            <?php if (($group['membership_status'] ?? 'approved') === 'pending'): ?>
                                <i class="fa-solid fa-clock"></i>
                                Pending
                            <?php else: ?>
                                <i class="fa-solid fa-check"></i>
                                Active
                            <?php endif; ?>
                        </span>
                        <div class="group-actions">
                            <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>" class="action-link action-link-primary">
                                <i class="fa-solid fa-eye"></i>
                                View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
