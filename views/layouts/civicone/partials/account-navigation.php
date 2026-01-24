<?php
/**
 * CivicOne Account Area Secondary Navigation
 * GOV.UK Frontend v5.14.0 Compliant
 *
 * Pattern: GOV.UK Service Navigation style
 * Based on: https://design-system.service.gov.uk/components/tabs/
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

// Get notification count for badge
$notifCount = 0;
if (isset($_SESSION['user_id'])) {
    $notifCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
}

// Define account section navigation items (text-only, GOV.UK compliant)
$accountNavItems = [
    [
        'label' => 'Overview',
        'url' => '/dashboard',
        'pattern' => '/dashboard'
    ],
    [
        'label' => 'Notifications',
        'url' => '/dashboard/notifications',
        'pattern' => '/dashboard/notifications',
        'badge' => $notifCount
    ],
    [
        'label' => 'My Hubs',
        'url' => '/dashboard/hubs',
        'pattern' => '/dashboard/hubs'
    ],
    [
        'label' => 'My Listings',
        'url' => '/dashboard/listings',
        'pattern' => '/dashboard/listings'
    ],
    [
        'label' => 'Wallet',
        'url' => '/dashboard/wallet',
        'pattern' => '/dashboard/wallet'
    ],
];

// Add Events only if feature is enabled
if (\Nexus\Core\TenantContext::hasFeature('events')) {
    $accountNavItems[] = [
        'label' => 'Events',
        'url' => '/dashboard/events',
        'pattern' => '/dashboard/events'
    ];
}
?>

<!-- GOV.UK Service Navigation (Account Area) -->
<nav class="govuk-!-margin-bottom-6" aria-label="Account sections" style="border-bottom: 1px solid #b1b4b6;">
    <ul class="govuk-list" style="display: flex; flex-wrap: wrap; gap: 0; margin: 0; padding: 0; list-style: none;">
        <?php foreach ($accountNavItems as $item): ?>
            <?php
            // Check if current page (remove query params for matching)
            $cleanPath = strtok($currentPath, '?');
            $isActive = ($cleanPath === $basePath . $item['pattern']) ||
                        (rtrim($cleanPath, '/') === rtrim($basePath . $item['pattern'], '/'));
            ?>
            <li style="margin: 0;">
                <a class="govuk-link govuk-link--no-underline"
                   href="<?= $basePath ?><?= $item['url'] ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>
                   style="display: block; padding: 10px 20px; color: #0b0c0c; font-weight: <?= $isActive ? '700' : '400' ?>; text-decoration: none; <?= $isActive ? 'border-bottom: 4px solid #1d70b8; margin-bottom: -1px;' : '' ?>">
                    <?= htmlspecialchars($item['label']) ?>
                    <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                        <strong class="govuk-tag govuk-tag--red" style="margin-left: 5px; font-size: 12px; vertical-align: middle;">
                            <?= $item['badge'] > 99 ? '99+' : $item['badge'] ?>
                        </strong>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
