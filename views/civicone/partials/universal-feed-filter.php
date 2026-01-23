<?php

/**
 * UniversalFeedFilter Component - EdgeRank Active
 *
 * Desktop: Floating glassmorphism bar with holographic border
 * Mobile: Compact trigger bar with inline expandable panel
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$userId = $_SESSION['user_id'] ?? null;

// Get user's location from profile
$userLat = null;
$userLng = null;
$userLocationName = null;
$hasUserLocation = false;

if ($userId) {
    try {
        if (!empty($_SESSION['user_latitude']) && !empty($_SESSION['user_longitude'])) {
            $userLat = (float)$_SESSION['user_latitude'];
            $userLng = (float)$_SESSION['user_longitude'];
            $userLocationName = $_SESSION['user_location'] ?? 'Your Location';
            $hasUserLocation = true;
        } else {
            $userCoords = \Nexus\Models\User::getCoordinates($userId);
            if ($userCoords && !empty($userCoords['latitude']) && !empty($userCoords['longitude'])) {
                $userLat = (float)$userCoords['latitude'];
                $userLng = (float)$userCoords['longitude'];
                $_SESSION['user_latitude'] = $userLat;
                $_SESSION['user_longitude'] = $userLng;
                $hasUserLocation = true;
                $userProfile = \Nexus\Models\User::findById($userId);
                $userLocationName = $userProfile['location'] ?? $userProfile['county'] ?? $userProfile['town'] ?? 'Your Location';
                $_SESSION['user_location'] = $userLocationName;
            }
        }
    } catch (\Exception $e) {
        // Silently continue
    }
}

// Feature flags
$hasEvents = \Nexus\Core\TenantContext::hasFeature('events') ?? true;
$hasGoals = \Nexus\Core\TenantContext::hasFeature('goals') ?? true;
$hasPolls = \Nexus\Core\TenantContext::hasFeature('polls') ?? true;
$hasVolunteering = \Nexus\Core\TenantContext::hasFeature('volunteering') ?? true;
$hasGroups = true;
$hasResources = \Nexus\Core\TenantContext::hasFeature('resources') ?? true;
$hasReviews = true;

// Current filter state
$currentFilter = $_GET['filter'] ?? 'all';
$currentSubFilter = $_GET['subfilter'] ?? null;
$locationMode = $_GET['location'] ?? 'global';
$currentRadius = (int)($_GET['radius'] ?? 500);
$algorithmMode = $_GET['algo'] ?? 'ranked';

// Filter labels
$filterLabels = [
    'all' => ['icon' => 'fa-stream', 'label' => 'All'],
    'listings' => ['icon' => 'fa-hand-holding-heart', 'label' => 'Listings'],
    'events' => ['icon' => 'fa-calendar-star', 'label' => 'Events'],
    'goals' => ['icon' => 'fa-bullseye-arrow', 'label' => 'Goals'],
    'polls' => ['icon' => 'fa-square-poll-vertical', 'label' => 'Polls'],
    'volunteering' => ['icon' => 'fa-hands-helping', 'label' => 'Volunteer'],
    'groups' => ['icon' => 'fa-users', 'label' => 'Groups'],
    'resources' => ['icon' => 'fa-folder-open', 'label' => 'Resources'],
    'reviews' => ['icon' => 'fa-star', 'label' => 'Reviews'],
];
$currentFilterData = $filterLabels[$currentFilter] ?? $filterLabels['all'];
?>

<div class="feed-filter" id="feedFilter"
    data-filter="<?= htmlspecialchars($currentFilter) ?>"
    data-subfilter="<?= htmlspecialchars($currentSubFilter ?? '') ?>"
    data-location="<?= htmlspecialchars($locationMode) ?>"
    data-radius="<?= $currentRadius ?>"
    data-algo="<?= htmlspecialchars($algorithmMode) ?>"
    data-user-lat="<?= $userLat ?? '' ?>"
    data-user-lng="<?= $userLng ?? '' ?>"
    data-has-location="<?= $hasUserLocation ? '1' : '0' ?>">

    <!-- Desktop Filter Bar -->
    <div class="feed-filter-primary">
        <div class="feed-filter-algo-toggle">
            <button type="button" class="feed-filter-algo-btn <?= $algorithmMode === 'ranked' ? 'active' : '' ?>" data-algo="ranked" onclick="FeedFilter.setAlgorithm('ranked')" title="Personalized feed">
                <i class="fa-solid fa-sparkles"></i>
                <span>For You</span>
            </button>
            <button type="button" class="feed-filter-algo-btn <?= $algorithmMode === 'recent' ? 'active' : '' ?>" data-algo="recent" onclick="FeedFilter.setAlgorithm('recent')" title="Chronological feed">
                <i class="fa-solid fa-clock"></i>
                <span>Recent</span>
            </button>
        </div>

        <div class="feed-filter-divider"></div>

        <div class="feed-filter-scroll">
            <div class="feed-filter-pills">
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'all' ? 'active' : '' ?>" data-filter="all" onclick="FeedFilter.setFilter('all')">
                    <i class="fa-solid fa-stream"></i>
                    <span>All</span>
                </button>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'listings' ? 'active' : '' ?>" data-filter="listings" onclick="FeedFilter.setFilter('listings')">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                    <span>Listings</span>
                </button>
                <?php if ($hasEvents): ?>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'events' ? 'active' : '' ?>" data-filter="events" onclick="FeedFilter.setFilter('events')">
                    <i class="fa-solid fa-calendar-star"></i>
                    <span>Events</span>
                </button>
                <?php endif; ?>
                <?php if ($hasGoals): ?>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'goals' ? 'active' : '' ?>" data-filter="goals" onclick="FeedFilter.setFilter('goals')">
                    <i class="fa-solid fa-bullseye-arrow"></i>
                    <span>Goals</span>
                </button>
                <?php endif; ?>
                <?php if ($hasPolls): ?>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'polls' ? 'active' : '' ?>" data-filter="polls" onclick="FeedFilter.setFilter('polls')">
                    <i class="fa-solid fa-square-poll-vertical"></i>
                    <span>Polls</span>
                </button>
                <?php endif; ?>
                <?php if ($hasVolunteering): ?>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'volunteering' ? 'active' : '' ?>" data-filter="volunteering" onclick="FeedFilter.setFilter('volunteering')">
                    <i class="fa-solid fa-hands-helping"></i>
                    <span>Volunteering</span>
                </button>
                <?php endif; ?>
                <?php if ($hasGroups): ?>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'groups' ? 'active' : '' ?>" data-filter="groups" onclick="FeedFilter.setFilter('groups')">
                    <i class="fa-solid fa-users"></i>
                    <span>Groups</span>
                </button>
                <?php endif; ?>
                <?php if ($hasResources): ?>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'resources' ? 'active' : '' ?>" data-filter="resources" onclick="FeedFilter.setFilter('resources')">
                    <i class="fa-solid fa-folder-open"></i>
                    <span>Resources</span>
                </button>
                <?php endif; ?>
                <?php if ($hasReviews): ?>
                <button type="button" class="feed-filter-pill <?= $currentFilter === 'reviews' ? 'active' : '' ?>" data-filter="reviews" onclick="FeedFilter.setFilter('reviews')">
                    <i class="fa-solid fa-star"></i>
                    <span>Reviews</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hasUserLocation): ?>
        <div class="feed-filter-location">
            <button type="button" class="feed-filter-location-btn <?= $locationMode === 'nearby' ? 'active' : '' ?>" id="locationToggle" onclick="FeedFilter.toggleLocation()" title="<?= $locationMode === 'nearby' ? 'Near: ' . htmlspecialchars($userLocationName) : 'Filter by location' ?>">
                <i class="fa-solid fa-location-dot"></i>
                <span class="feed-filter-location-label"><?= $locationMode === 'nearby' ? 'Near Me' : 'Global' ?></span>
            </button>
        </div>
        <?php else: ?>
        <div class="feed-filter-location">
            <button type="button" class="feed-filter-location-btn disabled" title="Add location in profile" onclick="FeedFilter.showLocationPrompt()">
                <i class="fa-solid fa-location-dot"></i>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Secondary Row (Desktop) -->
    <div class="feed-filter-secondary" id="feedFilterSecondary">
        <div class="feed-filter-subgroup feed-filter-radius <?= $locationMode !== 'nearby' ? 'hidden' : '' ?>" data-parent="location">
            <div class="feed-filter-radius-header">
                <i class="fa-solid fa-map-marker-alt"></i>
                <span><?= htmlspecialchars($userLocationName ?? 'Your Location') ?></span>
            </div>
            <div class="feed-filter-radius-control">
                <label for="radiusSlider" class="govuk-visually-hidden">Search radius in kilometres</label>
                <input type="range" id="radiusSlider" class="feed-filter-radius-slider" min="10" max="500" step="10" value="<?= $currentRadius ?>"
                    aria-label="Search radius"
                    aria-valuemin="10" aria-valuemax="500" aria-valuenow="<?= $currentRadius ?>"
                    oninput="FeedFilter.updateRadiusPreview(this.value)"
                    onchange="FeedFilter.setRadius(this.value)">
                <div class="feed-filter-radius-labels">
                    <span>10km</span>
                    <span class="feed-filter-radius-value" id="radiusValue"><?= $currentRadius ?>km</span>
                    <span>500km</span>
                </div>
            </div>
        </div>

        <div class="feed-filter-subgroup <?= $currentFilter !== 'listings' ? 'hidden' : '' ?>" data-parent="listings">
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'listings' && (!$currentSubFilter || $currentSubFilter === 'all')) ? 'active' : '' ?>" data-subfilter="all" onclick="FeedFilter.setSubFilter('all')">All</button>
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'listings' && $currentSubFilter === 'offers') ? 'active' : '' ?>" data-subfilter="offers" onclick="FeedFilter.setSubFilter('offers')">
                <i class="fa-solid fa-gift"></i> Offers
            </button>
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'listings' && $currentSubFilter === 'requests') ? 'active' : '' ?>" data-subfilter="requests" onclick="FeedFilter.setSubFilter('requests')">
                <i class="fa-solid fa-hand"></i> Requests
            </button>
        </div>

        <?php if ($hasVolunteering): ?>
        <div class="feed-filter-subgroup <?= $currentFilter !== 'volunteering' ? 'hidden' : '' ?>" data-parent="volunteering">
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'volunteering' && (!$currentSubFilter || $currentSubFilter === 'all')) ? 'active' : '' ?>" data-subfilter="all" onclick="FeedFilter.setSubFilter('all')">All</button>
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'volunteering' && $currentSubFilter === 'opportunities') ? 'active' : '' ?>" data-subfilter="opportunities" onclick="FeedFilter.setSubFilter('opportunities')">
                <i class="fa-solid fa-briefcase"></i> Opportunities
            </button>
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'volunteering' && $currentSubFilter === 'host-requests') ? 'active' : '' ?>" data-subfilter="host-requests" onclick="FeedFilter.setSubFilter('host-requests')">
                <i class="fa-solid fa-building-ngo"></i> Host Requests
            </button>
        </div>
        <?php endif; ?>

        <?php if ($hasGroups): ?>
        <div class="feed-filter-subgroup <?= $currentFilter !== 'groups' ? 'hidden' : '' ?>" data-parent="groups">
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'groups' && (!$currentSubFilter || $currentSubFilter === 'my-groups')) ? 'active' : '' ?>" data-subfilter="my-groups" onclick="FeedFilter.setSubFilter('my-groups')">
                <i class="fa-solid fa-user-group"></i> My Groups
            </button>
            <button type="button" class="feed-filter-sub <?= ($currentFilter === 'groups' && $currentSubFilter === 'discover') ? 'active' : '' ?>" data-subfilter="discover" onclick="FeedFilter.setSubFilter('discover')">
                <i class="fa-solid fa-compass"></i> Discover
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.FeedFilter = (function() {
    'use strict';

    let currentFilter = '<?= htmlspecialchars($currentFilter) ?>';
    let currentSubFilter = <?= $currentSubFilter ? "'" . htmlspecialchars($currentSubFilter) . "'" : 'null' ?>;
    let locationMode = '<?= htmlspecialchars($locationMode) ?>';
    let currentRadius = <?= $currentRadius ?>;
    let algorithmMode = '<?= htmlspecialchars($algorithmMode) ?>';
    let panelOpen = false;
    let callbacks = [];

    const hasUserLocation = <?= $hasUserLocation ? 'true' : 'false' ?>;
    const userLocationName = '<?= htmlspecialchars($userLocationName ?? '') ?>';
    const basePath = '<?= $basePath ?>';

    const filterLabels = {
        'all': { icon: 'fa-stream', label: 'All' },
        'listings': { icon: 'fa-hand-holding-heart', label: 'Listings' },
        'events': { icon: 'fa-calendar-star', label: 'Events' },
        'goals': { icon: 'fa-bullseye-arrow', label: 'Goals' },
        'polls': { icon: 'fa-square-poll-vertical', label: 'Polls' },
        'volunteering': { icon: 'fa-hands-helping', label: 'Volunteer' },
        'groups': { icon: 'fa-users', label: 'Groups' },
        'resources': { icon: 'fa-folder-open', label: 'Resources' },
        'reviews': { icon: 'fa-star', label: 'Reviews' }
    };

    const filterContainer = document.getElementById('feedFilter');
    const panel = document.getElementById('ffPanel');
    const chevron = document.getElementById('ffChipChevron');
    const secondaryRow = document.getElementById('feedFilterSecondary');

    function haptic() {
        if (navigator.vibrate) navigator.vibrate(10);
    }

    function togglePanel() {
        panelOpen = !panelOpen;
        if (panel) panel.classList.toggle('open', panelOpen);
        if (chevron) chevron.classList.toggle('open', panelOpen);
        haptic();
    }

    function closePanel() {
        panelOpen = false;
        if (panel) panel.classList.remove('open');
        if (chevron) chevron.classList.remove('open');
    }

    function toggleAlgo() {
        algorithmMode = algorithmMode === 'ranked' ? 'recent' : 'ranked';
        localStorage.setItem('feedAlgorithmMode', algorithmMode);
        updateAlgoUI();
        updateURL();
        haptic();
        notifyChange();
    }

    function setAlgorithm(mode) {
        if (algorithmMode === mode) return;
        algorithmMode = mode;
        localStorage.setItem('feedAlgorithmMode', mode);
        updateAlgoUI();
        updateURL();
        haptic();
        notifyChange();
    }

    function setFilter(type) {
        if (currentFilter === type) {
            closePanel();
            return;
        }
        currentFilter = type;
        currentSubFilter = null;
        updatePillStates();
        updateChipLabel();
        updateSecondaryVisibility();
        closePanel();
        updateURL();
        haptic();
        notifyChange();
    }

    function setSubFilter(subtype) {
        if (currentSubFilter === subtype) return;
        currentSubFilter = subtype;
        updateSubPillStates();
        updateURL();
        haptic();
        notifyChange();
    }

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

    function setRadius(value) {
        currentRadius = parseInt(value);
        localStorage.setItem('feedRadius', currentRadius);
        document.querySelectorAll('#radiusValue').forEach(el => {
            if (el) el.textContent = currentRadius + 'km';
        });
        updateURL();
        haptic();
        notifyChange();
    }

    function updateRadiusPreview(value) {
        document.querySelectorAll('#radiusValue').forEach(el => {
            if (el) el.textContent = value + 'km';
        });
    }

    function showLocationPrompt() {
        if (confirm('Add your location in profile settings to filter content near you.\n\nGo to profile settings now?')) {
            window.location.href = basePath + '/settings/profile';
        }
    }

    function updatePillStates() {
        document.querySelectorAll('.feed-filter-pill, .ff-panel-item').forEach(pill => {
            pill.classList.toggle('active', pill.dataset.filter === currentFilter);
        });
    }

    function updateSubPillStates() {
        const activeGroup = document.querySelector(`.feed-filter-subgroup[data-parent="${currentFilter}"]`);
        if (!activeGroup) return;
        activeGroup.querySelectorAll('.feed-filter-sub').forEach(pill => {
            const sf = pill.dataset.subfilter;
            pill.classList.toggle('active', sf === currentSubFilter || (sf === 'all' && !currentSubFilter));
        });
    }

    function updateAlgoUI() {
        // Desktop
        document.querySelectorAll('.feed-filter-algo-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.algo === algorithmMode);
        });
        // Mobile chip
        const algoChip = document.querySelector('.ff-chip-algo');
        if (algoChip) {
            const icon = algoChip.querySelector('i');
            const span = algoChip.querySelector('span');
            if (icon) icon.className = 'fa-solid ' + (algorithmMode === 'ranked' ? 'fa-sparkles' : 'fa-clock');
            if (span) span.textContent = algorithmMode === 'ranked' ? 'For You' : 'Recent';
        }
    }

    function updateChipLabel() {
        const chipLabel = document.getElementById('ffChipLabel');
        const chipBtn = document.querySelector('.ff-chip-filter');
        const data = filterLabels[currentFilter] || filterLabels['all'];
        if (chipLabel) chipLabel.textContent = data.label;
        if (chipBtn) {
            const icon = chipBtn.querySelector('i:first-child');
            if (icon) icon.className = 'fa-solid ' + data.icon;
        }
    }

    function updateLocationUI() {
        const locBtn = document.getElementById('locationToggle');
        const mobileLoc = document.querySelector('.ff-chip-loc');

        if (locBtn) {
            locBtn.classList.toggle('active', locationMode === 'nearby');
            const label = locBtn.querySelector('.feed-filter-location-label');
            if (label) label.textContent = locationMode === 'nearby' ? 'Near Me' : 'Global';
        }
        if (mobileLoc) mobileLoc.classList.toggle('active', locationMode === 'nearby');
    }

    function updateSecondaryVisibility() {
        const hasSecondary = ['listings', 'volunteering', 'groups'].includes(currentFilter);
        const showLocationSlider = locationMode === 'nearby' && hasUserLocation;

        document.querySelectorAll('.feed-filter-subgroup').forEach(group => {
            group.classList.add('hidden');
        });

        let shouldShow = false;

        if (showLocationSlider) {
            const locGroup = document.querySelector('.feed-filter-radius');
            if (locGroup) { locGroup.classList.remove('hidden'); shouldShow = true; }
        }

        if (hasSecondary) {
            const activeGroup = document.querySelector(`.feed-filter-subgroup[data-parent="${currentFilter}"]`);
            if (activeGroup) {
                activeGroup.classList.remove('hidden');
                shouldShow = true;
                if (!currentSubFilter) {
                    const first = activeGroup.querySelector('.feed-filter-sub');
                    if (first) currentSubFilter = first.dataset.subfilter;
                }
                updateSubPillStates();
            }
        }

        if (secondaryRow) secondaryRow.classList.toggle('visible', shouldShow);
    }

    function updateURL() {
        const params = new URLSearchParams(window.location.search);

        if (currentFilter !== 'all') params.set('filter', currentFilter);
        else params.delete('filter');

        if (currentSubFilter && currentSubFilter !== 'all') params.set('subfilter', currentSubFilter);
        else params.delete('subfilter');

        if (locationMode !== 'global') {
            params.set('location', locationMode);
            params.set('radius', currentRadius);
        } else {
            params.delete('location');
            params.delete('radius');
        }

        if (algorithmMode !== 'ranked') params.set('algo', algorithmMode);
        else params.delete('algo');

        const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newURL);
    }

    function onFilterChange(callback) {
        callbacks.push(callback);
        return () => { callbacks = callbacks.filter(cb => cb !== callback); };
    }

    function notifyChange() {
        const state = getActiveFilter();
        callbacks.forEach(cb => { try { cb(state); } catch (e) { console.error(e); } });
        filterContainer.dispatchEvent(new CustomEvent('filterchange', { detail: state, bubbles: true }));
    }

    function getActiveFilter() {
        return {
            filter: currentFilter,
            subFilter: currentSubFilter,
            locationMode: locationMode,
            radius: currentRadius,
            algorithmMode: algorithmMode,
            locationName: userLocationName
        };
    }

    function init() {
        updateSecondaryVisibility();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        setFilter,
        setSubFilter,
        setAlgorithm,
        toggleAlgo,
        toggleLocation,
        togglePanel,
        setRadius,
        updateRadiusPreview,
        getActiveFilter,
        onFilterChange,
        showLocationPrompt
    };
})();
</script>
