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
$isExternalListing = $isExternalListing ?? (!empty($listing['is_external']));
$externalPartnerId = $listing['external_partner_id'] ?? null;
$externalTenantId = $listing['external_tenant_id'] ?? 1;

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
                    <span class="listing-tenant<?= $isExternalListing ? ' external' : '' ?>">
                        <i class="fa-solid <?= $isExternalListing ? 'fa-globe' : 'fa-building' ?>"></i>
                        <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner Timebank') ?>
                        <?php if ($isExternalListing): ?>
                        <span class="external-tag">External</span>
                        <?php endif; ?>
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
                        <?php if ($isExternalListing && $externalPartnerId): ?>
                            <!-- External listing - use federated messaging with external partner -->
                            <a href="<?= $basePath ?>/federation/messages/compose?external_partner=<?= $externalPartnerId ?>&member_id=<?= $listing['owner_id'] ?>&member_name=<?= urlencode($ownerName) ?>&external_tenant=<?= $externalTenantId ?>"
                               class="action-btn action-btn-primary">
                                <i class="fa-solid fa-envelope"></i>
                                Contact <?= htmlspecialchars(explode(' ', $ownerName)[0]) ?>
                            </a>
                        <?php else: ?>
                            <!-- Internal federated listing -->
                            <a href="<?= $basePath ?>/federation/messages/compose?to=<?= $listing['owner_id'] ?>&tenant=<?= $listing['owner_tenant_id'] ?>"
                               class="action-btn action-btn-primary">
                                <i class="fa-solid fa-envelope"></i>
                                Contact <?= htmlspecialchars(explode(' ', $ownerName)[0]) ?>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled" title="Messaging not available">
                            <i class="fa-solid fa-envelope"></i>
                            Messaging Unavailable
                        </span>
                    <?php endif; ?>

                    <?php if ($isExternalListing && $externalPartnerId): ?>
                        <!-- External listing - link to external member profile -->
                        <a href="<?= $basePath ?>/federation/members/external/<?= $externalPartnerId ?>/<?= $listing['owner_id'] ?>"
                           class="action-btn" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 2px solid rgba(139, 92, 246, 0.3);">
                            <i class="fa-solid fa-user"></i>
                            View Profile
                        </a>
                    <?php else: ?>
                        <a href="<?= $basePath ?>/federation/members/<?= $listing['owner_id'] ?>"
                           class="action-btn" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 2px solid rgba(139, 92, 246, 0.3);">
                            <i class="fa-solid fa-user"></i>
                            View Profile
                        </a>
                    <?php endif; ?>
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
