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
