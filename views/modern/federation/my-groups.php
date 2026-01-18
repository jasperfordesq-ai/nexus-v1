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

        <style>
            /* Offline Banner */
            .offline-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10001;
                padding: 12px 20px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                font-size: 0.9rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transform: translateY(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            /* Content Reveal Animation */
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            #my-groups-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 1000px;
                margin: 0 auto;
                padding: 20px 0;
            }

            .page-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                flex-wrap: wrap;
                gap: 20px;
            }

            .page-title {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main);
                margin: 0;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .page-title i {
                color: #8b5cf6;
            }

            .page-subtitle {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
                margin-top: 6px;
            }

            .header-actions a {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 20px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.3s ease;
            }

            .header-actions a:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
            }

            /* Groups List */
            .groups-list {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .group-item {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12);
                padding: 24px;
                display: flex;
                align-items: center;
                gap: 20px;
                transition: all 0.3s ease;
            }

            [data-theme="dark"] .group-item {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .group-item:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 40px rgba(139, 92, 246, 0.15);
            }

            .group-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1));
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .group-icon i {
                font-size: 1.5rem;
                color: #8b5cf6;
            }

            .group-info {
                flex: 1;
                min-width: 0;
            }

            .group-name {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 6px 0;
            }

            .group-name a {
                color: inherit;
                text-decoration: none;
                transition: color 0.2s;
            }

            .group-name a:hover {
                color: #8b5cf6;
            }

            .group-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .group-meta-item {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .group-meta-item i {
                color: #8b5cf6;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 8px;
                font-size: 0.8rem;
                font-weight: 600;
                flex-shrink: 0;
            }

            .status-approved {
                background: rgba(16, 185, 129, 0.1);
                color: #059669;
            }

            .status-pending {
                background: rgba(245, 158, 11, 0.1);
                color: #d97706;
            }

            [data-theme="dark"] .status-approved {
                background: rgba(16, 185, 129, 0.2);
                color: #34d399;
            }

            [data-theme="dark"] .status-pending {
                background: rgba(245, 158, 11, 0.2);
                color: #fbbf24;
            }

            .group-actions {
                display: flex;
                gap: 10px;
                flex-shrink: 0;
            }

            .action-link {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 10px 16px;
                border-radius: 10px;
                font-size: 0.85rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
            }

            .action-link-primary {
                background: rgba(139, 92, 246, 0.1);
                color: #8b5cf6;
            }

            .action-link-primary:hover {
                background: rgba(139, 92, 246, 0.2);
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 24px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12);
            }

            [data-theme="dark"] .empty-state {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .empty-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 20px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .empty-icon i {
                font-size: 2rem;
                color: #8b5cf6;
            }

            .empty-title {
                font-size: 1.2rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 8px 0;
            }

            .empty-message {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
                margin-bottom: 20px;
            }

            .empty-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 0.95rem;
                transition: all 0.3s ease;
            }

            .empty-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
            }

            /* Alert Messages */
            .alert {
                padding: 16px 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .alert-success {
                background: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.3);
                color: #059669;
            }

            .alert-error {
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.3);
                color: #dc2626;
            }

            [data-theme="dark"] .alert-success {
                background: rgba(16, 185, 129, 0.15);
                color: #34d399;
            }

            [data-theme="dark"] .alert-error {
                background: rgba(239, 68, 68, 0.15);
                color: #f87171;
            }

            /* Touch Targets & Focus */
            .action-link,
            .empty-btn,
            .header-actions a {
                min-height: 44px;
            }

            .action-link:focus-visible,
            .empty-btn:focus-visible,
            .header-actions a:focus-visible,
            .group-name a:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            @media (max-width: 768px) {
                .page-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .group-item {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 16px;
                }

                .group-item:hover {
                    transform: none;
                }

                .group-actions {
                    width: 100%;
                }

                .action-link {
                    flex: 1;
                    justify-content: center;
                }
            }
        </style>

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
