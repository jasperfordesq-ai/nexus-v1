<?php
/**
 * Federation Hub - Partner Timebanks Landing Page
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Partner Timebanks";
$pageSubtitle = "Connect across timebank communities";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$userOptedIn = $userOptedIn ?? false;
$partnerCount = $partnerCount ?? 0;
$partnerTenants = $partnerTenants ?? [];
$features = $features ?? [];
$stats = $stats ?? [];
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/dashboard" class="civic-fed-back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Dashboard
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <h1>Partner Timebanks</h1>
        <?php if ($partnerCount > 0): ?>
        <span class="civic-fed-badge">
            <i class="fa-solid fa-handshake" aria-hidden="true"></i>
            <?= $partnerCount ?> Partner<?= $partnerCount !== 1 ? 's' : '' ?>
        </span>
        <?php endif; ?>
    </header>

    <p class="civic-fed-intro">
        Connect with members, browse listings, attend events, and join groups from our partner timebank communities.
    </p>

    <?php $currentPage = 'hub'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

    <!-- Opt-In Notice (if not opted in) -->
    <?php if (!$userOptedIn): ?>
    <div class="civic-fed-card civic-fed-card--accent">
        <div class="civic-fed-card-body">
            <div class="civic-fed-notice">
                <div class="civic-fed-notice-icon">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-notice-content">
                    <h3>Share Your Profile with Partners</h3>
                    <p>
                        You can browse partner timebanks, but to be visible to their members and share your own content,
                        you'll need to enable federation.
                    </p>
                </div>
                <a href="<?= $basePath ?>/federation/onboarding" class="civic-fed-btn civic-fed-btn--primary">
                    <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                    Get Started
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Feature Cards Grid -->
    <section class="civic-fed-section" aria-labelledby="features-heading">
        <h2 id="features-heading" class="civic-fed-section-title">
            <i class="fa-solid fa-compass" aria-hidden="true"></i>
            Explore Federation
        </h2>

        <div class="civic-fed-hub-grid">
            <!-- Members -->
            <a href="<?= $basePath ?>/federation/members" class="civic-fed-hub-card <?= !$features['members'] ? 'civic-fed-hub-card--disabled' : '' ?>">
                <div class="civic-fed-hub-card-icon civic-fed-hub-card-icon--members">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Members</h3>
                    <?php if ($stats['members'] > 0): ?>
                    <span class="civic-fed-hub-card-stat"><?= number_format($stats['members']) ?> available</span>
                    <?php endif; ?>
                    <p>Browse profiles and connect with members from partner timebanks.</p>
                </div>
                <span class="civic-fed-hub-card-action">
                    Browse Members <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>

            <!-- Listings -->
            <a href="<?= $basePath ?>/federation/listings" class="civic-fed-hub-card <?= !$features['listings'] ? 'civic-fed-hub-card--disabled' : '' ?>">
                <div class="civic-fed-hub-card-icon civic-fed-hub-card-icon--listings">
                    <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Listings</h3>
                    <?php if ($stats['listings'] > 0): ?>
                    <span class="civic-fed-hub-card-stat"><?= number_format($stats['listings']) ?> available</span>
                    <?php endif; ?>
                    <p>Discover offers and requests from partner communities.</p>
                </div>
                <span class="civic-fed-hub-card-action">
                    Browse Listings <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>

            <!-- Events -->
            <a href="<?= $basePath ?>/federation/events" class="civic-fed-hub-card <?= !$features['events'] ? 'civic-fed-hub-card--disabled' : '' ?>">
                <div class="civic-fed-hub-card-icon civic-fed-hub-card-icon--events">
                    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Events</h3>
                    <?php if ($stats['events'] > 0): ?>
                    <span class="civic-fed-hub-card-stat"><?= number_format($stats['events']) ?> upcoming</span>
                    <?php endif; ?>
                    <p>Find and attend events hosted by partner timebanks.</p>
                </div>
                <span class="civic-fed-hub-card-action">
                    Browse Events <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>

            <!-- Groups -->
            <a href="<?= $basePath ?>/federation/groups" class="civic-fed-hub-card <?= !$features['groups'] ? 'civic-fed-hub-card--disabled' : '' ?>">
                <div class="civic-fed-hub-card-icon civic-fed-hub-card-icon--groups">
                    <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Groups</h3>
                    <?php if ($stats['groups'] > 0): ?>
                    <span class="civic-fed-hub-card-stat"><?= number_format($stats['groups']) ?> available</span>
                    <?php endif; ?>
                    <p>Join interest groups and hubs from partner communities.</p>
                </div>
                <span class="civic-fed-hub-card-action">
                    Browse Groups <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>

            <!-- Messages -->
            <a href="<?= $basePath ?>/federation/messages" class="civic-fed-hub-card <?= !$features['messages'] ? 'civic-fed-hub-card--disabled' : '' ?>">
                <div class="civic-fed-hub-card-icon civic-fed-hub-card-icon--messages">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Messages</h3>
                    <p>Send and receive messages with partner timebank members.</p>
                </div>
                <span class="civic-fed-hub-card-action">
                    View Messages <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>

            <!-- Transactions -->
            <a href="<?= $basePath ?>/federation/transactions" class="civic-fed-hub-card <?= !$features['transactions'] ? 'civic-fed-hub-card--disabled' : '' ?>">
                <div class="civic-fed-hub-card-icon civic-fed-hub-card-icon--transactions">
                    <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Transactions</h3>
                    <p>Exchange time credits with partner timebank members.</p>
                </div>
                <span class="civic-fed-hub-card-action">
                    View Transactions <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>

            <!-- Activity Feed -->
            <a href="<?= $basePath ?>/federation/activity" class="civic-fed-hub-card">
                <div class="civic-fed-hub-card-icon civic-fed-hub-card-icon--activity">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Activity Feed</h3>
                    <p>View recent federation messages, transactions, and partner updates.</p>
                </div>
                <span class="civic-fed-hub-card-action">
                    View Activity <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>
        </div>
    </section>

    <!-- Partner Timebanks List -->
    <section class="civic-fed-section" aria-labelledby="partners-heading">
        <h2 id="partners-heading" class="civic-fed-section-title">
            <i class="fa-solid fa-handshake" aria-hidden="true"></i>
            Our Partners
        </h2>

        <?php if (!empty($partnerTenants)): ?>
        <div class="civic-fed-partners-grid">
            <?php foreach ($partnerTenants as $partner): ?>
            <a href="<?= $basePath ?>/federation/partners/<?= $partner['id'] ?>" class="civic-fed-partner-card">
                <div class="civic-fed-partner-logo">
                    <?php if (!empty($partner['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($partner['logo_url']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                    <span><?= strtoupper(substr($partner['name'], 0, 2)) ?></span>
                    <?php endif; ?>
                </div>
                <h3 class="civic-fed-partner-name"><?= htmlspecialchars($partner['name']) ?></h3>
                <div class="civic-fed-partner-features">
                    <?php if ($partner['members_enabled']): ?><span class="civic-fed-tag">Members</span><?php endif; ?>
                    <?php if ($partner['listings_enabled']): ?><span class="civic-fed-tag">Listings</span><?php endif; ?>
                    <?php if ($partner['events_enabled']): ?><span class="civic-fed-tag">Events</span><?php endif; ?>
                    <?php if ($partner['groups_enabled']): ?><span class="civic-fed-tag">Groups</span><?php endif; ?>
                </div>
                <span class="civic-fed-partner-link">
                    View Details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="civic-fed-empty">
            <div class="civic-fed-empty-icon">
                <i class="fa-solid fa-handshake-slash" aria-hidden="true"></i>
            </div>
            <h3>No Partner Timebanks Yet</h3>
            <p>No partner timebanks connected yet. Check back soon!</p>
        </div>
        <?php endif; ?>
    </section>
</div>

<script>
// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('civic-fed-offline-banner--visible');
    }

    function handleOnline() {
        banner.classList.remove('civic-fed-offline-banner--visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();
</script>

<?php
// Include real-time notifications for opted-in users
if ($userOptedIn):
    require dirname(__DIR__) . '/partials/federation-realtime.php';
endif;
?>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
