<?php
// Federated Group Detail - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Group";
$hideHero = true;

Nexus\Core\SEO::setTitle(($group['name'] ?? 'Group') . ' - Federated');
Nexus\Core\SEO::setDescription('Group details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
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

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
