<?php
// Members Directory - Glassmorphism 2025
$pageTitle = "Community Directory";
$pageSubtitle = "Connect with fellow community members";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Directory - Connect with Members');
Nexus\Core\SEO::setDescription('Browse and connect with members of your local community. Find neighbors, discover skills, and build meaningful connections.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="members-glass-wrapper">


        <!-- Smart Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">Community Directory</h1>
            <?php if (\Nexus\Services\MemberRankingService::isEnabled()): ?>
                <div class="mte-members--rank-badge">
                    <i class="fa-solid fa-diagram-project"></i>
                    <span>CommunityRank Active</span>
                </div>
            <?php endif; ?>
            <p class="nexus-welcome-subtitle">Browse and connect with members of your local community. Find neighbors, discover skills, and build meaningful connections.</p>

            <div class="nexus-smart-buttons">
                <a href="<?= $basePath ?>/members" class="nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-users"></i>
                    <span>All Members</span>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= $basePath ?>/members?filter=nearby" class="nexus-smart-btn nexus-smart-btn-secondary" id="nearby-btn">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Nearby</span>
                    </a>
                    <a href="<?= $basePath ?>/members?filter=new" class="nexus-smart-btn nexus-smart-btn-outline">
                        <i class="fa-solid fa-star"></i>
                        <span>New Members</span>
                    </a>
                <?php endif; ?>
                <a href="<?= $basePath ?>/members?filter=active" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-fire"></i>
                    <span>Most Active</span>
                </a>
                <?php
                // Show federation link if federation is enabled for this tenant
                $tenantId = \Nexus\Core\TenantContext::getId();
                $federationEnabled = \Nexus\Services\FederationFeatureService::isGloballyEnabled()
                    && \Nexus\Services\FederationFeatureService::isTenantWhitelisted($tenantId)
                    && \Nexus\Services\FederationFeatureService::isTenantFederationEnabled($tenantId);
                if ($federationEnabled && isset($_SESSION['user_id'])):
                ?>
                    <a href="<?= $basePath ?>/federation/members" class="nexus-smart-btn nexus-smart-btn-outline mte-members--federation-btn">
                        <i class="fa-solid fa-network-wired"></i>
                        <span>Partner Timebanks</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nearby Controls Panel -->
        <div class="nearby-controls<?= ($nearbyMode ?? false) ? ' active' : '' ?>" id="nearby-controls">
            <div class="nearby-header">
                <div class="nearby-title">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    <span>Find Nearby Members</span>
                </div>
                <div class="location-status detecting" id="location-status">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Loading your location...</span>
                </div>
            </div>

            <div class="radius-control">
                <label for="radius-slider" class="radius-label">Search Radius:</label>
                <div class="radius-slider-wrapper">
                    <input type="range"
                        class="radius-slider"
                        id="radius-slider"
                        min="1"
                        max="100"
                        value="25"
                        step="1"
                        aria-valuemin="1"
                        aria-valuemax="100"
                        aria-valuenow="25"
                        aria-valuetext="25 kilometers">
                    <span class="radius-value" id="radius-value" aria-hidden="true">25 km</span>
                </div>
            </div>
        </div>

        <!-- Glass Search Card -->
        <div class="glass-search-card">
            <div class="mte-members--search-layout">
                <div class="mte-members--search-header">
                    <h2 class="mte-members--search-title">Find Members</h2>
                    <span id="members-count" class="mte-members--search-count">
                        Showing <?= count($members) ?> of <?= $total_members ?? count($members) ?> members
                    </span>
                </div>

                <div class="mte-members--search-wrapper">
                    <label for="member-search" class="visually-hidden">Search members</label>
                    <input type="text" id="member-search" placeholder="Search by name, bio, location, skills..."
                        class="glass-search-input" aria-label="Search by name, bio, location, or skills">
                    <i class="fa-solid fa-search mte-members--search-icon" aria-hidden="true"></i>
                    <div id="search-spinner" class="spinner mte-members--search-spinner hidden" aria-hidden="true"></div>
                </div>
            </div>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <i class="fa-solid fa-users mte-members--section-icon"></i>
            <h2>Community Members</h2>
        </div>

        <!-- Members Grid Skeleton (shown during search/loading) -->
        <div id="members-skeleton" class="members-grid mte-members--skeleton hidden" aria-label="Loading members">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="member-card-skeleton">
                <div class="skeleton skeleton-avatar"></div>
                <div class="skeleton skeleton-name"></div>
                <div class="skeleton skeleton-location"></div>
                <div class="skeleton skeleton-bio"></div>
                <div class="skeleton skeleton-bio"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Members Grid -->
        <div id="members-grid" class="members-grid">
            <?php if (!empty($members)): ?>
                <?php $memberIndex = 0; ?>
                <?php foreach ($members as $member): ?>
                    <?php $memberOrgRoles = $orgLeadership[$member['id']] ?? []; ?>
                    <?php // First 6 members are above-the-fold, use eager loading to prevent FOUC ?>
                    <?= render_glass_member_card($member, $basePath, $memberOrgRoles, $memberIndex < 6) ?>
                    <?php $memberIndex++; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-empty-state">
                    <div class="mte-members--empty-emoji">👥</div>
                    <h3 class="mte-members--empty-title">No members found</h3>
                    <p class="mte-members--empty-text">Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Infinite Scroll Sentinel & Spinner -->
        <div id="infinite-scroll-trigger" class="mte-members--scroll-trigger"></div>

        <div id="load-more-spinner" class="mte-members--load-spinner hidden">
            <i class="fa-solid fa-spinner fa-spin mte-members--load-spinner-icon"></i>
        </div>

    </div><!-- #members-glass-wrapper -->
</div>

<?php
/**
 * Render a glass-style member card
 * @param array $member Member data
 * @param string $basePath Base URL path
 * @param array $orgRoles Organization roles for this member
 * @param bool $eager Use loading="eager" for above-the-fold avatars (first ~6 visible)
 */
function render_glass_member_card($member, $basePath, $orgRoles = [], $eager = false)
{
    ob_start();

    // Ensure required fields exist - prefer display_name, then construct from first/last, then fallback
    $memberName = $member['display_name'] ?? $member['name'] ?? null;
    if (empty($memberName) || trim($memberName) === '' || trim($memberName) === ' ') {
        // Construct from first_name and last_name
        $firstName = trim($member['first_name'] ?? '');
        $lastName = trim($member['last_name'] ?? '');
        $memberName = trim($firstName . ' ' . $lastName);
    }
    if (empty($memberName) || trim($memberName) === '') {
        $memberName = 'Member';
    }
    $fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=0891b2&color=fff&size=200';

    // Use database avatar_url if it exists and is not empty, otherwise use fallback
    $avatarUrl = (!empty($member['avatar_url']) && trim($member['avatar_url']) !== '') ? $member['avatar_url'] : $fallbackUrl;

    $profileUrl = $basePath . '/profile/' . $member['id'] . '?from=members';

    // Check online status - active within 5 minutes
    $memberLastActive = $member['last_active_at'] ?? null;
    $isMemberOnline = $memberLastActive && (strtotime($memberLastActive) > strtotime('-5 minutes'));

    // For above-the-fold avatars, use eager loading to prevent FOUC delay
    $avatarAttrs = $eager ? ['loading' => 'eager', 'fetchpriority' => 'high'] : [];
?>
    <a href="<?= $profileUrl ?>" class="glass-member-card">
        <div class="card-body">
            <div class="avatar-container">
                <div class="avatar-ring"></div>
                <?= webp_avatar($avatarUrl, $memberName, 80, $avatarAttrs) ?>
                <?php if ($isMemberOnline): ?>
                    <span class="online-indicator mte-members--online-indicator" title="Active now"></span>
                <?php endif; ?>
            </div>

            <h3 class="member-name">
                <?= htmlspecialchars($memberName) ?>
            </h3>

            <?php if (!empty($orgRoles)): ?>
                <div class="member-org-roles mte-members--org-roles">
                    <?php foreach (array_slice($orgRoles, 0, 2) as $org): ?>
                        <span class="org-role-badge mte-members--org-badge <?= $org['role'] === 'owner' ? 'mte-members--org-badge-owner' : 'mte-members--org-badge-admin' ?>">
                            <i class="fa-solid <?= $org['role'] === 'owner' ? 'fa-crown' : 'fa-shield' ?> mte-members--org-badge-icon"></i>
                            <?= htmlspecialchars(strlen($org['org_name']) > 15 ? substr($org['org_name'], 0, 15) . '...' : $org['org_name']) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($orgRoles) > 2): ?>
                        <span class="mte-members--org-more">+<?= count($orgRoles) - 2 ?> more</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="member-location">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($member['location'] ?: 'Unknown Location') ?>
            </div>

            <div class="member-since">
                <i class="fa-solid fa-calendar"></i>
                Member since <?= !empty($member['created_at']) ? date('M Y', strtotime($member['created_at'])) : 'Unknown' ?>
            </div>

            <span class="view-profile-btn">
                View Profile
            </span>
        </div>
    </a>
<?php
    return ob_get_clean();
}
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('member-search');
        const grid = document.getElementById('members-grid');
        const skeleton = document.getElementById('members-skeleton');
        const countLabel = document.getElementById('members-count');
        const spinner = document.getElementById('search-spinner');
        const nearbyBtn = document.getElementById('nearby-btn');
        const nearbyControls = document.getElementById('nearby-controls');
        const radiusSlider = document.getElementById('radius-slider');
        const radiusValue = document.getElementById('radius-value');
        const locationStatus = document.getElementById('location-status');

        let debounceTimer;
        let nearbyMode = <?= $nearbyMode ? 'true' : 'false' ?>;
        let userLocation = null;

        // Search functionality
        searchInput.addEventListener('keyup', function(e) {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();

            spinner.classList.remove('hidden');

            debounceTimer = setTimeout(() => {
                fetchMembers(query);
            }, 300);
        });

        // Radius slider
        if (radiusSlider) {
            radiusSlider.addEventListener('input', function() {
                radiusValue.textContent = this.value + ' km';
            });

            radiusSlider.addEventListener('change', function() {
                if (nearbyMode) {
                    fetchNearbyMembers();
                }
            });
        }

        // Initialize nearby mode if active
        if (nearbyMode) {
            if (nearbyBtn) nearbyBtn.classList.add('nearby-active');

            // IMPORTANT: Don't auto-fetch on page load
            if (window.location.search.includes('filter=nearby')) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, '', cleanUrl);
            }
        }

        function updateLocationStatus(type, message) {
            if (!locationStatus) return;
            locationStatus.className = 'location-status ' + type;
            let icon = '';
            if (type === 'detecting') icon = '<i class="fa-solid fa-spinner fa-spin"></i>';
            else if (type === 'success') icon = '<i class="fa-solid fa-check-circle"></i>';
            else if (type === 'error') icon = '<i class="fa-solid fa-exclamation-circle"></i>';
            locationStatus.innerHTML = icon + '<span>' + message + '</span>';
        }

        function fetchNearbyMembers() {
            const radius = radiusSlider ? radiusSlider.value : 25;
            spinner.classList.remove('hidden');
            updateLocationStatus('detecting', 'Finding nearby members...');

            // Show skeleton during nearby search
            if (skeleton) {
                skeleton.classList.remove('hidden');
                grid.classList.add('hidden');
            }

            const url = window.location.pathname + '?filter=nearby&radius=' + radius;

            fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('HTTP error ' + res.status);
                    }
                    return res.json();
                })
                .then(data => {
                    if (data && data.success) {
                        userLocation = data.userLocation;
                        updateLocationStatus('success', 'Using: ' + (userLocation || 'Your profile location'));
                        renderNearbyGrid(data.data || []);
                        countLabel.textContent = `Showing ${(data.data || []).length} nearby members`;
                    } else if (data && data.error === 'no_location') {
                        showNoLocationError();
                    } else if (data && data.error) {
                        showLoginError();
                    } else {
                        console.error('Unexpected response:', data);
                        renderNearbyGrid([]);
                        updateLocationStatus('success', 'No nearby members with coordinates');
                    }
                    spinner.classList.add('hidden');
                    // Hide skeleton, show grid
                    if (skeleton) {
                        skeleton.classList.add('hidden');
                        grid.classList.remove('hidden');
                    }
                })
                .catch(err => {
                    console.error('Nearby fetch error:', err);
                    spinner.classList.add('hidden');
                    updateLocationStatus('error', 'Connection error');
                    // Hide skeleton on error
                    if (skeleton) {
                        skeleton.classList.add('hidden');
                        grid.classList.remove('hidden');
                    }
                    // Show helpful error in grid
                    grid.innerHTML = `
                <div class="glass-empty-state">
                    <div class="mte-members--empty-emoji">⚠️</div>
                    <h3 class="mte-members--empty-title">Connection Error</h3>
                    <p class="mte-members--empty-text">Unable to load nearby members. Please try again.</p>
                    <button onclick="location.reload()" class="view-profile-btn mte-members--retry-btn">
                        <i class="fa-solid fa-refresh"></i> Retry
                    </button>
                </div>
            `;
                });
        }

        function fetchMembers(query) {
            const url = query.length > 0 ?
                window.location.pathname + '?q=' + encodeURIComponent(query) :
                window.location.pathname;

            // Show skeleton, hide grid during search
            if (skeleton) {
                skeleton.classList.remove('hidden');
                grid.classList.add('hidden');
            }

            fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => {
                    if (res.headers.get('content-type')?.includes('json')) {
                        return res.json();
                    } else {
                        if (query.length === 0) {
                            window.location.reload();
                            return null;
                        }
                        return null;
                    }
                })
                .then(data => {
                    if (!data) return;
                    renderGrid(data.data);
                    countLabel.textContent = `Showing ${data.data.length} members`;
                    spinner.classList.add('hidden');
                    // Hide skeleton, show grid
                    if (skeleton) {
                        skeleton.classList.add('hidden');
                        grid.classList.remove('hidden');
                    }
                })
                .catch(err => {
                    console.error(err);
                    spinner.classList.add('hidden');
                    // Hide skeleton on error too
                    if (skeleton) {
                        skeleton.classList.add('hidden');
                        grid.classList.remove('hidden');
                    }
                });
        }

        function renderNearbyGrid(members) {
            // Clear grid and render results
            grid.innerHTML = '';

            if (members.length === 0) {
                grid.innerHTML = `
                <div class="glass-empty-state">
                    <div class="mte-members--empty-emoji">📍</div>
                    <h3 class="mte-members--empty-title">No nearby members found</h3>
                    <p class="mte-members--empty-text">Try increasing your search radius or check back later.</p>
                </div>
            `;
                return;
            }

            const basePath = "<?= $basePath ?>";

            members.forEach(member => {
                // Ensure member name exists - prefer display_name, then construct from first/last, then fallback
                const memberName = member.display_name || member.name || (member.first_name || member.last_name ? `${member.first_name || ''} ${member.last_name || ''}`.trim() : 'Member') || 'Member';
                const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(memberName)}&background=0891b2&color=fff&size=200`;
                // Use fallback ONLY if avatar_url is null/empty, not for any other case
                let avatarUrl = (member.avatar_url && member.avatar_url.trim() !== '') ? member.avatar_url : fallbackUrl;

                const dateStr = member.created_at ? new Date(member.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    year: 'numeric'
                }) : 'Unknown';
                const distance = member.distance_km ? parseFloat(member.distance_km).toFixed(1) : null;
                const profileUrl = `${basePath}/profile/${member.id}?from=members`;

                // Check online status - active within 5 minutes
                const isOnline = member.last_active_at && (new Date(member.last_active_at) > new Date(Date.now() - 5 * 60 * 1000));
                const onlineIndicator = isOnline ? `<span class="online-indicator mte-members--online-indicator" title="Active now"></span>` : '';

                const card = document.createElement('a');
                card.href = profileUrl;
                card.className = 'glass-member-card';
                card.innerHTML = `
                <div class="card-body">
                    <div class="avatar-container">
                        <div class="avatar-ring"></div>
                        <img src="${avatarUrl}"
                             onerror="this.onerror=null; this.src='${fallbackUrl}'"
                             alt="${escapeHtml(memberName)}"
                             class="avatar-img"
                             loading="lazy">
                        ${onlineIndicator}
                    </div>
                    <h3 class="member-name">${escapeHtml(memberName)}</h3>
                    ${distance ? `<div class="distance-badge"><i class="fa-solid fa-route"></i> ${distance} km away</div>` : ''}
                    <div class="member-location">
                        <i class="fa-solid fa-location-dot"></i>
                        ${escapeHtml(member.location || 'Unknown Location')}
                    </div>
                    <div class="member-since">
                        <i class="fa-solid fa-calendar"></i>
                        Member since ${dateStr}
                    </div>
                    <span class="view-profile-btn">View Profile</span>
                </div>
            `;
                grid.appendChild(card);
            });
        }

        function renderGrid(members, append = false) {
            if (!append) {
                grid.innerHTML = '';
            }

            if (members.length === 0 && !append) {
                grid.innerHTML = `
                <div class="glass-empty-state">
                    <div class="mte-members--empty-emoji">🔍</div>
                    <h3 class="mte-members--empty-title">No members found</h3>
                    <p class="mte-members--empty-text">Try a different search term.</p>
                </div>
            `;
                return;
            }

            const basePath = "<?= $basePath ?>";

            members.forEach(member => {
                // Ensure member name exists - prefer display_name, then construct from first/last, then fallback
                const memberName = member.display_name || member.name || (member.first_name || member.last_name ? `${member.first_name || ''} ${member.last_name || ''}`.trim() : 'Member') || 'Member';
                const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(memberName)}&background=0891b2&color=fff&size=200`;
                // Use fallback ONLY if avatar_url is null/empty, not for any other case
                let avatarUrl = (member.avatar_url && member.avatar_url.trim() !== '') ? member.avatar_url : fallbackUrl;
                const dateStr = member.created_at ? new Date(member.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    year: 'numeric'
                }) : 'Unknown';
                const profileUrl = `${basePath}/profile/${member.id}?from=members`;

                // Check online status - active within 5 minutes
                const isOnline = member.last_active_at && (new Date(member.last_active_at) > new Date(Date.now() - 5 * 60 * 1000));
                const onlineIndicator = isOnline ? `<span class="online-indicator mte-members--online-indicator" title="Active now"></span>` : '';

                const card = document.createElement('a');
                card.href = profileUrl;
                card.className = 'glass-member-card';
                card.innerHTML = `
                <div class="card-body">
                    <div class="avatar-container">
                        <div class="avatar-ring"></div>
                        <img src="${avatarUrl}"
                             onerror="this.onerror=null; this.src='${fallbackUrl}'"
                             alt="${escapeHtml(memberName)}"
                             class="avatar-img"
                             loading="lazy">
                        ${onlineIndicator}
                    </div>
                    <h3 class="member-name">${escapeHtml(memberName)}</h3>
                    <div class="member-location">
                        <i class="fa-solid fa-location-dot"></i>
                        ${escapeHtml(member.location || 'Unknown Location')}
                    </div>
                    <div class="member-since">
                        <i class="fa-solid fa-calendar"></i>
                        Member since ${dateStr}
                    </div>
                    <span class="view-profile-btn">View Profile</span>
                </div>
            `;
                grid.appendChild(card);
            });
        }

        function showNoLocationError() {
            updateLocationStatus('error', 'No location in profile');
            grid.innerHTML = `
            <div class="glass-empty-state">
                <div class="mte-members--empty-emoji">📍</div>
                <h3 class="mte-members--empty-title">Location Required</h3>
                <p class="mte-members--empty-text">Please add your location in your profile settings to find nearby members.</p>
                <div class="mte-members--error-actions">
                    <a href="<?= $basePath ?>/profile/edit" class="view-profile-btn mte-members--retry-btn">
                        <i class="fa-solid fa-user-pen"></i> Edit Profile
                    </a>
                    <a href="<?= $basePath ?>/members" class="view-profile-btn">
                        View All Members
                    </a>
                </div>
            </div>
        `;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // ============================================
        // INFINITE SCROLL
        // ============================================
        const infiniteScrollTrigger = document.getElementById('infinite-scroll-trigger');
        const loadMoreSpinner = document.getElementById('load-more-spinner');
        let currentOffset = <?= count($members) ?>;
        const initialTotal = <?= $total_members ?? count($members) ?>;
        const batchSize = 30;
        let isLoading = false;
        let hasMore = currentOffset < initialTotal;

        if (infiniteScrollTrigger && hasMore) {
            const observerOptions = {
                root: null, // viewport
                rootMargin: '100px', // fetch before user hits absolute bottom
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && hasMore && !isLoading) {
                        loadMoreMembers();
                    }
                });
            }, observerOptions);

            observer.observe(infiniteScrollTrigger);

            function loadMoreMembers() {
                isLoading = true;
                loadMoreSpinner.classList.remove('hidden');

                const url = window.location.pathname + '?loadmore=1&offset=' + currentOffset + '&limit=' + batchSize;

                fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        loadMoreSpinner.classList.add('hidden');
                        isLoading = false;

                        if (data && data.data && data.data.length > 0) {
                            // Append new members
                            renderGrid(data.data, true);

                            currentOffset += data.data.length;
                            countLabel.textContent = `Showing ${currentOffset} of ${data.total} members`;

                            if (!data.hasMore) {
                                hasMore = false;
                                observer.disconnect(); // Stop observing if no more data
                            }
                        } else {
                            hasMore = false;
                            observer.disconnect();
                        }
                    })
                    .catch(err => {
                        console.error('Infinite scroll error:', err);
                        loadMoreSpinner.classList.add('hidden');
                        isLoading = false;
                    });
            }
        }
    });
</script>

<script>
    // ============================================
    // GOLD STANDARD - Native App Features
    // ============================================

    // Offline Indicator
    (function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        function handleOffline() {
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }

        function handleOnline() {
            banner.classList.remove('visible');
        }

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        if (!navigator.onLine) {
            handleOffline();
        }
    })();

    // Form Submission Offline Protection
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to submit.');
                return;
            }
        });
    });

    // Button Press States
    document.querySelectorAll('.htb-btn, button, .nexus-smart-btn, .view-profile-btn, .page-btn').forEach(btn => {
        btn.addEventListener('pointerdown', function() {
            this.style.transform = 'scale(0.96)';
        });
        btn.addEventListener('pointerup', function() {
            this.style.transform = '';
        });
        btn.addEventListener('pointerleave', function() {
            this.style.transform = '';
        });
    });

    // Dynamic Theme Color
    (function initDynamicThemeColor() {
        const metaTheme = document.querySelector('meta[name="theme-color"]');
        if (!metaTheme) {
            const meta = document.createElement('meta');
            meta.name = 'theme-color';
            meta.content = '#06b6d4';
            document.head.appendChild(meta);
        }

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', isDark ? '#0f172a' : '#06b6d4');
            }
        }

        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        updateThemeColor();
    })();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>