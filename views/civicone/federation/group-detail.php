<?php
/**
 * Federated Group Detail
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/groups" class="civic-fed-back-link" aria-label="Return to groups">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federated Groups
    </a>

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

    <!-- Group Card -->
    <article class="civic-fed-detail-card" aria-labelledby="group-title">
        <header class="civic-fed-detail-header">
            <div class="civic-fed-badges" role="group" aria-label="Group details">
                <span class="civic-fed-badge civic-fed-badge--partner">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <?= htmlspecialchars($group['tenant_name'] ?? 'Partner Timebank') ?>
                </span>
            </div>

            <h1 id="group-title" class="civic-fed-detail-title"><?= htmlspecialchars($group['name'] ?? 'Untitled Group') ?></h1>
        </header>

        <div class="civic-fed-detail-body">
            <!-- Group Stats -->
            <div class="civic-fed-info-grid" role="group" aria-label="Group statistics">
                <div class="civic-fed-info-item">
                    <div class="civic-fed-info-icon" aria-hidden="true">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="civic-fed-info-content">
                        <h4>Members</h4>
                        <p><?= (int)($group['member_count'] ?? 0) ?></p>
                    </div>
                </div>

                <div class="civic-fed-info-item">
                    <div class="civic-fed-info-icon" aria-hidden="true">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="civic-fed-info-content">
                        <h4>Created By</h4>
                        <p><?= htmlspecialchars($creatorName) ?></p>
                    </div>
                </div>

                <?php if (!empty($group['created_at'])): ?>
                    <div class="civic-fed-info-item">
                        <div class="civic-fed-info-icon" aria-hidden="true">
                            <i class="fa-solid fa-calendar"></i>
                        </div>
                        <div class="civic-fed-info-content">
                            <h4>Created</h4>
                            <p><time datetime="<?= date('c', strtotime($group['created_at'])) ?>"><?= date('M j, Y', strtotime($group['created_at'])) ?></time></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if (!empty($group['description'])): ?>
                <section class="civic-fed-section" aria-labelledby="about-heading">
                    <h3 id="about-heading" class="civic-fed-section-title">
                        <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                        About This Group
                    </h3>
                    <div class="civic-fed-content">
                        <?= nl2br(htmlspecialchars($group['description'])) ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Membership Section -->
            <section class="civic-fed-section" aria-labelledby="membership-heading">
                <h3 id="membership-heading" class="civic-fed-section-title">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    Membership
                </h3>

                <?php if ($isMember): ?>
                    <div class="civic-fed-registration-status" role="status" aria-live="polite">
                        <?php if ($membershipStatus === 'pending'): ?>
                            <span class="civic-fed-status-badge civic-fed-status-badge--pending">
                                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                Membership Pending Approval
                            </span>
                        <?php else: ?>
                            <span class="civic-fed-status-badge civic-fed-status-badge--success">
                                <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
                                You're a Member
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($membershipStatus === 'approved'): ?>
                        <form action="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>/leave" method="POST" onsubmit="return confirm('Are you sure you want to leave this group?');" aria-label="Leave group">
                            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($group['tenant_id'] ?? '') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="civic-fed-btn civic-fed-btn--danger">
                                <i class="fa-solid fa-sign-out-alt" aria-hidden="true"></i>
                                Leave Group
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="civic-fed-note">
                            Your membership request is awaiting approval from the group admin.
                        </p>
                    <?php endif; ?>

                <?php elseif ($canJoin): ?>
                    <form action="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>/join" method="POST" aria-label="Join group">
                        <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($group['tenant_id'] ?? '') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <button type="submit" class="civic-fed-btn civic-fed-btn--primary">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <?= !empty($group['requires_approval']) ? 'Request to Join' : 'Join Group' ?>
                        </button>
                    </form>
                    <?php if (!empty($group['requires_approval'])): ?>
                        <p class="civic-fed-note">
                            <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                            This group requires approval from a group admin.
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="civic-fed-btn civic-fed-btn--disabled" disabled aria-disabled="true">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        Membership Not Available
                    </button>
                    <p class="civic-fed-note">
                        Enable federation features in your settings to join groups from partner timebanks.
                    </p>
                <?php endif; ?>
            </section>

            <!-- Privacy Notice -->
            <aside class="civic-fed-notice" role="note">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div>
                    <strong>Federated Group</strong><br>
                    This group is hosted by <strong><?= htmlspecialchars($group['tenant_name'] ?? 'a partner timebank') ?></strong>.
                    When you join, your basic profile information will be visible to other group members.
                </div>
            </aside>
        </div>
    </article>
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
