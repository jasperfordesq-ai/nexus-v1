<?php
// Federation Listing Detail - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Listing";
$hideHero = true;

Nexus\Core\SEO::setTitle(($listing['title'] ?? 'Listing') . ' - Federated');
Nexus\Core\SEO::setDescription('Listing details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$listing = $listing ?? [];
$canMessage = $canMessage ?? false;

$ownerName = $listing['owner_name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($ownerName) . '&background=00796B&color=fff&size=200';
$ownerAvatar = !empty($listing['owner_avatar']) ? $listing['owner_avatar'] : $fallbackAvatar;
$type = $listing['type'] ?? 'offer';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-listing-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/listings" class="back-link" aria-label="Return to listings">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Federated Listings
        </a>

        <!-- Listing Card -->
        <article class="listing-card" aria-labelledby="listing-title">
            <header class="listing-header">
                <div class="listing-badges" role="group" aria-label="Listing details">
                    <span class="listing-type <?= htmlspecialchars($type) ?>" role="status">
                        <i class="fa-solid <?= $type === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding' ?>" aria-hidden="true"></i>
                        <?= ucfirst($type) ?>
                    </span>
                    <span class="listing-tenant" role="status">
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                        <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner Timebank') ?>
                    </span>
                </div>

                <h1 id="listing-title" class="listing-title"><?= htmlspecialchars($listing['title'] ?? 'Untitled') ?></h1>

                <?php if (!empty($listing['category_name'])): ?>
                    <span class="listing-category">
                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                        <?= htmlspecialchars($listing['category_name']) ?>
                    </span>
                <?php endif; ?>
            </header>

            <div class="listing-body">
                <?php if (!empty($listing['description'])): ?>
                    <section aria-labelledby="description-heading">
                        <h3 id="description-heading" class="section-title">
                            <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                            Description
                        </h3>
                        <div class="listing-description">
                            <?= nl2br(htmlspecialchars($listing['description'])) ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Owner Section -->
                <section class="owner-section" aria-labelledby="owner-heading">
                    <h3 id="owner-heading" class="section-title">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        Posted By
                    </h3>
                    <div class="owner-info">
                        <img src="<?= htmlspecialchars($ownerAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt=""
                             class="owner-avatar"
                             loading="lazy">
                        <div class="owner-details">
                            <h4><?= htmlspecialchars($ownerName) ?></h4>
                            <span class="owner-tenant">
                                <i class="fa-solid fa-building" aria-hidden="true"></i>
                                <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner Timebank') ?>
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Actions -->
                <div class="listing-actions" role="group" aria-label="Listing actions">
                    <?php if ($canMessage): ?>
                        <a href="<?= $basePath ?>/federation/messages/<?= $listing['owner_id'] ?>?tenant=<?= $listing['owner_tenant_id'] ?>"
                           class="action-btn action-btn-primary"
                           aria-label="Contact <?= htmlspecialchars($ownerName) ?>">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            Contact <?= htmlspecialchars(explode(' ', $ownerName)[0]) ?>
                        </a>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled" aria-disabled="true">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            Messaging Unavailable
                        </span>
                    <?php endif; ?>

                    <a href="<?= $basePath ?>/federation/members/<?= $listing['owner_id'] ?>"
                       class="action-btn action-btn-secondary"
                       aria-label="View <?= htmlspecialchars($ownerName) ?>'s profile">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        View Profile
                    </a>
                </div>

                <!-- Privacy Notice -->
                <aside class="privacy-notice" role="note">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <div>
                        <strong>Federated Listing</strong><br>
                        This listing is from <strong><?= htmlspecialchars($listing['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        Contact the poster to discuss terms and arrange an exchange.
                    </div>
                </aside>
            </div>
        </article>

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

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
