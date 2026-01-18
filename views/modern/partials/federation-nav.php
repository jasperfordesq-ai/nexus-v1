<?php
/**
 * Federation Navigation Tab Bar
 * Include on all federation pages for consistent navigation
 *
 * Required variables:
 * - $basePath: The tenant base path
 * - $currentPage: Current page identifier ('hub', 'dashboard', 'settings', 'activity', 'help')
 *
 * Optional:
 * - $userOptedIn: Whether user has opted into federation (default: false)
 */

$currentPage = $currentPage ?? '';
$userOptedIn = $userOptedIn ?? false;
$basePath = $basePath ?? '';
?>

<nav class="fed-nav-tabs" role="navigation" aria-label="Federation navigation">
    <a href="<?= htmlspecialchars($basePath) ?>/federation"
       class="fed-nav-tab <?= $currentPage === 'hub' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'hub' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-globe"></i>
        <span>Hub</span>
    </a>
    <?php if ($userOptedIn): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/dashboard"
       class="fed-nav-tab <?= $currentPage === 'dashboard' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'dashboard' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Dashboard</span>
    </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/settings"
       class="fed-nav-tab <?= $currentPage === 'settings' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'settings' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-sliders"></i>
        <span>Settings</span>
    </a>
    <?php if ($userOptedIn): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/activity"
       class="fed-nav-tab <?= $currentPage === 'activity' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'activity' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-bell"></i>
        <span>Activity</span>
    </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/help"
       class="fed-nav-tab <?= $currentPage === 'help' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'help' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-circle-question"></i>
        <span>Help</span>
    </a>
</nav>

<style>
/* Federation Navigation Tabs */
.fed-nav-tabs {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 16px 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.fed-nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: rgba(139, 92, 246, 0.08);
    border: 1px solid rgba(139, 92, 246, 0.15);
    border-radius: 12px;
    color: var(--htb-text-secondary, #6b7280);
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.fed-nav-tab:hover {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.3);
    color: #8b5cf6;
    transform: translateY(-2px);
}

.fed-nav-tab.active {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border-color: transparent;
    color: white;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.fed-nav-tab.active:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(139, 92, 246, 0.4);
}

.fed-nav-tab i {
    font-size: 1rem;
}

[data-theme="dark"] .fed-nav-tab {
    background: rgba(139, 92, 246, 0.12);
    border-color: rgba(139, 92, 246, 0.2);
    color: #94a3b8;
}

[data-theme="dark"] .fed-nav-tab:hover {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

[data-theme="dark"] .fed-nav-tab.active {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

@media (max-width: 640px) {
    .fed-nav-tabs {
        gap: 6px;
        padding: 12px 16px;
    }

    .fed-nav-tab {
        padding: 10px 14px;
        font-size: 0.85rem;
    }

    .fed-nav-tab span {
        display: none;
    }

    .fed-nav-tab i {
        font-size: 1.1rem;
    }
}
</style>
