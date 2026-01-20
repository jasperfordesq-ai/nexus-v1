<?php
/**
 * My Federated Groups
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "My Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('My Federated Groups');
Nexus\Core\SEO::setDescription('View and manage your group memberships from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$groups = $groups ?? [];
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/groups" class="civic-fed-back-link" aria-label="Return to federated groups">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federated Groups
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <div class="civic-fed-header-content">
            <h1>
                <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                My Federated Groups
            </h1>
            <p class="civic-fed-subtitle">Groups you've joined from partner timebanks</p>
        </div>
        <a href="<?= $basePath ?>/federation/groups" class="civic-fed-btn civic-fed-btn--secondary" aria-label="Browse available federated groups">
            <i class="fa-solid fa-search" aria-hidden="true"></i>
            Browse Groups
        </a>
    </header>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="civic-fed-alert civic-fed-alert--success" role="status" aria-live="polite">
            <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="civic-fed-alert civic-fed-alert--error" role="alert">
            <i class="fa-solid fa-exclamation-circle" aria-hidden="true"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (empty($groups)): ?>
        <div class="civic-fed-empty" role="status" aria-labelledby="empty-title">
            <div class="civic-fed-empty-icon" aria-hidden="true">
                <i class="fa-solid fa-people-group"></i>
            </div>
            <h3 id="empty-title">No Federated Groups Yet</h3>
            <p>
                You haven't joined any groups from partner timebanks.<br>
                Browse available groups to connect with members across the network.
            </p>
            <a href="<?= $basePath ?>/federation/groups" class="civic-fed-btn civic-fed-btn--primary">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                Browse Federated Groups
            </a>
        </div>
    <?php else: ?>
        <div class="civic-fed-my-groups-list" role="list" aria-label="Your federated groups">
            <?php foreach ($groups as $group): ?>
                <article class="civic-fed-my-group-card" role="listitem">
                    <div class="civic-fed-my-group-icon" aria-hidden="true">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="civic-fed-my-group-info">
                        <h3 class="civic-fed-my-group-name">
                            <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>">
                                <?= htmlspecialchars($group['name']) ?>
                            </a>
                        </h3>
                        <div class="civic-fed-my-group-meta">
                            <span class="civic-fed-my-group-meta-item">
                                <i class="fa-solid fa-building" aria-hidden="true"></i>
                                <?= htmlspecialchars($group['tenant_name'] ?? 'Partner Timebank') ?>
                            </span>
                            <span class="civic-fed-my-group-meta-item">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <?= (int)($group['member_count'] ?? 0) ?> members
                            </span>
                            <?php if (!empty($group['joined_at'])): ?>
                                <span class="civic-fed-my-group-meta-item">
                                    <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                                    Joined <time datetime="<?= date('c', strtotime($group['joined_at'])) ?>"><?= date('M j, Y', strtotime($group['joined_at'])) ?></time>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="civic-fed-status-badge civic-fed-status-badge--<?= ($group['membership_status'] ?? 'approved') === 'pending' ? 'pending' : 'success' ?>" role="status">
                        <?php if (($group['membership_status'] ?? 'approved') === 'pending'): ?>
                            <i class="fa-solid fa-clock" aria-hidden="true"></i>
                            Pending
                        <?php else: ?>
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            Active
                        <?php endif; ?>
                    </span>
                    <div class="civic-fed-my-group-actions">
                        <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>" class="civic-fed-btn civic-fed-btn--small civic-fed-btn--secondary" aria-label="View <?= htmlspecialchars($group['name']) ?>">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            View
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
