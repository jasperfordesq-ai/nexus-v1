<?php
/**
 * CivicOne Account Area Secondary Navigation
 * Pattern: MOJ Sub navigation
 * https://design-patterns.service.justice.gov.uk/components/sub-navigation/
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

// Get notification count for badge
$notifCount = 0;
if (isset($_SESSION['user_id'])) {
    $notifCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
}

// Define account section navigation items
$accountNavItems = [
    [
        'label' => 'Overview',
        'url' => '/dashboard',
        'pattern' => '/dashboard',
        'icon' => 'fa-house'
    ],
    [
        'label' => 'Notifications',
        'url' => '/dashboard/notifications',
        'pattern' => '/dashboard/notifications',
        'icon' => 'fa-bell',
        'badge' => $notifCount
    ],
    [
        'label' => 'My Hubs',
        'url' => '/dashboard/hubs',
        'pattern' => '/dashboard/hubs',
        'icon' => 'fa-users'
    ],
    [
        'label' => 'My Listings',
        'url' => '/dashboard/listings',
        'pattern' => '/dashboard/listings',
        'icon' => 'fa-list'
    ],
    [
        'label' => 'Wallet',
        'url' => '/dashboard/wallet',
        'pattern' => '/dashboard/wallet',
        'icon' => 'fa-wallet'
    ],
];

// Add Events only if feature is enabled
if (\Nexus\Core\TenantContext::hasFeature('events')) {
    $accountNavItems[] = [
        'label' => 'Events',
        'url' => '/dashboard/events',
        'pattern' => '/dashboard/events',
        'icon' => 'fa-calendar'
    ];
}
?>

<!-- MOJ Sub navigation pattern for Account Area -->
<nav class="moj-sub-navigation civicone-account-nav" aria-label="Account sections">
    <ul class="moj-sub-navigation__list">
        <?php foreach ($accountNavItems as $item): ?>
            <?php
            // Check if current page (remove query params for matching)
            $cleanPath = strtok($currentPath, '?');
            $isActive = ($cleanPath === $basePath . $item['pattern']) ||
                        (rtrim($cleanPath, '/') === rtrim($basePath . $item['pattern'], '/'));
            $activeClass = $isActive ? ' moj-sub-navigation__item--active' : '';
            ?>
            <li class="moj-sub-navigation__item<?= $activeClass ?>">
                <a class="moj-sub-navigation__link"
                   href="<?= $basePath ?><?= $item['url'] ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= $item['icon'] ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                        <span class="moj-notification-badge" aria-label="<?= $item['badge'] ?> unread">
                            <?= $item['badge'] > 99 ? '99+' : $item['badge'] ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
