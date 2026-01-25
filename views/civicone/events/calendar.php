<?php
/**
 * CivicOne View: Events Calendar
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Events Calendar';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

// Calendar Logic
$monthName = date('F', mktime(0, 0, 0, $month, 10));
$firstDayTimestamp = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayTimestamp);
$dayOfWeek = date('w', $firstDayTimestamp); // 0 (Sun) - 6 (Sat)

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$basePath = \Nexus\Core\TenantContext::getBasePath();
$prevLink = "$basePath/events/calendar?month=$prevMonth&year=$prevYear";
$nextLink = "$basePath/events/calendar?month=$nextMonth&year=$nextYear";
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/events">Events</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Calendar</li>
    </ol>
</nav>

<!-- Header Row -->
<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-one-quarter">
        <a href="<?= $basePath ?>/events" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-list govuk-!-margin-right-1" aria-hidden="true"></i> List View
        </a>
    </div>
    <div class="govuk-grid-column-one-half govuk-!-text-align-centre">
        <div class="civicone-calendar-nav">
            <a href="<?= $prevLink ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-label="Previous Month">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </a>
            <h1 class="govuk-heading-l govuk-!-margin-bottom-0"><?= $monthName ?> <?= $year ?></h1>
            <a href="<?= $nextLink ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-label="Next Month">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter govuk-!-text-align-right">
        <a href="<?= $basePath ?>/events/create" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Add Event
        </a>
    </div>
</div>

<!-- Calendar Grid -->
<div class="govuk-!-padding-4 civicone-sidebar-card civicone-calendar-wrapper">
    <table class="govuk-table civicone-calendar-table">
        <caption class="govuk-visually-hidden">Calendar for <?= $monthName ?> <?= $year ?></caption>
        <thead class="govuk-table__head">
            <tr class="govuk-table__row">
                <th scope="col" class="govuk-table__header civicone-calendar-day-header">Sun</th>
                <th scope="col" class="govuk-table__header civicone-calendar-day-header">Mon</th>
                <th scope="col" class="govuk-table__header civicone-calendar-day-header">Tue</th>
                <th scope="col" class="govuk-table__header civicone-calendar-day-header">Wed</th>
                <th scope="col" class="govuk-table__header civicone-calendar-day-header">Thu</th>
                <th scope="col" class="govuk-table__header civicone-calendar-day-header">Fri</th>
                <th scope="col" class="govuk-table__header civicone-calendar-day-header">Sat</th>
            </tr>
        </thead>
        <tbody class="govuk-table__body">
            <?php
            $cellCount = 0;
            $totalCells = $dayOfWeek + $daysInMonth;
            $totalRows = ceil($totalCells / 7);

            echo '<tr class="govuk-table__row">';

            // Empty cells for days before start of month
            for ($i = 0; $i < $dayOfWeek; $i++) {
                echo '<td class="govuk-table__cell civicone-panel-bg civicone-calendar-cell"></td>';
                $cellCount++;
            }

            // Days of Month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $isToday = ($day == date('j') && $month == date('m') && $year == date('Y'));
                $dayEvents = $eventsByDay[$day] ?? [];

                $todayClass = $isToday ? ' civicone-calendar-cell--today' : '';

                echo '<td class="govuk-table__cell civicone-calendar-cell' . $todayClass . '">';
                echo '<p class="govuk-body-s govuk-!-margin-bottom-1"><strong>' . $day . '</strong></p>';

                if (!empty($dayEvents)) {
                    foreach ($dayEvents as $ev) {
                        $time = date('g:ia', strtotime($ev['start_time']));
                        echo '<a href="' . $basePath . '/events/' . $ev['id'] . '" class="govuk-link govuk-body-s civicone-calendar-event">';
                        echo '<span class="govuk-tag govuk-tag--light-blue govuk-!-margin-right-1 civicone-calendar-time-tag">' . $time . '</span>';
                        echo htmlspecialchars(substr($ev['title'], 0, 15)) . (strlen($ev['title']) > 15 ? '...' : '');
                        echo '</a>';
                    }
                }

                echo '</td>';
                $cellCount++;

                // Start new row after Saturday
                if ($cellCount % 7 == 0 && $day < $daysInMonth) {
                    echo '</tr><tr class="govuk-table__row">';
                }
            }

            // Fill remaining cells
            $remainingCells = 7 - ($cellCount % 7);
            if ($remainingCells < 7) {
                for ($i = 0; $i < $remainingCells; $i++) {
                    echo '<td class="govuk-table__cell civicone-panel-bg civicone-calendar-cell"></td>';
                }
            }

            echo '</tr>';
            ?>
        </tbody>
    </table>
</div>

<!-- Mobile List View -->
<div class="govuk-!-margin-top-6" id="mobile-events-list">
    <h2 class="govuk-heading-m">Events this month</h2>
    <?php
    $hasEvents = false;
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dayEvents = $eventsByDay[$day] ?? [];
        if (!empty($dayEvents)) {
            $hasEvents = true;
            foreach ($dayEvents as $ev) {
                $dateStr = date('l, F j', mktime(0, 0, 0, $month, $day, $year));
                $time = date('g:i A', strtotime($ev['start_time']));
                ?>
                <div class="govuk-!-padding-4 govuk-!-margin-bottom-4 civicone-sidebar-card civicone-highlight-panel">
                    <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-secondary-text">
                        <?= $dateStr ?> at <?= $time ?>
                    </p>
                    <p class="govuk-body govuk-!-margin-bottom-2">
                        <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="govuk-link"><strong><?= htmlspecialchars($ev['title']) ?></strong></a>
                    </p>
                    <?php if (!empty($ev['location'])): ?>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                            <i class="fa-solid fa-location-dot govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($ev['location']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php
            }
        }
    }

    if (!$hasEvents): ?>
        <div class="govuk-inset-text">
            <p class="govuk-body">No events scheduled for <?= $monthName ?> <?= $year ?>.</p>
            <a href="<?= $basePath ?>/events/create" class="govuk-link">Create the first event</a>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
