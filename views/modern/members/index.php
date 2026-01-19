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
                <div style="display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(6, 182, 212, 0.15)); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 20px; padding: 4px 12px; font-size: 0.75rem; color: #10b981; margin-bottom: 8px;">
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
                    <a href="<?= $basePath ?>/federation/members" class="nexus-smart-btn nexus-smart-btn-outline" style="border-color: rgba(139, 92, 246, 0.3); color: #8b5cf6;">
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
                <span class="radius-label">Search Radius:</span>
                <div class="radius-slider-wrapper">
                    <input type="range"
                        class="radius-slider"
                        id="radius-slider"
                        min="1"
                        max="100"
                        value="25"
                        step="1">
                    <span class="radius-value" id="radius-value">25 km</span>
                </div>
            </div>
        </div>

        <!-- Glass Search Card -->
        <div class="glass-search-card">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; flex-wrap: wrap; gap: 10px;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0;">Find Members</h2>
                    <span id="members-count" style="font-size: 0.9rem; font-weight: 600; color: var(--htb-text-muted);">
                        Showing <?= count($members) ?> of <?= $total_members ?? count($members) ?> members
                        <!-- DEBUG: TenantID=<?= \Nexus\Core\TenantContext::getId() ?> -->
                    </span>
                </div>

                <div style="position: relative; width: 100%;">
                    <input type="text" id="member-search" placeholder="Search by name, bio, location, skills..."
                        class="glass-search-input">
                    <i class="fa-solid fa-search" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 1rem;"></i>
                    <div id="search-spinner" class="spinner" style="display: none; position: absolute; right: 18px; top: 50%; transform: translateY(-50%);"></div>
                </div>
            </div>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <i class="fa-solid fa-users" style="color: #06b6d4; font-size: 1.1rem;"></i>
            <h2>Community Members</h2>
        </div>

        <!-- Members Grid Skeleton (shown during search/loading) -->
        <div id="members-skeleton" class="members-grid" style="display: none;" aria-label="Loading members">
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
                <?php foreach ($members as $member): ?>
                    <?php $memberOrgRoles = $orgLeadership[$member['id']] ?? []; ?>
                    <?= render_glass_member_card($member, $basePath, $memberOrgRoles) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">👥</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No members found</h3>
                    <p style="color: var(--htb-text-muted);">Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Infinite Scroll Sentinel & Spinner -->
        <div id="infinite-scroll-trigger" style="height: 20px; margin-bottom: 20px;"></div>

        <div id="load-more-spinner" style="display: none; justify-content: center; margin-bottom: 40px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; color: var(--htb-accent);"></i>
        </div>

    </div><!-- #members-glass-wrapper -->
</div>

<?php
function render_glass_member_card($member, $basePath, $orgRoles = [])
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
?>
    <a href="<?= $profileUrl ?>" class="glass-member-card">
        <div class="card-body">
            <div class="avatar-container">
                <div class="avatar-ring"></div>
                <?= webp_avatar($avatarUrl, $memberName, 80) ?>
                <?php if ($isMemberOnline): ?>
                    <span class="online-indicator" style="position:absolute;bottom:4px;right:4px;width:16px;height:16px;background:#10b981;border:3px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(16,185,129,0.4);" title="Active now"></span>
                <?php endif; ?>
            </div>

            <h3 class="member-name">
                <?= htmlspecialchars($memberName) ?>
            </h3>

            <?php if (!empty($orgRoles)): ?>
                <div class="member-org-roles" style="display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-bottom: 10px;">
                    <?php foreach (array_slice($orgRoles, 0, 2) as $org): ?>
                        <span class="org-role-badge <?= $org['role'] ?>" style="
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 4px 10px;
                    border-radius: 12px;
                    font-size: 0.7rem;
                    font-weight: 600;
                    <?php if ($org['role'] === 'owner'): ?>
                    background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(245, 158, 11, 0.15));
                    color: #b45309;
                    <?php else: ?>
                    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(124, 58, 237, 0.15));
                    color: #7c3aed;
                    <?php endif; ?>
                ">
                            <i class="fa-solid <?= $org['role'] === 'owner' ? 'fa-crown' : 'fa-shield' ?>" style="font-size: 0.65rem;"></i>
                            <?= htmlspecialchars(strlen($org['org_name']) > 15 ? substr($org['org_name'], 0, 15) . '...' : $org['org_name']) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($orgRoles) > 2): ?>
                        <span style="font-size: 0.7rem; color: var(--htb-text-muted);">+<?= count($orgRoles) - 2 ?> more</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="member-location">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($member['location'] ?: 'Unknown Location') ?>
            </div>

            <div class="member-since">
                <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i>
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

            spinner.style.display = 'block';

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
            spinner.style.display = 'block';
            updateLocationStatus('detecting', 'Finding nearby members...');

            // Show skeleton during nearby search
            if (skeleton) {
                skeleton.style.display = 'grid';
                grid.style.display = 'none';
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
                    spinner.style.display = 'none';
                    // Hide skeleton, show grid
                    if (skeleton) {
                        skeleton.style.display = 'none';
                        grid.style.display = 'grid';
                    }
                })
                .catch(err => {
                    console.error('Nearby fetch error:', err);
                    spinner.style.display = 'none';
                    updateLocationStatus('error', 'Connection error');
                    // Hide skeleton on error
                    if (skeleton) {
                        skeleton.style.display = 'none';
                        grid.style.display = 'grid';
                    }
                    // Show helpful error in grid
                    grid.innerHTML = `
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">⚠️</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">Connection Error</h3>
                    <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Unable to load nearby members. Please try again.</p>
                    <button onclick="location.reload()" class="view-profile-btn" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #06b6d4, #22d3ee); color: white; border: none; cursor: pointer;">
                        <i class="fa-solid fa-refresh" style="margin-right: 6px;"></i> Retry
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
                skeleton.style.display = 'grid';
                grid.style.display = 'none';
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
                    spinner.style.display = 'none';
                    // Hide skeleton, show grid
                    if (skeleton) {
                        skeleton.style.display = 'none';
                        grid.style.display = 'grid';
                    }
                })
                .catch(err => {
                    console.error(err);
                    spinner.style.display = 'none';
                    // Hide skeleton on error too
                    if (skeleton) {
                        skeleton.style.display = 'none';
                        grid.style.display = 'grid';
                    }
                });
        }

        function renderNearbyGrid(members) {
            // Clear grid and render results
            grid.innerHTML = '';

            if (members.length === 0) {
                grid.innerHTML = `
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📍</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No nearby members found</h3>
                    <p style="color: var(--htb-text-muted);">Try increasing your search radius or check back later.</p>
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
                const onlineIndicator = isOnline ? `<span class="online-indicator" style="position:absolute;bottom:4px;right:4px;width:16px;height:16px;background:#10b981;border:3px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(16,185,129,0.4);" title="Active now"></span>` : '';

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
                        <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i>
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
                    <div style="font-size: 4rem; margin-bottom: 20px;">🔍</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No members found</h3>
                    <p style="color: var(--htb-text-muted);">Try a different search term.</p>
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
                const onlineIndicator = isOnline ? `<span class="online-indicator" style="position:absolute;bottom:4px;right:4px;width:16px;height:16px;background:#10b981;border:3px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(16,185,129,0.4);" title="Active now"></span>` : '';

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
                        <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i>
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
                <div style="font-size: 4rem; margin-bottom: 20px;">📍</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">Location Required</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Please add your location in your profile settings to find nearby members.</p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= $basePath ?>/profile/edit" class="view-profile-btn" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #06b6d4, #22d3ee); color: white; border: none;">
                        <i class="fa-solid fa-user-pen" style="margin-right: 6px;"></i> Edit Profile
                    </a>
                    <a href="<?= $basePath ?>/members" class="view-profile-btn" style="display: inline-block; padding: 12px 24px;">
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
                loadMoreSpinner.style.display = 'flex';

                const url = window.location.pathname + '?loadmore=1&offset=' + currentOffset + '&limit=' + batchSize;

                fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        loadMoreSpinner.style.display = 'none';
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
                        loadMoreSpinner.style.display = 'none';
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