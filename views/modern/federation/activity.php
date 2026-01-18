<?php
// Federation Activity Feed - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federation Activity";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Activity - Recent Updates');
Nexus\Core\SEO::setDescription('View your recent federation activity including messages, transactions, and new partner connections.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
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

        <style>
            /* ============================================
               FEDERATION ACTIVITY FEED - Glassmorphism
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

            /* Page Layout */
            #fed-activity-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px 0 60px;
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            /* Header Card */
            .activity-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 20px;
                padding: 24px;
                margin-bottom: 24px;
                text-align: center;
            }

            [data-theme="dark"] .activity-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.15) 0%,
                        rgba(168, 85, 247, 0.15) 50%,
                        rgba(192, 132, 252, 0.1) 100%);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .activity-header h1 {
                font-size: 1.5rem;
                font-weight: 800;
                background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin: 0 0 8px 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }

            .activity-header p {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
                margin: 0;
            }

            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 12px;
                margin-top: 20px;
            }

            .stat-card {
                background: rgba(255, 255, 255, 0.5);
                border-radius: 12px;
                padding: 14px 10px;
                text-align: center;
            }

            [data-theme="dark"] .stat-card {
                background: rgba(255, 255, 255, 0.1);
            }

            .stat-value {
                font-size: 1.35rem;
                font-weight: 800;
                color: #8b5cf6;
            }

            .stat-value.unread {
                color: #3b82f6;
            }

            .stat-value.sent {
                color: #ef4444;
            }

            .stat-value.received {
                color: #10b981;
            }

            .stat-label {
                font-size: 0.7rem;
                color: var(--htb-text-muted);
                margin-top: 4px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Filter Tabs */
            .filter-tabs {
                display: flex;
                gap: 8px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }

            .filter-tab {
                padding: 10px 18px;
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                border: 2px solid transparent;
                background: rgba(255, 255, 255, 0.6);
                color: var(--htb-text-muted);
            }

            [data-theme="dark"] .filter-tab {
                background: rgba(255, 255, 255, 0.1);
            }

            .filter-tab:hover {
                background: rgba(139, 92, 246, 0.1);
                color: #8b5cf6;
            }

            .filter-tab.active {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border-color: transparent;
            }

            .filter-tab .badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 20px;
                height: 20px;
                padding: 0 6px;
                border-radius: 10px;
                font-size: 0.75rem;
                font-weight: 700;
                margin-left: 6px;
                background: rgba(239, 68, 68, 0.2);
                color: #ef4444;
            }

            .filter-tab.active .badge {
                background: rgba(255, 255, 255, 0.3);
                color: white;
            }

            /* Activity List */
            .activity-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .activity-card {
                display: flex;
                gap: 16px;
                padding: 18px 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 16px;
                transition: all 0.3s ease;
                text-decoration: none;
                color: inherit;
            }

            [data-theme="dark"] .activity-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .activity-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(139, 92, 246, 0.15);
            }

            .activity-card.unread {
                border-left: 4px solid #3b82f6;
            }

            .activity-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.1rem;
                flex-shrink: 0;
            }

            .activity-content {
                flex: 1;
                min-width: 0;
            }

            .activity-title {
                font-size: 0.95rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 4px 0;
            }

            .activity-subtitle {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 0.75rem;
                color: #8b5cf6;
                background: rgba(139, 92, 246, 0.1);
                padding: 2px 8px;
                border-radius: 8px;
                margin-bottom: 6px;
            }

            .activity-desc {
                font-size: 0.85rem;
                color: var(--htb-text-muted);
                margin: 0;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .activity-preview {
                font-size: 0.85rem;
                color: var(--htb-text-muted);
                font-style: italic;
                margin-top: 6px;
            }

            .activity-meta {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                justify-content: space-between;
                flex-shrink: 0;
            }

            .activity-time {
                font-size: 0.75rem;
                color: var(--htb-text-muted);
                white-space: nowrap;
            }

            .unread-badge {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #3b82f6;
                margin-top: 8px;
            }

            /* Type Icons */
            .activity-icon.message {
                background: rgba(59, 130, 246, 0.15);
                color: #3b82f6;
            }

            .activity-icon.transaction-sent {
                background: rgba(239, 68, 68, 0.15);
                color: #ef4444;
            }

            .activity-icon.transaction-received {
                background: rgba(16, 185, 129, 0.15);
                color: #10b981;
            }

            .activity-icon.partner {
                background: rgba(139, 92, 246, 0.15);
                color: #8b5cf6;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.7),
                        rgba(255, 255, 255, 0.5));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
            }

            [data-theme="dark"] .empty-state {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .empty-state-icon {
                font-size: 4rem;
                color: #8b5cf6;
                margin-bottom: 20px;
            }

            .empty-state h3 {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 10px 0;
            }

            .empty-state p {
                color: var(--htb-text-muted);
                margin: 0 0 20px 0;
            }

            .explore-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 700;
                transition: all 0.3s ease;
            }

            .explore-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            }

            /* Not Opted In */
            .optin-notice {
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.05));
                border: 1px solid rgba(245, 158, 11, 0.2);
                border-radius: 16px;
                padding: 24px;
                margin-bottom: 24px;
                display: flex;
                align-items: flex-start;
                gap: 16px;
            }

            .optin-notice i {
                font-size: 1.5rem;
                color: #f59e0b;
            }

            .optin-notice h3 {
                margin: 0 0 8px;
                font-size: 1rem;
                font-weight: 700;
                color: var(--htb-text-main);
            }

            .optin-notice p {
                margin: 0 0 12px;
                font-size: 0.9rem;
                color: var(--htb-text-muted);
            }

            .optin-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 20px;
                background: linear-gradient(135deg, #f59e0b, #d97706);
                color: white;
                border-radius: 10px;
                font-weight: 600;
                font-size: 0.9rem;
                text-decoration: none;
                transition: all 0.2s ease;
            }

            .optin-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 16px rgba(245, 158, 11, 0.3);
            }

            /* Touch Targets */
            .filter-tab,
            .activity-card,
            .explore-btn,
            .optin-btn {
                min-height: 44px;
            }

            /* Focus Visible */
            .filter-tab:focus-visible,
            .activity-card:focus-visible,
            .explore-btn:focus-visible,
            .optin-btn:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            /* Responsive */
            @media (max-width: 640px) {
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }

                .activity-card {
                    flex-wrap: wrap;
                }

                .activity-meta {
                    width: 100%;
                    flex-direction: row;
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px solid rgba(139, 92, 246, 0.1);
                }
            }
        </style>

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

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
