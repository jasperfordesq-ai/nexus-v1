<?php
// My Federated Groups - Glassmorphism 2025
$pageTitle = $pageTitle ?? "My Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('My Federated Groups');
Nexus\Core\SEO::setDescription('View and manage your group memberships from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$groups = $groups ?? [];
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="my-groups-wrapper">

        <!-- Page Header -->
        <header class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                    My Federated Groups
                </h1>
                <p class="page-subtitle">Groups you've joined from partner timebanks</p>
            </div>
            <div class="header-actions">
                <a href="<?= $basePath ?>/federation/groups" aria-label="Browse available federated groups">
                    <i class="fa-solid fa-search" aria-hidden="true"></i>
                    Browse Groups
                </a>
            </div>
        </header>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success" role="status" aria-live="polite">
                <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
                <?= htmlspecialchars($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error" role="alert">
                <i class="fa-solid fa-exclamation-circle" aria-hidden="true"></i>
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (empty($groups)): ?>
            <div class="empty-state" role="status" aria-labelledby="empty-title">
                <div class="empty-icon" aria-hidden="true">
                    <i class="fa-solid fa-people-group"></i>
                </div>
                <h3 id="empty-title" class="empty-title">No Federated Groups Yet</h3>
                <p class="empty-message">
                    You haven't joined any groups from partner timebanks.<br>
                    Browse available groups to connect with members across the network.
                </p>
                <a href="<?= $basePath ?>/federation/groups" class="empty-btn">
                    <i class="fa-solid fa-search" aria-hidden="true"></i>
                    Browse Federated Groups
                </a>
            </div>
        <?php else: ?>
            <div class="groups-list" role="list" aria-label="Your federated groups">
                <?php foreach ($groups as $group): ?>
                    <article class="group-item" role="listitem">
                        <div class="group-icon" aria-hidden="true">
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
                                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                                    <?= htmlspecialchars($group['tenant_name'] ?? 'Partner Timebank') ?>
                                </span>
                                <span class="group-meta-item">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    <?= (int)($group['member_count'] ?? 0) ?> members
                                </span>
                                <?php if (!empty($group['joined_at'])): ?>
                                    <span class="group-meta-item">
                                        <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                                        Joined <time datetime="<?= date('c', strtotime($group['joined_at'])) ?>"><?= date('M j, Y', strtotime($group['joined_at'])) ?></time>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?= $group['membership_status'] ?? 'approved' ?>" role="status">
                            <?php if (($group['membership_status'] ?? 'approved') === 'pending'): ?>
                                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                Pending
                            <?php else: ?>
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                Active
                            <?php endif; ?>
                        </span>
                        <div class="group-actions">
                            <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>" class="action-link action-link-primary" aria-label="View <?= htmlspecialchars($group['name']) ?>">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                View
                            </a>
                        </div>
                    </article>
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

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
