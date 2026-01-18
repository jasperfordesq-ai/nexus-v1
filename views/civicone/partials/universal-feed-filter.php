<?php

/**
 * UniversalFeedFilter Component - EdgeRank Active
 *
 * A professional Meta/X-style feed filter navigation bar with intelligent ranking.
 *
 * Features:
 * - Desktop: Floating glassmorphism bar with holographic border
 * - Mobile: Horizontal scrolling pill navigation (native app feel)
 * - Two-tier conditional logic (secondary filters appear based on selection)
 * - EdgeRank algorithm integration (For You / Recent toggle)
 * - Location filtering using user profile coords + radius slider
 * - Smart scroll hide/show on mobile
 * - Full dark mode support
 *
 * Usage:
 * <?php include __DIR__ . '/../partials/universal-feed-filter.php'; ?>
 *
 * JavaScript API:
 * - FeedFilter.getActiveFilter() - Returns current filter state
 * - FeedFilter.setFilter(type, subtype) - Programmatically set filter
 * - FeedFilter.onFilterChange(callback) - Subscribe to filter changes
 * - FeedFilter.toggleLocation() - Toggle location mode
 * - FeedFilter.setRadius(km) - Set location radius
 * - FeedFilter.toggleAlgorithm() - Toggle For You / Recent
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$userId = $_SESSION['user_id'] ?? null;

// Get user's location from profile (not GPS)
$userLat = null;
$userLng = null;
$userLocationName = null;
$hasUserLocation = false;

if ($userId) {
    try {
        // Check session first for performance
        if (!empty($_SESSION['user_latitude']) && !empty($_SESSION['user_longitude'])) {
            $userLat = (float)$_SESSION['user_latitude'];
            $userLng = (float)$_SESSION['user_longitude'];
            $userLocationName = $_SESSION['user_location'] ?? 'Your Location';
            $hasUserLocation = true;
        } else {
            // Fetch from database
            $userCoords = \Nexus\Models\User::getCoordinates($userId);
            if ($userCoords && !empty($userCoords['latitude']) && !empty($userCoords['longitude'])) {
                $userLat = (float)$userCoords['latitude'];
                $userLng = (float)$userCoords['longitude'];
                // Cache in session
                $_SESSION['user_latitude'] = $userLat;
                $_SESSION['user_longitude'] = $userLng;
                $hasUserLocation = true;

                // Try to get location name
                $userProfile = \Nexus\Models\User::findById($userId);
                $userLocationName = $userProfile['location'] ?? $userProfile['county'] ?? $userProfile['town'] ?? 'Your Location';
                $_SESSION['user_location'] = $userLocationName;
            }
        }
    } catch (\Exception $e) {
        // Silently continue without location
    }
}

// Feature flags for conditional tabs
$hasEvents = \Nexus\Core\TenantContext::hasFeature('events') ?? true;
$hasGoals = \Nexus\Core\TenantContext::hasFeature('goals') ?? true;
$hasPolls = \Nexus\Core\TenantContext::hasFeature('polls') ?? true;
$hasVolunteering = \Nexus\Core\TenantContext::hasFeature('volunteering') ?? true;
$hasGroups = true;
$hasResources = \Nexus\Core\TenantContext::hasFeature('resources') ?? true;
$hasReviews = true; // Reviews are always available

// Current filter state (from URL or default)
$currentFilter = $_GET['filter'] ?? 'all';
$currentSubFilter = $_GET['subfilter'] ?? null;
$locationMode = $_GET['location'] ?? 'global';
$currentRadius = (int)($_GET['radius'] ?? 500); // Default 500km covers all of Ireland
$algorithmMode = $_GET['algo'] ?? 'ranked'; // 'ranked' (EdgeRank) or 'recent' (chronological)
?>

<!-- ============================================
     UNIVERSAL FEED FILTER COMPONENT
     EdgeRank Active - Intelligent Feed Ranking
     ============================================ -->
<div class="feed-filter"
    id="feedFilter"
    data-filter="<?= htmlspecialchars($currentFilter) ?>"
    data-subfilter="<?= htmlspecialchars($currentSubFilter ?? '') ?>"
    data-location="<?= htmlspecialchars($locationMode) ?>"
    data-radius="<?= $currentRadius ?>"
    data-algo="<?= htmlspecialchars($algorithmMode) ?>"
    data-user-lat="<?= $userLat ?? '' ?>"
    data-user-lng="<?= $userLng ?? '' ?>"
    data-has-location="<?= $hasUserLocation ? '1' : '0' ?>">

    <!-- Primary Filter Row -->
    <div class="feed-filter-primary">
        <!-- Algorithm Toggle (For You / Recent) -->
        <div class="feed-filter-algo-toggle">
            <button type="button"
                class="feed-filter-algo-btn <?= $algorithmMode === 'ranked' ? 'active' : '' ?>"
                data-algo="ranked"
                onclick="FeedFilter.setAlgorithm('ranked')"
                title="Personalized feed using EdgeRank">
                <i class="fa-solid fa-sparkles"></i>
                <span>For You</span>
            </button>
            <button type="button"
                class="feed-filter-algo-btn <?= $algorithmMode === 'recent' ? 'active' : '' ?>"
                data-algo="recent"
                onclick="FeedFilter.setAlgorithm('recent')"
                title="Chronological feed">
                <i class="fa-solid fa-clock"></i>
                <span>Recent</span>
            </button>
        </div>

        <div class="feed-filter-divider"></div>

        <div class="feed-filter-scroll">
            <div class="feed-filter-pills">
                <!-- All Posts -->
                <button type="button"
                    class="feed-filter-pill <?= $currentFilter === 'all' ? 'active' : '' ?>"
                    data-filter="all"
                    onclick="FeedFilter.setFilter('all')">
                    <i class="fa-solid fa-stream"></i>
                    <span>All</span>
                </button>

                <!-- Listings -->
                <button type="button"
                    class="feed-filter-pill <?= $currentFilter === 'listings' ? 'active' : '' ?>"
                    data-filter="listings"
                    onclick="FeedFilter.setFilter('listings')">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                    <span>Listings</span>
                </button>

                <!-- Events -->
                <?php if ($hasEvents): ?>
                    <button type="button"
                        class="feed-filter-pill <?= $currentFilter === 'events' ? 'active' : '' ?>"
                        data-filter="events"
                        onclick="FeedFilter.setFilter('events')">
                        <i class="fa-solid fa-calendar-star"></i>
                        <span>Events</span>
                    </button>
                <?php endif; ?>

                <!-- Goals -->
                <?php if ($hasGoals): ?>
                    <button type="button"
                        class="feed-filter-pill <?= $currentFilter === 'goals' ? 'active' : '' ?>"
                        data-filter="goals"
                        onclick="FeedFilter.setFilter('goals')">
                        <i class="fa-solid fa-bullseye-arrow"></i>
                        <span>Goals</span>
                    </button>
                <?php endif; ?>

                <!-- Polls -->
                <?php if ($hasPolls): ?>
                    <button type="button"
                        class="feed-filter-pill <?= $currentFilter === 'polls' ? 'active' : '' ?>"
                        data-filter="polls"
                        onclick="FeedFilter.setFilter('polls')">
                        <i class="fa-solid fa-square-poll-vertical"></i>
                        <span>Polls</span>
                    </button>
                <?php endif; ?>

                <!-- Volunteering -->
                <?php if ($hasVolunteering): ?>
                    <button type="button"
                        class="feed-filter-pill <?= $currentFilter === 'volunteering' ? 'active' : '' ?>"
                        data-filter="volunteering"
                        onclick="FeedFilter.setFilter('volunteering')">
                        <i class="fa-solid fa-hands-helping"></i>
                        <span>Volunteering</span>
                    </button>
                <?php endif; ?>

                <!-- Groups -->
                <?php if ($hasGroups): ?>
                    <button type="button"
                        class="feed-filter-pill <?= $currentFilter === 'groups' ? 'active' : '' ?>"
                        data-filter="groups"
                        onclick="FeedFilter.setFilter('groups')">
                        <i class="fa-solid fa-users"></i>
                        <span>Groups</span>
                    </button>
                <?php endif; ?>

                <!-- Resources -->
                <?php if ($hasResources): ?>
                    <button type="button"
                        class="feed-filter-pill <?= $currentFilter === 'resources' ? 'active' : '' ?>"
                        data-filter="resources"
                        onclick="FeedFilter.setFilter('resources')">
                        <i class="fa-solid fa-folder-open"></i>
                        <span>Resources</span>
                    </button>
                <?php endif; ?>

                <!-- Reviews -->
                <?php if ($hasReviews): ?>
                    <button type="button"
                        class="feed-filter-pill <?= $currentFilter === 'reviews' ? 'active' : '' ?>"
                        data-filter="reviews"
                        onclick="FeedFilter.setFilter('reviews')">
                        <i class="fa-solid fa-star"></i>
                        <span>Reviews</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Location Toggle (Fixed Right) -->
        <?php if ($hasUserLocation): ?>
            <div class="feed-filter-location">
                <button type="button"
                    class="feed-filter-location-btn <?= $locationMode === 'nearby' ? 'active' : '' ?>"
                    id="locationToggle"
                    onclick="FeedFilter.toggleLocation()"
                    aria-label="Toggle location filter"
                    title="<?= $locationMode === 'nearby' ? 'Showing nearby: ' . htmlspecialchars($userLocationName) : 'Filter by your location' ?>">
                    <i class="fa-solid fa-location-dot"></i>
                    <span class="feed-filter-location-label"><?= $locationMode === 'nearby' ? 'Near Me' : 'Global' ?></span>
                </button>
            </div>
        <?php else: ?>
            <div class="feed-filter-location">
                <button type="button"
                    class="feed-filter-location-btn disabled"
                    title="Add your location in profile settings to enable"
                    onclick="FeedFilter.showLocationPrompt()">
                    <i class="fa-solid fa-location-dot"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Secondary Filter Row (Conditional) -->
    <div class="feed-filter-secondary" id="feedFilterSecondary">

        <!-- Location Radius Slider (shows when location mode is active) -->
        <div class="feed-filter-subgroup feed-filter-radius" data-parent="location" <?= $locationMode !== 'nearby' ? 'style="display:none;"' : '' ?>>
            <div class="feed-filter-radius-header">
                <i class="fa-solid fa-map-marker-alt"></i>
                <span class="feed-filter-radius-location"><?= htmlspecialchars($userLocationName ?? 'Your Location') ?></span>
            </div>
            <div class="feed-filter-radius-control">
                <input type="range"
                    id="radiusSlider"
                    class="feed-filter-radius-slider"
                    min="10"
                    max="500"
                    step="10"
                    value="<?= $currentRadius ?>"
                    oninput="FeedFilter.updateRadiusPreview(this.value)"
                    onchange="FeedFilter.setRadius(this.value)">
                <div class="feed-filter-radius-labels">
                    <span>10km</span>
                    <span class="feed-filter-radius-value" id="radiusValue"><?= $currentRadius ?>km</span>
                    <span>500km</span>
                </div>
            </div>
            <div class="feed-filter-radius-hint">
                <i class="fa-solid fa-info-circle"></i>
                500km covers all of Ireland
            </div>
        </div>

        <!-- Listings Sub-filters -->
        <div class="feed-filter-subgroup" data-parent="listings" <?= $currentFilter !== 'listings' ? 'style="display:none;"' : '' ?>>
            <button type="button"
                class="feed-filter-sub <?= ($currentFilter === 'listings' && (!$currentSubFilter || $currentSubFilter === 'all')) ? 'active' : '' ?>"
                data-subfilter="all"
                onclick="FeedFilter.setSubFilter('all')">
                All
            </button>
            <button type="button"
                class="feed-filter-sub <?= ($currentFilter === 'listings' && $currentSubFilter === 'offers') ? 'active' : '' ?>"
                data-subfilter="offers"
                onclick="FeedFilter.setSubFilter('offers')">
                <i class="fa-solid fa-gift"></i>
                Offers
            </button>
            <button type="button"
                class="feed-filter-sub <?= ($currentFilter === 'listings' && $currentSubFilter === 'requests') ? 'active' : '' ?>"
                data-subfilter="requests"
                onclick="FeedFilter.setSubFilter('requests')">
                <i class="fa-solid fa-hand"></i>
                Requests
            </button>
        </div>

        <!-- Volunteering Sub-filters -->
        <?php if ($hasVolunteering): ?>
            <div class="feed-filter-subgroup" data-parent="volunteering" <?= $currentFilter !== 'volunteering' ? 'style="display:none;"' : '' ?>>
                <button type="button"
                    class="feed-filter-sub <?= ($currentFilter === 'volunteering' && (!$currentSubFilter || $currentSubFilter === 'all')) ? 'active' : '' ?>"
                    data-subfilter="all"
                    onclick="FeedFilter.setSubFilter('all')">
                    All
                </button>
                <button type="button"
                    class="feed-filter-sub <?= ($currentFilter === 'volunteering' && $currentSubFilter === 'opportunities') ? 'active' : '' ?>"
                    data-subfilter="opportunities"
                    onclick="FeedFilter.setSubFilter('opportunities')">
                    <i class="fa-solid fa-briefcase"></i>
                    Opportunities
                </button>
                <button type="button"
                    class="feed-filter-sub <?= ($currentFilter === 'volunteering' && $currentSubFilter === 'host-requests') ? 'active' : '' ?>"
                    data-subfilter="host-requests"
                    onclick="FeedFilter.setSubFilter('host-requests')">
                    <i class="fa-solid fa-building-ngo"></i>
                    Host Requests
                </button>
            </div>
        <?php endif; ?>

        <!-- Groups Sub-filters -->
        <?php if ($hasGroups): ?>
            <div class="feed-filter-subgroup" data-parent="groups" <?= $currentFilter !== 'groups' ? 'style="display:none;"' : '' ?>>
                <button type="button"
                    class="feed-filter-sub <?= ($currentFilter === 'groups' && (!$currentSubFilter || $currentSubFilter === 'my-groups')) ? 'active' : '' ?>"
                    data-subfilter="my-groups"
                    onclick="FeedFilter.setSubFilter('my-groups')">
                    <i class="fa-solid fa-user-group"></i>
                    My Groups
                </button>
                <button type="button"
                    class="feed-filter-sub <?= ($currentFilter === 'groups' && $currentSubFilter === 'discover') ? 'active' : '' ?>"
                    data-subfilter="discover"
                    onclick="FeedFilter.setSubFilter('discover')">
                    <i class="fa-solid fa-compass"></i>
                    Discover
                </button>
                <button type="button"
                    class="feed-filter-sub <?= ($currentFilter === 'groups' && $currentSubFilter === 'by-location') ? 'active' : '' ?>"
                    data-subfilter="by-location"
                    onclick="FeedFilter.setSubFilter('by-location')">
                    <i class="fa-solid fa-map-marker-alt"></i>
                    By Location
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    /**
     * FeedFilter - Universal Feed Filter Controller
     * EdgeRank Active Edition
     *
     * Handles filter state, algorithm toggle, location radius, and scroll behavior.
     */
    window.FeedFilter = (function() {
        'use strict';

        // State
        let currentFilter = '<?= htmlspecialchars($currentFilter) ?>';
        let currentSubFilter = <?= $currentSubFilter ? "'" . htmlspecialchars($currentSubFilter) . "'" : 'null' ?>;
        let locationMode = '<?= htmlspecialchars($locationMode) ?>';
        let currentRadius = <?= $currentRadius ?>;
        let algorithmMode = '<?= htmlspecialchars($algorithmMode) ?>';
        let callbacks = [];
        let lastScrollY = 0;
        let isHidden = false;
        let scrollThreshold = 50;

        // User location from profile
        const userLat = <?= $userLat ? $userLat : 'null' ?>;
        const userLng = <?= $userLng ? $userLng : 'null' ?>;
        const hasUserLocation = <?= $hasUserLocation ? 'true' : 'false' ?>;
        const userLocationName = '<?= htmlspecialchars($userLocationName ?? '') ?>';

        // DOM Elements
        const filterContainer = document.getElementById('feedFilter');
        const secondaryRow = document.getElementById('feedFilterSecondary');
        const locationBtn = document.getElementById('locationToggle');
        const radiusSlider = document.getElementById('radiusSlider');
        const radiusValue = document.getElementById('radiusValue');

        /**
         * Initialize the filter
         */
        function init() {
            setupScrollBehavior();
            updateSecondaryVisibility();

            // Sync localStorage with URL state (URL is source of truth from server)
            // The server renders content based on URL params, so client must match
            const urlParams = new URLSearchParams(window.location.search);
            const urlAlgo = urlParams.get('algo') || 'ranked'; // Default is 'ranked'

            // If localStorage differs from URL, update localStorage to match
            // (User might have opened link directly or bookmark)
            const savedAlgo = localStorage.getItem('feedAlgorithmMode');
            if (savedAlgo !== urlAlgo) {
                localStorage.setItem('feedAlgorithmMode', urlAlgo);
            }
            // Ensure algorithmMode variable matches what server rendered
            algorithmMode = urlAlgo;
            updateAlgoUI();

            const savedRadius = localStorage.getItem('feedRadius');
            if (savedRadius && radiusSlider) {
                currentRadius = parseInt(savedRadius);
                radiusSlider.value = currentRadius;
                if (radiusValue) radiusValue.textContent = currentRadius + 'km';
            }
        }

        /**
         * Set the algorithm mode (EdgeRank or Chronological)
         */
        function setAlgorithm(mode) {
            if (algorithmMode === mode) return;

            algorithmMode = mode;
            localStorage.setItem('feedAlgorithmMode', mode);
            updateAlgoUI();
            updateURL();
            haptic();
            notifyChange();

            // Add loading state
            filterContainer.classList.add('loading');
            setTimeout(() => filterContainer.classList.remove('loading'), 500);
        }

        /**
         * Update algorithm toggle UI
         */
        function updateAlgoUI() {
            document.querySelectorAll('.feed-filter-algo-btn').forEach(btn => {
                if (btn.dataset.algo === algorithmMode) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }

        /**
         * Set the primary filter
         */
        function setFilter(type) {
            if (currentFilter === type) return;

            currentFilter = type;
            currentSubFilter = null;

            updatePillStates();
            updateSecondaryVisibility();
            updateURL();
            haptic();
            notifyChange();

            filterContainer.classList.add('loading');
            setTimeout(() => filterContainer.classList.remove('loading'), 500);
        }

        /**
         * Set the sub-filter
         */
        function setSubFilter(subtype) {
            if (currentSubFilter === subtype) return;

            currentSubFilter = subtype;
            updateSubPillStates();
            updateURL();
            haptic();
            notifyChange();
        }

        /**
         * Toggle location mode
         */
        function toggleLocation() {
            if (!hasUserLocation) {
                showLocationPrompt();
                return;
            }

            locationMode = locationMode === 'global' ? 'nearby' : 'global';
            updateLocationUI();
            updateSecondaryVisibility();
            updateURL();
            haptic();
            notifyChange();
        }

        /**
         * Show prompt to add location
         */
        function showLocationPrompt() {
            const basePath = '<?= $basePath ?>';
            if (confirm('Add your location in profile settings to filter content near you.\n\nGo to profile settings now?')) {
                window.location.href = basePath + '/settings/profile';
            }
        }

        /**
         * Update radius preview (while dragging)
         */
        function updateRadiusPreview(value) {
            if (radiusValue) {
                radiusValue.textContent = value + 'km';
            }
        }

        /**
         * Set radius (on release)
         */
        function setRadius(value) {
            currentRadius = parseInt(value);
            localStorage.setItem('feedRadius', currentRadius);
            updateURL();
            haptic();
            notifyChange();
        }

        /**
         * Update primary pill states
         */
        function updatePillStates() {
            document.querySelectorAll('.feed-filter-pill').forEach(pill => {
                if (pill.dataset.filter === currentFilter) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });
        }

        /**
         * Update sub-filter pill states
         */
        function updateSubPillStates() {
            const activeGroup = document.querySelector(`.feed-filter-subgroup[data-parent="${currentFilter}"]`);
            if (!activeGroup) return;

            activeGroup.querySelectorAll('.feed-filter-sub').forEach(pill => {
                const subfilter = pill.dataset.subfilter;
                if (subfilter === currentSubFilter || (subfilter === 'all' && !currentSubFilter)) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });
        }

        /**
         * Update secondary row visibility
         */
        function updateSecondaryVisibility() {
            const hasSecondary = ['listings', 'volunteering', 'groups'].includes(currentFilter);
            const showLocationSlider = locationMode === 'nearby' && hasUserLocation;

            // Hide all subgroups
            document.querySelectorAll('.feed-filter-subgroup').forEach(group => {
                group.style.display = 'none';
            });

            let shouldShowSecondary = false;

            // Show location slider if in nearby mode
            if (showLocationSlider) {
                const locationGroup = document.querySelector('.feed-filter-radius');
                if (locationGroup) {
                    locationGroup.style.display = 'flex';
                    shouldShowSecondary = true;
                }
            }

            // Show relevant filter subgroup
            if (hasSecondary) {
                const activeGroup = document.querySelector(`.feed-filter-subgroup[data-parent="${currentFilter}"]`);
                if (activeGroup) {
                    activeGroup.style.display = 'flex';
                    shouldShowSecondary = true;

                    if (!currentSubFilter) {
                        const firstSub = activeGroup.querySelector('.feed-filter-sub');
                        if (firstSub) {
                            currentSubFilter = firstSub.dataset.subfilter;
                        }
                    }
                    updateSubPillStates();
                }
            }

            if (shouldShowSecondary) {
                secondaryRow.classList.add('visible');
            } else {
                secondaryRow.classList.remove('visible');
            }
        }

        /**
         * Update location button UI
         */
        function updateLocationUI() {
            if (!locationBtn) return;

            if (locationMode === 'nearby') {
                locationBtn.classList.add('active');
                locationBtn.title = 'Showing nearby: ' + userLocationName;
                locationBtn.querySelector('.feed-filter-location-label').textContent = 'Near Me';
            } else {
                locationBtn.classList.remove('active');
                locationBtn.title = 'Filter by your location';
                locationBtn.querySelector('.feed-filter-location-label').textContent = 'Global';
            }
        }

        /**
         * Update URL without reload
         */
        function updateURL() {
            const params = new URLSearchParams(window.location.search);

            if (currentFilter !== 'all') {
                params.set('filter', currentFilter);
            } else {
                params.delete('filter');
            }

            if (currentSubFilter && currentSubFilter !== 'all') {
                params.set('subfilter', currentSubFilter);
            } else {
                params.delete('subfilter');
            }

            if (locationMode !== 'global') {
                params.set('location', locationMode);
                params.set('radius', currentRadius);
            } else {
                params.delete('location');
                params.delete('radius');
            }

            if (algorithmMode !== 'ranked') {
                params.set('algo', algorithmMode);
            } else {
                params.delete('algo');
            }

            const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newURL);
        }

        /**
         * Setup scroll hide/show behavior (mobile only)
         */
        function setupScrollBehavior() {
            if (window.innerWidth > 768) return;

            let ticking = false;

            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        handleScroll();
                        ticking = false;
                    });
                    ticking = true;
                }
            }, {
                passive: true
            });
        }

        /**
         * Handle scroll for hide/show
         */
        function handleScroll() {
            const currentScrollY = window.scrollY;
            const scrollDiff = currentScrollY - lastScrollY;

            if (currentScrollY < scrollThreshold) {
                showFilter();
            } else if (scrollDiff > 5 && !isHidden) {
                hideFilter();
            } else if (scrollDiff < -5 && isHidden) {
                showFilter();
            }

            lastScrollY = currentScrollY;
        }

        function hideFilter() {
            filterContainer.classList.add('hidden');
            isHidden = true;
        }

        function showFilter() {
            filterContainer.classList.remove('hidden');
            isHidden = false;
        }

        /**
         * Haptic feedback
         */
        function haptic() {
            if (navigator.vibrate) {
                navigator.vibrate(10);
            }
            if (window.Capacitor?.Plugins?.Haptics) {
                window.Capacitor.Plugins.Haptics.impact({
                    style: 'light'
                });
            }
        }

        /**
         * Subscribe to filter changes
         */
        function onFilterChange(callback) {
            callbacks.push(callback);
            return () => {
                callbacks = callbacks.filter(cb => cb !== callback);
            };
        }

        /**
         * Notify all listeners
         */
        function notifyChange() {
            const state = getActiveFilter();
            callbacks.forEach(cb => {
                try {
                    cb(state);
                } catch (e) {
                    console.error('FeedFilter callback error:', e);
                }
            });

            filterContainer.dispatchEvent(new CustomEvent('filterchange', {
                detail: state,
                bubbles: true
            }));
        }

        /**
         * Get current filter state
         */
        function getActiveFilter() {
            return {
                filter: currentFilter,
                subFilter: currentSubFilter,
                locationMode: locationMode,
                radius: currentRadius,
                algorithmMode: algorithmMode,
                coords: hasUserLocation ? {
                    lat: userLat,
                    lng: userLng
                } : null,
                locationName: userLocationName
            };
        }

        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        // Public API
        return {
            setFilter,
            setSubFilter,
            setAlgorithm,
            toggleLocation,
            setRadius,
            updateRadiusPreview,
            getActiveFilter,
            onFilterChange,
            showLocationPrompt
        };
    })();
</script>