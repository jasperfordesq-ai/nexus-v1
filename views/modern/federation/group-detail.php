<?php
// Federated Group Detail - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Group";
$hideHero = true;

Nexus\Core\SEO::setTitle(($group['name'] ?? 'Group') . ' - Federated');
Nexus\Core\SEO::setDescription('Group details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$group = $group ?? [];
$canJoin = $canJoin ?? false;
$isMember = $isMember ?? false;
$membershipStatus = $membershipStatus ?? null;

$creatorName = trim(($group['creator_first_name'] ?? '') . ' ' . ($group['creator_last_name'] ?? '')) ?: 'Unknown';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-group-wrapper">

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

            #fed-group-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 900px;
                margin: 0 auto;
                padding: 20px 0;
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            .group-card {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 24px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
                overflow: hidden;
            }

            [data-theme="dark"] .group-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .group-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                padding: 30px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            [data-theme="dark"] .group-header {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .group-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 16px;
            }

            .group-tenant {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 10px 18px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 600;
                color: #8b5cf6;
            }

            [data-theme="dark"] .group-tenant {
                background: rgba(139, 92, 246, 0.2);
                color: #a78bfa;
            }

            .group-title {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main);
                margin: 0;
            }

            .group-body {
                padding: 30px;
            }

            .section-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 12px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-title i {
                color: #8b5cf6;
            }

            /* Group Stats */
            .group-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 16px;
                margin-bottom: 30px;
            }

            .stat-card {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.12);
                border-radius: 14px;
            }

            .stat-icon {
                width: 44px;
                height: 44px;
                background: rgba(139, 92, 246, 0.12);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .stat-icon i {
                font-size: 1.1rem;
                color: #8b5cf6;
            }

            .stat-content h4 {
                font-size: 0.8rem;
                font-weight: 600;
                color: var(--htb-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin: 0 0 4px 0;
            }

            .stat-content p {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0;
            }

            /* Description */
            .group-description {
                color: var(--htb-text-main);
                font-size: 1rem;
                line-height: 1.8;
                margin-bottom: 30px;
            }

            /* Membership Section */
            .membership-section {
                padding: 24px;
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.08),
                        rgba(168, 85, 247, 0.06));
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 16px;
                margin-bottom: 24px;
            }

            .membership-status {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                border-radius: 10px;
                font-weight: 600;
                font-size: 0.9rem;
            }

            .status-member {
                background: rgba(16, 185, 129, 0.15);
                color: #059669;
            }

            .status-pending {
                background: rgba(245, 158, 11, 0.15);
                color: #d97706;
            }

            [data-theme="dark"] .status-member {
                background: rgba(16, 185, 129, 0.25);
                color: #34d399;
            }

            [data-theme="dark"] .status-pending {
                background: rgba(245, 158, 11, 0.25);
                color: #fbbf24;
            }

            .action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 28px;
                border-radius: 14px;
                font-weight: 700;
                font-size: 0.95rem;
                text-decoration: none;
                transition: all 0.3s ease;
                cursor: pointer;
                border: none;
                width: 100%;
            }

            .action-btn-primary {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
            }

            .action-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.45);
            }

            .action-btn-danger {
                background: rgba(239, 68, 68, 0.1);
                color: #dc2626;
                border: 2px solid rgba(239, 68, 68, 0.3);
            }

            .action-btn-danger:hover {
                background: rgba(239, 68, 68, 0.2);
            }

            .action-btn-disabled {
                background: rgba(100, 100, 100, 0.1);
                color: var(--htb-text-muted);
                cursor: not-allowed;
                opacity: 0.6;
            }

            .action-btn-disabled:hover {
                transform: none;
            }

            /* Privacy Notice */
            .privacy-notice {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-top: 24px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 12px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .privacy-notice i {
                color: #8b5cf6;
                margin-top: 2px;
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

            /* Touch Targets */
            .action-btn {
                min-height: 44px;
            }

            /* Focus Visible */
            .action-btn:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            @media (max-width: 640px) {
                #fed-group-wrapper {
                    padding: 15px;
                }

                .group-header,
                .group-body {
                    padding: 20px;
                }

                .group-title {
                    font-size: 1.4rem;
                }

                .group-stats-grid {
                    grid-template-columns: 1fr;
                }

                .membership-status {
                    flex-direction: column;
                    gap: 12px;
                    align-items: flex-start;
                }
            }
        </style>

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/groups" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Federated Groups
        </a>

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

        <!-- Group Card -->
        <div class="group-card">
            <div class="group-header">
                <div class="group-badges">
                    <span class="group-tenant">
                        <i class="fa-solid fa-building"></i>
                        <?= htmlspecialchars($group['tenant_name'] ?? 'Partner Timebank') ?>
                    </span>
                </div>

                <h1 class="group-title"><?= htmlspecialchars($group['name'] ?? 'Untitled Group') ?></h1>
            </div>

            <div class="group-body">
                <!-- Group Stats -->
                <div class="group-stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Members</h4>
                            <p><?= (int)($group['member_count'] ?? 0) ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Created By</h4>
                            <p><?= htmlspecialchars($creatorName) ?></p>
                        </div>
                    </div>

                    <?php if (!empty($group['created_at'])): ?>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-calendar"></i>
                            </div>
                            <div class="stat-content">
                                <h4>Created</h4>
                                <p><?= date('M j, Y', strtotime($group['created_at'])) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <?php if (!empty($group['description'])): ?>
                    <h3 class="section-title">
                        <i class="fa-solid fa-align-left"></i>
                        About This Group
                    </h3>
                    <div class="group-description">
                        <?= nl2br(htmlspecialchars($group['description'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Membership Section -->
                <div class="membership-section">
                    <h3 class="section-title" style="margin-bottom: 16px;">
                        <i class="fa-solid fa-user-plus"></i>
                        Membership
                    </h3>

                    <?php if ($isMember): ?>
                        <div class="membership-status">
                            <?php if ($membershipStatus === 'pending'): ?>
                                <span class="status-badge status-pending">
                                    <i class="fa-solid fa-clock"></i>
                                    Membership Pending Approval
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-member">
                                    <i class="fa-solid fa-check-circle"></i>
                                    You're a Member
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($membershipStatus === 'approved'): ?>
                            <form action="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>/leave" method="POST" onsubmit="return confirm('Are you sure you want to leave this group?');">
                                <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($group['tenant_id'] ?? '') ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="action-btn action-btn-danger">
                                    <i class="fa-solid fa-sign-out-alt"></i>
                                    Leave Group
                                </button>
                            </form>
                        <?php else: ?>
                            <p style="color: var(--htb-text-muted); margin: 0;">
                                Your membership request is awaiting approval from the group admin.
                            </p>
                        <?php endif; ?>

                    <?php elseif ($canJoin): ?>
                        <form action="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>/join" method="POST">
                            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($group['tenant_id'] ?? '') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="action-btn action-btn-primary">
                                <i class="fa-solid fa-user-plus"></i>
                                <?= !empty($group['requires_approval']) ? 'Request to Join' : 'Join Group' ?>
                            </button>
                        </form>
                        <?php if (!empty($group['requires_approval'])): ?>
                            <p style="color: var(--htb-text-muted); margin-top: 12px; font-size: 0.9rem;">
                                <i class="fa-solid fa-info-circle" style="margin-right: 6px;"></i>
                                This group requires approval from a group admin.
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="action-btn action-btn-disabled" disabled>
                            <i class="fa-solid fa-lock"></i>
                            Membership Not Available
                        </button>
                        <p style="color: var(--htb-text-muted); margin-top: 12px; font-size: 0.9rem;">
                            Enable federation features in your settings to join groups from partner timebanks.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Privacy Notice -->
                <div class="privacy-notice">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Federated Group</strong><br>
                        This group is hosted by <strong><?= htmlspecialchars($group['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        When you join, your basic profile information will be visible to other group members.
                    </div>
                </div>
            </div>
        </div>

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
