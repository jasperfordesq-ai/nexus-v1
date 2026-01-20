<?php
/**
 * Federated Listings Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federated Listings";
$pageSubtitle = "Browse offers and requests from partner timebanks";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Listings - Partner Timebank Services');
Nexus\Core\SEO::setDescription('Browse offers and requests from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$listings = $listings ?? [];
$partnerTenants = $partnerTenants ?? [];
$categories = $categories ?? [];
$filters = $filters ?? [];
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/listings" class="civic-fed-back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Local Listings
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <h1>Federated Listings</h1>
        <span class="civic-fed-badge">
            <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
            Federation Network
        </span>
    </header>

    <p class="civic-fed-intro">
        Discover offers and requests from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
    </p>

    <!-- Search & Filters -->
    <div class="civic-fed-search-card" role="search" aria-label="Search federated listings">
        <div class="civic-fed-search-header">
            <h2 class="civic-fed-search-title">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                Search Listings
            </h2>
            <span id="listings-count" class="civic-fed-results-count" role="status" aria-live="polite">
                <?= count($listings) ?> listing<?= count($listings) !== 1 ? 's' : '' ?> found
            </span>
        </div>

        <div class="civic-fed-search-row">
            <div class="civic-fed-search-box">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <label for="listing-search" class="visually-hidden">Search titles, descriptions</label>
                <input type="text"
                       id="listing-search"
                       class="civic-fed-input"
                       placeholder="Search titles, descriptions..."
                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                       aria-describedby="listings-count">
                <div id="search-spinner" class="civic-fed-spinner" style="display: none;" role="status">
                    <span class="visually-hidden">Searching...</span>
                </div>
            </div>
        </div>

        <div class="civic-fed-filter-row" role="group" aria-label="Filter options">
            <div class="civic-fed-filter-group">
                <label for="tenant-filter" class="civic-fed-filter-label">Partner Timebank</label>
                <select id="tenant-filter" class="civic-fed-select">
                    <option value="">All Partners</option>
                    <?php foreach ($partnerTenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="civic-fed-filter-group">
                <label for="type-filter" class="civic-fed-filter-label">Type</label>
                <select id="type-filter" class="civic-fed-select">
                    <option value="">All Types</option>
                    <option value="offer" <?= ($filters['type'] ?? '') === 'offer' ? 'selected' : '' ?>>Offers</option>
                    <option value="request" <?= ($filters['type'] ?? '') === 'request' ? 'selected' : '' ?>>Requests</option>
                </select>
            </div>
            <div class="civic-fed-filter-group">
                <label for="category-filter" class="civic-fed-filter-label">Category</label>
                <select id="category-filter" class="civic-fed-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($filters['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Listings Grid -->
    <div id="listings-grid" class="civic-fed-listings-grid" role="list" aria-label="Federated listings">
        <?php if (!empty($listings)): ?>
            <?php foreach ($listings as $listing): ?>
                <?php
                $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($listing['owner_name'] ?? 'User') . '&background=00796B&color=fff&size=100';
                $avatar = !empty($listing['owner_avatar']) ? $listing['owner_avatar'] : $fallbackAvatar;
                $listingUrl = $basePath . '/federation/listings/' . $listing['id'];
                $listingType = $listing['type'] ?? 'offer';
                ?>
                <a href="<?= $listingUrl ?>" class="civic-fed-listing-card" role="listitem">
                    <span class="civic-fed-listing-type civic-fed-listing-type--<?= htmlspecialchars($listingType) ?>">
                        <i class="fa-solid <?= $listingType === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding' ?>" aria-hidden="true"></i>
                        <?= ucfirst($listingType) ?>
                    </span>

                    <div class="civic-fed-listing-source">
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                        <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner') ?>
                    </div>

                    <h3 class="civic-fed-listing-title"><?= htmlspecialchars($listing['title'] ?? 'Untitled') ?></h3>

                    <?php if (!empty($listing['description'])): ?>
                        <p class="civic-fed-listing-desc"><?= htmlspecialchars($listing['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($listing['category_name'])): ?>
                        <span class="civic-fed-tag">
                            <i class="fa-solid fa-tag" aria-hidden="true"></i>
                            <?= htmlspecialchars($listing['category_name']) ?>
                        </span>
                    <?php endif; ?>

                    <div class="civic-fed-listing-owner">
                        <img src="<?= htmlspecialchars($avatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt=""
                             class="civic-fed-listing-avatar"
                             loading="lazy">
                        <span><?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="civic-fed-empty" role="status">
                <div class="civic-fed-empty-icon">
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                </div>
                <h3>No Federated Listings Found</h3>
                <p>
                    <?php if (empty($partnerTenants)): ?>
                        Your timebank doesn't have any active partnerships yet.
                    <?php else: ?>
                        Try adjusting your search filters.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Load More -->
    <div id="infinite-scroll-trigger" aria-hidden="true"></div>
    <div id="load-more-spinner" class="civic-fed-loading" style="display: none;" role="status">
        <div class="civic-fed-spinner" aria-hidden="true"></div>
        <span class="visually-hidden">Loading more listings...</span>
    </div>
</div>

<script src="/assets/js/federation-listings.js?v=<?= time() ?>"></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
