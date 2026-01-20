<?php
// Federated Events - CivicOne WCAG 2.1 AA
$pageTitle = $pageTitle ?? "Federated Events";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Events - Partner Timebank Calendar');
Nexus\Core\SEO::setDescription('Discover and join events from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-events-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation" class="back-link" aria-label="Return to federation hub">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Federation Hub
        </a>

        <!-- Page Header -->
        <div class="page-header" role="banner">
            <div>
                <h1 class="page-title">
                    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                    Federated Events
                </h1>
                <p class="page-subtitle">Discover and join events from partner timebanks</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar" role="search" aria-label="Filter events">
            <div class="search-box">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <label for="event-search" class="visually-hidden">Search events</label>
                <input type="text" id="event-search" placeholder="Search events..." aria-describedby="events-count">
            </div>
            <div class="filter-group">
                <label for="timebank-filter" class="visually-hidden">Filter by timebank</label>
                <select id="timebank-filter" class="filter-select" aria-label="Filter by timebank">
                    <option value="">All Timebanks</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="time-filter" class="visually-hidden">Filter by time period</label>
                <select id="time-filter" class="filter-select" aria-label="Filter by time period">
                    <option value="upcoming">Upcoming Events</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                    <option value="all">All Events</option>
                </select>
            </div>
        </div>

        <!-- Events Grid -->
        <div id="events-container" role="region" aria-label="Events list" aria-live="polite">
            <div class="loading-state" role="status">
                <div class="loading-spinner" aria-hidden="true"></div>
                <p class="loading-text">Loading federated events...</p>
            </div>
        </div>

        <!-- Pagination -->
        <nav class="pagination" id="pagination" style="display: none;" aria-label="Events pagination">
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

<script src="/assets/js/federation-events.js?v=<?= time() ?>"></script>
<script>
    // Initialize with base path
    window.federationEventsBasePath = '<?= $basePath ?>';
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
