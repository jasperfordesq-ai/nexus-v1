<?php
// Federation Members Directory - WCAG 2.1 AA Compliant
// CSS extracted to civicone-federation.css
$pageTitle = $pageTitle ?? "Federated Members";
$pageSubtitle = "Connect with members from partner timebanks";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Members - Partner Timebank Directory');
Nexus\Core\SEO::setDescription('Browse and connect with members from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$members = $members ?? [];
$partnerTenants = $partnerTenants ?? [];
$filters = $filters ?? [];
$partnerships = $partnerships ?? [];

// Calculate active filters count
$activeFiltersCount = 0;
if (!empty($filters['tenant_id'])) $activeFiltersCount++;
if (!empty($filters['service_reach'])) $activeFiltersCount++;
if (!empty($filters['skills'])) $activeFiltersCount++;
if (!empty($filters['location'])) $activeFiltersCount++;
if (!empty($filters['messaging_enabled'])) $activeFiltersCount++;
if (!empty($filters['transactions_enabled'])) $activeFiltersCount++;

$searchStats = $searchStats ?? [];
$popularSkills = $popularSkills ?? [];
$selectedSkills = [];
if (!empty($filters['skills'])) {
    $selectedSkills = is_array($filters['skills'])
        ? $filters['skills']
        : array_map('trim', explode(',', $filters['skills']));
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-glass-wrapper">

        <!-- Back to Members -->
        <a href="<?= $basePath ?>/members" class="back-link">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Local Members
        </a>

        <!-- Welcome Hero -->
        <div class="nexus-welcome-hero" role="banner">
            <div class="federation-badge">
                <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
                <span>Federation Network</span>
            </div>
            <h1 class="nexus-welcome-title">Federated Members</h1>
            <p class="nexus-welcome-subtitle">
                Discover members from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
                Connect, collaborate, and exchange services across communities.
            </p>
        </div>

        <!-- Search Stats Bar -->
        <?php if (!empty($searchStats) && ($searchStats['total_members'] ?? 0) > 0): ?>
        <div class="search-stats" role="status" aria-label="Federation statistics">
            <div class="stat-item">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <strong><?= number_format($searchStats['total_members']) ?></strong> federated members
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-laptop-house" aria-hidden="true"></i>
                <strong><?= number_format($searchStats['remote_available']) ?></strong> offer remote services
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-car" aria-hidden="true"></i>
                <strong><?= number_format($searchStats['travel_available']) ?></strong> will travel
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-comments" aria-hidden="true"></i>
                <strong><?= number_format($searchStats['messaging_enabled']) ?></strong> accept messages
            </div>
        </div>
        <?php endif; ?>

        <!-- Search & Filters -->
        <div class="glass-search-card" role="search" aria-label="Search federated members">
            <div class="fed-search-layout">
                <div class="fed-search-header">
                    <h2 class="fed-search-title">
                        <i class="fa-solid fa-search" aria-hidden="true"></i>
                        Find Federated Members
                    </h2>
                    <div class="fed-search-meta">
                        <label for="sort-filter" class="visually-hidden">Sort members by</label>
                        <select id="sort-filter" class="sort-select">
                            <option value="name" <?= ($filters['sort'] ?? 'name') === 'name' ? 'selected' : '' ?>>Sort: Name</option>
                            <option value="recent" <?= ($filters['sort'] ?? '') === 'recent' ? 'selected' : '' ?>>Sort: Newest</option>
                            <option value="active" <?= ($filters['sort'] ?? '') === 'active' ? 'selected' : '' ?>>Sort: Most Active</option>
                        </select>
                        <span id="members-count" class="fed-results-count" role="status" aria-live="polite">
                            <?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?> found
                        </span>
                    </div>
                </div>

                <div class="search-box">
                    <i class="fa-solid fa-search" aria-hidden="true"></i>
                    <label for="federation-search" class="visually-hidden">Search by name, skills, bio</label>
                    <input type="text"
                           id="federation-search"
                           placeholder="Search by name, skills, bio..."
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                           class="glass-search-input">
                    <div id="search-spinner" class="spinner fed-search-spinner" aria-hidden="true"></div>
                </div>

                <div class="filter-row">
                    <div class="fed-filter-group">
                        <label class="filter-label" for="tenant-filter">Partner Timebank</label>
                        <select id="tenant-filter" class="glass-select">
                            <option value="">All Partners</option>
                            <?php foreach ($partnerTenants as $tenant): ?>
                                <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tenant['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fed-filter-group">
                        <label class="filter-label" for="reach-filter">Service Reach</label>
                        <select id="reach-filter" class="glass-select">
                            <option value="">Any</option>
                            <option value="remote_ok" <?= ($filters['service_reach'] ?? '') === 'remote_ok' ? 'selected' : '' ?>>Remote Services</option>
                            <option value="travel_ok" <?= ($filters['service_reach'] ?? '') === 'travel_ok' ? 'selected' : '' ?>>Will Travel</option>
                        </select>
                    </div>
                </div>

                <!-- Advanced Filters Toggle -->
                <div class="fed-filter-actions">
                    <button type="button" class="advanced-toggle" id="advanced-toggle" aria-expanded="false" aria-controls="advanced-filters">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        Advanced Filters
                        <?php if ($activeFiltersCount > 0): ?>
                        <span class="active-filters-count" aria-label="<?= $activeFiltersCount ?> active filters"><?= $activeFiltersCount ?></span>
                        <?php endif; ?>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <?php if ($activeFiltersCount > 0): ?>
                    <button type="button" class="clear-filters" id="clear-filters">
                        <i class="fa-solid fa-times" aria-hidden="true"></i> Clear Filters
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Advanced Filters Panel -->
                <div class="advanced-filters" id="advanced-filters" role="region" aria-label="Advanced search filters">
                    <div class="filter-row">
                        <!-- Skills Filter -->
                        <div class="fed-filter-group fed-filter-skills">
                            <label class="filter-label" for="skills-input">Skills</label>
                            <div class="skills-input-container">
                                <input type="text"
                                       id="skills-input"
                                       placeholder="Type to search skills..."
                                       class="skills-input"
                                       autocomplete="off"
                                       aria-describedby="skills-help">
                                <div id="skills-suggestions" class="skills-suggestions" role="listbox" aria-label="Skill suggestions"></div>
                            </div>
                            <div id="skill-tags" class="skill-tags" role="list" aria-label="Selected skills">
                                <?php foreach ($selectedSkills as $skill): if (!empty($skill)): ?>
                                <span class="skill-tag" data-skill="<?= htmlspecialchars($skill) ?>" role="listitem">
                                    <?= htmlspecialchars($skill) ?>
                                    <i class="fa-solid fa-times remove" role="button" aria-label="Remove <?= htmlspecialchars($skill) ?>"></i>
                                </span>
                                <?php endif; endforeach; ?>
                            </div>
                            <?php if (!empty($popularSkills)): ?>
                            <div class="popular-skills" id="skills-help">
                                <span class="popular-skills-label">Popular:</span>
                                <?php foreach (array_slice($popularSkills, 0, 8) as $skill): ?>
                                <button type="button" class="popular-skill" data-skill="<?= htmlspecialchars($skill) ?>">
                                    <?= htmlspecialchars($skill) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Location Filter -->
                        <div class="fed-filter-group">
                            <label class="filter-label" for="location-filter">Location</label>
                            <input type="text"
                                   id="location-filter"
                                   placeholder="City or region..."
                                   value="<?= htmlspecialchars($filters['location'] ?? '') ?>"
                                   class="skills-input">
                        </div>
                    </div>

                    <!-- Availability Toggles -->
                    <fieldset class="fed-filter-fieldset">
                        <legend class="filter-label">Availability</legend>
                        <div class="filter-toggles">
                            <label class="filter-toggle">
                                <input type="checkbox" id="messaging-filter" <?= !empty($filters['messaging_enabled']) ? 'checked' : '' ?>>
                                <i class="fa-solid fa-comments" aria-hidden="true"></i>
                                Accepts Messages
                            </label>
                            <label class="filter-toggle">
                                <input type="checkbox" id="transactions-filter" <?= !empty($filters['transactions_enabled']) ? 'checked' : '' ?>>
                                <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                                Accepts Transactions
                            </label>
                        </div>
                    </fieldset>
                </div>
            </div>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <i class="fa-solid fa-globe" aria-hidden="true"></i>
            <h2>Members from Partner Timebanks</h2>
        </div>

        <!-- Members Grid -->
        <div id="members-grid" class="members-grid" role="list" aria-label="Federated members">
            <?php if (!empty($members)): ?>
                <?php foreach ($members as $member): ?>
                    <?= renderFederatedMemberCard($member, $basePath) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" role="status">
                    <div class="empty-state-icon">
                        <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
                    </div>
                    <h3>No federated members found</h3>
                    <p>
                        <?php if (empty($partnerTenants)): ?>
                            Your timebank doesn't have any active partnerships yet.
                        <?php else: ?>
                            Try adjusting your search filters or check back later.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Load More / Infinite Scroll Trigger -->
        <div id="infinite-scroll-trigger" class="fed-infinite-trigger" aria-hidden="true"></div>
        <div id="load-more-spinner" class="loading-state fed-load-more" aria-live="polite">
            <div class="loading-spinner"></div>
            <span class="visually-hidden">Loading more members...</span>
        </div>

    </div>
</div>

<?php
function renderFederatedMemberCard($member, $basePath)
{
    ob_start();

    $memberName = $member['name'] ?? 'Member';
    $fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=00796B&color=fff&size=200';
    $avatarUrl = !empty($member['avatar_url']) ? $member['avatar_url'] : $fallbackUrl;
    $profileUrl = $basePath . '/federation/members/' . $member['id'];

    $reachClass = '';
    $reachLabel = '';
    $reachIcon = '';
    switch ($member['service_reach'] ?? 'local_only') {
        case 'remote_ok':
            $reachClass = 'remote';
            $reachLabel = 'Remote OK';
            $reachIcon = 'fa-laptop-house';
            break;
        case 'travel_ok':
            $reachClass = 'travel';
            $reachLabel = 'Will Travel';
            $reachIcon = 'fa-car';
            break;
        default:
            $reachClass = 'local';
            $reachLabel = 'Local Only';
            $reachIcon = 'fa-location-dot';
    }
?>
    <a href="<?= $profileUrl ?>" class="glass-member-card" role="listitem">
        <div class="card-body">
            <div class="avatar-container">
                <div class="avatar-ring" aria-hidden="true"></div>
                <img src="<?= htmlspecialchars($avatarUrl) ?>"
                     onerror="this.onerror=null; this.src='<?= $fallbackUrl ?>'"
                     loading="lazy"
                     alt=""
                     class="avatar-img">
            </div>

            <h3 class="member-name"><?= htmlspecialchars($memberName) ?></h3>

            <div class="tenant-badge">
                <i class="fa-solid fa-building" aria-hidden="true"></i>
                <?= htmlspecialchars($member['tenant_name'] ?? 'Partner Timebank') ?>
            </div>

            <div class="reach-badge <?= $reachClass ?>">
                <i class="fa-solid <?= $reachIcon ?>" aria-hidden="true"></i>
                <?= $reachLabel ?>
            </div>

            <?php if (!empty($member['location'])): ?>
                <div class="member-location">
                    <i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i>
                    <?= htmlspecialchars($member['location']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($member['bio'])): ?>
                <div class="member-bio">
                    <?= htmlspecialchars($member['bio']) ?>
                </div>
            <?php endif; ?>

            <span class="view-profile-btn">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
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
    const searchInput = document.getElementById('federation-search');
    const tenantFilter = document.getElementById('tenant-filter');
    const reachFilter = document.getElementById('reach-filter');
    const sortFilter = document.getElementById('sort-filter');
    const locationFilter = document.getElementById('location-filter');
    const messagingFilter = document.getElementById('messaging-filter');
    const transactionsFilter = document.getElementById('transactions-filter');
    const skillsInput = document.getElementById('skills-input');
    const skillsSuggestions = document.getElementById('skills-suggestions');
    const skillTags = document.getElementById('skill-tags');
    const advancedToggle = document.getElementById('advanced-toggle');
    const advancedFilters = document.getElementById('advanced-filters');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const grid = document.getElementById('members-grid');
    const countLabel = document.getElementById('members-count');
    const spinner = document.getElementById('search-spinner');
    const loadMoreSpinner = document.getElementById('load-more-spinner');

    let debounceTimer;
    let skillsDebounceTimer;
    let currentOffset = <?= count($members) ?>;
    let isLoading = false;
    let hasMore = <?= count($members) >= 30 ? 'true' : 'false' ?>;
    let selectedSkills = <?= json_encode($selectedSkills ?? []) ?>;

    // Advanced filters toggle with ARIA
    advancedToggle.addEventListener('click', function() {
        const isOpen = this.classList.toggle('open');
        advancedFilters.classList.toggle('open');
        this.setAttribute('aria-expanded', isOpen);
    });

    // Open advanced filters if any are active
    <?php if ($activeFiltersCount > 0): ?>
    advancedToggle.classList.add('open');
    advancedFilters.classList.add('open');
    advancedToggle.setAttribute('aria-expanded', 'true');
    <?php endif; ?>

    // Clear filters
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            tenantFilter.value = '';
            reachFilter.value = '';
            sortFilter.value = 'name';
            locationFilter.value = '';
            messagingFilter.checked = false;
            transactionsFilter.checked = false;
            selectedSkills = [];
            renderSkillTags();
            currentOffset = 0;
            hasMore = true;
            fetchMembers();
        });
    }

    // Search functionality
    searchInput.addEventListener('keyup', function(e) {
        clearTimeout(debounceTimer);
        spinner.style.display = 'block';
        debounceTimer = setTimeout(() => {
            currentOffset = 0;
            hasMore = true;
            fetchMembers();
        }, 300);
    });

    // Filter change handlers
    tenantFilter.addEventListener('change', triggerSearch);
    reachFilter.addEventListener('change', triggerSearch);
    sortFilter.addEventListener('change', triggerSearch);
    messagingFilter.addEventListener('change', triggerSearch);
    transactionsFilter.addEventListener('change', triggerSearch);

    // Location filter with debounce
    locationFilter.addEventListener('keyup', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerSearch, 400);
    });

    function triggerSearch() {
        currentOffset = 0;
        hasMore = true;
        fetchMembers();
    }

    // Skills autocomplete
    skillsInput.addEventListener('keyup', function(e) {
        const query = this.value.trim();

        if (e.key === 'Enter' && query) {
            addSkill(query);
            this.value = '';
            skillsSuggestions.classList.remove('show');
            return;
        }

        clearTimeout(skillsDebounceTimer);
        if (query.length < 2) {
            skillsSuggestions.classList.remove('show');
            return;
        }

        skillsDebounceTimer = setTimeout(() => {
            fetch('<?= $basePath ?>/federation/members/skills?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.skills.length > 0) {
                        skillsSuggestions.innerHTML = data.skills
                            .filter(s => !selectedSkills.includes(s))
                            .map(s => `<div class="skill-suggestion" role="option" data-skill="${escapeHtml(s)}">${escapeHtml(s)}</div>`)
                            .join('');
                        skillsSuggestions.classList.add('show');
                    } else {
                        skillsSuggestions.classList.remove('show');
                    }
                });
        }, 200);
    });

    // Hide suggestions on blur
    skillsInput.addEventListener('blur', function() {
        setTimeout(() => skillsSuggestions.classList.remove('show'), 200);
    });

    // Handle skill suggestion click
    skillsSuggestions.addEventListener('click', function(e) {
        if (e.target.classList.contains('skill-suggestion')) {
            addSkill(e.target.dataset.skill);
            skillsInput.value = '';
            skillsSuggestions.classList.remove('show');
        }
    });

    // Handle popular skill click
    document.querySelectorAll('.popular-skill').forEach(el => {
        el.addEventListener('click', function() {
            addSkill(this.dataset.skill);
        });
    });

    // Handle skill tag removal
    skillTags.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove') || e.target.closest('.remove')) {
            const tag = e.target.closest('.skill-tag');
            if (tag) {
                removeSkill(tag.dataset.skill);
            }
        }
    });

    function addSkill(skill) {
        skill = skill.trim();
        if (skill && !selectedSkills.includes(skill)) {
            selectedSkills.push(skill);
            renderSkillTags();
            triggerSearch();
        }
    }

    function removeSkill(skill) {
        selectedSkills = selectedSkills.filter(s => s !== skill);
        renderSkillTags();
        triggerSearch();
    }

    function renderSkillTags() {
        skillTags.innerHTML = selectedSkills.map(skill => `
            <span class="skill-tag" data-skill="${escapeHtml(skill)}" role="listitem">
                ${escapeHtml(skill)}
                <i class="fa-solid fa-times remove" role="button" aria-label="Remove ${escapeHtml(skill)}"></i>
            </span>
        `).join('');
    }

    function fetchMembers(append = false) {
        const params = new URLSearchParams({
            q: searchInput.value,
            tenant: tenantFilter.value,
            reach: reachFilter.value,
            sort: sortFilter.value,
            location: locationFilter.value,
            messaging: messagingFilter.checked ? '1' : '',
            transactions: transactionsFilter.checked ? '1' : '',
            skills: selectedSkills.join(','),
            offset: append ? currentOffset : 0,
            limit: 30
        });

        if (!append) {
            spinner.style.display = 'block';
        }

        fetch('<?= $basePath ?>/federation/members/api?' + params.toString())
            .then(res => res.json())
            .then(data => {
                spinner.style.display = 'none';
                loadMoreSpinner.style.display = 'none';
                isLoading = false;

                if (data.success) {
                    if (append) {
                        appendMembers(data.members);
                        currentOffset += data.members.length;
                    } else {
                        renderGrid(data.members);
                        currentOffset = data.members.length;
                    }
                    hasMore = data.hasMore;
                    countLabel.textContent = `${append ? currentOffset : data.members.length} member${data.members.length !== 1 ? 's' : ''} found`;
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                spinner.style.display = 'none';
                loadMoreSpinner.style.display = 'none';
                isLoading = false;
            });
    }

    function renderGrid(members) {
        if (members.length === 0) {
            grid.innerHTML = `
                <div class="empty-state" role="status">
                    <div class="empty-state-icon">
                        <i class="fa-solid fa-search" aria-hidden="true"></i>
                    </div>
                    <h3>No members found</h3>
                    <p>Try adjusting your search or filters.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = '';
        members.forEach(member => {
            grid.appendChild(createMemberCard(member));
        });
    }

    function appendMembers(members) {
        members.forEach(member => {
            grid.appendChild(createMemberCard(member));
        });
    }

    function createMemberCard(member) {
        const basePath = "<?= $basePath ?>";
        const memberName = member.name || 'Member';
        const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(memberName)}&background=00796B&color=fff&size=200`;
        const avatarUrl = member.avatar_url || fallbackUrl;
        const profileUrl = `${basePath}/federation/members/${member.id}`;

        let reachClass = 'local';
        let reachLabel = 'Local Only';
        let reachIcon = 'fa-location-dot';

        if (member.service_reach === 'remote_ok') {
            reachClass = 'remote';
            reachLabel = 'Remote OK';
            reachIcon = 'fa-laptop-house';
        } else if (member.service_reach === 'travel_ok') {
            reachClass = 'travel';
            reachLabel = 'Will Travel';
            reachIcon = 'fa-car';
        }

        const card = document.createElement('a');
        card.href = profileUrl;
        card.className = 'glass-member-card';
        card.setAttribute('role', 'listitem');
        card.innerHTML = `
            <div class="card-body">
                <div class="avatar-container">
                    <div class="avatar-ring" aria-hidden="true"></div>
                    <img src="${escapeHtml(avatarUrl)}"
                         onerror="this.onerror=null; this.src='${fallbackUrl}'"
                         loading="lazy"
                         alt=""
                         class="avatar-img">
                </div>
                <h3 class="member-name">${escapeHtml(memberName)}</h3>
                <div class="tenant-badge">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    ${escapeHtml(member.tenant_name || 'Partner Timebank')}
                </div>
                <div class="reach-badge ${reachClass}">
                    <i class="fa-solid ${reachIcon}" aria-hidden="true"></i>
                    ${reachLabel}
                </div>
                ${member.location ? `
                    <div class="member-location">
                        <i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i>
                        ${escapeHtml(member.location)}
                    </div>
                ` : ''}
                ${member.bio ? `<div class="member-bio">${escapeHtml(member.bio)}</div>` : ''}
                <span class="view-profile-btn">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    View Profile
                </span>
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
                    fetchMembers(true);
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
