<?php
// Federation Listing Detail - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Listing";
$hideHero = true;

Nexus\Core\SEO::setTitle(($listing['title'] ?? 'Listing') . ' - Federated');
Nexus\Core\SEO::setDescription('Listing details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$listing = $listing ?? [];
$canMessage = $canMessage ?? false;

$ownerName = $listing['owner_name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($ownerName) . '&background=8b5cf6&color=fff&size=200';
$ownerAvatar = !empty($listing['owner_avatar']) ? $listing['owner_avatar'] : $fallbackAvatar;
$type = $listing['type'] ?? 'offer';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-listing-wrapper">

        <style>
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

            #fed-listing-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px 0;
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

            .listing-card {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 24px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
                overflow: hidden;
            }

            [data-theme="dark"] .listing-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .listing-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                padding: 30px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            [data-theme="dark"] .listing-header {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .listing-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 16px;
            }

            .listing-type {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                border-radius: 12px;
                font-size: 0.85rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .listing-type.offer {
                background: rgba(16, 185, 129, 0.15);
                color: #059669;
            }

            .listing-type.request {
                background: rgba(59, 130, 246, 0.15);
                color: #2563eb;
            }

            [data-theme="dark"] .listing-type.offer {
                background: rgba(16, 185, 129, 0.25);
                color: #34d399;
            }

            [data-theme="dark"] .listing-type.request {
                background: rgba(59, 130, 246, 0.25);
                color: #60a5fa;
            }

            .listing-tenant {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 12px;
                font-size: 0.85rem;
                font-weight: 600;
                color: #8b5cf6;
            }

            [data-theme="dark"] .listing-tenant {
                background: rgba(139, 92, 246, 0.2);
                color: #a78bfa;
            }

            .listing-title {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main);
                margin: 0 0 8px 0;
            }

            .listing-category {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
            }

            .listing-body {
                padding: 30px;
            }

            .section-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 12px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-title i {
                color: #8b5cf6;
            }

            .listing-description {
                color: var(--htb-text-main);
                font-size: 1rem;
                line-height: 1.8;
                margin-bottom: 30px;
            }

            /* Owner Section */
            .owner-section {
                padding: 24px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 16px;
                margin-bottom: 24px;
            }

            .owner-info {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .owner-avatar {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid rgba(139, 92, 246, 0.3);
            }

            .owner-details h4 {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 4px 0;
            }

            .owner-details .owner-tenant {
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            /* Actions */
            .listing-actions {
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }

            .action-btn {
                flex: 1;
                min-width: 200px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 24px;
                border-radius: 14px;
                font-weight: 700;
                font-size: 0.95rem;
                text-decoration: none;
                transition: all 0.3s ease;
                cursor: pointer;
                border: none;
            }

            .action-btn-primary {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
            }

            .action-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.45);
            }

            .action-btn-disabled {
                background: rgba(100, 100, 100, 0.1);
                color: var(--htb-text-muted);
                cursor: not-allowed;
                opacity: 0.6;
            }

            .action-btn-disabled:hover {
                transform: none;
            }

            /* Privacy Notice */
            .privacy-notice {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-top: 24px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 12px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .privacy-notice i {
                color: #8b5cf6;
                margin-top: 2px;
            }

            /* Touch Targets */
            .action-btn {
                min-height: 44px;
            }

            /* Focus Visible */
            .action-btn:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            @media (max-width: 640px) {
                #fed-listing-wrapper {
                    padding: 15px;
                }

                .listing-header,
                .listing-body {
                    padding: 20px;
                }

                .listing-title {
                    font-size: 1.4rem;
                }

                .listing-actions {
                    flex-direction: column;
                }

                .action-btn {
                    width: 100%;
                }
            }
        </style>

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/listings" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Federated Listings
        </a>

        <!-- Listing Card -->
        <div class="listing-card">
            <div class="listing-header">
                <div class="listing-badges">
                    <span class="listing-type <?= htmlspecialchars($type) ?>">
                        <i class="fa-solid <?= $type === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding' ?>"></i>
                        <?= ucfirst($type) ?>
                    </span>
                    <span class="listing-tenant">
                        <i class="fa-solid fa-building"></i>
                        <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner Timebank') ?>
                    </span>
                </div>

                <h1 class="listing-title"><?= htmlspecialchars($listing['title'] ?? 'Untitled') ?></h1>

                <?php if (!empty($listing['category_name'])): ?>
                    <span class="listing-category">
                        <i class="fa-solid fa-tag" style="margin-right: 6px;"></i>
                        <?= htmlspecialchars($listing['category_name']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="listing-body">
                <?php if (!empty($listing['description'])): ?>
                    <h3 class="section-title">
                        <i class="fa-solid fa-align-left"></i>
                        Description
                    </h3>
                    <div class="listing-description">
                        <?= nl2br(htmlspecialchars($listing['description'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Owner Section -->
                <div class="owner-section">
                    <h3 class="section-title" style="margin-bottom: 16px;">
                        <i class="fa-solid fa-user"></i>
                        Posted By
                    </h3>
                    <div class="owner-info">
                        <img src="<?= htmlspecialchars($ownerAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt="<?= htmlspecialchars($ownerName) ?>"
                             class="owner-avatar">
                        <div class="owner-details">
                            <h4><?= htmlspecialchars($ownerName) ?></h4>
                            <span class="owner-tenant">
                                <i class="fa-solid fa-building" style="margin-right: 6px;"></i>
                                <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner Timebank') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="listing-actions">
                    <?php if ($canMessage): ?>
                        <a href="<?= $basePath ?>/federation/messages/<?= $listing['owner_id'] ?>?tenant=<?= $listing['owner_tenant_id'] ?>"
                           class="action-btn action-btn-primary">
                            <i class="fa-solid fa-envelope"></i>
                            Contact <?= htmlspecialchars(explode(' ', $ownerName)[0]) ?>
                        </a>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled" title="Messaging not available">
                            <i class="fa-solid fa-envelope"></i>
                            Messaging Unavailable
                        </span>
                    <?php endif; ?>

                    <a href="<?= $basePath ?>/federation/members/<?= $listing['owner_id'] ?>"
                       class="action-btn" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 2px solid rgba(139, 92, 246, 0.3);">
                        <i class="fa-solid fa-user"></i>
                        View Profile
                    </a>
                </div>

                <!-- Privacy Notice -->
                <div class="privacy-notice">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Federated Listing</strong><br>
                        This listing is from <strong><?= htmlspecialchars($listing['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        Contact the poster to discuss terms and arrange an exchange.
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
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
