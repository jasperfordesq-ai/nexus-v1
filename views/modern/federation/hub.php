<?php
// Federation Hub - Partner Timebanks Landing Page
$pageTitle = $pageTitle ?? "Partner Timebanks";
$pageSubtitle = "Connect across timebank communities";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
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

        <style>
            /* ============================================
               FEDERATION HUB - Master Landing Page
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

            /* Offline body state - shift content down */
            body.is-offline #federation-hub-wrapper {
                padding-top: 160px;
            }

            @media (max-width: 768px) {
                body.is-offline #federation-hub-wrapper {
                    padding-top: 140px;
                }
            }

            /* Offline overlay for cards */
            .offline-overlay {
                position: absolute;
                top: 8px;
                right: 8px;
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                background: rgba(239, 68, 68, 0.9);
                border-radius: 6px;
                font-size: 0.7rem;
                font-weight: 600;
                color: white;
                z-index: 10;
                backdrop-filter: blur(4px);
                -webkit-backdrop-filter: blur(4px);
            }

            .offline-overlay i {
                font-size: 0.65rem;
            }

            /* Disabled state for elements requiring network */
            .offline-disabled {
                opacity: 0.6;
                pointer-events: none;
                cursor: not-allowed;
                position: relative;
            }

            .offline-disabled::after {
                content: '';
                position: absolute;
                inset: 0;
                background: repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 5px,
                    rgba(0, 0, 0, 0.03) 5px,
                    rgba(0, 0, 0, 0.03) 10px
                );
                border-radius: inherit;
                pointer-events: none;
            }

            /* Content Reveal Animation */
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Page Layout */
            #federation-hub-wrapper {
                padding: 120px 20px 60px;
                max-width: 1200px;
                margin: 0 auto;
            }

            @media (max-width: 768px) {
                #federation-hub-wrapper {
                    padding: 100px 16px 100px;
                }
            }

            /* Hero Section */
            .fed-hero {
                text-align: center;
                margin-bottom: 48px;
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

            /* Partner Count Badge */
            .fed-partner-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(99, 102, 241, 0.1));
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 100px;
                font-size: 0.9rem;
                font-weight: 600;
                color: #8b5cf6;
                margin-top: 20px;
            }

            [data-theme="dark"] .fed-partner-badge {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.15));
                border-color: rgba(139, 92, 246, 0.3);
            }

            /* Opt-In Notice */
            .fed-optin-notice {
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.05));
                border: 1px solid rgba(245, 158, 11, 0.2);
                border-radius: 16px;
                padding: 24px;
                margin-bottom: 32px;
                display: flex;
                align-items: flex-start;
                gap: 16px;
                animation: fadeInUp 0.5s ease-out 0.1s both;
            }

            .fed-optin-notice i {
                font-size: 1.5rem;
                color: #f59e0b;
                margin-top: 2px;
            }

            .fed-optin-notice h3 {
                margin: 0 0 8px;
                font-size: 1rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
            }

            [data-theme="dark"] .fed-optin-notice h3 {
                color: #f1f5f9;
            }

            .fed-optin-notice p {
                margin: 0 0 12px;
                font-size: 0.9rem;
                color: var(--htb-text-secondary, #6b7280);
                line-height: 1.5;
            }

            [data-theme="dark"] .fed-optin-notice p {
                color: #94a3b8;
            }

            .fed-optin-btn {
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

            .fed-optin-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 16px rgba(245, 158, 11, 0.3);
            }

            /* Quick Links Bar */
            .fed-quick-links {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 12px;
                margin-bottom: 32px;
                animation: fadeInUp 0.4s ease-out 0.15s both;
            }

            .fed-quick-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                background: rgba(139, 92, 246, 0.1);
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 25px;
                color: #8b5cf6;
                font-size: 0.9rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
            }

            .fed-quick-link:hover {
                background: rgba(139, 92, 246, 0.2);
                border-color: rgba(139, 92, 246, 0.4);
                transform: translateY(-2px);
            }

            .fed-quick-link i {
                font-size: 0.85rem;
            }

            [data-theme="dark"] .fed-quick-link {
                background: rgba(139, 92, 246, 0.15);
                border-color: rgba(139, 92, 246, 0.25);
                color: #a78bfa;
            }

            [data-theme="dark"] .fed-quick-link:hover {
                background: rgba(139, 92, 246, 0.25);
                border-color: rgba(139, 92, 246, 0.4);
            }

            @media (max-width: 640px) {
                .fed-quick-links {
                    gap: 8px;
                }

                .fed-quick-link {
                    padding: 8px 14px;
                    font-size: 0.85rem;
                }

                .fed-quick-link span {
                    display: none;
                }

                .fed-quick-link i {
                    font-size: 1rem;
                }
            }

            /* Feature Grid */
            .fed-features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 48px;
            }

            @media (max-width: 640px) {
                .fed-features-grid {
                    grid-template-columns: 1fr;
                }
            }

            .fed-feature-card {
                background: rgba(255, 255, 255, 0.8);
                border: 1px solid rgba(139, 92, 246, 0.1);
                border-radius: 20px;
                padding: 24px;
                text-decoration: none;
                transition: all 0.3s ease;
                animation: fadeInUp 0.5s ease-out both;
                display: block;
            }

            .fed-feature-card:nth-child(1) { animation-delay: 0.1s; }
            .fed-feature-card:nth-child(2) { animation-delay: 0.15s; }
            .fed-feature-card:nth-child(3) { animation-delay: 0.2s; }
            .fed-feature-card:nth-child(4) { animation-delay: 0.25s; }
            .fed-feature-card:nth-child(5) { animation-delay: 0.3s; }
            .fed-feature-card:nth-child(6) { animation-delay: 0.35s; }

            [data-theme="dark"] .fed-feature-card {
                background: rgba(30, 41, 59, 0.8);
                border-color: rgba(139, 92, 246, 0.15);
            }

            .fed-feature-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 40px rgba(139, 92, 246, 0.15);
                border-color: rgba(139, 92, 246, 0.3);
            }

            .fed-feature-card.disabled {
                opacity: 0.5;
                pointer-events: none;
            }

            .fed-feature-header {
                display: flex;
                align-items: center;
                gap: 16px;
                margin-bottom: 12px;
            }

            .fed-feature-icon {
                width: 56px;
                height: 56px;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                flex-shrink: 0;
            }

            .fed-feature-icon.members { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
            .fed-feature-icon.listings { background: linear-gradient(135deg, #10b981, #059669); color: white; }
            .fed-feature-icon.events { background: linear-gradient(135deg, #ec4899, #db2777); color: white; }
            .fed-feature-icon.groups { background: linear-gradient(135deg, #f97316, #ea580c); color: white; }
            .fed-feature-icon.messages { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
            .fed-feature-icon.transactions { background: linear-gradient(135deg, #14b8a6, #0d9488); color: white; }

            .fed-feature-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0;
            }

            [data-theme="dark"] .fed-feature-title {
                color: #f1f5f9;
            }

            .fed-feature-stat {
                font-size: 0.85rem;
                color: #8b5cf6;
                font-weight: 600;
            }

            .fed-feature-desc {
                font-size: 0.9rem;
                color: var(--htb-text-secondary, #6b7280);
                line-height: 1.5;
                margin: 0;
            }

            [data-theme="dark"] .fed-feature-desc {
                color: #94a3b8;
            }

            .fed-feature-arrow {
                margin-top: 16px;
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 0.85rem;
                font-weight: 600;
                color: #8b5cf6;
            }

            .fed-feature-arrow i {
                transition: transform 0.2s ease;
            }

            .fed-feature-card:hover .fed-feature-arrow i {
                transform: translateX(4px);
            }

            /* Partners Section */
            .fed-partners-section {
                margin-top: 48px;
                animation: fadeInUp 0.5s ease-out 0.4s both;
            }

            .fed-section-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 20px;
            }

            .fed-section-title {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            [data-theme="dark"] .fed-section-title {
                color: #f1f5f9;
            }

            .fed-partners-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 16px;
            }

            .fed-partner-card {
                background: rgba(255, 255, 255, 0.7);
                border: 1px solid rgba(139, 92, 246, 0.1);
                border-radius: 16px;
                padding: 20px;
                text-align: center;
                transition: all 0.2s ease;
            }

            [data-theme="dark"] .fed-partner-card {
                background: rgba(30, 41, 59, 0.6);
                border-color: rgba(139, 92, 246, 0.15);
            }

            .fed-partner-card:hover {
                border-color: rgba(139, 92, 246, 0.3);
                box-shadow: 0 4px 20px rgba(139, 92, 246, 0.1);
            }

            .fed-partner-logo {
                width: 64px;
                height: 64px;
                border-radius: 16px;
                object-fit: cover;
                margin: 0 auto 12px;
                background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                font-weight: 700;
                color: #6366f1;
            }

            .fed-partner-logo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 16px;
            }

            .fed-partner-name {
                font-weight: 600;
                font-size: 0.95rem;
                color: var(--htb-text-main, #1f2937);
                margin-bottom: 8px;
            }

            [data-theme="dark"] .fed-partner-name {
                color: #f1f5f9;
            }

            .fed-partner-features {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 4px;
            }

            .fed-partner-feature-tag {
                font-size: 0.7rem;
                padding: 3px 8px;
                background: rgba(139, 92, 246, 0.1);
                color: #8b5cf6;
                border-radius: 100px;
                font-weight: 500;
            }

            [data-theme="dark"] .fed-partner-feature-tag {
                background: rgba(139, 92, 246, 0.2);
            }

            .fed-partner-view-link {
                margin-top: 12px;
                font-size: 0.8rem;
                color: #8b5cf6;
                font-weight: 600;
                opacity: 0;
                transform: translateY(4px);
                transition: all 0.2s ease;
            }

            .fed-partner-card:hover .fed-partner-view-link {
                opacity: 1;
                transform: translateY(0);
            }

            .fed-partner-view-link i {
                margin-left: 4px;
                transition: transform 0.2s ease;
            }

            .fed-partner-card:hover .fed-partner-view-link i {
                transform: translateX(4px);
            }

            /* Empty State */
            .fed-empty-partners {
                text-align: center;
                padding: 40px 20px;
                background: rgba(139, 92, 246, 0.05);
                border-radius: 16px;
                border: 1px dashed rgba(139, 92, 246, 0.2);
            }

            .fed-empty-partners i {
                font-size: 2.5rem;
                color: #8b5cf6;
                opacity: 0.5;
                margin-bottom: 16px;
            }

            .fed-empty-partners p {
                color: var(--htb-text-secondary, #6b7280);
                margin: 0;
            }

            /* Touch Targets */
            .fed-feature-card,
            .fed-optin-btn,
            button {
                min-height: 44px;
            }

            /* Focus Visible */
            .fed-feature-card:focus-visible,
            .fed-optin-btn:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }
        </style>

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

<style>
/* Quick Actions FAB */
.fed-fab-container {
    position: fixed;
    bottom: 100px;
    right: 24px;
    z-index: 9000;
}

@media (min-width: 769px) {
    .fed-fab-container {
        bottom: 32px;
    }
}

.fed-fab-toggle {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 2;
}

.fed-fab-toggle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 28px rgba(139, 92, 246, 0.5);
}

.fed-fab-toggle:active {
    transform: scale(0.95);
}

.fed-fab-toggle i:first-child {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.fed-fab-toggle i:last-child {
    position: absolute;
    opacity: 0;
    transform: rotate(-90deg);
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.fed-fab-container.open .fed-fab-toggle {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
}

.fed-fab-container.open .fed-fab-toggle i:first-child {
    opacity: 0;
    transform: rotate(90deg);
}

.fed-fab-container.open .fed-fab-toggle i:last-child {
    opacity: 1;
    transform: rotate(0deg);
}

.fed-fab-menu {
    position: absolute;
    bottom: 70px;
    right: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px) scale(0.9);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fed-fab-container.open .fed-fab-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.fed-fab-item {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    white-space: nowrap;
    animation: fabItemIn 0.3s ease backwards;
}

.fed-fab-container.open .fed-fab-item:nth-child(1) { animation-delay: 0.05s; }
.fed-fab-container.open .fed-fab-item:nth-child(2) { animation-delay: 0.1s; }
.fed-fab-container.open .fed-fab-item:nth-child(3) { animation-delay: 0.15s; }
.fed-fab-container.open .fed-fab-item:nth-child(4) { animation-delay: 0.2s; }
.fed-fab-container.open .fed-fab-item:nth-child(5) { animation-delay: 0.25s; }
.fed-fab-container.open .fed-fab-item:nth-child(6) { animation-delay: 0.3s; }
.fed-fab-container.open .fed-fab-item:nth-child(7) { animation-delay: 0.35s; }
.fed-fab-container.open .fed-fab-item:nth-child(8) { animation-delay: 0.4s; }

@keyframes fabItemIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.fed-fab-label {
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f2937;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

[data-theme="dark"] .fed-fab-label {
    background: rgba(30, 41, 59, 0.95);
    color: #f1f5f9;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.fed-fab-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.fed-fab-item:hover .fed-fab-icon {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(139, 92, 246, 0.4);
}

.fed-fab-item:hover .fed-fab-label {
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
}

[data-theme="dark"] .fed-fab-item:hover .fed-fab-label {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

/* Backdrop overlay when FAB is open */
.fed-fab-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    z-index: 8999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.fed-fab-backdrop.visible {
    opacity: 1;
    visibility: visible;
}

/* Focus styles */
.fed-fab-toggle:focus-visible,
.fed-fab-item:focus-visible .fed-fab-icon {
    outline: 3px solid rgba(139, 92, 246, 0.5);
    outline-offset: 2px;
}
</style>

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

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
