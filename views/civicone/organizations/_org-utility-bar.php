<?php
/**
 * Organization Utility Bar - Shared Component
 *
 * This provides a consistent navigation bar across all organization pages.
 * Include this partial after setting the required variables:
 *
 * Required:
 * - $org (array) - The organization data with 'id', 'name'
 * - $activeTab (string) - Current tab: 'profile', 'wallet', 'members', 'requests'
 *
 * Optional:
 * - $isAdmin (bool) - Whether current user is org admin
 * - $isMember (bool) - Whether current user is org member
 * - $isOwner (bool) - Whether current user is org owner
 * - $role (string) - Current user's role in org
 * - $pendingCount (int) - Number of pending requests (for badge)
 */

$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = \Nexus\Core\TenantContext::hasFeature('wallet');
$orgId = $org['id'] ?? 0;
$orgName = $org['name'] ?? 'Organization';
$activeTab = $activeTab ?? 'profile';
$isAdmin = $isAdmin ?? false;
$isMember = $isMember ?? false;
$role = $role ?? 'member';
$pendingCount = $pendingCount ?? 0;

// Load utility bar CSS (loaded once per page, shared across org pages)
static $utilityBarCssLoaded = false;
if (!$utilityBarCssLoaded):
    $utilityBarCssLoaded = true;
?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/purged/civicone-organizations-utility-bar.min.css">
<?php endif; ?>

<div class="org-utility-bar">
    <!-- Header with org identity and actions -->
    <div class="org-utility-header">
        <div class="org-utility-identity">
            <div class="org-utility-logo">
                <?php if (!empty($org['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="<?= htmlspecialchars($orgName) ?>">
                <?php else: ?>
                    <?= strtoupper(substr($orgName, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="org-utility-info">
                <h1 class="org-utility-name"><?= htmlspecialchars($orgName) ?></h1>
                <?php if ($isMember): ?>
                    <span class="org-utility-role <?= htmlspecialchars($role) ?>">
                        <i class="fa-solid <?= $role === 'owner' ? 'fa-crown' : ($role === 'admin' ? 'fa-shield' : 'fa-user') ?>"></i>
                        <?= ucfirst($role) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="org-utility-actions">
            <?php if ($isAdmin): ?>
                <a href="<?= $base ?>/volunteering/org/edit/<?= $orgId ?>" class="org-utility-action">
                    <i class="fa-solid fa-pen"></i>
                    <span>Edit</span>
                </a>
            <?php endif; ?>
            <a href="<?= $base ?>/volunteering" class="org-utility-back">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Volunteering</span>
            </a>
        </div>
    </div>

    <!-- Tab Navigation -->
    <nav role="navigation" aria-label="Main navigation" class="org-utility-tabs">
        <a href="<?= $base ?>/volunteering/organization/<?= $orgId ?>"
           class="org-utility-tab <?= $activeTab === 'profile' ? 'active' : '' ?>">
            <i class="fa-solid fa-building"></i>
            <span>Profile</span>
        </a>

        <?php if ($hasTimebanking && $isMember): ?>
            <a href="<?= $base ?>/organizations/<?= $orgId ?>/wallet"
               class="org-utility-tab <?= $activeTab === 'wallet' ? 'active' : '' ?>">
                <i class="fa-solid fa-wallet"></i>
                <span>Wallet</span>
            </a>

            <a href="<?= $base ?>/organizations/<?= $orgId ?>/members"
               class="org-utility-tab <?= $activeTab === 'members' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Members</span>
            </a>

            <?php if ($isAdmin): ?>
                <a href="<?= $base ?>/organizations/<?= $orgId ?>/wallet/requests"
                   class="org-utility-tab <?= $activeTab === 'requests' ? 'active' : '' ?>">
                    <i class="fa-solid fa-inbox"></i>
                    <span>Requests</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="org-tab-badge"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="<?= $base ?>/organizations/<?= $orgId ?>/audit-log"
                   class="org-utility-tab <?= $activeTab === 'audit' ? 'active' : '' ?>">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Audit</span>
                </a>
            <?php endif; ?>
        <?php elseif ($hasTimebanking): ?>
            <!-- Non-member sees limited tabs -->
            <a href="<?= $base ?>/organizations/<?= $orgId ?>/members"
               class="org-utility-tab <?= $activeTab === 'members' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Members</span>
            </a>
        <?php endif; ?>
    </nav>
</div>
