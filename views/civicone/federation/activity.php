<?php
// Federation Activity Feed - Glassmorphism 2025
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
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-activity-wrapper">

        <!-- Hero Section -->
        <div class="fed-hero">
            <div class="fed-hero-icon">
                <i class="fa-solid fa-bell"></i>
            </div>
            <h1>Federation Activity</h1>
            <p class="fed-hero-subtitle">
                View your recent messages, transactions, and updates from partner timebanks.
            </p>
        </div>

        <?php $currentPage = 'activity'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

        <!-- Stats Cards -->
        <?php if ($userOptedIn && !empty($stats)): ?>
        <div class="activity-header">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value unread"><?= $stats['unread_messages'] ?? 0 ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_messages'] ?? 0 ?></div>
                    <div class="stat-label">Messages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value sent"><?= number_format($stats['hours_sent'] ?? 0, 1) ?></div>
                    <div class="stat-label">Hrs Sent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value received"><?= number_format($stats['hours_received'] ?? 0, 1) ?></div>
                    <div class="stat-label">Hrs Received</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['partner_count'] ?? 0 ?></div>
                    <div class="stat-label">Partners</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$userOptedIn): ?>
        <!-- Not Opted In Notice -->
        <div class="optin-notice">
            <i class="fa-solid fa-user-shield"></i>
            <div>
                <h3>Enable Federation to See Full Activity</h3>
                <p>
                    You can browse partner timebanks, but to receive messages, send transactions,
                    and participate fully in the federation network, enable federation in your settings.
                </p>
                <a href="<?= $basePath ?>/settings?section=federation" class="optin-btn">
                    <i class="fa-solid fa-toggle-on"></i>
                    Enable Federation
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">
                <i class="fa-solid fa-stream"></i> All Activity
            </button>
            <button class="filter-tab" data-filter="message">
                <i class="fa-solid fa-envelope"></i> Messages
                <?php if (($stats['unread_messages'] ?? 0) > 0): ?>
                <span class="badge"><?= $stats['unread_messages'] ?></span>
                <?php endif; ?>
            </button>
            <button class="filter-tab" data-filter="transaction">
                <i class="fa-solid fa-exchange-alt"></i> Transactions
            </button>
            <button class="filter-tab" data-filter="new_partner">
                <i class="fa-solid fa-handshake"></i> Partners
            </button>
        </div>

        <!-- Activity List -->
        <?php if (!empty($activities)): ?>
        <div class="activity-list" id="activity-list">
            <?php foreach ($activities as $activity): ?>
            <?php
            $iconClass = 'partner';
            if ($activity['type'] === 'message') {
                $iconClass = 'message';
            } elseif ($activity['type'] === 'transaction') {
                $iconClass = ($activity['meta']['direction'] ?? '') === 'sent' ? 'transaction-sent' : 'transaction-received';
            }
            ?>
            <a href="<?= $basePath . ($activity['link'] ?? '/federation') ?>"
               class="activity-card <?= ($activity['is_unread'] ?? false) ? 'unread' : '' ?>"
               data-type="<?= htmlspecialchars($activity['type']) ?>">
                <div class="activity-icon <?= $iconClass ?>" style="<?= !empty($activity['color']) ? 'background: ' . $activity['color'] . '20; color: ' . $activity['color'] : '' ?>">
                    <i class="fa-solid <?= htmlspecialchars($activity['icon'] ?? 'fa-bell') ?>"></i>
                </div>
                <div class="activity-content">
                    <h3 class="activity-title"><?= htmlspecialchars($activity['title'] ?? '') ?></h3>
                    <?php if (!empty($activity['subtitle'])): ?>
                    <span class="activity-subtitle">
                        <i class="fa-solid fa-building"></i>
                        <?= htmlspecialchars($activity['subtitle']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($activity['description'])): ?>
                    <p class="activity-desc"><?= htmlspecialchars($activity['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($activity['preview'])): ?>
                    <p class="activity-preview">"<?= htmlspecialchars($activity['preview']) ?>..."</p>
                    <?php endif; ?>
                </div>
                <div class="activity-meta">
                    <span class="activity-time"><?= formatActivityTime($activity['timestamp'] ?? '') ?></span>
                    <?php if ($activity['is_unread'] ?? false): ?>
                    <span class="unread-badge"></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fa-solid fa-bell-slash"></i>
            </div>
            <h3>No Federation Activity Yet</h3>
            <p>
                <?php if (!$userOptedIn): ?>
                Enable federation to start connecting with partner timebanks!
                <?php else: ?>
                Start connecting with members from partner timebanks to see activity here.
                <?php endif; ?>
            </p>
            <a href="<?= $basePath ?>/federation/members" class="explore-btn">
                <i class="fa-solid fa-users"></i>
                Browse Federated Members
            </a>
        </div>
        <?php endif; ?>

    </div>
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
    const filterTabs = document.querySelectorAll('.filter-tab');
    const activityCards = document.querySelectorAll('.activity-card');

    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            filterTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

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
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
