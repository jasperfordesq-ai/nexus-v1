<?php
/**
 * CivicOne View: Federation Dashboard
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "My Federation";
$hideHero = true;
$bodyClass = 'civicone--federation';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();

// Extract data
$userSettings = $userSettings ?? [];
$userProfile = $userProfile ?? [];
$stats = $stats ?? [];
$recentActivity = $recentActivity ?? [];
$partnerCount = $partnerCount ?? 0;
$federatedGroups = $federatedGroups ?? [];
$upcomingEvents = $upcomingEvents ?? [];
$unreadMessages = $unreadMessages ?? 0;

// User display name
$displayName = $userProfile['name'] ?? trim(($userProfile['first_name'] ?? '') . ' ' . ($userProfile['last_name'] ?? '')) ?: 'Member';
$privacyLevel = $userSettings['privacy_level'] ?? 'discovery';

// Helper function for time ago
function federationTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/federation">Federation</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Dashboard</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/federation" class="govuk-back-link govuk-!-margin-bottom-6">Back to Federation Hub</a>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-globe govuk-!-margin-right-2" aria-hidden="true"></i>
            My Federation Dashboard
        </h1>
        <p class="govuk-body-l">Track your federation activity, view stats, and manage your connections with partner timebanks.</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/federation/settings" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-cog govuk-!-margin-right-1" aria-hidden="true"></i>
            Settings
        </a>
    </div>
</div>

<?php $currentPage = 'dashboard'; $userOptedIn = true; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

<!-- Profile Card -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 60px; height: 60px; border-radius: 50%; background: #1d70b8; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <?php if (!empty($userProfile['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($userProfile['avatar_url']) ?>" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <?= strtoupper(substr($displayName, 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div>
            <h2 class="govuk-heading-m govuk-!-margin-bottom-1"><?= htmlspecialchars($displayName) ?></h2>
            <div>
                <span class="govuk-tag govuk-tag--blue govuk-!-margin-right-2"><?= ucfirst($privacyLevel) ?> Level</span>
                <span class="govuk-tag govuk-tag--grey"><?= $partnerCount ?> Partner<?= $partnerCount !== 1 ? 's' : '' ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= number_format($stats['hours_given'] ?? 0, 1) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Hours Given</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= number_format($stats['hours_received'] ?? 0, 1) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Hours Received</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= ($stats['messages_sent'] ?? 0) + ($stats['messages_received'] ?? 0) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Messages</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= ($stats['groups_joined'] ?? 0) + ($stats['events_attended'] ?? 0) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Connections</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<h2 class="govuk-heading-m">
    <i class="fa-solid fa-bolt govuk-!-margin-right-2" aria-hidden="true"></i>
    Quick Actions
</h2>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/federation/messages" class="govuk-link" style="display: block; padding: 1rem; background: #f3f2f1; text-align: center; text-decoration: none;">
            <?php if ($unreadMessages > 0): ?>
                <span class="govuk-tag govuk-tag--blue" style="position: relative; top: -0.5rem;"><?= $unreadMessages ?></span>
            <?php endif; ?>
            <i class="fa-solid fa-envelope" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;" aria-hidden="true"></i>
            Messages
        </a>
    </div>
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/federation/transactions/new" class="govuk-link" style="display: block; padding: 1rem; background: #f3f2f1; text-align: center; text-decoration: none;">
            <i class="fa-solid fa-paper-plane" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;" aria-hidden="true"></i>
            Send Credits
        </a>
    </div>
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/federation/members" class="govuk-link" style="display: block; padding: 1rem; background: #f3f2f1; text-align: center; text-decoration: none;">
            <i class="fa-solid fa-user-group" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;" aria-hidden="true"></i>
            Find Members
        </a>
    </div>
</div>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/federation" class="govuk-link" style="display: block; padding: 1rem; background: #f3f2f1; text-align: center; text-decoration: none;">
            <i class="fa-solid fa-globe" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;" aria-hidden="true"></i>
            Browse Hub
        </a>
    </div>
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/federation/settings" class="govuk-link" style="display: block; padding: 1rem; background: #f3f2f1; text-align: center; text-decoration: none;">
            <i class="fa-solid fa-sliders" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;" aria-hidden="true"></i>
            Settings
        </a>
    </div>
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/federation/help" class="govuk-link" style="display: block; padding: 1rem; background: #f3f2f1; text-align: center; text-decoration: none;">
            <i class="fa-solid fa-circle-question" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;" aria-hidden="true"></i>
            Help
        </a>
    </div>
</div>

<!-- Recent Activity -->
<div class="govuk-grid-row govuk-!-margin-bottom-2">
    <div class="govuk-grid-column-two-thirds">
        <h2 class="govuk-heading-m">
            <i class="fa-solid fa-clock-rotate-left govuk-!-margin-right-2" aria-hidden="true"></i>
            Recent Activity
        </h2>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/federation/activity" class="govuk-link">View All</a>
    </div>
</div>

<?php if (!empty($recentActivity)): ?>
<ul class="govuk-list govuk-!-margin-bottom-6">
    <?php foreach ($recentActivity as $activity): ?>
    <li class="govuk-!-padding-3 govuk-!-margin-bottom-2" style="border-left: 4px solid #1d70b8; background: #f8f8f8;">
        <p class="govuk-body govuk-!-margin-bottom-1">
            <strong><?= htmlspecialchars($activity['title']) ?></strong>
            <span class="govuk-body-s" style="color: #505a5f;"> — <?= federationTimeAgo($activity['date']) ?></span>
        </p>
        <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
            <?= htmlspecialchars($activity['description']) ?>
            <span class="govuk-tag govuk-tag--grey"><?= htmlspecialchars($activity['subtitle']) ?></span>
        </p>
    </li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<div class="govuk-inset-text govuk-!-margin-bottom-6">
    <h3 class="govuk-heading-s govuk-!-margin-bottom-2">No Federation Activity Yet</h3>
    <p class="govuk-body govuk-!-margin-bottom-2">Start connecting with partner timebanks!</p>
    <a href="<?= $basePath ?>/federation/members" class="govuk-button govuk-button--secondary" data-module="govuk-button">
        <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
        Browse Members
    </a>
</div>
<?php endif; ?>

<!-- Upcoming Events -->
<?php if (!empty($upcomingEvents)): ?>
<div class="govuk-grid-row govuk-!-margin-bottom-2">
    <div class="govuk-grid-column-two-thirds">
        <h2 class="govuk-heading-m">
            <i class="fa-solid fa-calendar govuk-!-margin-right-2" aria-hidden="true"></i>
            Upcoming Events
        </h2>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/federation/events" class="govuk-link">View All</a>
    </div>
</div>
<ul class="govuk-list govuk-!-margin-bottom-6">
    <?php foreach ($upcomingEvents as $event): ?>
    <li>
        <a href="<?= $basePath ?>/federation/events/<?= $event['id'] ?>" class="govuk-link">
            <?= htmlspecialchars($event['title']) ?>
        </a>
        <span class="govuk-body-s" style="color: #505a5f;">
            — <?= date('M j, g:ia', strtotime($event['start_time'])) ?> &bull; <?= htmlspecialchars($event['tenant_name']) ?>
        </span>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<!-- My Federated Groups -->
<?php if (!empty($federatedGroups)): ?>
<div class="govuk-grid-row govuk-!-margin-bottom-2">
    <div class="govuk-grid-column-two-thirds">
        <h2 class="govuk-heading-m">
            <i class="fa-solid fa-people-group govuk-!-margin-right-2" aria-hidden="true"></i>
            My Groups
        </h2>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/federation/groups/my" class="govuk-link">View All</a>
    </div>
</div>
<ul class="govuk-list govuk-!-margin-bottom-6">
    <?php foreach ($federatedGroups as $group): ?>
    <li>
        <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>" class="govuk-link">
            <?= htmlspecialchars($group['name']) ?>
        </a>
        <span class="govuk-body-s" style="color: #505a5f;">
            — <?= $group['member_count'] ?> members &bull; <?= htmlspecialchars($group['tenant_name']) ?>
        </span>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
