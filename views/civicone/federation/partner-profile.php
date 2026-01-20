<?php
// Federation Partner Profile - Detailed Partner Timebank View
$pageTitle = $pageTitle ?? "Partner Timebank";
$pageSubtitle = "Partner timebank details";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
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
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="partner-profile-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation" class="back-link" aria-label="Return to partner timebanks">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Partner Timebanks
        </a>

        <!-- Partner Header Card -->
        <article class="partner-header-card" aria-labelledby="partner-name">
            <header class="partner-header">
                <div class="partner-logo" aria-hidden="true">
                    <?php if (!empty($partner['og_image_url'])): ?>
                    <img src="<?= htmlspecialchars($partner['og_image_url']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                    <?= strtoupper(substr($partner['name'], 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <div class="partner-info">
                    <h1 id="partner-name" class="partner-name"><?= htmlspecialchars($partner['name']) ?></h1>
                    <div class="partner-meta">
                        <span class="partner-meta-item">
                            <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                            Partner since <?= $partnerSinceFormatted ?>
                        </span>
                        <span class="partner-meta-item">
                            <i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
                            <?= $enabledFeatureCount ?> features enabled
                        </span>
                        <?php if (!empty($partner['domain'])): ?>
                        <span class="partner-meta-item">
                            <i class="fa-solid fa-globe" aria-hidden="true"></i>
                            <?= htmlspecialchars($partner['domain']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($partner['description'])): ?>
                    <p class="partner-description"><?= htmlspecialchars($partner['description']) ?></p>
                    <?php endif; ?>
                    <div class="partnership-badge" role="status">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        Active Partnership
                    </div>
                </div>
            </header>
        </article>

        <!-- Stats Grid -->
        <section class="stats-grid" aria-label="Partner statistics">
            <?php if ($features['members']): ?>
            <div class="stat-card">
                <div class="stat-icon members" aria-hidden="true">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['members']) ?></div>
                <div class="stat-label">Members</div>
            </div>
            <?php endif; ?>

            <?php if ($features['listings']): ?>
            <div class="stat-card">
                <div class="stat-icon listings" aria-hidden="true">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['listings']) ?></div>
                <div class="stat-label">Listings</div>
            </div>
            <?php endif; ?>

            <?php if ($features['events']): ?>
            <div class="stat-card">
                <div class="stat-icon events" aria-hidden="true">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['events']) ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <?php endif; ?>

            <?php if ($features['groups']): ?>
            <div class="stat-card">
                <div class="stat-icon groups" aria-hidden="true">
                    <i class="fa-solid fa-people-group"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['groups']) ?></div>
                <div class="stat-label">Groups</div>
            </div>
            <?php endif; ?>

            <?php if ($features['transactions']): ?>
            <div class="stat-card">
                <div class="stat-icon hours" aria-hidden="true">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['total_hours_exchanged'], 1) ?></div>
                <div class="stat-label">Hours Exchanged</div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Available Features -->
        <section class="section-card" aria-labelledby="features-heading">
            <h2 id="features-heading" class="section-title">
                <i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
                Available Features
            </h2>
            <div class="features-grid" role="list">
                <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['members'] ? 'disabled' : '' ?>" role="listitem" <?= !$features['members'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="feature-icon members" aria-hidden="true">
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

                <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['listings'] ? 'disabled' : '' ?>" role="listitem" <?= !$features['listings'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="feature-icon listings" aria-hidden="true">
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

                <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['events'] ? 'disabled' : '' ?>" role="listitem" <?= !$features['events'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="feature-icon events" aria-hidden="true">
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

                <a href="<?= $basePath ?>/federation/groups?tenant=<?= $partner['id'] ?>" class="feature-item <?= !$features['groups'] ? 'disabled' : '' ?>" role="listitem" <?= !$features['groups'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="feature-icon groups" aria-hidden="true">
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

                <div class="feature-item feature-item-static <?= !$features['messaging'] ? 'disabled' : '' ?>" role="listitem">
                    <div class="feature-icon messaging" aria-hidden="true">
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

                <div class="feature-item feature-item-static <?= !$features['transactions'] ? 'disabled' : '' ?>" role="listitem">
                    <div class="feature-icon transactions" aria-hidden="true">
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
        </section>

        <!-- Recent Activity with Partner -->
        <section class="section-card" aria-labelledby="activity-heading">
            <h2 id="activity-heading" class="section-title">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                Your Recent Activity
            </h2>
            <?php if (!empty($recentActivity)): ?>
            <div class="activity-list" role="list" aria-label="Recent activity">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item" role="listitem">
                    <div class="activity-icon" aria-hidden="true">
                        <i class="fa-solid <?= htmlspecialchars($activity['icon']) ?>"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-text"><?= htmlspecialchars($activity['description']) ?></p>
                        <span class="activity-time">
                            <time datetime="<?= date('c', strtotime($activity['date'])) ?>"><?= date('M j, Y', strtotime($activity['date'])) ?></time>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-activity" role="status">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <p>No recent activity with this partner yet.</p>
                <p class="empty-activity-hint">Start by browsing their members or listings!</p>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <nav class="quick-actions" aria-label="Quick actions">
                <?php if ($features['members']): ?>
                <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="action-btn primary">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    Browse Members
                </a>
                <?php endif; ?>

                <?php if ($features['listings']): ?>
                <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="action-btn secondary">
                    <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
                    Browse Listings
                </a>
                <?php endif; ?>

                <?php if ($features['events']): ?>
                <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="action-btn secondary">
                    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                    View Events
                </a>
                <?php endif; ?>
            </nav>
        </section>

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

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
