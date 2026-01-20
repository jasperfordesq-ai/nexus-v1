<?php
/**
 * CivicOne Dashboard - My Listings Page
 * WCAG 2.1 AA Compliant
 * Template: Account Area Template (Template G)
 */

$hTitle = "My Listings";
$hSubtitle = "Manage your offers and requests";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();

$offerCount = 0;
$reqCount = 0;
foreach ($my_listings as $ml) {
    if ($ml['type'] === 'offer') $offerCount++;
    else $reqCount++;
}
?>

<div class="civic-dashboard civicone-account-area">

    <!-- Account Area Secondary Navigation -->
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- LISTINGS CONTENT -->
    <section class="civic-dash-card" aria-labelledby="my-listings-heading">
        <div class="civic-dash-card-header">
            <h2 id="my-listings-heading" class="civic-dash-card-title">
                <i class="fa-solid fa-list" aria-hidden="true"></i>
                My Listings
            </h2>
            <div class="civic-listing-stats">
                <span class="civic-stat-badge civic-stat-offers"><?= $offerCount ?> Offers</span>
                <span class="civic-stat-badge civic-stat-requests"><?= $reqCount ?> Requests</span>
            </div>
        </div>

        <div class="civic-listings-actions">
            <a href="<?= $basePath ?>/compose?type=listing" class="civic-button" role="button">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Post New Listing
            </a>
        </div>

        <?php if (empty($my_listings)): ?>
            <div class="civic-empty-state civic-empty-large">
                <div class="civic-empty-icon"><i class="fa-solid fa-seedling" aria-hidden="true"></i></div>
                <h3>No listings yet</h3>
                <p class="civic-empty-text">You haven't posted any offers or requests yet.</p>
            </div>
        <?php else: ?>
            <div class="civic-listings-grid">
                <?php foreach ($my_listings as $listing): ?>
                    <article class="civic-listing-card" id="listing-<?= $listing['id'] ?>">
                        <?php if (!empty($listing['image_url'])): ?>
                            <div class="civic-listing-image" style="background-image: url('<?= htmlspecialchars($listing['image_url']) ?>')"></div>
                        <?php else: ?>
                            <div class="civic-listing-image civic-listing-placeholder <?= strtolower($listing['type']) ?>">
                                <i class="fa-solid fa-<?= $listing['type'] === 'offer' ? 'hand-holding-heart' : 'hand-holding' ?>" aria-hidden="true"></i>
                            </div>
                        <?php endif; ?>
                        <div class="civic-listing-card-body">
                            <div class="civic-listing-meta">
                                <span class="civic-listing-type <?= strtolower($listing['type']) ?>">
                                    <?= strtoupper($listing['type']) ?>
                                </span>
                                <span class="civic-listing-date"><?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
                            </div>
                            <h3 class="civic-listing-title">
                                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>"><?= htmlspecialchars($listing['title']) ?></a>
                            </h3>
                            <div class="civic-listing-card-actions">
                                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="civic-button civic-button--secondary" role="button">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i> View
                                </a>
                                <button type="button" onclick="deleteListing(<?= $listing['id'] ?>)" class="civic-button civic-button--warning">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<script src="/assets/js/civicone-dashboard.js"></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
