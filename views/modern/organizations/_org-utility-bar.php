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
?>

<style>
/* ============================================
   ORGANIZATION UTILITY BAR - GLASSMORPHISM
   A unified navigation component for org pages
   ============================================ */

.org-utility-bar {
    position: sticky;
    top: 56px; /* Below main utility bar */
    z-index: 50;
    margin-bottom: 24px;
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.95) 0%,
        rgba(255, 255, 255, 0.85) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

[data-theme="dark"] .org-utility-bar {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.95) 0%,
        rgba(30, 41, 59, 0.85) 100%);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
}

/* Org Header Section */
.org-utility-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    flex-wrap: wrap;
    gap: 12px;
}

[data-theme="dark"] .org-utility-header {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.org-utility-identity {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.org-utility-logo {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.org-utility-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.org-utility-info {
    min-width: 0;
}

.org-utility-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .org-utility-name {
    color: #f1f5f9;
}

.org-utility-role {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.org-utility-role.owner {
    background: rgba(251, 191, 36, 0.15);
    color: #b45309;
}

.org-utility-role.admin {
    background: rgba(139, 92, 246, 0.15);
    color: #7c3aed;
}

.org-utility-role.member {
    background: rgba(107, 114, 128, 0.15);
    color: #6b7280;
}

[data-theme="dark"] .org-utility-role.owner {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

[data-theme="dark"] .org-utility-role.admin {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

[data-theme="dark"] .org-utility-role.member {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
}

/* Quick Actions */
.org-utility-actions {
    display: flex;
    gap: 8px;
}

.org-utility-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 10px;
    color: #059669;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.org-utility-action:hover {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.3);
    transform: translateY(-1px);
}

.org-utility-action.primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.org-utility-action.primary:hover {
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

[data-theme="dark"] .org-utility-action {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.3);
    color: #34d399;
}

/* Tab Navigation */
.org-utility-tabs {
    display: flex;
    padding: 0 8px;
    gap: 4px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.org-utility-tabs::-webkit-scrollbar {
    display: none;
}

.org-utility-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 18px;
    color: #6b7280;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    white-space: nowrap;
    position: relative;
}

.org-utility-tab:hover {
    color: #10b981;
    background: rgba(16, 185, 129, 0.05);
}

.org-utility-tab.active {
    color: #10b981;
    border-bottom-color: #10b981;
    background: rgba(16, 185, 129, 0.08);
}

.org-utility-tab i {
    font-size: 1rem;
}

[data-theme="dark"] .org-utility-tab {
    color: #94a3b8;
}

[data-theme="dark"] .org-utility-tab:hover {
    color: #34d399;
    background: rgba(16, 185, 129, 0.1);
}

[data-theme="dark"] .org-utility-tab.active {
    color: #34d399;
    border-bottom-color: #34d399;
    background: rgba(16, 185, 129, 0.15);
}

/* Tab Badge */
.org-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

/* Back to Volunteering */
.org-utility-back {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    color: #6b7280;
    font-weight: 500;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border-radius: 8px;
}

.org-utility-back:hover {
    color: #374151;
    background: rgba(0, 0, 0, 0.05);
}

.org-utility-back i {
    font-size: 0.8rem;
}

[data-theme="dark"] .org-utility-back {
    color: #94a3b8;
}

[data-theme="dark"] .org-utility-back:hover {
    color: #e2e8f0;
    background: rgba(255, 255, 255, 0.05);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .org-utility-bar {
        margin: 0 -16px 24px -16px;
        border-radius: 0;
        position: sticky;
        top: 56px;
    }

    .org-utility-header {
        padding: 12px 16px;
    }

    .org-utility-logo {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        font-size: 1rem;
    }

    .org-utility-name {
        font-size: 1rem;
    }

    .org-utility-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .org-utility-action {
        padding: 8px 12px;
        font-size: 0.8rem;
    }

    .org-utility-action span {
        display: none;
    }

    .org-utility-tabs {
        padding: 0 4px;
    }

    .org-utility-tab {
        padding: 12px 14px;
        font-size: 0.85rem;
    }

    .org-utility-tab span {
        display: none;
    }
}
</style>

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
