<?php
// User Federation Dashboard - Personal Activity View
$pageTitle = $pageTitle ?? "My Federation";
$hideHero = true;

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
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-dashboard-wrapper">

        <!-- Hero Section -->
        <div class="fed-hero">
            <div class="fed-hero-icon">
                <i class="fa-solid fa-gauge-high"></i>
            </div>
            <h1>My Federation Dashboard</h1>
            <p class="fed-hero-subtitle">
                Track your federation activity, view stats, and manage your connections with partner timebanks.
            </p>
        </div>

        <?php $currentPage = 'dashboard'; $userOptedIn = true; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

        <!-- Profile Header -->
        <div class="dash-profile-header">
            <div class="dash-avatar">
                <?php if (!empty($userProfile['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($userProfile['avatar_url']) ?>" alt="Avatar">
                <?php else: ?>
                    <?= strtoupper(substr($displayName, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="dash-profile-info">
                <h1 class="dash-name"><?= htmlspecialchars($displayName) ?></h1>
                <div class="dash-badges">
                    <span class="dash-badge">
                        <i class="fa-solid fa-globe"></i>
                        <?= ucfirst($privacyLevel) ?> Level
                    </span>
                    <span class="dash-badge partners">
                        <i class="fa-solid fa-handshake"></i>
                        <?= $partnerCount ?> Partner<?= $partnerCount !== 1 ? 's' : '' ?>
                    </span>
                </div>
            </div>
            <a href="<?= $basePath ?>/settings?section=federation" class="dash-settings-btn" aria-label="Federation Settings">
                <i class="fa-solid fa-cog"></i>
            </a>
        </div>

        <!-- Stats Grid -->
        <div class="dash-stats-grid">
            <div class="dash-stat-card">
                <p class="dash-stat-value"><?= number_format($stats['hours_given'] ?? 0, 1) ?></p>
                <p class="dash-stat-label">Hours Given</p>
            </div>
            <div class="dash-stat-card">
                <p class="dash-stat-value"><?= number_format($stats['hours_received'] ?? 0, 1) ?></p>
                <p class="dash-stat-label">Hours Received</p>
            </div>
            <div class="dash-stat-card">
                <p class="dash-stat-value"><?= ($stats['messages_sent'] ?? 0) + ($stats['messages_received'] ?? 0) ?></p>
                <p class="dash-stat-label">Messages</p>
            </div>
            <div class="dash-stat-card">
                <p class="dash-stat-value"><?= ($stats['groups_joined'] ?? 0) + ($stats['events_attended'] ?? 0) ?></p>
                <p class="dash-stat-label">Connections</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dash-quick-actions">
            <a href="<?= $basePath ?>/federation/messages" class="dash-action-btn">
                <?php if ($unreadMessages > 0): ?>
                    <span class="dash-action-badge"><?= $unreadMessages ?></span>
                <?php endif; ?>
                <div class="dash-action-icon">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <span class="dash-action-label">Messages</span>
            </a>
            <a href="<?= $basePath ?>/federation/transactions/new" class="dash-action-btn">
                <div class="dash-action-icon">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <span class="dash-action-label">Send Credits</span>
            </a>
            <a href="<?= $basePath ?>/federation/members" class="dash-action-btn">
                <div class="dash-action-icon">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <span class="dash-action-label">Find Members</span>
            </a>
            <a href="<?= $basePath ?>/federation" class="dash-action-btn">
                <div class="dash-action-icon">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <span class="dash-action-label">Browse Hub</span>
            </a>
            <a href="<?= $basePath ?>/federation/settings" class="dash-action-btn">
                <div class="dash-action-icon">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <span class="dash-action-label">Settings</span>
            </a>
            <a href="<?= $basePath ?>/federation/help" class="dash-action-btn">
                <div class="dash-action-icon">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <span class="dash-action-label">Help</span>
            </a>
        </div>

        <!-- Recent Activity -->
        <div class="dash-section">
            <div class="dash-section-header">
                <h2 class="dash-section-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Recent Activity
                </h2>
                <a href="<?= $basePath ?>/federation/activity" class="dash-view-all">View All</a>
            </div>

            <?php if (!empty($recentActivity)): ?>
            <div class="dash-activity-list">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="dash-activity-item">
                    <div class="dash-activity-avatar <?= $activity['direction'] ?? '' ?>">
                        <?php if (!empty($activity['avatar'])): ?>
                            <img src="<?= htmlspecialchars($activity['avatar']) ?>" alt="">
                        <?php else: ?>
                            <i class="fa-solid <?= $activity['icon'] ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="dash-activity-content">
                        <p class="dash-activity-title"><?= htmlspecialchars($activity['title']) ?></p>
                        <p class="dash-activity-desc"><?= htmlspecialchars($activity['description']) ?></p>
                    </div>
                    <div class="dash-activity-meta">
                        <p class="dash-activity-time"><?= timeAgo($activity['date']) ?></p>
                        <span class="dash-activity-badge"><?= htmlspecialchars($activity['subtitle']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-activity-list">
                <div class="dash-empty">
                    <i class="fa-solid fa-inbox"></i>
                    <p>No federation activity yet. Start connecting with partner timebanks!</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Events -->
        <?php if (!empty($upcomingEvents)): ?>
        <div class="dash-section">
            <div class="dash-section-header">
                <h2 class="dash-section-title">
                    <i class="fa-solid fa-calendar"></i>
                    Upcoming Events
                </h2>
                <a href="<?= $basePath ?>/federation/events" class="dash-view-all">View All</a>
            </div>
            <div class="dash-card-grid">
                <?php foreach ($upcomingEvents as $event): ?>
                <a href="<?= $basePath ?>/federation/events/<?= $event['id'] ?>" class="dash-mini-card">
                    <div class="dash-mini-icon">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div class="dash-mini-content">
                        <p class="dash-mini-title"><?= htmlspecialchars($event['title']) ?></p>
                        <p class="dash-mini-subtitle">
                            <?= date('M j, g:ia', strtotime($event['start_time'])) ?> &bull; <?= htmlspecialchars($event['tenant_name']) ?>
                        </p>
                    </div>
                    <i class="fa-solid fa-chevron-right dash-mini-arrow"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- My Federated Groups -->
        <?php if (!empty($federatedGroups)): ?>
        <div class="dash-section">
            <div class="dash-section-header">
                <h2 class="dash-section-title">
                    <i class="fa-solid fa-people-group"></i>
                    My Groups
                </h2>
                <a href="<?= $basePath ?>/federation/groups/my" class="dash-view-all">View All</a>
            </div>
            <div class="dash-card-grid">
                <?php foreach ($federatedGroups as $group): ?>
                <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>" class="dash-mini-card">
                    <div class="dash-mini-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="dash-mini-content">
                        <p class="dash-mini-title"><?= htmlspecialchars($group['name']) ?></p>
                        <p class="dash-mini-subtitle">
                            <?= $group['member_count'] ?> members &bull; <?= htmlspecialchars($group['tenant_name']) ?>
                        </p>
                    </div>
                    <i class="fa-solid fa-chevron-right dash-mini-arrow"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
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
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
