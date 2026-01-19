<?php
// Organization Profile Page - Enhanced Glassmorphism Design
// Shows: org info, volunteer opportunities, membership options

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = \Nexus\Core\TenantContext::hasFeature('wallet');

// Set variables for the utility bar
$activeTab = 'profile';
$isMember = $isMember ?? false;
$isAdmin = $isAdmin ?? false;
$isOwner = $isOwner ?? false;
$role = $isOwner ? 'owner' : ($isAdmin ? 'admin' : ($isMember ? 'member' : ''));

// Get pending count for badge (if admin)
$pendingCount = 0;
if ($isAdmin && $hasTimebanking) {
    try {
        $pendingCount = \Nexus\Models\OrgTransferRequest::countPending($org['id']);
    } catch (\Exception $e) {
        $pendingCount = 0;
    }
}

$hero_title = htmlspecialchars($org['name']);
$hero_subtitle = "Volunteer Organization";
$hero_gradient = 'htb-hero-gradient-teal';
$hideHero = true;

require __DIR__ . '/../../layouts/header.php';
?>


<div class="org-profile-bg"></div>

<div class="org-profile-container">

    <!-- Include shared utility bar -->
    <?php include __DIR__ . '/../organizations/_org-utility-bar.php'; ?>

    <!-- Hero Banner -->
    <div class="org-hero-banner">
        <div class="org-hero-pattern"></div>
    </div>

    <!-- Profile Card -->
    <div class="org-profile-card">
        <div class="org-profile-content">
            <div class="org-profile-logo">
                <?php if (!empty($org['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="<?= htmlspecialchars($org['name']) ?>">
                <?php else: ?>
                    <i class="fa-solid fa-building"></i>
                <?php endif; ?>
            </div>
            <div class="org-profile-info">
                <h1 class="org-profile-name"><?= htmlspecialchars($org['name']) ?></h1>

                <span class="org-profile-status <?= ($org['status'] ?? 'active') === 'active' ? 'active' : 'pending' ?>">
                    <i class="fa-solid <?= ($org['status'] ?? 'active') === 'active' ? 'fa-circle-check' : 'fa-clock' ?>"></i>
                    <?= ucfirst($org['status'] ?? 'active') ?>
                </span>

                <div class="org-profile-meta">
                    <?php if (!empty($org['contact_email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($org['contact_email']) ?>" class="org-profile-meta-item">
                            <i class="fa-solid fa-envelope"></i>
                            <?= htmlspecialchars($org['contact_email']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($org['website'])): ?>
                        <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="org-profile-meta-item">
                            <i class="fa-solid fa-globe"></i>
                            Website
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($org['location'])): ?>
                        <span class="org-profile-meta-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= htmlspecialchars($org['location']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($hasTimebanking && $memberCount > 0): ?>
                        <span class="org-profile-meta-item">
                            <i class="fa-solid fa-users"></i>
                            <?= $memberCount ?> member<?= $memberCount !== 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($org['description'])): ?>
                    <p class="org-profile-description"><?= nl2br(htmlspecialchars($org['description'])) ?></p>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="org-quick-actions">
                    <?php if ($hasTimebanking): ?>
                        <?php if ($isMember): ?>
                            <a href="<?= $base ?>/organizations/<?= $org['id'] ?>/wallet" class="org-action-btn org-action-btn--wallet">
                                <i class="fa-solid fa-wallet"></i> View Wallet
                            </a>
                        <?php elseif ($isLoggedIn): ?>
                            <form action="<?= $base ?>/organizations/<?= $org['id'] ?>/members/request" method="POST" style="display: inline;">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <button type="submit" class="org-action-btn org-action-btn--primary">
                                    <i class="fa-solid fa-user-plus"></i> Request to Join
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($org['website'])): ?>
                        <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="org-action-btn org-action-btn--secondary">
                            <i class="fa-solid fa-external-link"></i> Visit Website
                        </a>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                        <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="org-action-btn org-action-btn--secondary">
                            <i class="fa-solid fa-plus"></i> Add Opportunity
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="org-stats-grid">
        <div class="org-stat-card">
            <div class="org-stat-icon">
                <i class="fa-solid fa-hand-holding-heart"></i>
            </div>
            <div class="org-stat-value"><?= count($opportunities) ?></div>
            <div class="org-stat-label">Opportunities</div>
        </div>
        <?php if ($hasTimebanking && $memberCount > 0): ?>
            <div class="org-stat-card">
                <div class="org-stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="org-stat-value"><?= $memberCount ?></div>
                <div class="org-stat-label">Members</div>
            </div>
        <?php endif; ?>
        <?php if (!empty($org['created_at'])): ?>
            <div class="org-stat-card">
                <div class="org-stat-icon">
                    <i class="fa-solid fa-calendar"></i>
                </div>
                <div class="org-stat-value"><?= date('Y', strtotime($org['created_at'])) ?></div>
                <div class="org-stat-label">Founded</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Volunteer Opportunities Section -->
    <div class="org-section">
        <div class="org-section-header">
            <h2 class="org-section-title">
                <i class="fa-solid fa-hand-holding-heart"></i>
                Volunteer Opportunities
            </h2>
            <?php if ($isAdmin): ?>
                <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="org-section-action">
                    <i class="fa-solid fa-plus"></i> Add New
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($opportunities)): ?>
            <div class="org-opps-grid">
                <?php foreach ($opportunities as $opp): ?>
                    <a href="<?= $base ?>/volunteering/<?= $opp['id'] ?>" class="org-opp-card">
                        <div class="org-opp-header">
                            <div class="org-opp-icon">
                                <i class="fa-solid fa-hands-helping"></i>
                            </div>
                            <div>
                                <h3 class="org-opp-title"><?= htmlspecialchars($opp['title']) ?></h3>
                                <?php if (!empty($opp['commitment_type'])): ?>
                                    <span class="org-opp-type"><?= htmlspecialchars($opp['commitment_type']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="org-opp-desc"><?= htmlspecialchars(substr($opp['description'], 0, 140)) ?>...</p>
                        <div class="org-opp-footer">
                            <?php if (!empty($opp['location'])): ?>
                                <span class="org-opp-meta">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($opp['location']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($opp['created_at'])): ?>
                                <span class="org-opp-meta">
                                    <i class="fa-solid fa-clock"></i>
                                    <?= date('M j, Y', strtotime($opp['created_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="org-empty-state">
                <div class="org-empty-icon">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <h3 class="org-empty-title">No Opportunities Yet</h3>
                <p class="org-empty-desc">This organization hasn't posted any volunteer opportunities.</p>
                <?php if ($isAdmin): ?>
                    <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="org-action-btn org-action-btn--primary" style="margin-top: 20px;">
                        <i class="fa-solid fa-plus"></i> Create First Opportunity
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
