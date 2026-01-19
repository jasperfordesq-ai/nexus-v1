<?php
// Federation Listings Directory - Glassmorphism 2025
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
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-listings-wrapper">

        <!-- Back to Listings -->
        <a href="<?= $basePath ?>/listings" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Local Listings
        </a>

        <!-- Welcome Hero -->
        <div class="nexus-welcome-hero">
            <div class="federation-badge">
                <i class="fa-solid fa-network-wired"></i>
                <span>Federation Network</span>
            </div>
            <h1 class="nexus-welcome-title">Federated Listings</h1>
            <p class="nexus-welcome-subtitle">
                Discover offers and requests from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
            </p>
        </div>

        <!-- Search & Filters -->
        <div class="glass-search-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px;">
                <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--htb-text-main); margin: 0;">
                    <i class="fa-solid fa-search" style="color: #8b5cf6; margin-right: 8px;"></i>
                    Search Listings
                </h2>
                <span id="listings-count" style="font-size: 0.9rem; color: var(--htb-text-muted);">
                    <?= count($listings) ?> listing<?= count($listings) !== 1 ? 's' : '' ?> found
                </span>
            </div>

            <div style="position: relative;">
                <input type="text"
                       id="listing-search"
                       placeholder="Search titles, descriptions..."
                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                       class="glass-search-input">
                <i class="fa-solid fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af;"></i>
                <div id="search-spinner" class="spinner" style="display: none; position: absolute; right: 16px; top: 50%; transform: translateY(-50%);"></div>
            </div>

            <div class="filter-row">
                <div>
                    <label class="filter-label">Partner Timebank</label>
                    <select id="tenant-filter" class="glass-select">
                        <option value="">All Partners</option>
                        <?php foreach ($partnerTenants as $tenant): ?>
                            <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tenant['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="filter-label">Type</label>
                    <select id="type-filter" class="glass-select">
                        <option value="">All Types</option>
                        <option value="offer" <?= ($filters['type'] ?? '') === 'offer' ? 'selected' : '' ?>>Offers</option>
                        <option value="request" <?= ($filters['type'] ?? '') === 'request' ? 'selected' : '' ?>>Requests</option>
                    </select>
                </div>
                <div>
                    <label class="filter-label">Category</label>
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
        <div id="listings-grid" class="listings-grid">
            <?php if (!empty($listings)): ?>
                <?php foreach ($listings as $listing): ?>
                    <?php
                    $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($listing['owner_name'] ?? 'User') . '&background=8b5cf6&color=fff&size=100';
                    $avatar = !empty($listing['owner_avatar']) ? $listing['owner_avatar'] : $fallbackAvatar;
                    $listingUrl = $basePath . '/federation/listings/' . $listing['id'];
                    ?>
                    <a href="<?= $listingUrl ?>" class="listing-card">
                        <div class="listing-card-body">
                            <span class="listing-type <?= htmlspecialchars($listing['type'] ?? 'offer') ?>">
                                <i class="fa-solid <?= ($listing['type'] ?? 'offer') === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding' ?>"></i>
                                <?= ucfirst($listing['type'] ?? 'Offer') ?>
                            </span>

                            <div class="listing-tenant">
                                <i class="fa-solid fa-building"></i>
                                <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner') ?>
                            </div>

                            <h3 class="listing-title"><?= htmlspecialchars($listing['title'] ?? 'Untitled') ?></h3>

                            <?php if (!empty($listing['description'])): ?>
                                <p class="listing-description"><?= htmlspecialchars($listing['description']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($listing['category_name'])): ?>
                                <span class="listing-category">
                                    <i class="fa-solid fa-tag" style="margin-right: 4px;"></i>
                                    <?= htmlspecialchars($listing['category_name']) ?>
                                </span>
                            <?php endif; ?>

                            <div class="listing-owner">
                                <img src="<?= htmlspecialchars($avatar) ?>"
                                     onerror="this.src='<?= $fallbackAvatar ?>'"
                                     alt="<?= htmlspecialchars($listing['owner_name'] ?? 'User') ?>"
                                     class="owner-avatar">
                                <span class="owner-name"><?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 10px 0;">
                        No federated listings found
                    </h3>
                    <p style="color: var(--htb-text-muted); margin: 0;">
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
        <div id="infinite-scroll-trigger" style="height: 20px; margin-top: 20px;"></div>
        <div id="load-more-spinner" style="display: none; justify-content: center; margin: 30px 0;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; color: #8b5cf6;"></i>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('listing-search');
    const tenantFilter = document.getElementById('tenant-filter');
    const typeFilter = document.getElementById('type-filter');
    const categoryFilter = document.getElementById('category-filter');
    const grid = document.getElementById('listings-grid');
    const countLabel = document.getElementById('listings-count');
    const spinner = document.getElementById('search-spinner');
    const loadMoreSpinner = document.getElementById('load-more-spinner');

    let debounceTimer;
    let currentOffset = <?= count($listings) ?>;
    let isLoading = false;
    let hasMore = <?= count($listings) >= 30 ? 'true' : 'false' ?>;

    // Search & filter handlers
    searchInput.addEventListener('keyup', function() {
        clearTimeout(debounceTimer);
        spinner.style.display = 'block';
        debounceTimer = setTimeout(() => {
            currentOffset = 0;
            hasMore = true;
            fetchListings();
        }, 300);
    });

    [tenantFilter, typeFilter, categoryFilter].forEach(el => {
        el.addEventListener('change', function() {
            currentOffset = 0;
            hasMore = true;
            fetchListings();
        });
    });

    function fetchListings(append = false) {
        const params = new URLSearchParams({
            q: searchInput.value,
            tenant: tenantFilter.value,
            type: typeFilter.value,
            category: categoryFilter.value,
            offset: append ? currentOffset : 0,
            limit: 30
        });

        if (!append) spinner.style.display = 'block';

        fetch('<?= $basePath ?>/federation/listings/api?' + params.toString())
            .then(res => res.json())
            .then(data => {
                spinner.style.display = 'none';
                loadMoreSpinner.style.display = 'none';
                isLoading = false;

                if (data.success) {
                    if (append) {
                        appendListings(data.listings);
                        currentOffset += data.listings.length;
                    } else {
                        renderGrid(data.listings);
                        currentOffset = data.listings.length;
                    }
                    hasMore = data.hasMore;
                    countLabel.textContent = `${append ? currentOffset : data.listings.length} listing${data.listings.length !== 1 ? 's' : ''} found`;
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                spinner.style.display = 'none';
                loadMoreSpinner.style.display = 'none';
                isLoading = false;
            });
    }

    function renderGrid(listings) {
        if (listings.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-search"></i></div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 10px 0;">No listings found</h3>
                    <p style="color: var(--htb-text-muted);">Try adjusting your search or filters.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = '';
        listings.forEach(l => grid.appendChild(createListingCard(l)));
    }

    function appendListings(listings) {
        listings.forEach(l => grid.appendChild(createListingCard(l)));
    }

    function createListingCard(listing) {
        const basePath = "<?= $basePath ?>";
        const fallbackAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(listing.owner_name || 'User')}&background=8b5cf6&color=fff&size=100`;
        const avatar = listing.owner_avatar || fallbackAvatar;
        const listingUrl = `${basePath}/federation/listings/${listing.id}`;
        const type = listing.type || 'offer';
        const typeIcon = type === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding';

        const card = document.createElement('a');
        card.href = listingUrl;
        card.className = 'listing-card';
        card.innerHTML = `
            <div class="listing-card-body">
                <span class="listing-type ${type}">
                    <i class="fa-solid ${typeIcon}"></i>
                    ${type.charAt(0).toUpperCase() + type.slice(1)}
                </span>
                <div class="listing-tenant">
                    <i class="fa-solid fa-building"></i>
                    ${escapeHtml(listing.tenant_name || 'Partner')}
                </div>
                <h3 class="listing-title">${escapeHtml(listing.title || 'Untitled')}</h3>
                ${listing.description ? `<p class="listing-description">${escapeHtml(listing.description)}</p>` : ''}
                ${listing.category_name ? `<span class="listing-category"><i class="fa-solid fa-tag" style="margin-right: 4px;"></i>${escapeHtml(listing.category_name)}</span>` : ''}
                <div class="listing-owner">
                    <img src="${escapeHtml(avatar)}" onerror="this.src='${fallbackAvatar}'" class="owner-avatar">
                    <span class="owner-name">${escapeHtml(listing.owner_name || 'Unknown')}</span>
                </div>
            </div>
        `;
        return card;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Infinite scroll
    const infiniteScrollTrigger = document.getElementById('infinite-scroll-trigger');
    if (infiniteScrollTrigger) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    isLoading = true;
                    loadMoreSpinner.style.display = 'flex';
                    fetchListings(true);
                }
            });
        }, { rootMargin: '100px', threshold: 0.1 });
        observer.observe(infiniteScrollTrigger);
    }
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
