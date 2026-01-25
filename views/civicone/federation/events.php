<?php
/**
 * Federation Events Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Directory/List (MOJ Filter a list pattern)
 */
$pageTitle = $pageTitle ?? "Federated Events";
$pageSubtitle = "Discover and join events from partner timebanks";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'events';

\Nexus\Core\SEO::setTitle('Federated Events - Partner Timebank Calendar');
\Nexus\Core\SEO::setDescription('Discover and join events from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

$events = $events ?? [];
$partnerTenants = $partnerTenants ?? [];
$filters = $filters ?? [];
$partnerCommunities = $partnerCommunities ?? $partnerTenants;
$currentScope = $currentScope ?? 'all';

// Build active filters array for tags
$activeFilters = [];
if (!empty($filters['tenant_id'])) {
    $tenantName = '';
    foreach ($partnerTenants as $t) {
        if ($t['id'] == $filters['tenant_id']) {
            $tenantName = $t['name'];
            break;
        }
    }
    $activeFilters[] = [
        'label' => 'Community: ' . $tenantName,
        'remove_url' => $basePath . '/federation/events?' . http_build_query(array_diff_key($filters, ['tenant_id' => '']))
    ];
}
if (!empty($filters['search'])) {
    $activeFilters[] = [
        'label' => 'Search: "' . htmlspecialchars($filters['search']) . '"',
        'remove_url' => $basePath . '/federation/events?' . http_build_query(array_diff_key($filters, ['search' => '']))
    ];
}
if (!empty($filters['remote_only'])) {
    $activeFilters[] = [
        'label' => 'Remote attendance available',
        'remove_url' => $basePath . '/federation/events?' . http_build_query(array_diff_key($filters, ['remote_only' => '']))
    ];
}
if (isset($filters['upcoming_only']) && !$filters['upcoming_only']) {
    $activeFilters[] = [
        'label' => 'Including past events',
        'remove_url' => $basePath . '/federation/events?' . http_build_query(array_merge($filters, ['upcoming_only' => '1']))
    ];
}
?>

<!-- Federation Scope Switcher (only if user has 2+ communities) -->
<?php if (count($partnerCommunities) >= 2): ?>
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-scope-switcher.php'; ?>
<?php endif; ?>

<!-- Federation Service Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-service-navigation.php'; ?>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper">

        <!-- Page Header -->
        <h1 class="govuk-heading-xl">Federated Events</h1>

        <p class="govuk-body-l">
            Discover and join events from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
        </p>

        <!-- Selected Filters (MOJ Filter component) -->
        <?php if (!empty($activeFilters)): ?>
        <div class="moj-filter-tags">
            <h2 class="govuk-heading-s">Selected filters</h2>
            <div class="moj-filter-tags__wrapper">
                <?php foreach ($activeFilters as $filter): ?>
                <a href="<?= $filter['remove_url'] ?>" class="moj-filter-tag">
                    <?= htmlspecialchars($filter['label']) ?>
                    <span class="govuk-visually-hidden">Remove filter</span>
                </a>
                <?php endforeach; ?>
                <a href="<?= $basePath ?>/federation/events" class="moj-filter-tag moj-filter-tag--clear-all">
                    Clear all filters
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Two-column layout: Filters (1/3) + Results (2/3) -->
        <div class="moj-filter-layout">

            <!-- Filter Panel (1/3 width) -->
            <div class="moj-filter-layout__filter">
                <form method="GET" action="<?= $basePath ?>/federation/events">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            Filter events
                        </legend>

                        <!-- Search Input -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="search-input">
                                Search titles, descriptions, and locations
                            </label>
                            <input class="govuk-input" id="search-input" name="q" type="text"
                                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                                   placeholder="Enter keywords...">
                        </div>

                        <!-- Source Community Filter (MANDATORY) -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="tenant-filter">
                                Source community
                            </label>
                            <select id="tenant-filter" name="tenant" class="govuk-select">
                                <option value="">All partner communities</option>
                                <?php foreach ($partnerTenants as $tenant): ?>
                                    <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tenant['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Time Period Filter -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="time-filter">
                                Time period
                            </label>
                            <select id="time-filter" name="past" class="govuk-select">
                                <option value="" <?= ($filters['upcoming_only'] ?? true) ? 'selected' : '' ?>>
                                    Upcoming events only
                                </option>
                                <option value="1" <?= !($filters['upcoming_only'] ?? true) ? 'selected' : '' ?>>
                                    Include past events
                                </option>
                            </select>
                        </div>

                        <!-- Remote Attendance Filter -->
                        <div class="govuk-checkboxes govuk-checkboxes--small">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="remote-filter" name="remote" type="checkbox" value="1" <?= !empty($filters['remote_only']) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="remote-filter">
                                    Remote attendance available
                                </label>
                            </div>
                        </div>

                        <!-- Apply Filters Button -->
                        <button type="submit" class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">
                            Apply filters
                        </button>
                    </fieldset>
                </form>
            </div>

            <!-- Results Panel (2/3 width) -->
            <div class="moj-filter-layout__content" aria-live="polite" aria-busy="false">

                <!-- Results Count -->
                <div class="moj-action-bar">
                    <div class="moj-action-bar__filter">
                        <p class="govuk-body">
                            <strong><?= count($events) ?></strong> event<?= count($events) !== 1 ? 's' : '' ?> found
                        </p>
                    </div>
                </div>

                <!-- Events List -->
                <?php if (!empty($events)): ?>
                    <ul class="govuk-list">
                        <?php foreach ($events as $event): ?>
                        <li class="govuk-!-margin-bottom-6">
                            <div class="govuk-summary-card">
                                <div class="govuk-summary-card__title-wrapper">
                                    <h3 class="govuk-summary-card__title">
                                        <a href="<?= $basePath ?>/federation/events/<?= $event['id'] ?>" class="govuk-link">
                                            <?= htmlspecialchars($event['title'] ?? 'Untitled') ?>
                                        </a>
                                    </h3>
                                    <!-- PROVENANCE LABEL (MANDATORY) -->
                                    <span class="govuk-tag govuk-tag--grey">
                                        Shared from <?= htmlspecialchars($event['tenant_name'] ?? 'Partner') ?>
                                    </span>
                                </div>
                                <div class="govuk-summary-card__content">
                                    <?php if (!empty($event['description'])): ?>
                                    <p class="govuk-body-s">
                                        <?= htmlspecialchars(mb_substr($event['description'], 0, 200)) ?><?= mb_strlen($event['description']) > 200 ? '...' : '' ?>
                                    </p>
                                    <?php endif; ?>

                                    <dl class="govuk-summary-list govuk-summary-list--no-border">
                                        <?php if (!empty($event['start_time'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Date and time</dt>
                                            <dd class="govuk-summary-list__value">
                                                <?= date('d M Y, H:i', strtotime($event['start_time'])) ?>
                                                <?php if (!empty($event['end_time'])): ?>
                                                    - <?= date('H:i', strtotime($event['end_time'])) ?>
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($event['location'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Location</dt>
                                            <dd class="govuk-summary-list__value">
                                                <?= htmlspecialchars($event['location']) ?>
                                                <?php if (!empty($event['allow_remote_attendance'])): ?>
                                                    <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-left-2">Remote OK</span>
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($event['organizer_name'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Organizer</dt>
                                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($event['organizer_name']) ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($event['attendee_count'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Attendees</dt>
                                            <dd class="govuk-summary-list__value">
                                                <?= $event['attendee_count'] ?> attending
                                                <?php if (!empty($event['max_attendees'])): ?>
                                                    (max <?= $event['max_attendees'] ?>)
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <div class="govuk-summary-card__actions">
                                    <a href="<?= $basePath ?>/federation/events/<?= $event['id'] ?>" class="govuk-link">
                                        View event<span class="govuk-visually-hidden"> <?= htmlspecialchars($event['title'] ?? 'event') ?></span>
                                    </a>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Pagination (GOV.UK Pattern - MANDATORY) -->
                    <?php
                    $currentPage = (int)(($_GET['page'] ?? 1));
                    $totalResults = count($events); // In production, this would come from controller
                    $perPage = 30;
                    $totalPages = max(1, ceil($totalResults / $perPage));

                    // Build query string without page param
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                    ?>
                    <?php if ($totalPages > 1): ?>
                    <nav class="govuk-pagination" role="navigation" aria-label="Results navigation">
                        <span class="govuk-visually-hidden">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                        <?php if ($currentPage > 1): ?>
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage - 1 ?><?= $queryString ?>" rel="prev">
                                <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                                </svg>
                                <span class="govuk-pagination__link-title">Previous</span>
                            </a>
                        </div>
                        <?php endif; ?>

                        <ul class="govuk-pagination__list">
                            <?php for ($i = 1; $i <= min(5, $totalPages); $i++): ?>
                            <li class="govuk-pagination__item <?= $i === $currentPage ? 'govuk-pagination__item--current' : '' ?>">
                                <?php if ($i === $currentPage): ?>
                                    <span class="govuk-pagination__link-label" aria-current="page"><?= $i ?></span>
                                <?php else: ?>
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $i ?><?= $queryString ?>" aria-label="Page <?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <?php endfor; ?>
                        </ul>

                        <?php if ($currentPage < $totalPages): ?>
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage + 1 ?><?= $queryString ?>" rel="next">
                                <span class="govuk-pagination__link-title">Next</span>
                                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                                </svg>
                            </a>
                        </div>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="govuk-panel govuk-panel--bordered" role="status" aria-live="polite">
                        <p class="govuk-body">No events found matching your filters.</p>
                        <p class="govuk-body">
                            Try adjusting your filters or <a href="<?= $basePath ?>/federation/events" class="govuk-link">clear all filters</a> to see all events.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
