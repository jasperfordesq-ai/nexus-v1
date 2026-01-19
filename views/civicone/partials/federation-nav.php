<?php
/**
 * Federation Navigation Tab Bar - CivicOne Version
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
        <i class="fa-solid fa-globe" aria-hidden="true"></i>
        <span>Hub</span>
    </a>
    <?php if ($userOptedIn): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/dashboard"
       class="fed-nav-tab <?= $currentPage === 'dashboard' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'dashboard' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
        <span>Dashboard</span>
    </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/settings"
       class="fed-nav-tab <?= $currentPage === 'settings' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'settings' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
        <span>Settings</span>
    </a>
    <?php if ($userOptedIn): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/activity"
       class="fed-nav-tab <?= $currentPage === 'activity' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'activity' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-bell" aria-hidden="true"></i>
        <span>Activity</span>
    </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/help"
       class="fed-nav-tab <?= $currentPage === 'help' ? 'active' : '' ?>"
       aria-current="<?= $currentPage === 'help' ? 'page' : 'false' ?>">
        <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
        <span>Help</span>
    </a>
</nav>
