<?php
// Federation Hub - Partner Timebanks Landing Page
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
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-hub-wrapper">

        <!-- Hero Section -->
        <div class="fed-hero">
            <div class="fed-hero-icon">
                <i class="fa-solid fa-globe"></i>
            </div>
            <h1>Partner Timebanks</h1>
            <p class="fed-hero-subtitle">
                Connect with members, browse listings, attend events, and join groups from our partner timebank communities.
            </p>
            <?php if ($partnerCount > 0): ?>
            <div class="fed-partner-badge">
                <i class="fa-solid fa-handshake"></i>
                <?= $partnerCount ?> Partner Timebank<?= $partnerCount !== 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
        </div>

        <?php $currentPage = 'hub'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

        <!-- Opt-In Notice (if not opted in) -->
        <?php if (!$userOptedIn): ?>
        <div class="fed-optin-notice">
            <i class="fa-solid fa-user-shield"></i>
            <div>
                <h3>Share Your Profile with Partners</h3>
                <p>
                    You can browse partner timebanks, but to be visible to their members and share your own content,
                    you'll need to enable federation.
                </p>
                <a href="<?= $basePath ?>/federation/onboarding" class="fed-optin-btn">
                    <i class="fa-solid fa-rocket"></i>
                    Get Started
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feature Cards Grid -->
        <div class="fed-features-grid">
            <!-- Members -->
            <a href="<?= $basePath ?>/federation/members" class="fed-feature-card <?= !$features['members'] ? 'disabled' : '' ?>">
                <div class="fed-feature-header">
                    <div class="fed-feature-icon members">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h3 class="fed-feature-title">Members</h3>
                        <?php if ($stats['members'] > 0): ?>
                        <span class="fed-feature-stat"><?= number_format($stats['members']) ?> available</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="fed-feature-desc">
                    Browse profiles and connect with members from partner timebanks.
                </p>
                <div class="fed-feature-arrow">
                    Browse Members <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- Listings -->
            <a href="<?= $basePath ?>/federation/listings" class="fed-feature-card <?= !$features['listings'] ? 'disabled' : '' ?>">
                <div class="fed-feature-header">
                    <div class="fed-feature-icon listings">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>
                    <div>
                        <h3 class="fed-feature-title">Listings</h3>
                        <?php if ($stats['listings'] > 0): ?>
                        <span class="fed-feature-stat"><?= number_format($stats['listings']) ?> available</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="fed-feature-desc">
                    Discover offers and requests from partner communities.
                </p>
                <div class="fed-feature-arrow">
                    Browse Listings <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- Events -->
            <a href="<?= $basePath ?>/federation/events" class="fed-feature-card <?= !$features['events'] ? 'disabled' : '' ?>">
                <div class="fed-feature-header">
                    <div class="fed-feature-icon events">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <h3 class="fed-feature-title">Events</h3>
                        <?php if ($stats['events'] > 0): ?>
                        <span class="fed-feature-stat"><?= number_format($stats['events']) ?> upcoming</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="fed-feature-desc">
                    Find and attend events hosted by partner timebanks.
                </p>
                <div class="fed-feature-arrow">
                    Browse Events <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- Groups -->
            <a href="<?= $basePath ?>/federation/groups" class="fed-feature-card <?= !$features['groups'] ? 'disabled' : '' ?>">
                <div class="fed-feature-header">
                    <div class="fed-feature-icon groups">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div>
                        <h3 class="fed-feature-title">Groups</h3>
                        <?php if ($stats['groups'] > 0): ?>
                        <span class="fed-feature-stat"><?= number_format($stats['groups']) ?> available</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="fed-feature-desc">
                    Join interest groups and hubs from partner communities.
                </p>
                <div class="fed-feature-arrow">
                    Browse Groups <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- Messages -->
            <a href="<?= $basePath ?>/federation/messages" class="fed-feature-card <?= !$features['messages'] ? 'disabled' : '' ?>">
                <div class="fed-feature-header">
                    <div class="fed-feature-icon messages">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div>
                        <h3 class="fed-feature-title">Messages</h3>
                    </div>
                </div>
                <p class="fed-feature-desc">
                    Send and receive messages with partner timebank members.
                </p>
                <div class="fed-feature-arrow">
                    View Messages <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- Transactions -->
            <a href="<?= $basePath ?>/federation/transactions" class="fed-feature-card <?= !$features['transactions'] ? 'disabled' : '' ?>">
                <div class="fed-feature-header">
                    <div class="fed-feature-icon transactions">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i>
                    </div>
                    <div>
                        <h3 class="fed-feature-title">Transactions</h3>
                    </div>
                </div>
                <p class="fed-feature-desc">
                    Exchange time credits with partner timebank members.
                </p>
                <div class="fed-feature-arrow">
                    View Transactions <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <!-- Activity Feed -->
            <a href="<?= $basePath ?>/federation/activity" class="fed-feature-card">
                <div class="fed-feature-header">
                    <div class="fed-feature-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div>
                        <h3 class="fed-feature-title">Activity Feed</h3>
                    </div>
                </div>
                <p class="fed-feature-desc">
                    View recent federation messages, transactions, and partner updates.
                </p>
                <div class="fed-feature-arrow">
                    View Activity <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>
        </div>

        <!-- Partner Timebanks List -->
        <?php if (!empty($partnerTenants)): ?>
        <div class="fed-partners-section">
            <div class="fed-section-header">
                <h2 class="fed-section-title">
                    <i class="fa-solid fa-handshake"></i>
                    Our Partners
                </h2>
            </div>

            <div class="fed-partners-grid">
                <?php foreach ($partnerTenants as $partner): ?>
                <a href="<?= $basePath ?>/federation/partners/<?= $partner['id'] ?>" class="fed-partner-card" style="text-decoration: none; color: inherit;">
                    <div class="fed-partner-logo">
                        <?php if (!empty($partner['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($partner['logo_url']) ?>" alt="<?= htmlspecialchars($partner['name']) ?>" loading="lazy">
                        <?php else: ?>
                        <?= strtoupper(substr($partner['name'], 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="fed-partner-name"><?= htmlspecialchars($partner['name']) ?></div>
                    <div class="fed-partner-features">
                        <?php if ($partner['members_enabled']): ?><span class="fed-partner-feature-tag">Members</span><?php endif; ?>
                        <?php if ($partner['listings_enabled']): ?><span class="fed-partner-feature-tag">Listings</span><?php endif; ?>
                        <?php if ($partner['events_enabled']): ?><span class="fed-partner-feature-tag">Events</span><?php endif; ?>
                        <?php if ($partner['groups_enabled']): ?><span class="fed-partner-feature-tag">Groups</span><?php endif; ?>
                    </div>
                    <div class="fed-partner-view-link">
                        View Details <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="fed-partners-section">
            <div class="fed-empty-partners">
                <i class="fa-solid fa-handshake-slash"></i>
                <p>No partner timebanks connected yet. Check back soon!</p>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Quick Actions FAB -->
<?php if ($userOptedIn && $partnerCount > 0): ?>
<div class="fed-fab-container" id="fabContainer">
    <button class="fed-fab-toggle" id="fabToggle" aria-label="Quick Actions" aria-expanded="false">
        <i class="fa-solid fa-bolt"></i>
        <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="fed-fab-menu" id="fabMenu" role="menu">
        <a href="<?= $basePath ?>/federation/dashboard" class="fed-fab-item" role="menuitem">
            <span class="fed-fab-label">My Dashboard</span>
            <span class="fed-fab-icon"><i class="fa-solid fa-gauge-high"></i></span>
        </a>
        <?php if ($features['messages']): ?>
        <a href="<?= $basePath ?>/federation/messages" class="fed-fab-item" role="menuitem">
            <span class="fed-fab-label">Messages</span>
            <span class="fed-fab-icon"><i class="fa-solid fa-envelope"></i></span>
        </a>
        <?php endif; ?>
        <?php if ($features['transactions']): ?>
        <a href="<?= $basePath ?>/federation/transactions/new" class="fed-fab-item" role="menuitem">
            <span class="fed-fab-label">Send Credits</span>
            <span class="fed-fab-icon"><i class="fa-solid fa-paper-plane"></i></span>
        </a>
        <?php endif; ?>
        <?php if ($features['members']): ?>
        <a href="<?= $basePath ?>/federation/members" class="fed-fab-item" role="menuitem">
            <span class="fed-fab-label">Find Members</span>
            <span class="fed-fab-icon"><i class="fa-solid fa-user-group"></i></span>
        </a>
        <?php endif; ?>
        <a href="<?= $basePath ?>/federation/activity" class="fed-fab-item" role="menuitem">
            <span class="fed-fab-label">Activity</span>
            <span class="fed-fab-icon"><i class="fa-solid fa-bell"></i></span>
        </a>
        <a href="<?= $basePath ?>/federation/settings" class="fed-fab-item" role="menuitem">
            <span class="fed-fab-label">Settings</span>
            <span class="fed-fab-icon"><i class="fa-solid fa-sliders"></i></span>
        </a>
        <a href="<?= $basePath ?>/federation/help" class="fed-fab-item" role="menuitem">
            <span class="fed-fab-label">Help & FAQ</span>
            <span class="fed-fab-icon"><i class="fa-solid fa-circle-question"></i></span>
        </a>
    </div>
</div>

<div class="fed-fab-backdrop" id="fabBackdrop"></div>
<?php endif; ?>

<script>
// Quick Actions FAB
(function initFAB() {
    const container = document.getElementById('fabContainer');
    const toggle = document.getElementById('fabToggle');
    const backdrop = document.getElementById('fabBackdrop');

    if (!container || !toggle) return;

    function openFAB() {
        container.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        if (backdrop) backdrop.classList.add('visible');
    }

    function closeFAB() {
        container.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        if (backdrop) backdrop.classList.remove('visible');
    }

    function toggleFAB() {
        if (container.classList.contains('open')) {
            closeFAB();
        } else {
            openFAB();
        }
    }

    toggle.addEventListener('click', toggleFAB);

    if (backdrop) {
        backdrop.addEventListener('click', closeFAB);
    }

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && container.classList.contains('open')) {
            closeFAB();
            toggle.focus();
        }
    });

    // Close when clicking a menu item
    container.querySelectorAll('.fed-fab-item').forEach(function(item) {
        item.addEventListener('click', function() {
            closeFAB();
        });
    });
})();

// Offline Indicator & Feature Degradation
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        document.body.classList.add('is-offline');
        if (navigator.vibrate) navigator.vibrate(100);

        // Disable interactive elements that require network
        document.querySelectorAll('[data-requires-network]').forEach(el => {
            el.classList.add('offline-disabled');
            el.setAttribute('aria-disabled', 'true');
        });

        // Show offline overlay on cards that need live data
        document.querySelectorAll('.fed-partner-card, .fed-member-card, .fed-listing-card').forEach(card => {
            if (!card.querySelector('.offline-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'offline-overlay';
                overlay.innerHTML = '<i class="fa-solid fa-wifi-slash"></i><span>Cached</span>';
                card.style.position = 'relative';
                card.appendChild(overlay);
            }
        });
    }

    function handleOnline() {
        banner.classList.remove('visible');
        document.body.classList.remove('is-offline');

        // Re-enable interactive elements
        document.querySelectorAll('[data-requires-network]').forEach(el => {
            el.classList.remove('offline-disabled');
            el.removeAttribute('aria-disabled');
        });

        // Remove offline overlays
        document.querySelectorAll('.offline-overlay').forEach(overlay => {
            overlay.remove();
        });
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#8b5cf6';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#8b5cf6');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php
// Include real-time notifications for opted-in users
if ($userOptedIn):
    require dirname(__DIR__) . '/partials/federation-realtime.php';
endif;
?>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
