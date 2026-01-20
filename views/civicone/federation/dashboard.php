<?php
/**
 * User Federation Dashboard - Personal Activity View
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "My Federation";
$hideHero = true;
$bodyClass = 'civicone--federation';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

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
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation" class="civic-fed-back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federation Hub
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <h1>My Federation Dashboard</h1>
        <a href="<?= $basePath ?>/federation/settings" class="civic-fed-btn civic-fed-btn--secondary" aria-label="Federation Settings">
            <i class="fa-solid fa-cog" aria-hidden="true"></i>
            Settings
        </a>
    </header>

    <p class="civic-fed-intro">
        Track your federation activity, view stats, and manage your connections with partner timebanks.
    </p>

    <?php $currentPage = 'dashboard'; $userOptedIn = true; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

    <!-- Profile Card -->
    <div class="civic-fed-card">
        <div class="civic-fed-card-body">
            <div class="civic-fed-profile-header">
                <div class="civic-fed-avatar civic-fed-avatar--large">
                    <?php if (!empty($userProfile['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($userProfile['avatar_url']) ?>" alt="">
                    <?php else: ?>
                        <span><?= strtoupper(substr($displayName, 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="civic-fed-profile-info">
                    <h2 class="civic-fed-profile-name"><?= htmlspecialchars($displayName) ?></h2>
                    <div class="civic-fed-profile-badges">
                        <span class="civic-fed-badge">
                            <i class="fa-solid fa-globe" aria-hidden="true"></i>
                            <?= ucfirst($privacyLevel) ?> Level
                        </span>
                        <span class="civic-fed-badge civic-fed-badge--secondary">
                            <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                            <?= $partnerCount ?> Partner<?= $partnerCount !== 1 ? 's' : '' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="civic-fed-stats-grid">
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value"><?= number_format($stats['hours_given'] ?? 0, 1) ?></span>
            <span class="civic-fed-stat-label">Hours Given</span>
        </div>
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value"><?= number_format($stats['hours_received'] ?? 0, 1) ?></span>
            <span class="civic-fed-stat-label">Hours Received</span>
        </div>
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value"><?= ($stats['messages_sent'] ?? 0) + ($stats['messages_received'] ?? 0) ?></span>
            <span class="civic-fed-stat-label">Messages</span>
        </div>
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value"><?= ($stats['groups_joined'] ?? 0) + ($stats['events_attended'] ?? 0) ?></span>
            <span class="civic-fed-stat-label">Connections</span>
        </div>
    </div>

    <!-- Quick Actions -->
    <section class="civic-fed-section" aria-labelledby="actions-heading">
        <h2 id="actions-heading" class="civic-fed-section-title">
            <i class="fa-solid fa-bolt" aria-hidden="true"></i>
            Quick Actions
        </h2>

        <div class="civic-fed-actions-grid">
            <a href="<?= $basePath ?>/federation/messages" class="civic-fed-action-card">
                <?php if ($unreadMessages > 0): ?>
                    <span class="civic-fed-action-badge"><?= $unreadMessages ?></span>
                <?php endif; ?>
                <div class="civic-fed-action-icon">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                </div>
                <span class="civic-fed-action-label">Messages</span>
            </a>
            <a href="<?= $basePath ?>/federation/transactions/new" class="civic-fed-action-card">
                <div class="civic-fed-action-icon">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </div>
                <span class="civic-fed-action-label">Send Credits</span>
            </a>
            <a href="<?= $basePath ?>/federation/members" class="civic-fed-action-card">
                <div class="civic-fed-action-icon">
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                </div>
                <span class="civic-fed-action-label">Find Members</span>
            </a>
            <a href="<?= $basePath ?>/federation" class="civic-fed-action-card">
                <div class="civic-fed-action-icon">
                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                </div>
                <span class="civic-fed-action-label">Browse Hub</span>
            </a>
            <a href="<?= $basePath ?>/federation/settings" class="civic-fed-action-card">
                <div class="civic-fed-action-icon">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                </div>
                <span class="civic-fed-action-label">Settings</span>
            </a>
            <a href="<?= $basePath ?>/federation/help" class="civic-fed-action-card">
                <div class="civic-fed-action-icon">
                    <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                </div>
                <span class="civic-fed-action-label">Help</span>
            </a>
        </div>
    </section>

    <!-- Recent Activity -->
    <section class="civic-fed-section" aria-labelledby="activity-heading">
        <div class="civic-fed-section-header">
            <h2 id="activity-heading" class="civic-fed-section-title">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                Recent Activity
            </h2>
            <a href="<?= $basePath ?>/federation/activity" class="civic-fed-link">View All</a>
        </div>

        <?php if (!empty($recentActivity)): ?>
        <div class="civic-fed-activity-list">
            <?php foreach ($recentActivity as $activity): ?>
            <div class="civic-fed-activity-item">
                <div class="civic-fed-activity-avatar <?= $activity['direction'] ?? '' ?>">
                    <?php if (!empty($activity['avatar'])): ?>
                        <img src="<?= htmlspecialchars($activity['avatar']) ?>" alt="">
                    <?php else: ?>
                        <i class="fa-solid <?= $activity['icon'] ?>" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="civic-fed-activity-content">
                    <p class="civic-fed-activity-title"><?= htmlspecialchars($activity['title']) ?></p>
                    <p class="civic-fed-activity-desc"><?= htmlspecialchars($activity['description']) ?></p>
                </div>
                <div class="civic-fed-activity-meta">
                    <span class="civic-fed-activity-time"><?= timeAgo($activity['date']) ?></span>
                    <span class="civic-fed-tag"><?= htmlspecialchars($activity['subtitle']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="civic-fed-empty">
            <div class="civic-fed-empty-icon">
                <i class="fa-solid fa-inbox" aria-hidden="true"></i>
            </div>
            <h3>No Federation Activity Yet</h3>
            <p>Start connecting with partner timebanks!</p>
            <a href="<?= $basePath ?>/federation/members" class="civic-fed-btn civic-fed-btn--primary">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Browse Members
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- Upcoming Events -->
    <?php if (!empty($upcomingEvents)): ?>
    <section class="civic-fed-section" aria-labelledby="events-heading">
        <div class="civic-fed-section-header">
            <h2 id="events-heading" class="civic-fed-section-title">
                <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                Upcoming Events
            </h2>
            <a href="<?= $basePath ?>/federation/events" class="civic-fed-link">View All</a>
        </div>
        <div class="civic-fed-list">
            <?php foreach ($upcomingEvents as $event): ?>
            <a href="<?= $basePath ?>/federation/events/<?= $event['id'] ?>" class="civic-fed-list-item">
                <div class="civic-fed-list-icon">
                    <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-list-content">
                    <p class="civic-fed-list-title"><?= htmlspecialchars($event['title']) ?></p>
                    <p class="civic-fed-list-subtitle">
                        <?= date('M j, g:ia', strtotime($event['start_time'])) ?> &bull; <?= htmlspecialchars($event['tenant_name']) ?>
                    </p>
                </div>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- My Federated Groups -->
    <?php if (!empty($federatedGroups)): ?>
    <section class="civic-fed-section" aria-labelledby="groups-heading">
        <div class="civic-fed-section-header">
            <h2 id="groups-heading" class="civic-fed-section-title">
                <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                My Groups
            </h2>
            <a href="<?= $basePath ?>/federation/groups/my" class="civic-fed-link">View All</a>
        </div>
        <div class="civic-fed-list">
            <?php foreach ($federatedGroups as $group): ?>
            <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>" class="civic-fed-list-item">
                <div class="civic-fed-list-icon">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-list-content">
                    <p class="civic-fed-list-title"><?= htmlspecialchars($group['name']) ?></p>
                    <p class="civic-fed-list-subtitle">
                        <?= $group['member_count'] ?> members &bull; <?= htmlspecialchars($group['tenant_name']) ?>
                    </p>
                </div>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php
// Helper function for time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}
?>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
