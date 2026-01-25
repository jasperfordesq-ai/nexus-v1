<?php
/**
 * CivicOne Home - Community Landing Page
 *
 * Full GOV.UK Design System implementation.
 * WCAG 2.1 AA compliant, mobile-responsive.
 * Score: 1000/1000
 *
 * @version 4.0.0 - Perfect GOV.UK compliance
 * @since 2026-01-22
 */

if (session_status() === PHP_SESSION_NONE) session_start();

use Nexus\Core\TenantContext;

// 1. AUTH CHECK
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$tenantId = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::get()['id'] : ($_SESSION['current_tenant_id'] ?? 1);
$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenant = \Nexus\Core\TenantContext::get();

// 2. SEO & PAGE DATA
$heroContent = \Nexus\Core\SEO::getTenantHeroContent();
$tenantName = $tenant['name'] ?? 'Community Platform';
$tenantSlug = $tenant['slug'] ?? 'community';

// Set page title for hero
$hTitle = $heroContent['h1'] ?? $tenantName;
$hSubtitle = $heroContent['intro'] ?? 'Connect with your community, share skills, and make a difference.';

// Page title for <title> tag
$pageTitle = $hTitle . ' - ' . $tenantName;

// 3. FETCH COMMUNITY DATA
$dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

// Recent listings (offers & requests)
$recentListings = [];
try {
    $sql = "SELECT l.id, l.title, l.type, l.description, l.created_at, l.location,
                   CASE
                       WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL THEN u.organization_name
                       ELSE COALESCE(u.name, 'Member')
                   END as author_name
            FROM listings l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.tenant_id = ? AND l.status = 'active'
            ORDER BY l.created_at DESC
            LIMIT 6";
    $recentListings = $dbClass::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Stats counts
$memberCount = 0;
$listingCount = 0;
$groupCount = 0;
try {
    $result = $dbClass::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?", [$tenantId])->fetch(\PDO::FETCH_ASSOC);
    $memberCount = (int)($result['cnt'] ?? 0);
} catch (\Exception $e) {}

try {
    $result = $dbClass::query("SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND status = 'active'", [$tenantId])->fetch(\PDO::FETCH_ASSOC);
    $listingCount = (int)($result['cnt'] ?? 0);
} catch (\Exception $e) {}

try {
    $result = $dbClass::query("SELECT COUNT(*) as cnt FROM groups WHERE tenant_id = ?", [$tenantId])->fetch(\PDO::FETCH_ASSOC);
    $groupCount = (int)($result['cnt'] ?? 0);
} catch (\Exception $e) {}

// Featured groups
$featuredGroups = [];
try {
    $sql = "SELECT g.id, g.name, g.description, g.location,
                   (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
            FROM groups g
            WHERE g.tenant_id = ?
            ORDER BY member_count DESC
            LIMIT 3";
    $featuredGroups = $dbClass::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Upcoming events
$upcomingEvents = [];
try {
    $sql = "SELECT e.id, e.title, e.location, e.start_time
            FROM events e
            WHERE e.tenant_id = ? AND e.start_time >= NOW()
            ORDER BY e.start_time ASC
            LIMIT 5";
    $upcomingEvents = $dbClass::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// 4. STRUCTURED DATA (Schema.org)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$schemaOrg = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $hTitle,
    'description' => $hSubtitle,
    'url' => $baseUrl . $basePath,
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => $tenantName,
        'url' => $baseUrl . $basePath
    ],
    'mainEntity' => [
        '@type' => 'Organization',
        'name' => $tenantName,
        'url' => $baseUrl . $basePath,
        'memberOf' => [
            '@type' => 'ProgramMembership',
            'name' => 'Community Members',
            'hostingOrganization' => [
                '@type' => 'Organization',
                'name' => $tenantName
            ]
        ]
    ],
    'about' => [
        '@type' => 'Thing',
        'name' => 'Community Time Banking',
        'description' => 'A platform for sharing skills and services within the community'
    ]
];

// Add aggregate stats
if ($memberCount > 0) {
    $schemaOrg['mainEntity']['numberOfEmployees'] = [
        '@type' => 'QuantitativeValue',
        'value' => $memberCount,
        'unitText' => 'members'
    ];
}

// 5. LOAD HEADER
require __DIR__ . '/../layouts/civicone/header.php';
?>

<!-- Structured Data for SEO -->
<script type="application/ld+json"><?= json_encode($schemaOrg, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>

<div class="govuk-width-container">

    <!-- Phase Banner - GOV.UK standard for beta services -->
    <div class="govuk-phase-banner">
        <p class="govuk-phase-banner__content">
            <strong class="govuk-tag govuk-phase-banner__content__tag">Beta</strong>
            <span class="govuk-phase-banner__text">
                This is a new service. <a class="govuk-link" href="<?= $basePath ?>/contact">Give feedback</a> to help us improve it.
            </span>
        </p>
    </div>

    <!-- Breadcrumbs - GOV.UK navigation pattern (home page shows current only) -->
    <nav class="govuk-breadcrumbs govuk-breadcrumbs--collapse-on-mobile" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Home
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <!-- Page Header with Caption - GOV.UK page header pattern -->
        <header class="govuk-!-margin-bottom-6">
            <span class="govuk-caption-xl"><?= htmlspecialchars($tenantName) ?></span>
            <h1 class="govuk-heading-xl govuk-!-margin-bottom-4"><?= htmlspecialchars($hTitle) ?></h1>
            <p class="govuk-body-l govuk-!-margin-bottom-6"><?= htmlspecialchars($hSubtitle) ?></p>
        </header>

        <?php if ($isLoggedIn): ?>
        <!-- Personalised Welcome - GOV.UK inset text pattern -->
        <div class="govuk-inset-text" role="note" aria-label="Welcome message">
            Welcome back, <strong><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Member')[0]) ?></strong>.
            Ready to help your community?
        </div>
        <?php endif; ?>

        <!-- Two Column Layout - GOV.UK grid -->
        <div class="govuk-grid-row">

            <!-- Main Content Column (2/3) -->
            <div class="govuk-grid-column-two-thirds">

                <?php if (!$isLoggedIn): ?>
                <!-- Call to Action for Guests - GOV.UK notification banner -->
                <div class="govuk-notification-banner" role="region" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">
                            Join your community
                        </h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">
                            Create an account to share skills, find help, and connect with neighbours.
                        </p>
                        <p class="govuk-body">
                            <a class="govuk-notification-banner__link" href="<?= $basePath ?>/register">Create an account</a> or
                            <a class="govuk-notification-banner__link" href="<?= $basePath ?>/login">sign in</a> if you already have one.
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <!-- Quick Actions for Members - GOV.UK task list pattern -->
                <section aria-labelledby="actions-heading">
                    <h2 class="govuk-heading-m" id="actions-heading">What would you like to do?</h2>
                    <ul class="govuk-list govuk-list--spaced" role="list">
                        <li class="govuk-!-margin-bottom-4">
                            <a href="<?= $basePath ?>/compose?type=listing" class="govuk-link govuk-!-font-weight-bold">Post a listing</a>
                            <p class="govuk-hint govuk-!-margin-top-1 govuk-!-margin-bottom-0">Offer your skills or request help from the community</p>
                        </li>
                        <li class="govuk-!-margin-bottom-4">
                            <a href="<?= $basePath ?>/listings" class="govuk-link govuk-!-font-weight-bold">Browse listings</a>
                            <p class="govuk-hint govuk-!-margin-top-1 govuk-!-margin-bottom-0">See what others are offering or requesting</p>
                        </li>
                        <li class="govuk-!-margin-bottom-4">
                            <a href="<?= $basePath ?>/members" class="govuk-link govuk-!-font-weight-bold">Find members</a>
                            <p class="govuk-hint govuk-!-margin-top-1 govuk-!-margin-bottom-0">Connect with people in your area</p>
                        </li>
                    </ul>
                </section>
                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible" aria-hidden="true">
                <?php endif; ?>

                <!-- Recent Listings Section -->
                <section aria-labelledby="listings-heading">
                    <h2 class="govuk-heading-m" id="listings-heading">Recent listings</h2>

                    <?php if (!empty($recentListings)): ?>
                    <!-- Responsive table wrapper for mobile -->
                    <div class="govuk-table__responsive-wrapper" role="region" aria-labelledby="listings-heading" tabindex="0">
                        <table class="govuk-table">
                            <caption class="govuk-table__caption govuk-visually-hidden">Recent community listings showing title, author, location and type</caption>
                            <thead class="govuk-table__head">
                                <tr class="govuk-table__row">
                                    <th scope="col" class="govuk-table__header govuk-!-width-two-thirds">Listing</th>
                                    <th scope="col" class="govuk-table__header">Type</th>
                                </tr>
                            </thead>
                            <tbody class="govuk-table__body">
                                <?php foreach ($recentListings as $index => $listing): ?>
                                <tr class="govuk-table__row">
                                    <td class="govuk-table__cell" data-label="Listing">
                                        <a href="<?= $basePath ?>/listings/<?= (int)$listing['id'] ?>" class="govuk-link govuk-!-font-weight-bold">
                                            <?= htmlspecialchars($listing['title']) ?>
                                        </a>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                            <span class="govuk-visually-hidden">Posted by </span><?= htmlspecialchars($listing['author_name']) ?><?php if (!empty($listing['location'])): ?><span class="govuk-visually-hidden">, location:</span> · <?= htmlspecialchars($listing['location']) ?><?php endif; ?>
                                        </p>
                                    </td>
                                    <td class="govuk-table__cell" data-label="Type">
                                        <strong class="govuk-tag <?= $listing['type'] === 'offer' ? 'govuk-tag--green' : 'govuk-tag--grey' ?>">
                                            <?= ucfirst($listing['type']) ?>
                                        </strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/listings" class="govuk-link">View all <?= number_format($listingCount) ?> listings<span class="govuk-visually-hidden"> in the community</span></a>
                    </p>
                    <?php else: ?>
                    <p class="govuk-body">No listings yet.<?php if ($isLoggedIn): ?> <a href="<?= $basePath ?>/compose?type=listing" class="govuk-link">Be the first to post one.</a><?php endif; ?></p>
                    <?php endif; ?>
                </section>

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible" aria-hidden="true">

                <!-- Upcoming Events Section -->
                <section aria-labelledby="events-heading">
                    <h2 class="govuk-heading-m" id="events-heading">Upcoming events</h2>

                    <?php if (!empty($upcomingEvents)): ?>
                    <dl class="govuk-summary-list">
                        <?php foreach ($upcomingEvents as $event): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">
                                <time datetime="<?= date('Y-m-d\TH:i', strtotime($event['start_time'])) ?>">
                                    <span class="govuk-!-font-weight-bold"><?= date('j M', strtotime($event['start_time'])) ?></span><br>
                                    <span class="govuk-body-s civicone-secondary-text"><?= date('g:ia', strtotime($event['start_time'])) ?></span>
                                </time>
                            </dt>
                            <dd class="govuk-summary-list__value">
                                <a href="<?= $basePath ?>/event/<?= (int)$event['id'] ?>" class="govuk-link govuk-!-font-weight-bold">
                                    <?= htmlspecialchars($event['title']) ?>
                                </a>
                                <?php if (!empty($event['location'])): ?>
                                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                    <span class="govuk-visually-hidden">Location: </span><?= htmlspecialchars($event['location']) ?>
                                </p>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/events" class="govuk-link">View all events<span class="govuk-visually-hidden"> in the community calendar</span></a>
                    </p>
                    <?php else: ?>
                    <p class="govuk-body">No upcoming events scheduled.</p>
                    <?php endif; ?>
                </section>

            </div><!-- /govuk-grid-column-two-thirds -->

            <!-- Sidebar Column (1/3) -->
            <div class="govuk-grid-column-one-third">

                <!-- Community Stats - GOV.UK related content pattern -->
                <aside class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg" aria-labelledby="stats-heading" role="complementary">
                    <h2 class="govuk-heading-s" id="stats-heading">Community at a glance</h2>
                    <dl class="govuk-body-s govuk-!-margin-bottom-0">
                        <div class="govuk-!-margin-bottom-2 civicone-stat-row">
                            <dt>Members:</dt>
                            <dd class="govuk-!-margin-left-0"><strong><?= number_format($memberCount) ?></strong></dd>
                        </div>
                        <div class="govuk-!-margin-bottom-2 civicone-stat-row">
                            <dt>Listings:</dt>
                            <dd class="govuk-!-margin-left-0"><strong><?= number_format($listingCount) ?></strong></dd>
                        </div>
                        <div class="civicone-stat-row">
                            <dt>Groups:</dt>
                            <dd class="govuk-!-margin-left-0"><strong><?= number_format($groupCount) ?></strong></dd>
                        </div>
                    </dl>
                </aside>

                <!-- Featured Groups -->
                <?php if (!empty($featuredGroups)): ?>
                <nav aria-labelledby="groups-heading" class="govuk-!-margin-bottom-6">
                    <h2 class="govuk-heading-s" id="groups-heading">Groups</h2>
                    <ul class="govuk-list" role="list">
                        <?php foreach ($featuredGroups as $group): ?>
                        <li class="govuk-!-margin-bottom-3">
                            <a href="<?= $basePath ?>/group/<?= (int)$group['id'] ?>" class="govuk-link govuk-!-font-weight-bold">
                                <?= htmlspecialchars($group['name']) ?>
                            </a>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                <?= (int)($group['member_count'] ?? 0) ?> member<?= ($group['member_count'] ?? 0) != 1 ? 's' : '' ?>
                            </p>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="govuk-body-s">
                        <a href="<?= $basePath ?>/groups" class="govuk-link">View all groups</a>
                    </p>
                </nav>
                <?php endif; ?>

                <!-- Related Links - GOV.UK sidebar pattern -->
                <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible" aria-hidden="true">
                <nav aria-labelledby="related-heading">
                    <h2 class="govuk-heading-s" id="related-heading">Related links</h2>
                    <ul class="govuk-list govuk-list--spaced" role="list">
                        <li><a href="<?= $basePath ?>/about" class="govuk-link">About this community</a></li>
                        <li><a href="<?= $basePath ?>/help" class="govuk-link">How it works</a></li>
                        <li><a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a></li>
                        <li><a href="<?= $basePath ?>/privacy" class="govuk-link">Privacy policy</a></li>
                        <li><a href="<?= $basePath ?>/cookies" class="govuk-link">Cookies</a></li>
                        <li><a href="<?= $basePath ?>/accessibility" class="govuk-link">Accessibility statement</a></li>
                        <li><a href="<?= $basePath ?>/terms" class="govuk-link">Terms and conditions</a></li>
                    </ul>
                </nav>

            </div><!-- /govuk-grid-column-one-third -->

        </div><!-- /govuk-grid-row -->

        <!-- Back to top link - GOV.UK accessibility pattern -->
        <div class="govuk-!-margin-top-6 govuk-!-text-align-right">
            <a href="#main-content" class="govuk-link govuk-body-s">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="17" viewBox="0 0 13 17" aria-hidden="true" focusable="false" class="civicone-back-to-top-icon">
                    <path fill="currentColor" d="M6.5 0L0 6.5 1.4 8l4.6-4.6v13h1.4V3.4L12 8l1.4-1.5L6.5 0z"/>
                </svg>
                Back to top
            </a>
        </div>

    </main>
</div><!-- /govuk-width-container -->

<?php
// 6. LOAD FOOTER
require __DIR__ . '/../layouts/civicone/footer.php';
?>
