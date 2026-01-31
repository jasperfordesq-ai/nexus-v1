<?php
// Phoenix Listings View
$pageTitle = "Offers & Requests";
$pageSubtitle = "Browse community offers and requests";
$hideHero = true; // Using custom hero instead

Nexus\Core\SEO::setTitle('Offers & Requests Marketplace - Timebank Ireland');
Nexus\Core\SEO::setDescription('Browse the latest offers and requests from the Timebank community. Exchange skills and support using time credits.');

require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Pull to Refresh removed -->

<!-- Main content wrapper (main tag opened in header.php) -->
<div class="htb-container-full">
    <!-- ✅ GLASSMORPHISM FILE LOADED: views/modern/listings/index.php - Last Modified: <?php echo date('Y-m-d H:i:s', filemtime(__FILE__)); ?> -->
    <div id="listings-index-glass-wrapper">

        <!-- Create Listing Prompt (Facebook-style) -->
        <?php
        $isLoggedIn = !empty($_SESSION['user_id']);
        $userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
        $userName = $_SESSION['user_name'] ?? 'User';
        $firstName = explode(' ', $userName)[0];
        ?>

        <!-- Smart Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">
                <i class="fa-solid fa-handshake"></i> Offers &amp; Requests
            </h1>
            <?php if (\Nexus\Services\ListingRankingService::isEnabled()): ?>
                <div style="display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15)); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 20px; padding: 4px 12px; font-size: 0.75rem; color: #6366f1; margin-bottom: 8px;">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>MatchRank Active</span>
                </div>
            <?php endif; ?>
            <p class="nexus-welcome-subtitle">Browse and share skills, items, and services within your community. Every exchange strengthens our network.</p>
        </div>

        <div class="listings-create-post">
            <div style="padding: 14px 16px;">
                <?php if ($isLoggedIn): ?>
                    <!-- Logged In: Simple prompt that opens /compose with listing tab -->
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing" class="compose-prompt-link">
                        <div class="compose-prompt">
                            <div class="composer-avatar-ring">
                                <?= webp_avatar($userAvatar ?? null, $userName ?? 'User', 40) ?>
                            </div>
                            <div class="compose-prompt-input">
                                What can you offer or need, <?= htmlspecialchars($firstName) ?>?
                            </div>
                        </div>
                    </a>

                    <!-- Quick action buttons -->
                    <div class="compose-quick-actions">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing&listing_type=offer" class="compose-quick-btn">
                            <i class="fa-solid fa-gift" style="color: #10b981;"></i>
                            <span>Offer</span>
                        </a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing&listing_type=request" class="compose-quick-btn">
                            <i class="fa-solid fa-hand" style="color: #f97316;"></i>
                            <span>Request</span>
                        </a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/dashboard?tab=listings" class="compose-quick-btn">
                            <i class="fa-solid fa-list" style="color: #6366f1;"></i>
                            <span>My Listings</span>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Logged Out: Join CTA -->
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="compose-prompt-link">
                        <div class="compose-prompt">
                            <div class="composer-avatar-ring guest">
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-hand-holding-heart" style="color: white; font-size: 18px;"></i>
                                </div>
                            </div>
                            <div class="compose-prompt-input">
                                Join to share offers & requests...
                            </div>
                        </div>
                    </a>

                    <!-- Auth buttons -->
                    <div class="compose-quick-actions">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="compose-quick-btn">
                            <i class="fa-solid fa-right-to-bracket" style="color: #3b82f6;"></i>
                            <span>Log In</span>
                        </a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="compose-quick-btn highlight">
                            <i class="fa-solid fa-user-plus" style="color: #fff;"></i>
                            <span>Sign Up</span>
                        </a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings?type=offer" class="compose-quick-btn">
                            <i class="fa-solid fa-gift" style="color: #10b981;"></i>
                            <span>Offers</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modern Creative Search Bar -->
        <div class="glass-search-card">
            <div class="search-card-inner">
                <!-- Search Input Row -->
                <div class="search-row">
                    <div class="search-input-wrapper">
                        <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
                        <label for="listing-search" class="visually-hidden">Search listings</label>
                        <input type="text" id="listing-search" placeholder="Search offers, requests, skills..." value="<?= htmlspecialchars($query ?? '') ?>" class="glass-search-input" aria-label="Search offers, requests, skills">
                        <div id="search-spinner" class="spinner" style="display: none;"></div>
                    </div>
                    <span id="listings-count" class="listings-count-badge">
                        <?= count($listings) ?>
                    </span>
                </div>

                <!-- Type Filter Pills (horizontally scrollable) -->
                <div class="filter-pills-row">
                    <?php
                    $currentType = $_GET['type'] ?? null;
                    $currentCat = $_GET['cat'] ?? null;
                    ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="filter-pill <?= (!$currentType && !$currentCat) ? 'active' : '' ?>">
                        <i class="fa-solid fa-list"></i> All
                    </a>
                    <a href="?type=offer" class="filter-pill offer <?= $currentType === 'offer' ? 'active' : '' ?>">
                        <i class="fa-solid fa-gift"></i> Offers
                    </a>
                    <a href="?type=request" class="filter-pill request <?= $currentType === 'request' ? 'active' : '' ?>">
                        <i class="fa-solid fa-hand"></i> Requests
                    </a>
                    <?php if (!empty($categoriesWithCounts)): ?>
                        <?php foreach ($categoriesWithCounts as $index => $cat):
                            $isActive = $currentCat == $cat['id'];
                        ?>
                            <a href="?cat=<?= (int)$cat['id'] ?><?= $currentType ? '&type=' . urlencode($currentType) : '' ?>"
                                class="filter-pill category <?= $isActive ? 'active' : '' ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                                <span class="pill-count"><?= (int)$cat['listing_count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($currentCat): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings<?= $currentType ? '?type=' . urlencode($currentType) : '' ?>" class="filter-pill clear">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Collapsible Location Search -->
                <?php
                $currentUserCoords = null;
                $currentUserLocation = null;
                if (isset($_SESSION['user_id'])) {
                    $currentUserCoords = \Nexus\Models\User::getCoordinates($_SESSION['user_id']);
                    $currentUserData = \Nexus\Models\User::findById($_SESSION['user_id']);
                    $currentUserLocation = $currentUserData['location'] ?? null;
                }
                $hasLocation = $currentUserCoords && !empty($currentUserCoords['latitude']) && !empty($currentUserCoords['longitude']);
                ?>
                <div class="nearby-section">
                    <button type="button" class="nearby-toggle" onclick="toggleNearbySection()">
                        <i class="fa-solid fa-location-crosshairs"></i>
                        <span>Search Nearby</span>
                        <i class="fa-solid fa-chevron-down toggle-icon"></i>
                    </button>

                    <div id="nearby-content" class="nearby-content collapsed">
                        <?php if ($hasLocation): ?>
                            <div class="nearby-controls">
                                <div class="location-info">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <span><?= htmlspecialchars($currentUserLocation) ?></span>
                                </div>
                                <div class="radius-control">
                                    <label>Radius: <span id="radius-value">25</span> km</label>
                                    <input type="range" id="radius-slider" min="5" max="100" value="25" step="5"
                                        oninput="document.getElementById('radius-value').textContent = this.value">
                                </div>
                                <div class="nearby-buttons">
                                    <button type="button" onclick="searchNearby()" class="btn-nearby-search">
                                        <i class="fa-solid fa-magnifying-glass-location"></i> Find
                                    </button>
                                    <button type="button" onclick="clearNearbySearch()" class="btn-nearby-clear">Clear</button>
                                </div>
                                <div id="location-status" class="location-status"></div>
                            </div>
                            <script>
                                var userLat = <?= json_encode((float)$currentUserCoords['latitude']) ?>;
                                var userLon = <?= json_encode((float)$currentUserCoords['longitude']) ?>;
                            </script>
                        <?php elseif (isset($_SESSION['user_id'])): ?>
                            <div class="nearby-notice warning">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <span>Add location to your profile</span>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/edit">Update</a>
                            </div>
                            <script>
                                var userLat = null;
                                var userLon = null;
                            </script>
                        <?php else: ?>
                            <div class="nearby-notice info">
                                <i class="fa-solid fa-user"></i>
                                <span>Log in for nearby search</span>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Log In</a>
                            </div>
                            <script>
                                var userLat = null;
                                var userLon = null;
                            </script>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Skeleton Loaders -->
        <div class="listings-skeleton-grid skeleton-container" id="listingsSkeleton" aria-label="Loading listings">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="listing-card-skeleton">
                <div class="skeleton-image">
                    <div class="skeleton skeleton-badge"></div>
                </div>
                <div class="skeleton-body">
                    <div class="skeleton-meta">
                        <div class="skeleton skeleton-category"></div>
                        <div class="skeleton skeleton-date"></div>
                    </div>
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-desc"></div>
                    <div class="skeleton skeleton-desc"></div>
                    <div class="skeleton-footer">
                        <div class="skeleton-author">
                            <div class="skeleton skeleton-avatar small"></div>
                            <div class="skeleton skeleton-text" style="width: 80px; height: 14px; margin: 0;"></div>
                        </div>
                        <div class="skeleton skeleton-view-btn"></div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Grid -->
        <div id="listings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; margin-top: 40px;">
            <?php if (!empty($listings)): ?>
                <?php foreach ($listings as $listing):
                    $badgeColor = ($listing['type'] === 'offer') ? '#10b981' : '#f97316';
                    $cardGradient = $listing['type'] === 'offer' ? 'var(--htb-gradient-offers)' : 'var(--htb-gradient-requests)';
                    $basePath = Nexus\Core\TenantContext::getBasePath();
                    $listingUrl = $basePath . '/listings/' . $listing['id'];
                    $avatarUrl = $listing['avatar_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($listing['author_name']) . '&background=random&color=fff&size=64';
                    $displayLocation = $listing['user_location'] ?? $listing['location'] ?? null;
                    $distanceKm = $listing['distance_km'] ?? null;
                ?>
                    <a href="<?= $listingUrl ?>" class="glass-listing-card" aria-label="View <?= htmlspecialchars($listing['title']) ?>">
                        <?php if (!empty($listing['image_url'])): ?>
                            <div class="card-header-img" style="height: 180px; background: #e5e7eb; overflow: hidden; position: relative;">
                                <?= webp_image($listing['image_url'], htmlspecialchars($listing['title']), '', ['style' => 'width: 100%; height: 100%; object-fit: cover;']) ?>
                                <span class="nexus-badge" style="position:absolute; top:12px; left:12px; background:<?= $badgeColor ?>; color:white; font-weight:700; padding:6px 14px; border-radius:99px; font-size:0.8rem; box-shadow:0 2px 8px rgba(0,0,0,0.15);"><?= ucfirst($listing['type']) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="card-header-img" style="height: 180px; background: <?= $cardGradient ?>; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                                <div style="position: absolute; width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                <i class="<?= ($listing['type'] === 'offer' ? 'fa-solid fa-hand-holding-heart' : 'fa-solid fa-hand-sparkles') ?>"
                                    style="font-size: 3.5rem; color: rgba(255,255,255,0.9); z-index: 2; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));"></i>
                                <span class="nexus-badge" style="position:absolute; top:12px; left:12px; background:<?= $badgeColor ?>; color:white; font-weight:700; padding:6px 14px; border-radius:99px; font-size:0.8rem; box-shadow:0 2px 8px rgba(0,0,0,0.15); z-index: 3;"><?= ucfirst($listing['type']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="htb-card-body">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 0.75rem; font-weight: 800; color: var(--htb-text-muted); letter-spacing: 1px; text-transform: uppercase;">
                                    <?= htmlspecialchars($listing['category_name'] ?? 'General') ?>
                                </span>
                                <span style="font-size: 0.75rem; font-weight: 600; color: var(--htb-text-muted);">
                                    <?= date('M d', strtotime($listing['created_at'])) ?>
                                </span>
                            </div>

                            <?php if (!empty($displayLocation) || $distanceKm !== null): ?>
                                <div style="margin-bottom: 10px; font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <?php if (!empty($displayLocation)): ?>
                                        <span style="display: flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid fa-location-dot" style="color: #06b6d4;"></i>
                                            <?= htmlspecialchars($displayLocation) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($distanceKm !== null): ?>
                                        <span style="background: rgba(6, 182, 212, 0.1); color: #0891b2; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                            <?= round($distanceKm, 1) ?> km away
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <h3 class="card-title"><?= htmlspecialchars($listing['title']) ?></h3>

                            <p class="card-desc" style="color: var(--htb-text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 16px;">
                                <?= substr(strip_tags($listing['description']), 0, 80) ?>...
                            </p>

                            <div class="card-footer">
                                <div class="card-author" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='<?= $basePath ?>/profile/<?= $listing['user_id'] ?>';" role="link" aria-label="View <?= htmlspecialchars($listing['author_name']) ?>'s profile">
                                    <?= webp_avatar($avatarUrl, $listing['author_name'], 28) ?>
                                    <span style="font-size: 0.9rem; font-weight: 600;"><?= htmlspecialchars($listing['author_name']) ?></span>
                                </div>
                                <span class="card-view-btn">
                                    <i class="fa-solid fa-arrow-right"></i>
                                    View
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-empty-state" style="grid-column: 1/-1;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">🛒</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No listings yet</h3>
                    <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Be the first to post an offer or request!</p>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/create" class="btn btn--primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 14px 24px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border-radius: 12px; text-decoration: none; font-weight: 600;">
                        <i class="fa-solid fa-plus"></i> Create Listing
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // userLat and userLon are set by PHP above based on the user's profile location

        // Helper functions (global scope so they're accessible everywhere)
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }

        function capitalize(s) {
            return s && s[0].toUpperCase() + s.slice(1);
        }

        function renderGrid(listings) {
            const grid = document.getElementById('listings-grid');
            const basePath = "<?= Nexus\Core\TenantContext::getBasePath() ?>";

            grid.innerHTML = '';
            if (listings.length === 0) {
                grid.innerHTML = '<div class="glass-empty-state"><h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No matches found</h3><p style="color: var(--htb-text-muted);">Try different keywords or browse all categories.</p></div>';
                return;
            }

            listings.forEach(listing => {
                const isOffer = listing.type === 'offer';
                const badgeColor = isOffer ? '#10b981' : '#f97316';
                const gradient = isOffer ? 'var(--htb-gradient-offers)' : 'var(--htb-gradient-requests)';
                const iconClass = isOffer ? 'fa-solid fa-hand-holding-heart' : 'fa-solid fa-hand-sparkles';
                const dateStr = new Date(listing.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    day: '2-digit'
                });
                const avatarUrl = listing.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(listing.author_name)}&background=random&color=fff&size=64`;
                const listingUrl = `${basePath}/listings/${listing.id}`;

                // Create clickable card link
                const card = document.createElement('a');
                card.href = listingUrl;
                card.className = 'glass-listing-card';

                let headerHtml = '';
                if (listing.image_url) {
                    headerHtml = `
                <div class="card-header-img" style="height: 180px; background: #e5e7eb; overflow: hidden; position: relative;">
                    <img src="${escapeHtml(listing.image_url)}" style="width: 100%; height: 100%; object-fit: cover;" alt="${escapeHtml(listing.title)}" loading="lazy">
                    <span class="nexus-badge" style="position:absolute; top:12px; left:12px; background:${badgeColor}; color:white; font-weight:700; padding:6px 14px; border-radius:99px; font-size:0.8rem; box-shadow:0 2px 8px rgba(0,0,0,0.15);">${capitalize(listing.type)}</span>
                </div>`;
                } else {
                    headerHtml = `
                <div class="card-header-img" style="height: 180px; background: ${gradient}; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                    <div style="position: absolute; width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                    <i class="${iconClass}" style="font-size: 3.5rem; color: rgba(255,255,255,0.9); z-index: 2; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));"></i>
                    <span class="nexus-badge" style="position:absolute; top:12px; left:12px; background:${badgeColor}; color:white; font-weight:700; padding:6px 14px; border-radius:99px; font-size:0.8rem; box-shadow:0 2px 8px rgba(0,0,0,0.15); z-index: 3;">${capitalize(listing.type)}</span>
                </div>`;
                }

                // Build location/distance display
                const displayLocation = listing.user_location || listing.location || '';
                const distanceKm = listing.distance_km;
                let locationHtml = '';
                if (displayLocation || distanceKm !== undefined) {
                    locationHtml = `<div style="margin-bottom: 10px; font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">`;
                    if (displayLocation) {
                        locationHtml += `<span style="display: flex; align-items: center; gap: 4px;"><i class="fa-solid fa-location-dot" style="color: #06b6d4;"></i>${escapeHtml(displayLocation)}</span>`;
                    }
                    if (distanceKm !== undefined && distanceKm !== null) {
                        locationHtml += `<span style="background: rgba(6, 182, 212, 0.1); color: #0891b2; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">${parseFloat(distanceKm).toFixed(1)} km away</span>`;
                    }
                    locationHtml += `</div>`;
                }

                card.innerHTML = `
            ${headerHtml}
            <div class="htb-card-body">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-size: 0.75rem; font-weight: 800; color: var(--htb-text-muted); letter-spacing: 1px; text-transform: uppercase;">
                        ${escapeHtml(listing.category_name || 'General')}
                    </span>
                    <span style="font-size: 0.75rem; font-weight: 600; color: var(--htb-text-muted);">
                        ${dateStr}
                    </span>
                </div>

                ${locationHtml}

                <h3 class="card-title">${escapeHtml(listing.title)}</h3>

                <p class="card-desc" style="color: var(--htb-text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 16px;">
                    ${escapeHtml((listing.description || '').substring(0, 80))}...
                </p>

                <div class="card-footer">
                    <div class="card-author" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='${basePath}/profile/${listing.user_id}';">
                        <img src="${avatarUrl}" alt="${escapeHtml(listing.author_name)}" loading="lazy">
                        <span style="font-size: 0.9rem; font-weight: 600;">${escapeHtml(listing.author_name)}</span>
                    </div>
                    <span class="card-view-btn">
                        <i class="fa-solid fa-arrow-right"></i>
                        View
                    </span>
                </div>
            </div>
            `;

                grid.appendChild(card);
            });
        }

        // Toggle nearby search section
        function toggleNearbySection() {
            const toggle = document.querySelector('.nearby-toggle');
            const content = document.getElementById('nearby-content');
            if (toggle && content) {
                toggle.classList.toggle('expanded');
                content.classList.toggle('collapsed');
            }
        }

        function searchNearby() {
            if (typeof userLat === 'undefined' || typeof userLon === 'undefined' || !userLat || !userLon) {
                const status = document.getElementById('location-status');
                if (status) {
                    status.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-exclamation-triangle"></i> No location set. Please update your profile.</span>';
                }
                return;
            }

            const radius = document.getElementById('radius-slider').value;
            const grid = document.getElementById('listings-grid');
            const countLabel = document.getElementById('listings-count');
            const spinner = document.getElementById('search-spinner');
            const status = document.getElementById('location-status');

            spinner.style.display = 'block';
            status.innerHTML = '<span style="color: #06b6d4;"><i class="fa-solid fa-spinner fa-spin"></i> Searching within ' + radius + 'km...</span>';

            const url = window.location.pathname + '?lat=' + userLat + '&lon=' + userLon + '&radius=' + radius;

            fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Server error: ' + res.status);
                    return res.text();
                })
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text.substring(0, 500));
                        throw new Error('Invalid response from server');
                    }
                })
                .then(data => {
                    renderGrid(data.data || []);
                    const count = (data.data || []).length;
                    countLabel.textContent = `Showing ${count} listings within ${radius}km`;
                    spinner.style.display = 'none';
                    if (count === 0) {
                        status.innerHTML = '<span style="color: #f59e0b;"><i class="fa-solid fa-info-circle"></i> No listings found within ' + radius + 'km. Try increasing the radius or <a href="/sync-listing-locations" style="color: #06b6d4;">sync listing locations</a>.</span>';
                    } else {
                        status.innerHTML = '<span style="color: #10b981;"><i class="fa-solid fa-check-circle"></i> Found ' + count + ' listings within ' + radius + 'km</span>';
                    }
                })
                .catch(err => {
                    console.error('Nearby search error:', err);
                    spinner.style.display = 'none';
                    status.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-exclamation-triangle"></i> ' + escapeHtml(err.message || 'Search failed. Please try again.') + '</span>';
                });
        }

        function clearNearbySearch() {
            const slider = document.getElementById('radius-slider');
            const radiusValue = document.getElementById('radius-value');
            if (slider) slider.value = 25;
            if (radiusValue) radiusValue.textContent = '25';
            window.location.href = window.location.pathname;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('listing-search');
            const countLabel = document.getElementById('listings-count');
            const spinner = document.getElementById('search-spinner');
            let debounceTimer;

            searchInput.addEventListener('keyup', function(e) {
                clearTimeout(debounceTimer);
                const query = e.target.value.trim();
                spinner.style.display = 'block';

                debounceTimer = setTimeout(() => {
                    fetchListings(query);
                }, 300);
            });

            function fetchListings(query) {
                // If empty, simple reload to restore initial state
                if (query.length === 0) {
                    window.location.reload();
                    return;
                }

                const url = window.location.pathname + '?q=' + encodeURIComponent(query);

                fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        renderGrid(data.data);
                        countLabel.textContent = `Showing ${data.data.length} listings`;
                        spinner.style.display = 'none';
                    })
                    .catch(err => {
                        console.error(err);
                        spinner.style.display = 'none';
                    });
            }
        });

        // ============================================
        // GOLD STANDARD - Native App Features
        // ============================================

        // Skeleton Loader Transition
        document.addEventListener('DOMContentLoaded', function() {
            const skeleton = document.getElementById('listingsSkeleton');
            const grid = document.getElementById('listings-grid');

            if (skeleton && grid) {
                setTimeout(function() {
                    skeleton.classList.add('hidden');
                    grid.classList.add('content-loaded');
                }, 300);
            }
        });

        // Pull-to-refresh feature has been permanently removed

        // Offline Indicator
        (function initOfflineIndicator() {
            const banner = document.getElementById('offlineBanner');
            if (!banner) return;

            let wasOffline = false;

            function handleOffline() {
                wasOffline = true;
                banner.classList.add('visible');
                if (navigator.vibrate) navigator.vibrate(100);
            }

            function handleOnline() {
                banner.classList.remove('visible');
                if (wasOffline) {
                    wasOffline = false;
                }
            }

            window.addEventListener('online', handleOnline);
            window.addEventListener('offline', handleOffline);

            // Don't check on initial load - only respond to actual offline events
            // navigator.onLine is unreliable on localhost and can flash false positives
        })();

        // Button Press States & Ripple
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nexus-smart-btn, .quick-action-btn, .glass-listing-card').forEach(btn => {
                btn.addEventListener('pointerdown', function(e) {
                    this.classList.add('pressing');
                });

                btn.addEventListener('pointerup', function(e) {
                    this.classList.remove('pressing');
                    // Ripple effect
                    if (this.classList.contains('nexus-smart-btn') || this.classList.contains('quick-action-btn')) {
                        this.classList.add('rippling');
                        setTimeout(() => this.classList.remove('rippling'), 600);
                    }
                });

                btn.addEventListener('pointerleave', function(e) {
                    this.classList.remove('pressing');
                });

                btn.addEventListener('pointercancel', function(e) {
                    this.classList.remove('pressing');
                });
            });
        });

        // Dynamic Theme Color
        (function initDynamicThemeColor() {
            const themeColorMeta = document.querySelector('meta[name="theme-color"]');
            if (!themeColorMeta) return;

            function updateThemeColor() {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                themeColorMeta.setAttribute('content', isDark ? '#0f172a' : '#ffffff');
            }

            const observer = new MutationObserver(updateThemeColor);
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme']
            });

            updateThemeColor();
        })();
    </script>

</div><!-- #listings-index-glass-wrapper -->
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>