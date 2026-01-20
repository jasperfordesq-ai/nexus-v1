<?php
// Federation Listings Directory - CivicOne WCAG 2.1 AA
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
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-listings-wrapper">

        <!-- Back to Listings -->
        <a href="<?= $basePath ?>/listings" class="back-link" aria-label="Return to local listings">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Local Listings
        </a>

        <!-- Welcome Hero -->
        <div class="nexus-welcome-hero" role="banner">
            <div class="federation-badge">
                <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
                <span>Federation Network</span>
            </div>
            <h1 class="nexus-welcome-title">Federated Listings</h1>
            <p class="nexus-welcome-subtitle">
                Discover offers and requests from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
            </p>
        </div>

        <!-- Search & Filters -->
        <div class="glass-search-card" role="search" aria-label="Search federated listings">
            <div class="fed-search-layout">
                <div class="fed-search-header">
                    <h2 class="fed-search-title">
                        <i class="fa-solid fa-search" aria-hidden="true"></i>
                        Search Listings
                    </h2>
                    <span id="listings-count" class="fed-results-count" role="status" aria-live="polite">
                        <?= count($listings) ?> listing<?= count($listings) !== 1 ? 's' : '' ?> found
                    </span>
                </div>
            </div>

            <div class="search-box">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <label for="listing-search" class="visually-hidden">Search titles, descriptions</label>
                <input type="text"
                       id="listing-search"
                       placeholder="Search titles, descriptions..."
                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                       class="glass-search-input"
                       aria-describedby="listings-count">
                <div id="search-spinner" class="search-spinner" style="display: none;" role="status" aria-label="Searching">
                    <span class="visually-hidden">Searching...</span>
                </div>
            </div>

            <div class="filter-row" role="group" aria-label="Filter options">
                <div class="filter-group">
                    <label for="tenant-filter" class="filter-label">Partner Timebank</label>
                    <select id="tenant-filter" class="glass-select">
                        <option value="">All Partners</option>
                        <?php foreach ($partnerTenants as $tenant): ?>
                            <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tenant['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="type-filter" class="filter-label">Type</label>
                    <select id="type-filter" class="glass-select">
                        <option value="">All Types</option>
                        <option value="offer" <?= ($filters['type'] ?? '') === 'offer' ? 'selected' : '' ?>>Offers</option>
                        <option value="request" <?= ($filters['type'] ?? '') === 'request' ? 'selected' : '' ?>>Requests</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="category-filter" class="filter-label">Category</label>
                    <select id="category-filter" class="glass-select">
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
        <div id="listings-grid" class="listings-grid" role="list" aria-label="Federated listings">
            <?php if (!empty($listings)): ?>
                <?php foreach ($listings as $listing): ?>
                    <?php
                    $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($listing['owner_name'] ?? 'User') . '&background=8b5cf6&color=fff&size=100';
                    $avatar = !empty($listing['owner_avatar']) ? $listing['owner_avatar'] : $fallbackAvatar;
                    $listingUrl = $basePath . '/federation/listings/' . $listing['id'];
                    $listingType = $listing['type'] ?? 'offer';
                    ?>
                    <a href="<?= $listingUrl ?>" class="listing-card" role="listitem" aria-label="<?= ucfirst($listingType) ?>: <?= htmlspecialchars($listing['title'] ?? 'Untitled') ?> by <?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?>">
                        <div class="listing-card-body">
                            <span class="listing-type <?= htmlspecialchars($listingType) ?>">
                                <i class="fa-solid <?= $listingType === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding' ?>" aria-hidden="true"></i>
                                <?= ucfirst($listingType) ?>
                            </span>

                            <div class="listing-tenant">
                                <i class="fa-solid fa-building" aria-hidden="true"></i>
                                <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner') ?>
                            </div>

                            <h3 class="listing-title"><?= htmlspecialchars($listing['title'] ?? 'Untitled') ?></h3>

                            <?php if (!empty($listing['description'])): ?>
                                <p class="listing-description"><?= htmlspecialchars($listing['description']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($listing['category_name'])): ?>
                                <span class="listing-category">
                                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                                    <?= htmlspecialchars($listing['category_name']) ?>
                                </span>
                            <?php endif; ?>

                            <div class="listing-owner">
                                <img src="<?= htmlspecialchars($avatar) ?>"
                                     onerror="this.src='<?= $fallbackAvatar ?>'"
                                     alt=""
                                     class="owner-avatar"
                                     loading="lazy">
                                <span class="owner-name"><?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" role="status">
                    <div class="empty-state-icon" aria-hidden="true">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                    <h3 class="empty-state-title">No federated listings found</h3>
                    <p class="empty-state-text">
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
        <div id="infinite-scroll-trigger" class="infinite-scroll-trigger" aria-hidden="true"></div>
        <div id="load-more-spinner" class="load-more-spinner" style="display: none;" role="status">
            <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
            <span class="visually-hidden">Loading more listings...</span>
        </div>

    </div>
</div>

<script src="/assets/js/federation-listings.js?v=<?= time() ?>"></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
