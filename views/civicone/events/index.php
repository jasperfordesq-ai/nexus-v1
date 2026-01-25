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

<!-- Breadcrumbs (GOV.UK Template A requirement) -->
<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
            <ol class="govuk-breadcrumbs__list">
                <li class="govuk-breadcrumbs__list-item">
                    <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
                </li>
                <li class="govuk-breadcrumbs__list-item" aria-current="page">
                    Events
                </li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Events</h1>
                <p class="govuk-body-l">Discover upcoming events and activities in your community.</p>
            </div>
            <div class="govuk-grid-column-one-third">
                <a href="<?= $basePath ?>/events/create" class="govuk-button govuk-!-margin-bottom-0">
                    Host Event
                </a>
            </div>
        </div>

        <!-- Directory Layout: 1/3 Filters + 2/3 Results -->
        <div class="govuk-grid-row">

            <!-- Filters Panel (1/3) -->
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg">
                    <h2 class="govuk-heading-m">Filter events</h2>

                    <form method="get" action="<?= $basePath ?>/events">
                        <!-- Search Input -->
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="event-search">
                                Search by title or location
                            </label>
                            <input
                                type="text"
                                id="event-search"
                                name="q"
                                class="govuk-input"
                                placeholder="Enter keywords..."
                                value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                            >
                        </div>

                        <!-- Event Type Checkboxes -->
                        <div class="govuk-form-group">
                            <fieldset class="govuk-fieldset">
                                <legend class="govuk-fieldset__legend">Event type</legend>
                                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                    <div class="govuk-checkboxes__item">
                                        <input type="checkbox" id="type-online" name="type[]" value="online" class="govuk-checkboxes__input" <?= in_array('online', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label class="govuk-label govuk-checkboxes__label" for="type-online">Online</label>
                                    </div>
                                    <div class="govuk-checkboxes__item">
                                        <input type="checkbox" id="type-inperson" name="type[]" value="inperson" class="govuk-checkboxes__input" <?= in_array('inperson', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label class="govuk-label govuk-checkboxes__label" for="type-inperson">In-person</label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <!-- Time Range Checkboxes -->
                        <div class="govuk-form-group">
                            <fieldset class="govuk-fieldset">
                                <legend class="govuk-fieldset__legend">Time range</legend>
                                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                    <div class="govuk-checkboxes__item">
                                        <input type="checkbox" id="time-today" name="time[]" value="today" class="govuk-checkboxes__input" <?= in_array('today', $_GET['time'] ?? []) ? 'checked' : '' ?>>
                                        <label class="govuk-label govuk-checkboxes__label" for="time-today">Today</label>
                                    </div>
                                    <div class="govuk-checkboxes__item">
                                        <input type="checkbox" id="time-week" name="time[]" value="week" class="govuk-checkboxes__input" <?= in_array('week', $_GET['time'] ?? []) ? 'checked' : '' ?>>
                                        <label class="govuk-label govuk-checkboxes__label" for="time-week">This week</label>
                                    </div>
                                    <div class="govuk-checkboxes__item">
                                        <input type="checkbox" id="time-month" name="time[]" value="month" class="govuk-checkboxes__input" <?= in_array('month', $_GET['time'] ?? []) ? 'checked' : '' ?>>
                                        <label class="govuk-label govuk-checkboxes__label" for="time-month">This month</label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <button type="submit" class="govuk-button govuk-button--secondary">
                            Apply filters
                        </button>
                    </form>

                    <!-- Selected Filters -->
                    <?php
                    $hasFilters = !empty($_GET['q']) || !empty($_GET['type']) || !empty($_GET['time']);
                    if ($hasFilters):
                    ?>
                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
                    <h3 class="govuk-heading-s">Active filters</h3>
                    <p class="govuk-body">
                        <?php if (!empty($_GET['q'])): ?>
                            <strong class="govuk-tag">Search: <?= htmlspecialchars($_GET['q']) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($_GET['type'])): ?>
                            <?php foreach ($_GET['type'] as $type): ?>
                                <strong class="govuk-tag govuk-tag--grey">Type: <?= htmlspecialchars(ucfirst($type)) ?></strong>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($_GET['time'])): ?>
                            <?php foreach ($_GET['time'] as $time): ?>
                                <strong class="govuk-tag govuk-tag--grey">Time: <?= htmlspecialchars(ucfirst($time)) ?></strong>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </p>
                    <a href="<?= $basePath ?>/events" class="govuk-link">Clear all filters</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Results Panel (2/3) -->
            <div class="govuk-grid-column-two-thirds">

                <!-- Results Count -->
                <p class="govuk-body govuk-!-margin-bottom-4">
                    Showing <strong><?= count($events ?? []) ?></strong> <?= count($events ?? []) === 1 ? 'event' : 'events' ?>
                </p>

                <!-- Results List -->
                <?php if (empty($events)): ?>
                    <div class="govuk-inset-text">
                        <h2 class="govuk-heading-m">No events found</h2>
                        <p class="govuk-body">
                            <?php if (!empty($_GET['q'])): ?>
                                No events match your search. Try different keywords or check back later.
                            <?php else: ?>
                                There are no upcoming events at the moment. Be the first to host a gathering!
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <ul class="govuk-list" role="list">
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
                            $eventLocation = htmlspecialchars($ev['location'] ?? 'Online');
                            $isOnline = empty($ev['location']) || strtolower($ev['location']) === 'online';
                            $organizerName = htmlspecialchars($ev['organizer_name'] ?? 'Organizer');
                            $attendeeCount = $ev['attendee_count'] ?? 0;
                            ?>
                            <li class="govuk-!-margin-bottom-4 govuk-!-padding-bottom-4 civicone-listing-item">
                                <!-- Date & Type -->
                                <p class="govuk-body-s govuk-!-margin-bottom-1">
                                    <strong class="govuk-tag <?= $isOnline ? 'govuk-tag--light-blue' : 'govuk-tag--green' ?>">
                                        <?= $isOnline ? 'Online' : 'In-person' ?>
                                    </strong>
                                    <span class="civicone-secondary-text">&middot; <?= $eventDateFormatted ?> at <?= $eventTime ?></span>
                                </p>

                                <!-- Title -->
                                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                                    <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="govuk-link">
                                        <?= $eventTitle ?>
                                    </a>
                                </h3>

                                <!-- Description -->
                                <p class="govuk-body govuk-!-margin-bottom-2"><?= $eventDesc ?></p>

                                <!-- Meta -->
                                <p class="govuk-body-s govuk-!-margin-bottom-2 civicone-secondary-text">
                                    <i class="fa-solid fa-location-dot govuk-!-margin-right-1" aria-hidden="true"></i>
                                    <?= $eventLocation ?>
                                    &middot; Hosted by <strong><?= $organizerName ?></strong>
                                    <?php if ($attendeeCount > 0): ?>
                                        &middot; <?= $attendeeCount ?> <?= $attendeeCount === 1 ? 'person' : 'people' ?> attending
                                    <?php endif; ?>
                                </p>

                                <!-- Action -->
                                <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>"
                                   class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0"
                                   aria-label="View details and RSVP for <?= $eventTitle ?>">
                                    View & RSVP
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- GOV.UK Pagination -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <?php
                    $current = $pagination['current_page'];
                    $total = $pagination['total_pages'];
                    $base = $pagination['base_path'];
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
                    <nav class="govuk-pagination" role="navigation" aria-label="Pagination">
                        <?php if ($current > 1): ?>
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" rel="prev">
                                <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                                </svg>
                                <span class="govuk-pagination__link-title">Previous<span class="govuk-visually-hidden"> page</span></span>
                            </a>
                        </div>
                        <?php endif; ?>

                        <ul class="govuk-pagination__list">
                            <?php for ($i = 1; $i <= $total; $i++): ?>
                                <?php if ($i == 1 || $i == $total || ($i >= $current - 1 && $i <= $current + 1)): ?>
                                    <li class="govuk-pagination__item<?= $i == $current ? ' govuk-pagination__item--current' : '' ?>">
                                        <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $i ?><?= $query ?>" aria-label="Page <?= $i ?>"<?= $i == $current ? ' aria-current="page"' : '' ?>>
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $current - 2 || $i == $current + 2): ?>
                                    <li class="govuk-pagination__item govuk-pagination__item--ellipses">&ctdot;</li>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </ul>

                        <?php if ($current < $total): ?>
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" rel="next">
                                <span class="govuk-pagination__link-title">Next<span class="govuk-visually-hidden"> page</span></span>
                                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                                </svg>
                            </a>
                        </div>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

        </div><!-- /.govuk-grid-column-two-thirds -->
    </div><!-- /.govuk-grid-row -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
