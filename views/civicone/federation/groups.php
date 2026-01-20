<?php
// Federated Groups - CivicOne WCAG 2.1 AA
$pageTitle = $pageTitle ?? "Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Groups - Partner Timebank Communities');
Nexus\Core\SEO::setDescription('Discover and join groups from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-groups-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation" class="back-link" aria-label="Return to federation hub">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Federation Hub
        </a>

        <!-- Page Header -->
        <div class="page-header" role="banner">
            <div>
                <h1 class="page-title">
                    <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                    Federated Groups
                </h1>
                <p class="page-subtitle">Discover and join groups from partner timebanks</p>
            </div>
            <div class="header-actions">
                <a href="<?= $basePath ?>/federation/groups/my" class="btn-header-action" aria-label="View your federated groups">
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                    <span>My Federated Groups</span>
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar" role="search" aria-label="Filter groups">
            <div class="search-box">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <label for="group-search" class="visually-hidden">Search groups</label>
                <input type="text" id="group-search" placeholder="Search groups..." aria-describedby="groups-count">
            </div>
            <div class="filter-group">
                <label for="timebank-filter" class="visually-hidden">Filter by timebank</label>
                <select id="timebank-filter" class="filter-select" aria-label="Filter by timebank">
                    <option value="">All Timebanks</option>
                </select>
            </div>
        </div>

        <!-- Groups Grid -->
        <div id="groups-container" role="region" aria-label="Groups list" aria-live="polite">
            <div class="loading-state" role="status">
                <div class="loading-spinner" aria-hidden="true"></div>
                <p class="loading-text">Loading federated groups...</p>
            </div>
        </div>

        <!-- Pagination -->
        <nav class="pagination" id="pagination" style="display: none;" aria-label="Groups pagination">
            <button id="prev-page" disabled aria-label="Previous page">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                <span>Previous</span>
            </button>
            <span class="page-info" id="page-info" role="status">Page 1 of 1</span>
            <button id="next-page" disabled aria-label="Next page">
                <span>Next</span>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </nav>

    </div>
</div>

<script src="/assets/js/federation-groups.js?v=<?= time() ?>"></script>
<script>
    // Initialize with base path
    window.federationGroupsBasePath = '<?= $basePath ?>';
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
