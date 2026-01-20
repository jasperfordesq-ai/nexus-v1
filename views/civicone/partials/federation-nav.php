<?php
/**
 * Federation Navigation Tab Bar - CivicOne Version
 * WCAG 2.1 AA Compliant - GOV.UK Design Patterns
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

<nav class="civic-fed-tabs" role="navigation" aria-label="Federation navigation">
    <a href="<?= htmlspecialchars($basePath) ?>/federation"
       class="civic-fed-tab <?= $currentPage === 'hub' ? 'civic-fed-tab--active' : '' ?>"
       <?= $currentPage === 'hub' ? 'aria-current="page"' : '' ?>>
        <i class="fa-solid fa-globe" aria-hidden="true"></i>
        <span>Hub</span>
    </a>
    <?php if ($userOptedIn): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/dashboard"
       class="civic-fed-tab <?= $currentPage === 'dashboard' ? 'civic-fed-tab--active' : '' ?>"
       <?= $currentPage === 'dashboard' ? 'aria-current="page"' : '' ?>>
        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
        <span>Dashboard</span>
    </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/settings"
       class="civic-fed-tab <?= $currentPage === 'settings' ? 'civic-fed-tab--active' : '' ?>"
       <?= $currentPage === 'settings' ? 'aria-current="page"' : '' ?>>
        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
        <span>Settings</span>
    </a>
    <?php if ($userOptedIn): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/activity"
       class="civic-fed-tab <?= $currentPage === 'activity' ? 'civic-fed-tab--active' : '' ?>"
       <?= $currentPage === 'activity' ? 'aria-current="page"' : '' ?>>
        <i class="fa-solid fa-bell" aria-hidden="true"></i>
        <span>Activity</span>
    </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($basePath) ?>/federation/help"
       class="civic-fed-tab <?= $currentPage === 'help' ? 'civic-fed-tab--active' : '' ?>"
       <?= $currentPage === 'help' ? 'aria-current="page"' : '' ?>>
        <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
        <span>Help</span>
    </a>
</nav>
