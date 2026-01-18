<?php
// User Federation Dashboard - Personal Activity View
$pageTitle = $pageTitle ?? "My Federation";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
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

        <style>
            .offline-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10001;
                padding: 12px 20px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                font-size: 0.9rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Hero Section */
            .fed-hero {
                text-align: center;
                margin-bottom: 32px;
                animation: fadeInUp 0.5s ease-out;
            }

            .fed-hero-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #8b5cf6, #6366f1);
                border-radius: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
                font-size: 2rem;
                color: white;
                box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
            }

            .fed-hero h1 {
                font-size: 2.25rem;
                font-weight: 800;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 12px;
            }

            [data-theme="dark"] .fed-hero h1 {
                color: #f1f5f9;
            }

            .fed-hero-subtitle {
                font-size: 1.1rem;
                color: var(--htb-text-secondary, #6b7280);
                max-width: 600px;
                margin: 0 auto;
                line-height: 1.6;
            }

            [data-theme="dark"] .fed-hero-subtitle {
                color: #94a3b8;
            }

            #fed-dashboard-wrapper {
                padding: 100px 16px 120px;
                max-width: 1000px;
                margin: 0 auto;
            }

            @media (min-width: 768px) {
                #fed-dashboard-wrapper {
                    padding: 120px 24px 60px;
                }
            }

            /* Profile Header */
            .dash-profile-header {
                display: flex;
                align-items: center;
                gap: 20px;
                padding: 24px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.08));
                border-radius: 24px;
                margin-bottom: 24px;
                animation: fadeInUp 0.4s ease;
            }

            .dash-avatar {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2rem;
                color: white;
                flex-shrink: 0;
                overflow: hidden;
                box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3);
            }

            .dash-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .dash-profile-info {
                flex: 1;
                min-width: 0;
            }

            .dash-name {
                font-size: 1.5rem;
                font-weight: 800;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 6px;
            }

            [data-theme="dark"] .dash-name {
                color: #f1f5f9;
            }

            .dash-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .dash-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                background: rgba(139, 92, 246, 0.15);
                color: #7c3aed;
                border-radius: 100px;
                font-size: 0.8rem;
                font-weight: 600;
            }

            .dash-badge.partners {
                background: rgba(16, 185, 129, 0.15);
                color: #059669;
            }

            .dash-settings-btn {
                padding: 12px;
                background: rgba(255, 255, 255, 0.8);
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 12px;
                color: #8b5cf6;
                text-decoration: none;
                transition: all 0.2s ease;
            }

            .dash-settings-btn:hover {
                background: rgba(139, 92, 246, 0.1);
                transform: scale(1.05);
            }

            [data-theme="dark"] .dash-settings-btn {
                background: rgba(30, 41, 59, 0.8);
            }

            /* Stats Grid */
            .dash-stats-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 24px;
            }

            @media (min-width: 640px) {
                .dash-stats-grid {
                    grid-template-columns: repeat(4, 1fr);
                }
            }

            .dash-stat-card {
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 16px;
                padding: 20px;
                text-align: center;
                animation: fadeInUp 0.4s ease backwards;
                border: 1px solid rgba(255, 255, 255, 0.8);
            }

            [data-theme="dark"] .dash-stat-card {
                background: rgba(30, 41, 59, 0.9);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .dash-stat-card:nth-child(1) { animation-delay: 0.05s; }
            .dash-stat-card:nth-child(2) { animation-delay: 0.1s; }
            .dash-stat-card:nth-child(3) { animation-delay: 0.15s; }
            .dash-stat-card:nth-child(4) { animation-delay: 0.2s; }

            .dash-stat-value {
                font-size: 2rem;
                font-weight: 800;
                color: #8b5cf6;
                margin: 0 0 4px;
            }

            .dash-stat-label {
                font-size: 0.8rem;
                color: var(--htb-text-muted, #6b7280);
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Quick Actions */
            .dash-quick-actions {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 24px;
            }

            @media (min-width: 640px) {
                .dash-quick-actions {
                    grid-template-columns: repeat(4, 1fr);
                }
            }

            .dash-action-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                padding: 20px 16px;
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(20px);
                border-radius: 16px;
                text-decoration: none;
                transition: all 0.2s ease;
                border: 1px solid rgba(255, 255, 255, 0.8);
                position: relative;
            }

            [data-theme="dark"] .dash-action-btn {
                background: rgba(30, 41, 59, 0.9);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .dash-action-btn:hover {
                transform: translateY(-4px);
                box-shadow: 0 8px 24px rgba(139, 92, 246, 0.15);
            }

            .dash-action-icon {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.1));
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                color: #8b5cf6;
            }

            .dash-action-label {
                font-size: 0.85rem;
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
            }

            [data-theme="dark"] .dash-action-label {
                color: #f1f5f9;
            }

            .dash-action-badge {
                position: absolute;
                top: 12px;
                right: 12px;
                min-width: 22px;
                height: 22px;
                background: #ef4444;
                color: white;
                border-radius: 11px;
                font-size: 0.75rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 6px;
            }

            /* Section Headers */
            .dash-section {
                margin-bottom: 24px;
                animation: fadeInUp 0.4s ease backwards;
            }

            .dash-section:nth-of-type(3) { animation-delay: 0.25s; }
            .dash-section:nth-of-type(4) { animation-delay: 0.3s; }
            .dash-section:nth-of-type(5) { animation-delay: 0.35s; }

            .dash-section-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 16px;
            }

            .dash-section-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            [data-theme="dark"] .dash-section-title {
                color: #f1f5f9;
            }

            .dash-section-title i {
                color: #8b5cf6;
            }

            .dash-view-all {
                font-size: 0.85rem;
                color: #8b5cf6;
                text-decoration: none;
                font-weight: 600;
            }

            .dash-view-all:hover {
                text-decoration: underline;
            }

            /* Activity List */
            .dash-activity-list {
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, 0.8);
            }

            [data-theme="dark"] .dash-activity-list {
                background: rgba(30, 41, 59, 0.9);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .dash-activity-item {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 16px 20px;
                border-bottom: 1px solid rgba(139, 92, 246, 0.1);
                transition: background 0.2s ease;
            }

            .dash-activity-item:last-child {
                border-bottom: none;
            }

            .dash-activity-item:hover {
                background: rgba(139, 92, 246, 0.05);
            }

            .dash-activity-avatar {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1rem;
                flex-shrink: 0;
                overflow: hidden;
            }

            .dash-activity-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .dash-activity-avatar.sent {
                background: linear-gradient(135deg, #f59e0b, #d97706);
            }

            .dash-activity-avatar.received {
                background: linear-gradient(135deg, #10b981, #059669);
            }

            .dash-activity-content {
                flex: 1;
                min-width: 0;
            }

            .dash-activity-title {
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 2px;
                font-size: 0.95rem;
            }

            [data-theme="dark"] .dash-activity-title {
                color: #f1f5f9;
            }

            .dash-activity-desc {
                font-size: 0.85rem;
                color: var(--htb-text-muted, #6b7280);
                margin: 0;
            }

            .dash-activity-meta {
                text-align: right;
                flex-shrink: 0;
            }

            .dash-activity-time {
                font-size: 0.8rem;
                color: var(--htb-text-muted, #6b7280);
            }

            .dash-activity-badge {
                font-size: 0.75rem;
                padding: 3px 8px;
                background: rgba(139, 92, 246, 0.1);
                color: #7c3aed;
                border-radius: 6px;
                margin-top: 4px;
                display: inline-block;
            }

            /* Groups/Events Cards */
            .dash-card-grid {
                display: grid;
                gap: 12px;
            }

            .dash-mini-card {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 16px;
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(20px);
                border-radius: 14px;
                text-decoration: none;
                border: 1px solid rgba(255, 255, 255, 0.8);
                transition: all 0.2s ease;
            }

            [data-theme="dark"] .dash-mini-card {
                background: rgba(30, 41, 59, 0.9);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .dash-mini-card:hover {
                transform: translateX(4px);
                box-shadow: 0 4px 16px rgba(139, 92, 246, 0.1);
            }

            .dash-mini-icon {
                width: 44px;
                height: 44px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.1));
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #8b5cf6;
                font-size: 1.1rem;
                flex-shrink: 0;
            }

            .dash-mini-content {
                flex: 1;
                min-width: 0;
            }

            .dash-mini-title {
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 2px;
                font-size: 0.95rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            [data-theme="dark"] .dash-mini-title {
                color: #f1f5f9;
            }

            .dash-mini-subtitle {
                font-size: 0.8rem;
                color: var(--htb-text-muted, #6b7280);
                margin: 0;
            }

            .dash-mini-arrow {
                color: #8b5cf6;
                opacity: 0.5;
            }

            /* Empty State */
            .dash-empty {
                text-align: center;
                padding: 32px 20px;
                color: var(--htb-text-muted, #6b7280);
            }

            .dash-empty i {
                font-size: 2.5rem;
                margin-bottom: 12px;
                opacity: 0.4;
            }

            .dash-empty p {
                margin: 0;
                font-size: 0.9rem;
            }

            /* Focus styles */
            .dash-action-btn:focus-visible,
            .dash-mini-card:focus-visible,
            .dash-settings-btn:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            /* Touch targets */
            .dash-action-btn,
            .dash-mini-card,
            .dash-settings-btn {
                min-height: 44px;
            }
        </style>

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

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
