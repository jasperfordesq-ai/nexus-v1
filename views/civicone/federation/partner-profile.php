<?php
/**
 * Federation Partner Profile
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Partner Timebank";
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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation" class="civic-fed-back-link" aria-label="Return to partner timebanks">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Partner Timebanks
    </a>

    <!-- Partner Header Card -->
    <article class="civic-fed-partner-header-card" aria-labelledby="partner-name">
        <header class="civic-fed-partner-header">
            <div class="civic-fed-partner-logo" aria-hidden="true">
                <?php if (!empty($partner['og_image_url'])): ?>
                <img src="<?= htmlspecialchars($partner['og_image_url']) ?>" alt="" loading="lazy">
                <?php else: ?>
                <?= strtoupper(substr($partner['name'], 0, 2)) ?>
                <?php endif; ?>
            </div>
            <div class="civic-fed-partner-info">
                <h1 id="partner-name" class="civic-fed-partner-name"><?= htmlspecialchars($partner['name']) ?></h1>
                <div class="civic-fed-partner-meta">
                    <span class="civic-fed-partner-meta-item">
                        <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                        Partner since <?= $partnerSinceFormatted ?>
                    </span>
                    <span class="civic-fed-partner-meta-item">
                        <i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
                        <?= $enabledFeatureCount ?> features enabled
                    </span>
                    <?php if (!empty($partner['domain'])): ?>
                    <span class="civic-fed-partner-meta-item">
                        <i class="fa-solid fa-globe" aria-hidden="true"></i>
                        <?= htmlspecialchars($partner['domain']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($partner['description'])): ?>
                <p class="civic-fed-partner-description"><?= htmlspecialchars($partner['description']) ?></p>
                <?php endif; ?>
                <div class="civic-fed-status-badge civic-fed-status-badge--success" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Active Partnership
                </div>
            </div>
        </header>
    </article>

    <!-- Stats Grid -->
    <section class="civic-fed-stats-grid" aria-label="Partner statistics">
        <?php if ($features['members']): ?>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($stats['members']) ?></div>
                <div class="civic-fed-stat-label">Members</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($features['listings']): ?>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-hand-holding-heart"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($stats['listings']) ?></div>
                <div class="civic-fed-stat-label">Listings</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($features['events']): ?>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($stats['events']) ?></div>
                <div class="civic-fed-stat-label">Upcoming Events</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($features['groups']): ?>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-people-group"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($stats['groups']) ?></div>
                <div class="civic-fed-stat-label">Groups</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($features['transactions']): ?>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($stats['total_hours_exchanged'], 1) ?></div>
                <div class="civic-fed-stat-label">Hours Exchanged</div>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Available Features -->
    <section class="civic-fed-card" aria-labelledby="features-heading">
        <h2 id="features-heading" class="civic-fed-section-title">
            <i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
            Available Features
        </h2>
        <div class="civic-fed-features-grid" role="list">
            <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['members'] ? 'civic-fed-feature-item--disabled' : '' ?>" role="listitem" <?= !$features['members'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                <div class="civic-fed-feature-icon" aria-hidden="true">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="civic-fed-feature-info">
                    <h4>Browse Members</h4>
                    <p>View profiles from this timebank</p>
                </div>
                <span class="civic-fed-feature-status <?= $features['members'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                    <?= $features['members'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </a>

            <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['listings'] ? 'civic-fed-feature-item--disabled' : '' ?>" role="listitem" <?= !$features['listings'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                <div class="civic-fed-feature-icon" aria-hidden="true">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
                <div class="civic-fed-feature-info">
                    <h4>Browse Listings</h4>
                    <p>Offers & requests</p>
                </div>
                <span class="civic-fed-feature-status <?= $features['listings'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                    <?= $features['listings'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </a>

            <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['events'] ? 'civic-fed-feature-item--disabled' : '' ?>" role="listitem" <?= !$features['events'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                <div class="civic-fed-feature-icon" aria-hidden="true">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div class="civic-fed-feature-info">
                    <h4>Browse Events</h4>
                    <p>Upcoming events to join</p>
                </div>
                <span class="civic-fed-feature-status <?= $features['events'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                    <?= $features['events'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </a>

            <a href="<?= $basePath ?>/federation/groups?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['groups'] ? 'civic-fed-feature-item--disabled' : '' ?>" role="listitem" <?= !$features['groups'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                <div class="civic-fed-feature-icon" aria-hidden="true">
                    <i class="fa-solid fa-people-group"></i>
                </div>
                <div class="civic-fed-feature-info">
                    <h4>Browse Groups</h4>
                    <p>Interest groups to join</p>
                </div>
                <span class="civic-fed-feature-status <?= $features['groups'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                    <?= $features['groups'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </a>

            <div class="civic-fed-feature-item <?= !$features['messaging'] ? 'civic-fed-feature-item--disabled' : '' ?>" role="listitem">
                <div class="civic-fed-feature-icon" aria-hidden="true">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <div class="civic-fed-feature-info">
                    <h4>Cross-Messaging</h4>
                    <p>Message members directly</p>
                </div>
                <span class="civic-fed-feature-status <?= $features['messaging'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                    <?= $features['messaging'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>

            <div class="civic-fed-feature-item <?= !$features['transactions'] ? 'civic-fed-feature-item--disabled' : '' ?>" role="listitem">
                <div class="civic-fed-feature-icon" aria-hidden="true">
                    <i class="fa-solid fa-exchange-alt"></i>
                </div>
                <div class="civic-fed-feature-info">
                    <h4>Hour Exchanges</h4>
                    <p>Exchange time credits</p>
                </div>
                <span class="civic-fed-feature-status <?= $features['transactions'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                    <?= $features['transactions'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>
        </div>
    </section>

    <!-- Recent Activity with Partner -->
    <section class="civic-fed-card" aria-labelledby="activity-heading">
        <h2 id="activity-heading" class="civic-fed-section-title">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            Your Recent Activity
        </h2>
        <?php if (!empty($recentActivity)): ?>
        <div class="civic-fed-activity-list" role="list" aria-label="Recent activity">
            <?php foreach ($recentActivity as $activity): ?>
            <div class="civic-fed-activity-item" role="listitem">
                <div class="civic-fed-activity-icon" aria-hidden="true">
                    <i class="fa-solid <?= htmlspecialchars($activity['icon']) ?>"></i>
                </div>
                <div class="civic-fed-activity-content">
                    <p class="civic-fed-activity-text"><?= htmlspecialchars($activity['description']) ?></p>
                    <span class="civic-fed-activity-time">
                        <time datetime="<?= date('c', strtotime($activity['date'])) ?>"><?= date('M j, Y', strtotime($activity['date'])) ?></time>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="civic-fed-empty civic-fed-empty--compact" role="status">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <p>No recent activity with this partner yet.</p>
            <small>Start by browsing their members or listings!</small>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="civic-fed-actions" aria-label="Quick actions">
            <?php if ($features['members']): ?>
            <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="civic-fed-btn civic-fed-btn--primary">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Browse Members
            </a>
            <?php endif; ?>

            <?php if ($features['listings']): ?>
            <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="civic-fed-btn civic-fed-btn--secondary">
                <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
                Browse Listings
            </a>
            <?php endif; ?>

            <?php if ($features['events']): ?>
            <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="civic-fed-btn civic-fed-btn--secondary">
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                View Events
            </a>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
// Offline Indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
