<?php
/**
 * Federation Navigation Tab Bar - CivicOne Version
 * WCAG 2.1 AA Compliant - GOV.UK Design Patterns
 * Updated: 2026-01-31 - Migrated to GOV.UK Tabs pattern
 *
 * Required variables:
 * - $basePath: The tenant base path
 * - $currentPage: Current page identifier ('hub', 'dashboard', 'settings', 'activity', 'help')
 *
 * Optional:
 * - $userOptedIn: Whether user has opted into federation (default: false)
 *
 * GOV.UK Design System Reference:
 * https://design-system.service.gov.uk/components/tabs/
 */

$currentPage = $currentPage ?? '';
$userOptedIn = $userOptedIn ?? false;
$basePath = $basePath ?? '';

// Build navigation items dynamically based on user state
$navItems = [
    ['id' => 'hub', 'label' => 'Hub', 'icon' => 'fa-globe', 'url' => '/federation', 'always' => true],
    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'url' => '/federation/dashboard', 'always' => false],
    ['id' => 'settings', 'label' => 'Settings', 'icon' => 'fa-sliders', 'url' => '/federation/settings', 'always' => true],
    ['id' => 'activity', 'label' => 'Activity', 'icon' => 'fa-bell', 'url' => '/federation/activity', 'always' => false],
    ['id' => 'help', 'label' => 'Help', 'icon' => 'fa-circle-question', 'url' => '/federation/help', 'always' => true],
];
?>

<nav class="govuk-tabs civicone-federation-tabs" data-module="govuk-tabs" aria-label="Federation navigation">
    <h2 class="govuk-tabs__title govuk-visually-hidden">Federation sections</h2>
    <ul class="govuk-tabs__list" role="tablist">
        <?php foreach ($navItems as $item):
            // Skip items that require opt-in if user hasn't opted in
            if (!$item['always'] && !$userOptedIn) continue;

            $isActive = $currentPage === $item['id'];
            $itemUrl = htmlspecialchars($basePath . $item['url']);
        ?>
        <li class="govuk-tabs__list-item<?= $isActive ? ' govuk-tabs__list-item--selected' : '' ?>" role="presentation">
            <a class="govuk-tabs__tab"
               href="<?= $itemUrl ?>"
               role="tab"
               aria-selected="<?= $isActive ? 'true' : 'false' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>
               tabindex="<?= $isActive ? '0' : '-1' ?>">
                <i class="fa-solid <?= htmlspecialchars($item['icon']) ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</nav>
