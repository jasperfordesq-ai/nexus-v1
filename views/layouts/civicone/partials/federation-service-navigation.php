<?php
/**
 * Federation Service Navigation Partial
 * GOV.UK Service Navigation Pattern
 *
 * Primary navigation for Federation service area
 * 7 items: Hub, Members, Listings, Events, Groups, Messages, Transactions
 *
 * Required variables:
 * - $currentPage: Current page slug (e.g., 'hub', 'members', 'listings')
 */

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$currentPage = $currentPage ?? '';

// Detect current page from REQUEST_URI if not explicitly set
if (empty($currentPage) && isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if (strpos($uri, '/federation/members') !== false) {
        $currentPage = 'members';
    } elseif (strpos($uri, '/federation/listings') !== false) {
        $currentPage = 'listings';
    } elseif (strpos($uri, '/federation/events') !== false) {
        $currentPage = 'events';
    } elseif (strpos($uri, '/federation/groups') !== false) {
        $currentPage = 'groups';
    } elseif (strpos($uri, '/federation/messages') !== false) {
        $currentPage = 'messages';
    } elseif (strpos($uri, '/federation/transactions') !== false) {
        $currentPage = 'transactions';
    } elseif (strpos($uri, '/federation') !== false) {
        $currentPage = 'hub';
    }
}

// Navigation items configuration
$navItems = [
    [
        'slug' => 'hub',
        'label' => 'Hub',
        'url' => $basePath . '/federation',
        'icon' => 'fa-home'
    ],
    [
        'slug' => 'members',
        'label' => 'Members',
        'url' => $basePath . '/federation/members',
        'icon' => 'fa-users'
    ],
    [
        'slug' => 'listings',
        'label' => 'Listings',
        'url' => $basePath . '/federation/listings',
        'icon' => 'fa-hand-holding-heart'
    ],
    [
        'slug' => 'events',
        'label' => 'Events',
        'url' => $basePath . '/federation/events',
        'icon' => 'fa-calendar-days'
    ],
    [
        'slug' => 'groups',
        'label' => 'Groups',
        'url' => $basePath . '/federation/groups',
        'icon' => 'fa-people-group'
    ],
    [
        'slug' => 'messages',
        'label' => 'Messages',
        'url' => $basePath . '/federation/messages',
        'icon' => 'fa-comments'
    ],
    [
        'slug' => 'transactions',
        'label' => 'Transactions',
        'url' => $basePath . '/federation/transactions',
        'icon' => 'fa-arrow-right-arrow-left'
    ]
];
?>

<!-- Federation Service Navigation (GOV.UK Pattern) -->
<nav class="civicone-service-navigation" aria-label="Federation navigation">
    <div class="civicone-service-navigation__container">
        <div class="civicone-service-navigation__branding">
            <a href="<?= $basePath ?>/federation" class="civicone-service-navigation__logo">
                <span class="civicone-service-navigation__service-name">Partner Communities</span>
            </a>
        </div>
        <ul class="civicone-service-navigation__list">
            <?php foreach ($navItems as $item): ?>
            <li class="civicone-service-navigation__item">
                <a href="<?= $item['url'] ?>"
                   class="civicone-service-navigation__link"
                   <?= $currentPage === $item['slug'] ? 'aria-current="page"' : '' ?>>
                    <?= $item['label'] ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
