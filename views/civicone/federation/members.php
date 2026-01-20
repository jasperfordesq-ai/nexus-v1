<?php
/**
 * Federation Members Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federated Members";
$pageSubtitle = "Connect with members from partner timebanks";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Members - Partner Timebank Directory');
Nexus\Core\SEO::setDescription('Browse and connect with members from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$members = $members ?? [];
$partnerTenants = $partnerTenants ?? [];
$filters = $filters ?? [];
$partnerships = $partnerships ?? [];

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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/members" class="civic-fed-back-link" aria-label="Return to local members">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Local Members
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <h1>Federated Members</h1>
        <span class="civic-fed-badge">
            <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
            Federation Network
        </span>
    </header>

    <p class="civic-fed-intro">
        Discover members from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
        Connect, collaborate, and exchange services across communities.
    </p>

    <!-- Search Stats Bar -->
    <?php if (!empty($searchStats) && ($searchStats['total_members'] ?? 0) > 0): ?>
    <div class="civic-fed-stats-bar" role="status" aria-label="Federation statistics">
        <div class="civic-fed-stat-item">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            <strong><?= number_format($searchStats['total_members']) ?></strong> federated members
        </div>
        <div class="civic-fed-stat-item">
            <i class="fa-solid fa-laptop-house" aria-hidden="true"></i>
            <strong><?= number_format($searchStats['remote_available']) ?></strong> offer remote services
        </div>
        <div class="civic-fed-stat-item">
            <i class="fa-solid fa-car" aria-hidden="true"></i>
            <strong><?= number_format($searchStats['travel_available']) ?></strong> will travel
        </div>
        <div class="civic-fed-stat-item">
            <i class="fa-solid fa-comments" aria-hidden="true"></i>
            <strong><?= number_format($searchStats['messaging_enabled']) ?></strong> accept messages
        </div>
    </div>
    <?php endif; ?>

    <!-- Search & Filters Card -->
    <div class="civic-fed-search-card" role="search" aria-label="Search federated members">
        <div class="civic-fed-search-header">
            <h2 class="civic-fed-search-title">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                Find Federated Members
            </h2>
            <div class="civic-fed-search-meta">
                <label for="sort-filter" class="visually-hidden">Sort members by</label>
                <select id="sort-filter" class="civic-fed-select civic-fed-select--small">
                    <option value="name" <?= ($filters['sort'] ?? 'name') === 'name' ? 'selected' : '' ?>>Sort: Name</option>
                    <option value="recent" <?= ($filters['sort'] ?? '') === 'recent' ? 'selected' : '' ?>>Sort: Newest</option>
                    <option value="active" <?= ($filters['sort'] ?? '') === 'active' ? 'selected' : '' ?>>Sort: Most Active</option>
                </select>
                <span id="members-count" class="civic-fed-results-count" role="status" aria-live="polite">
                    <?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?> found
                </span>
            </div>
        </div>

        <div class="civic-fed-search-row">
            <div class="civic-fed-search-box">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <label for="federation-search" class="visually-hidden">Search by name, skills, bio</label>
                <input type="text"
                       id="federation-search"
                       class="civic-fed-input"
                       placeholder="Search by name, skills, bio..."
                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                       aria-describedby="members-count">
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
                <label for="reach-filter" class="civic-fed-filter-label">Service Reach</label>
                <select id="reach-filter" class="civic-fed-select">
                    <option value="">Any</option>
                    <option value="remote_ok" <?= ($filters['service_reach'] ?? '') === 'remote_ok' ? 'selected' : '' ?>>Remote Services</option>
                    <option value="travel_ok" <?= ($filters['service_reach'] ?? '') === 'travel_ok' ? 'selected' : '' ?>>Will Travel</option>
                </select>
            </div>
        </div>

        <!-- Advanced Filters Toggle -->
        <div class="civic-fed-filter-actions">
            <button type="button" class="civic-fed-advanced-toggle" id="advanced-toggle" aria-expanded="false" aria-controls="advanced-filters">
                <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                Advanced Filters
                <?php if ($activeFiltersCount > 0): ?>
                <span class="civic-fed-filter-count" aria-label="<?= $activeFiltersCount ?> active filters"><?= $activeFiltersCount ?></span>
                <?php endif; ?>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <?php if ($activeFiltersCount > 0): ?>
            <button type="button" class="civic-fed-btn civic-fed-btn--small civic-fed-btn--secondary" id="clear-filters">
                <i class="fa-solid fa-times" aria-hidden="true"></i> Clear Filters
            </button>
            <?php endif; ?>
        </div>

        <!-- Advanced Filters Panel -->
        <div class="civic-fed-advanced-filters" id="advanced-filters" role="region" aria-label="Advanced search filters">
            <div class="civic-fed-filter-row">
                <div class="civic-fed-filter-group civic-fed-filter-group--skills">
                    <label for="skills-input" class="civic-fed-filter-label">Skills</label>
                    <div class="civic-fed-skills-input-container">
                        <input type="text"
                               id="skills-input"
                               class="civic-fed-input"
                               placeholder="Type to search skills..."
                               autocomplete="off"
                               aria-describedby="skills-help">
                        <div id="skills-suggestions" class="civic-fed-skills-suggestions" role="listbox" aria-label="Skill suggestions"></div>
                    </div>
                    <div id="skill-tags" class="civic-fed-skill-tags" role="list" aria-label="Selected skills">
                        <?php foreach ($selectedSkills as $skill): if (!empty($skill)): ?>
                        <span class="civic-fed-skill-tag" data-skill="<?= htmlspecialchars($skill) ?>" role="listitem">
                            <?= htmlspecialchars($skill) ?>
                            <i class="fa-solid fa-times civic-fed-skill-remove" role="button" aria-label="Remove <?= htmlspecialchars($skill) ?>"></i>
                        </span>
                        <?php endif; endforeach; ?>
                    </div>
                    <?php if (!empty($popularSkills)): ?>
                    <div class="civic-fed-popular-skills" id="skills-help">
                        <span class="civic-fed-popular-label">Popular:</span>
                        <?php foreach (array_slice($popularSkills, 0, 8) as $skill): ?>
                        <button type="button" class="civic-fed-popular-skill" data-skill="<?= htmlspecialchars($skill) ?>">
                            <?= htmlspecialchars($skill) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="civic-fed-filter-group">
                    <label for="location-filter" class="civic-fed-filter-label">Location</label>
                    <input type="text"
                           id="location-filter"
                           class="civic-fed-input"
                           placeholder="City or region..."
                           value="<?= htmlspecialchars($filters['location'] ?? '') ?>">
                </div>
            </div>

            <fieldset class="civic-fed-fieldset">
                <legend class="civic-fed-filter-label">Availability</legend>
                <div class="civic-fed-toggle-row">
                    <label class="civic-fed-toggle">
                        <input type="checkbox" id="messaging-filter" <?= !empty($filters['messaging_enabled']) ? 'checked' : '' ?>>
                        <span class="civic-fed-toggle-label">
                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                            Accepts Messages
                        </span>
                    </label>
                    <label class="civic-fed-toggle">
                        <input type="checkbox" id="transactions-filter" <?= !empty($filters['transactions_enabled']) ? 'checked' : '' ?>>
                        <span class="civic-fed-toggle-label">
                            <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                            Accepts Transactions
                        </span>
                    </label>
                </div>
            </fieldset>
        </div>
    </div>

    <!-- Members Grid -->
    <div id="members-grid" class="civic-fed-members-grid" role="list" aria-label="Federated members">
        <?php if (!empty($members)): ?>
            <?php foreach ($members as $member): ?>
                <?= renderFederatedMemberCard($member, $basePath) ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="civic-fed-empty" role="status">
                <div class="civic-fed-empty-icon">
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
    <div id="infinite-scroll-trigger" aria-hidden="true"></div>
    <div id="load-more-spinner" class="civic-fed-loading" style="display: none;" role="status">
        <div class="civic-fed-spinner" aria-hidden="true"></div>
        <span class="visually-hidden">Loading more members...</span>
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
            $reachClass = 'civic-fed-reach--remote';
            $reachLabel = 'Remote OK';
            $reachIcon = 'fa-laptop-house';
            break;
        case 'travel_ok':
            $reachClass = 'civic-fed-reach--travel';
            $reachLabel = 'Will Travel';
            $reachIcon = 'fa-car';
            break;
        default:
            $reachClass = 'civic-fed-reach--local';
            $reachLabel = 'Local Only';
            $reachIcon = 'fa-location-dot';
    }
?>
    <a href="<?= $profileUrl ?>" class="civic-fed-member-card" role="listitem">
        <div class="civic-fed-member-avatar">
            <img src="<?= htmlspecialchars($avatarUrl) ?>"
                 onerror="this.onerror=null; this.src='<?= $fallbackUrl ?>'"
                 loading="lazy"
                 alt="">
        </div>

        <h3 class="civic-fed-member-name"><?= htmlspecialchars($memberName) ?></h3>

        <div class="civic-fed-badge civic-fed-badge--partner">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            <?= htmlspecialchars($member['tenant_name'] ?? 'Partner Timebank') ?>
        </div>

        <div class="civic-fed-badge <?= $reachClass ?>">
            <i class="fa-solid <?= $reachIcon ?>" aria-hidden="true"></i>
            <?= $reachLabel ?>
        </div>

        <?php if (!empty($member['location'])): ?>
            <div class="civic-fed-member-location">
                <i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i>
                <?= htmlspecialchars($member['location']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($member['bio'])): ?>
            <p class="civic-fed-member-bio"><?= htmlspecialchars($member['bio']) ?></p>
        <?php endif; ?>

        <span class="civic-fed-btn civic-fed-btn--small civic-fed-btn--secondary">
            <i class="fa-solid fa-user" aria-hidden="true"></i>
            View Profile
        </span>
    </a>
<?php
    return ob_get_clean();
}
?>

<script src="/assets/js/federation-members.js?v=<?= time() ?>"></script>
<script>
// Initialize with PHP data
window.federationMembersConfig = {
    basePath: "<?= $basePath ?>",
    initialCount: <?= count($members) ?>,
    hasMore: <?= count($members) >= 30 ? 'true' : 'false' ?>,
    selectedSkills: <?= json_encode($selectedSkills ?? []) ?>,
    activeFiltersCount: <?= $activeFiltersCount ?>
};

// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
