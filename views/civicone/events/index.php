<?php
/**
 * CivicOne Events Directory
 * Template A: Directory/List Page (Section 10.2)
 * With Page Hero (Section 9C: Page Hero Contract)
 * GOV.UK Design System v5.14.0 - WCAG 2.1 AA Compliant
 */

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Breadcrumbs (GOV.UK Template A requirement) -->
        <nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
            <ol class="civicone-breadcrumbs__list">
                <li class="civicone-breadcrumbs__list-item">
                    <a class="civicone-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
                </li>
                <li class="civicone-breadcrumbs__list-item" aria-current="page">
                    Events
                </li>
            </ol>
        </nav>

        <!-- Hero (auto-resolves from config/heroes.php for /events route) -->
        <?php require dirname(__DIR__, 2) . '/layouts/civicone/partials/render-hero.php'; ?>

        <!-- Action Button -->
        <div class="civicone-grid-row civicone-action-row">
            <div class="civicone-grid-column-one-third">
                <a href="<?= $basePath ?>/events/create" class="civicone-button civicone-button--primary civicone-button--full-width">
                    Host Event
                </a>
            </div>
        </div>

        <!-- Directory Layout: 1/3 Filters + 2/3 Results -->
        <div class="civicone-grid-row">

            <!-- Filters Panel (1/3) -->
            <div class="civicone-grid-column-one-third">
                <div class="civicone-filter-panel" role="search" aria-label="Filter events">

                    <div class="civicone-filter-header">
                        <h2 class="civicone-heading-m">Filter events</h2>
                    </div>

                    <form method="get" action="<?= $basePath ?>/events">
                        <div class="civicone-filter-group">
                            <label for="event-search" class="civicone-label">
                                Search by title or location
                            </label>
                            <div class="civicone-search-wrapper">
                                <input
                                    type="text"
                                    id="event-search"
                                    name="q"
                                    class="civicone-input civicone-search-input"
                                    placeholder="Enter keywords..."
                                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                                >
                                <span class="civicone-search-icon" aria-hidden="true"></span>
                            </div>
                        </div>

                        <div class="civicone-filter-group">
                            <fieldset class="civicone-fieldset">
                                <legend class="civicone-label">Event type</legend>
                                <div class="civicone-checkboxes">
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="type-online" name="type[]" value="online" class="civicone-checkbox" <?= in_array('online', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label for="type-online" class="civicone-checkbox-label">Online</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="type-inperson" name="type[]" value="inperson" class="civicone-checkbox" <?= in_array('inperson', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label for="type-inperson" class="civicone-checkbox-label">In-person</label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <div class="civicone-filter-group">
                            <fieldset class="civicone-fieldset">
                                <legend class="civicone-label">Time range</legend>
                                <div class="civicone-checkboxes">
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="time-today" name="time[]" value="today" class="civicone-checkbox" <?= in_array('today', $_GET['time'] ?? []) ? 'checked' : '' ?>>
                                        <label for="time-today" class="civicone-checkbox-label">Today</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="time-week" name="time[]" value="week" class="civicone-checkbox" <?= in_array('week', $_GET['time'] ?? []) ? 'checked' : '' ?>>
                                        <label for="time-week" class="civicone-checkbox-label">This week</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="time-month" name="time[]" value="month" class="civicone-checkbox" <?= in_array('month', $_GET['time'] ?? []) ? 'checked' : '' ?>>
                                        <label for="time-month" class="civicone-checkbox-label">This month</label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <button type="submit" class="civicone-button civicone-button--secondary civicone-button--full-width">
                            Apply filters
                        </button>
                    </form>

                    <!-- Selected Filters (shown when filters are active) -->
                    <?php
                    $hasFilters = !empty($_GET['q']) || !empty($_GET['type']) || !empty($_GET['time']);
                    if ($hasFilters):
                    ?>
                    <div class="civicone-selected-filters">
                        <h3 class="civicone-heading-s">Active filters</h3>
                        <div class="civicone-filter-tags">
                            <?php if (!empty($_GET['q'])): ?>
                            <a href="<?= $basePath ?>/events" class="civicone-tag civicone-tag--removable">
                                Search: <?= htmlspecialchars($_GET['q']) ?>
                                <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($_GET['type'])): ?>
                                <?php foreach ($_GET['type'] as $type): ?>
                                <a href="<?= $basePath ?>/events?q=<?= urlencode($_GET['q'] ?? '') ?>" class="civicone-tag civicone-tag--removable">
                                    Type: <?= htmlspecialchars(ucfirst($type)) ?>
                                    <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($_GET['time'])): ?>
                                <?php foreach ($_GET['time'] as $time): ?>
                                <a href="<?= $basePath ?>/events?q=<?= urlencode($_GET['q'] ?? '') ?>" class="civicone-tag civicone-tag--removable">
                                    Time: <?= htmlspecialchars(ucfirst($time)) ?>
                                    <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="<?= $basePath ?>/events" class="civicone-link">Clear all filters</a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Results Panel (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Results Header with Count -->
                <div class="civicone-results-header">
                    <p class="civicone-results-count" id="results-count">
                        Showing <strong><?= count($events ?? []) ?></strong> <?= count($events ?? []) === 1 ? 'event' : 'events' ?>
                    </p>
                </div>

                <!-- Results: LIST LAYOUT (structured result rows) -->
                <?php if (empty($events)): ?>
                    <div class="civicone-empty-state">
                        <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <h2 class="civicone-heading-m">No events found</h2>
                        <p class="civicone-body">
                            <?php if (!empty($_GET['q'])): ?>
                                No events match your search. Try different keywords or check back later.
                            <?php else: ?>
                                There are no upcoming events at the moment. Be the first to host a gathering!
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <ul class="civicone-events-list" role="list">
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $eventTitle = htmlspecialchars($ev['title']);
                            $eventDesc = htmlspecialchars(substr($ev['description'] ?? 'No description available.', 0, 180));
                            if (strlen($ev['description'] ?? '') > 180) {
                                $eventDesc .= '...';
                            }
                            $eventDate = strtotime($ev['start_time']);
                            $eventDateFormatted = date('l, F j, Y', $eventDate);
                            $eventTime = date('g:i A', $eventDate);
                            $eventMonth = date('M', $eventDate);
                            $eventDay = date('d', $eventDate);
                            $eventLocation = htmlspecialchars($ev['location'] ?? 'Online');
                            $isOnline = empty($ev['location']) || strtolower($ev['location']) === 'online';
                            $organizerName = htmlspecialchars($ev['organizer_name'] ?? 'Organizer');
                            $attendeeCount = $ev['attendee_count'] ?? 0;
                            ?>
                            <li class="civicone-event-item" role="listitem">
                                <!-- Date Badge -->
                                <div class="civicone-event-item__date-badge" aria-hidden="true">
                                    <div class="civicone-event-item__date-month"><?= $eventMonth ?></div>
                                    <div class="civicone-event-item__date-day"><?= $eventDay ?></div>
                                </div>

                                <!-- Event Content -->
                                <div class="civicone-event-item__content">
                                    <!-- Title (Main Link) -->
                                    <h3 class="civicone-event-item__title">
                                        <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="civicone-link">
                                            <?= $eventTitle ?>
                                        </a>
                                    </h3>

                                    <!-- Metadata (Date, Time, Location) -->
                                    <div class="civicone-event-item__meta">
                                        <span class="civicone-event-item__meta-item">
                                            <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12 6 12 12 16 14"></polyline>
                                            </svg>
                                            <span class="govuk-visually-hidden">Time: </span><?= $eventDateFormatted ?> at <?= $eventTime ?>
                                        </span>
                                        <span class="civicone-event-item__meta-item">
                                            <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <?php if ($isOnline): ?>
                                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                                <line x1="12" y1="17" x2="12" y2="21"></line>
                                                <?php else: ?>
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                                <?php endif; ?>
                                            </svg>
                                            <span class="govuk-visually-hidden">Location: </span><?= $eventLocation ?>
                                        </span>
                                    </div>

                                    <!-- Description Excerpt -->
                                    <p class="civicone-event-item__description"><?= $eventDesc ?></p>

                                    <!-- Footer (Organizer + Attendees) -->
                                    <div class="civicone-event-item__footer">
                                        <span class="civicone-event-item__organizer">
                                            Hosted by <strong><?= $organizerName ?></strong>
                                        </span>
                                        <?php if ($attendeeCount > 0): ?>
                                        <span class="civicone-event-item__attendees">
                                            <svg class="civicone-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                            <?= $attendeeCount ?> <?= $attendeeCount === 1 ? 'person' : 'people' ?> attending
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Action Link -->
                                <div class="civicone-event-item__action">
                                    <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>"
                                       class="civicone-button civicone-button--secondary"
                                       aria-label="View details and RSVP for <?= $eventTitle ?>">
                                        View & RSVP
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <nav class="civicone-pagination" aria-label="Events pagination">
                        <?php
                        $current = $pagination['current_page'];
                        $total = $pagination['total_pages'];
                        $base = $pagination['base_path'];
                        $range = 2;
                        $query = !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '';
                        if (!empty($_GET['type'])) {
                            foreach ($_GET['type'] as $type) {
                                $query .= '&type[]=' . urlencode($type);
                            }
                        }
                        if (!empty($_GET['time'])) {
                            foreach ($_GET['time'] as $time) {
                                $query .= '&time[]=' . urlencode($time);
                            }
                        }
                        ?>

                        <div class="civicone-pagination__results">
                            Showing <?= (($current - 1) * 20 + 1) ?> to <?= min($current * 20, $total_events ?? count($events ?? [])) ?> of <?= $total_events ?? count($events ?? []) ?> events
                        </div>

                        <ul class="civicone-pagination__list">
                            <?php if ($current > 1): ?>
                                <li class="civicone-pagination__item civicone-pagination__item--prev">
                                    <a href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to previous page">
                                        <span aria-hidden="true">‹</span> Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total; $i++): ?>
                                <?php if ($i == 1 || $i == $total || ($i >= $current - $range && $i <= $current + $range)): ?>
                                    <li class="civicone-pagination__item">
                                        <?php if ($i == $current): ?>
                                            <span class="civicone-pagination__link civicone-pagination__link--current" aria-current="page">
                                                <?= $i ?>
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= $base ?>?page=<?= $i ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to page <?= $i ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php elseif ($i == $current - $range - 1 || $i == $current + $range + 1): ?>
                                    <li class="civicone-pagination__item civicone-pagination__item--ellipsis" aria-hidden="true">
                                        <span>⋯</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($current < $total): ?>
                                <li class="civicone-pagination__item civicone-pagination__item--next">
                                    <a href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to next page">
                                        Next <span aria-hidden="true">›</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div><!-- /two-thirds -->
        </div><!-- /grid-row -->

    </main>
</div><!-- /width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
