<?php
/**
 * Federation Activity Feed
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federation Activity";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Activity - Recent Updates');
Nexus\Core\SEO::setDescription('View your recent federation activity including messages, transactions, and new partner connections.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$activities = $activities ?? [];
$stats = $stats ?? [];
$userOptedIn = $userOptedIn ?? false;
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
        <h1>Federation Activity</h1>
    </header>

    <p class="civic-fed-intro">
        View your recent messages, transactions, and updates from partner timebanks.
    </p>

    <?php $currentPage = 'activity'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

    <!-- Stats Cards -->
    <?php if ($userOptedIn && !empty($stats)): ?>
    <div class="civic-fed-stats-grid">
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value civic-fed-stat-value--highlight"><?= $stats['unread_messages'] ?? 0 ?></span>
            <span class="civic-fed-stat-label">Unread</span>
        </div>
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value"><?= $stats['total_messages'] ?? 0 ?></span>
            <span class="civic-fed-stat-label">Messages</span>
        </div>
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value civic-fed-stat-value--sent"><?= number_format($stats['hours_sent'] ?? 0, 1) ?></span>
            <span class="civic-fed-stat-label">Hrs Sent</span>
        </div>
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value civic-fed-stat-value--received"><?= number_format($stats['hours_received'] ?? 0, 1) ?></span>
            <span class="civic-fed-stat-label">Hrs Received</span>
        </div>
        <div class="civic-fed-stat-card">
            <span class="civic-fed-stat-value"><?= $stats['partner_count'] ?? 0 ?></span>
            <span class="civic-fed-stat-label">Partners</span>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$userOptedIn): ?>
    <!-- Not Opted In Notice -->
    <div class="civic-fed-card civic-fed-card--accent">
        <div class="civic-fed-card-body">
            <div class="civic-fed-notice">
                <div class="civic-fed-notice-icon">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-notice-content">
                    <h3>Enable Federation to See Full Activity</h3>
                    <p>
                        You can browse partner timebanks, but to receive messages, send transactions,
                        and participate fully in the federation network, enable federation in your settings.
                    </p>
                </div>
                <a href="<?= $basePath ?>/settings?section=federation" class="civic-fed-btn civic-fed-btn--primary">
                    <i class="fa-solid fa-toggle-on" aria-hidden="true"></i>
                    Enable Federation
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="civic-fed-filter-tabs" role="tablist" aria-label="Filter activity">
        <button class="civic-fed-filter-tab civic-fed-filter-tab--active" data-filter="all" role="tab" aria-selected="true">
            <i class="fa-solid fa-stream" aria-hidden="true"></i> All Activity
        </button>
        <button class="civic-fed-filter-tab" data-filter="message" role="tab" aria-selected="false">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i> Messages
            <?php if (($stats['unread_messages'] ?? 0) > 0): ?>
            <span class="civic-fed-filter-badge"><?= $stats['unread_messages'] ?></span>
            <?php endif; ?>
        </button>
        <button class="civic-fed-filter-tab" data-filter="transaction" role="tab" aria-selected="false">
            <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i> Transactions
        </button>
        <button class="civic-fed-filter-tab" data-filter="new_partner" role="tab" aria-selected="false">
            <i class="fa-solid fa-handshake" aria-hidden="true"></i> Partners
        </button>
    </div>

    <!-- Activity List -->
    <?php if (!empty($activities)): ?>
    <div class="civic-fed-activity-feed" id="activity-list">
        <?php foreach ($activities as $activity): ?>
        <?php
        $iconClass = 'civic-fed-activity-icon--partner';
        if ($activity['type'] === 'message') {
            $iconClass = 'civic-fed-activity-icon--message';
        } elseif ($activity['type'] === 'transaction') {
            $iconClass = ($activity['meta']['direction'] ?? '') === 'sent' ? 'civic-fed-activity-icon--sent' : 'civic-fed-activity-icon--received';
        }
        ?>
        <a href="<?= $basePath . ($activity['link'] ?? '/federation') ?>"
           class="civic-fed-activity-card <?= ($activity['is_unread'] ?? false) ? 'civic-fed-activity-card--unread' : '' ?>"
           data-type="<?= htmlspecialchars($activity['type']) ?>">
            <div class="civic-fed-activity-icon <?= $iconClass ?>">
                <i class="fa-solid <?= htmlspecialchars($activity['icon'] ?? 'fa-bell') ?>" aria-hidden="true"></i>
            </div>
            <div class="civic-fed-activity-body">
                <h3 class="civic-fed-activity-title"><?= htmlspecialchars($activity['title'] ?? '') ?></h3>
                <?php if (!empty($activity['subtitle'])): ?>
                <span class="civic-fed-activity-source">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <?= htmlspecialchars($activity['subtitle']) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($activity['description'])): ?>
                <p class="civic-fed-activity-desc"><?= htmlspecialchars($activity['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($activity['preview'])): ?>
                <p class="civic-fed-activity-preview">"<?= htmlspecialchars($activity['preview']) ?>..."</p>
                <?php endif; ?>
            </div>
            <div class="civic-fed-activity-time">
                <span><?= formatActivityTime($activity['timestamp'] ?? '') ?></span>
                <?php if ($activity['is_unread'] ?? false): ?>
                <span class="civic-fed-unread-dot" aria-label="Unread"></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="civic-fed-empty">
        <div class="civic-fed-empty-icon">
            <i class="fa-solid fa-bell-slash" aria-hidden="true"></i>
        </div>
        <h3>No Federation Activity Yet</h3>
        <p>
            <?php if (!$userOptedIn): ?>
            Enable federation to start connecting with partner timebanks!
            <?php else: ?>
            Start connecting with members from partner timebanks to see activity here.
            <?php endif; ?>
        </p>
        <a href="<?= $basePath ?>/federation/members" class="civic-fed-btn civic-fed-btn--primary">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            Browse Federated Members
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
function formatActivityTime($timestamp) {
    if (empty($timestamp)) return '';

    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . 'm ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd ago';
    } else {
        return date('M j', $time);
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterTabs = document.querySelectorAll('.civic-fed-filter-tab');
    const activityCards = document.querySelectorAll('.civic-fed-activity-card');

    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            filterTabs.forEach(t => {
                t.classList.remove('civic-fed-filter-tab--active');
                t.setAttribute('aria-selected', 'false');
            });
            this.classList.add('civic-fed-filter-tab--active');
            this.setAttribute('aria-selected', 'true');

            const filter = this.dataset.filter;

            // Filter cards
            activityCards.forEach(card => {
                if (filter === 'all' || card.dataset.type === filter) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});

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
