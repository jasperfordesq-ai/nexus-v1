<?php
/**
 * Federated Events
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federated Events";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Events - Partner Timebank Calendar');
Nexus\Core\SEO::setDescription('Discover and join events from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation" class="civic-fed-back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federation Hub
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <h1>Federated Events</h1>
    </header>

    <p class="civic-fed-intro">
        Discover and join events from partner timebanks
    </p>

    <!-- Filters -->
    <div class="civic-fed-search-card" role="search" aria-label="Filter events">
        <div class="civic-fed-search-row">
            <div class="civic-fed-search-box">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <label for="event-search" class="visually-hidden">Search events</label>
                <input type="text" id="event-search" class="civic-fed-input" placeholder="Search events..." aria-describedby="events-count">
            </div>
        </div>
        <div class="civic-fed-filter-row">
            <div class="civic-fed-filter-group">
                <label for="timebank-filter" class="civic-fed-filter-label">Partner Timebank</label>
                <select id="timebank-filter" class="civic-fed-select" aria-label="Filter by timebank">
                    <option value="">All Timebanks</option>
                </select>
            </div>
            <div class="civic-fed-filter-group">
                <label for="time-filter" class="civic-fed-filter-label">Time Period</label>
                <select id="time-filter" class="civic-fed-select" aria-label="Filter by time period">
                    <option value="upcoming">Upcoming Events</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                    <option value="all">All Events</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Events Grid -->
    <div id="events-container" class="civic-fed-events-grid" role="region" aria-label="Events list" aria-live="polite">
        <div class="civic-fed-loading" role="status">
            <div class="civic-fed-spinner" aria-hidden="true"></div>
            <p>Loading federated events...</p>
        </div>
    </div>

    <!-- Pagination -->
    <nav class="civic-fed-pagination" id="pagination" style="display: none;" aria-label="Events pagination">
        <button class="civic-fed-pagination-btn" id="prev-page" disabled>
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            <span>Previous</span>
        </button>
        <span class="civic-fed-pagination-info" id="page-info" role="status">Page 1 of 1</span>
        <button class="civic-fed-pagination-btn" id="next-page" disabled>
            <span>Next</span>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
    </nav>
</div>

<script src="/assets/js/federation-events.js?v=<?= time() ?>"></script>
<script>
    window.federationEventsBasePath = '<?= $basePath ?>';
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
