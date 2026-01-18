<?php
// Federation Partner Profile - Detailed Partner Timebank View
$pageTitle = $pageTitle ?? "Partner Timebank";
$pageSubtitle = "Partner timebank details";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$partner = $partner ?? [];
$partnership = $partnership ?? [];
$features = $features ?? [];
$stats = $stats ?? [];
$recentActivity = $recentActivity ?? [];
$partnershipSince = $partnershipSince ?? null;
$userOptedIn = $userOptedIn ?? false;

// Format partnership date
$partnerSinceFormatted = $partnershipSince ? date('F Y', strtotime($partnershipSince)) : 'Unknown';

// Count enabled features
$enabledFeatureCount = count(array_filter($features));
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="partner-profile-wrapper">

        <style>
            /* ============================================
               PARTNER PROFILE - Detailed View
               Purple/Violet Theme for Federation
               ============================================ */

            /* Offline Banner */
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
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            /* Content Reveal Animation */
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Page Layout */
            #partner-profile-wrapper {
                padding: 120px 20px 60px;
                max-width: 1000px;
                margin: 0 auto;
            }

            @media (max-width: 768px) {
                #partner-profile-wrapper {
                    padding: 100px 16px 100px;
                }
            }

            /* Back Link */
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted, #6b7280);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 24px;
                transition: color 0.2s ease;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            /* Partner Header Card */
            .partner-header-card {
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 24px;
                border: 1px solid rgba(139, 92, 246, 0.15);
                padding: 32px;
                margin-bottom: 24px;
                animation: fadeInUp 0.5s ease-out;
            }

            [data-theme="dark"] .partner-header-card {
                background: rgba(15, 23, 42, 0.7);
                border-color: rgba(139, 92, 246, 0.2);
            }

            .partner-header {
                display: flex;
                align-items: flex-start;
                gap: 24px;
            }

            @media (max-width: 600px) {
                .partner-header {
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                }
            }

            .partner-logo {
                width: 100px;
                height: 100px;
                border-radius: 20px;
                background: linear-gradient(135deg, #8b5cf6, #6366f1);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2rem;
                font-weight: 700;
                color: white;
                flex-shrink: 0;
                overflow: hidden;
                box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
            }

            .partner-logo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .partner-info {
                flex: 1;
            }

            .partner-name {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 8px;
            }

            [data-theme="dark"] .partner-name {
                color: #f1f5f9;
            }

            .partner-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                margin-bottom: 16px;
            }

            .partner-meta-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 0.9rem;
                color: var(--htb-text-muted, #6b7280);
            }

            .partner-meta-item i {
                color: #8b5cf6;
            }

            .partner-description {
                font-size: 1rem;
                color: var(--htb-text-secondary, #4b5563);
                line-height: 1.6;
                margin: 0;
            }

            [data-theme="dark"] .partner-description {
                color: #94a3b8;
            }

            /* Partnership Status Badge */
            .partnership-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.05));
                border: 1px solid rgba(34, 197, 94, 0.2);
                border-radius: 100px;
                font-size: 0.85rem;
                font-weight: 600;
                color: #16a34a;
                margin-top: 16px;
            }

            [data-theme="dark"] .partnership-badge {
                background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(22, 163, 74, 0.1));
            }

            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 16px;
                margin-bottom: 24px;
                animation: fadeInUp 0.5s ease-out 0.1s both;
            }

            .stat-card {
                background: rgba(255, 255, 255, 0.6);
                backdrop-filter: blur(10px);
                border-radius: 16px;
                border: 1px solid rgba(139, 92, 246, 0.1);
                padding: 20px;
                text-align: center;
                transition: all 0.2s ease;
            }

            [data-theme="dark"] .stat-card {
                background: rgba(15, 23, 42, 0.6);
                border-color: rgba(139, 92, 246, 0.15);
            }

            .stat-card:hover {
                transform: translateY(-2px);
                border-color: rgba(139, 92, 246, 0.3);
            }

            .stat-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 12px;
                font-size: 1.1rem;
            }

            .stat-icon.members { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
            .stat-icon.listings { background: linear-gradient(135deg, #10b981, #059669); color: white; }
            .stat-icon.events { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
            .stat-icon.groups { background: linear-gradient(135deg, #ec4899, #db2777); color: white; }
            .stat-icon.hours { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }

            .stat-value {
                font-size: 1.5rem;
                font-weight: 800;
                color: var(--htb-text-main, #1f2937);
                margin-bottom: 4px;
            }

            [data-theme="dark"] .stat-value {
                color: #f1f5f9;
            }

            .stat-label {
                font-size: 0.8rem;
                color: var(--htb-text-muted, #6b7280);
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Features Section */
            .section-card {
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                border: 1px solid rgba(139, 92, 246, 0.15);
                padding: 24px;
                margin-bottom: 24px;
                animation: fadeInUp 0.5s ease-out 0.2s both;
            }

            [data-theme="dark"] .section-card {
                background: rgba(15, 23, 42, 0.7);
                border-color: rgba(139, 92, 246, 0.2);
            }

            .section-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            [data-theme="dark"] .section-title {
                color: #f1f5f9;
            }

            .section-title i {
                color: #8b5cf6;
            }

            /* Features Grid */
            .features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
            }

            .feature-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border-radius: 12px;
                border: 1px solid rgba(139, 92, 246, 0.1);
                text-decoration: none;
                color: inherit;
                transition: all 0.2s ease;
            }

            .feature-item:hover {
                background: rgba(139, 92, 246, 0.1);
                border-color: rgba(139, 92, 246, 0.2);
                transform: translateX(4px);
            }

            .feature-item.disabled {
                opacity: 0.5;
                pointer-events: none;
            }

            .feature-icon {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                flex-shrink: 0;
            }

            .feature-icon.members { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
            .feature-icon.listings { background: linear-gradient(135deg, #10b981, #059669); color: white; }
            .feature-icon.events { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
            .feature-icon.groups { background: linear-gradient(135deg, #ec4899, #db2777); color: white; }
            .feature-icon.messaging { background: linear-gradient(135deg, #06b6d4, #0891b2); color: white; }
            .feature-icon.transactions { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }

            .feature-info h4 {
                font-size: 0.95rem;
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 4px;
            }

            [data-theme="dark"] .feature-info h4 {
                color: #f1f5f9;
            }

            .feature-info p {
                font-size: 0.8rem;
                color: var(--htb-text-muted, #6b7280);
                margin: 0;
            }

            .feature-status {
                margin-left: auto;
                font-size: 0.75rem;
                padding: 4px 10px;
                border-radius: 100px;
                font-weight: 600;
            }

            .feature-status.enabled {
                background: rgba(34, 197, 94, 0.1);
                color: #16a34a;
            }

            .feature-status.disabled {
                background: rgba(107, 114, 128, 0.1);
                color: #6b7280;
            }

            /* Recent Activity */
            .activity-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .activity-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                background: rgba(139, 92, 246, 0.03);
                border-radius: 12px;
                border: 1px solid rgba(139, 92, 246, 0.08);
            }

            .activity-icon {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.05));
                display: flex;
                align-items: center;
                justify-content: center;
                color: #8b5cf6;
                flex-shrink: 0;
            }

            .activity-content {
                flex: 1;
            }

            .activity-text {
                font-size: 0.9rem;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 2px;
            }

            [data-theme="dark"] .activity-text {
                color: #f1f5f9;
            }

            .activity-time {
                font-size: 0.75rem;
                color: var(--htb-text-muted, #6b7280);
            }

            .empty-activity {
                text-align: center;
                padding: 32px;
                color: var(--htb-text-muted, #6b7280);
            }

            .empty-activity i {
                font-size: 2rem;
                opacity: 0.3;
                margin-bottom: 12px;
            }

            /* Quick Actions */
            .quick-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 24px;
            }

            .action-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 20px;
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
            }

            .action-btn.primary {
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                border: none;
            }

            .action-btn.primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(139, 92, 246, 0.35);
            }

            .action-btn.secondary {
                background: rgba(139, 92, 246, 0.1);
                color: #8b5cf6;
                border: 1px solid rgba(139, 92, 246, 0.2);
            }

            .action-btn.secondary:hover {
                background: rgba(139, 92, 246, 0.15);
            }

            /* Touch Targets */
            .action-btn,
            .feature-item {
                min-height: 44px;
            }

            /* Focus States */
            .action-btn:focus-visible,
            .feature-item:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }
        </style>

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Partner Timebanks
        </a>

        <!-- Partner Header Card -->
        <div class="partner-header-card">
            <div class="partner-header">
                <div class="partner-logo">
                    <?php if (!empty($partner['og_image_url'])): ?>
                    <img src="<?= htmlspecialchars($partner['og_image_url']) ?>" alt="<?= htmlspecialchars($partner['name']) ?>" loading="lazy">
                    <?php else: ?>
                    <?= strtoupper(substr($partner['name'], 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <div class="partner-info">
                    <h1 class="partner-name"><?= htmlspecialchars($partner['name']) ?></h1>
                    <div class="partner-meta">
                        <span class="partner-meta-item">
                            <i class="fa-solid fa-handshake"></i>
                            Partner since <?= $partnerSinceFormatted ?>
                        </span>
                        <span class="partner-meta-item">
                            <i class="fa-solid fa-puzzle-piece"></i>
                            <?= $enabledFeatureCount ?> features enabled
                        </span>
                        <?php if (!empty($partner['domain'])): ?>
                        <span class="partner-meta-item">
                            <i class="fa-solid fa-globe"></i>
                            <?= htmlspecialchars($partner['domain']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($partner['description'])): ?>
                    <p class="partner-description"><?= htmlspecialchars($partner['description']) ?></p>
                    <?php endif; ?>
                    <div class="partnership-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        Active Partnership
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <?php if ($features['members']): ?>
            <div class="stat-card">
                <div class="stat-icon members">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['members']) ?></div>
                <div class="stat-label">Members</div>
            </div>
            <?php endif; ?>

            <?php if ($features['listings']): ?>
            <div class="stat-card">
                <div class="stat-icon listings">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['listings']) ?></div>
                <div class="stat-label">Listings</div>
            </div>
            <?php endif; ?>

            <?php if ($features['events']): ?>
            <div class="stat-card">
                <div class="stat-icon events">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['events']) ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <?php endif; ?>

            <?php if ($features['groups']): ?>
            <div class="stat-card">
                <div class="stat-icon groups">
                    <i class="fa-solid fa-people-group"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['groups']) ?></div>
                <div class="stat-label">Groups</div>
            </div>
            <?php endif; ?>

            <?php if ($features['transactions']): ?>
            <div class="stat-card">
                <div class="stat-icon hours">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['total_hours_exchanged'], 1) ?></div>
                <div class="stat-label">Hours Exchanged</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Available Features -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fa-solid fa-puzzle-piece"></i>
                Available Features
            </h2>
            <div class="features-grid">
                <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['members'] ? 'disabled' : '' ?>">
                    <div class="feature-icon members">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="feature-info">
                        <h4>Browse Members</h4>
                        <p>View profiles from this timebank</p>
                    </div>
                    <span class="feature-status <?= $features['members'] ? 'enabled' : 'disabled' ?>">
                        <?= $features['members'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>

                <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['listings'] ? 'disabled' : '' ?>">
                    <div class="feature-icon listings">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>
                    <div class="feature-info">
                        <h4>Browse Listings</h4>
                        <p>Offers & requests</p>
                    </div>
                    <span class="feature-status <?= $features['listings'] ? 'enabled' : 'disabled' ?>">
                        <?= $features['listings'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>

                <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['events'] ? 'disabled' : '' ?>">
                    <div class="feature-icon events">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div class="feature-info">
                        <h4>Browse Events</h4>
                        <p>Upcoming events to join</p>
                    </div>
                    <span class="feature-status <?= $features['events'] ? 'enabled' : 'disabled' ?>">
                        <?= $features['events'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>

                <a href="<?= $basePath ?>/federation/groups?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['groups'] ? 'disabled' : '' ?>">
                    <div class="feature-icon groups">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="feature-info">
                        <h4>Browse Groups</h4>
                        <p>Interest groups to join</p>
                    </div>
                    <span class="feature-status <?= $features['groups'] ? 'enabled' : 'disabled' ?>">
                        <?= $features['groups'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>

                <div class="feature-item <?= !$features['messaging'] ? 'disabled' : '' ?>" style="cursor: default;">
                    <div class="feature-icon messaging">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div class="feature-info">
                        <h4>Cross-Messaging</h4>
                        <p>Message members directly</p>
                    </div>
                    <span class="feature-status <?= $features['messaging'] ? 'enabled' : 'disabled' ?>">
                        <?= $features['messaging'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>

                <div class="feature-item <?= !$features['transactions'] ? 'disabled' : '' ?>" style="cursor: default;">
                    <div class="feature-icon transactions">
                        <i class="fa-solid fa-exchange-alt"></i>
                    </div>
                    <div class="feature-info">
                        <h4>Hour Exchanges</h4>
                        <p>Exchange time credits</p>
                    </div>
                    <span class="feature-status <?= $features['transactions'] ? 'enabled' : 'disabled' ?>">
                        <?= $features['transactions'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Recent Activity with Partner -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Your Recent Activity
            </h2>
            <?php if (!empty($recentActivity)): ?>
            <div class="activity-list">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fa-solid <?= htmlspecialchars($activity['icon']) ?>"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-text"><?= htmlspecialchars($activity['description']) ?></p>
                        <span class="activity-time"><?= date('M j, Y', strtotime($activity['date'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-activity">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <p>No recent activity with this partner yet.</p>
                <p style="font-size: 0.85rem; margin-top: 8px;">Start by browsing their members or listings!</p>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <?php if ($features['members']): ?>
                <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="action-btn primary">
                    <i class="fa-solid fa-users"></i>
                    Browse Members
                </a>
                <?php endif; ?>

                <?php if ($features['listings']): ?>
                <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="action-btn secondary">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                    Browse Listings
                </a>
                <?php endif; ?>

                <?php if ($features['events']): ?>
                <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="action-btn secondary">
                    <i class="fa-solid fa-calendar-days"></i>
                    View Events
                </a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
