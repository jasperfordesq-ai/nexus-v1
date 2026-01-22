<?php
/**
 * CivicOne Events Calendar - Monthly Grid View
 * Custom Template: Calendar Interface (Section 10.9)
 * Holographic glassmorphism design with responsive mobile list view
 * WCAG 2.1 AA Compliant
 */
$monthName = date('F', mktime(0, 0, 0, $month, 10));
$heroTitle = "$monthName $year";
$heroSub = "Community Schedule";
$heroType = 'Event';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

// Calendar Logic
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
<link rel="stylesheet" href="/assets/css/purged/civicone-events-calendar.min.css?v=<?= time() ?>">

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Offline Banner -->
        <div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
            <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
            <span>No internet connection</span>
        </div>

        <div class="holo-calendar-page">
            <!-- Floating Orbs -->
            <div class="holo-orb holo-orb-1" aria-hidden="true"></div>
            <div class="holo-orb holo-orb-2" aria-hidden="true"></div>
            <div class="holo-orb holo-orb-3" aria-hidden="true"></div>

            <div class="holo-calendar-container">
                <!-- Header -->
                <div class="holo-calendar-header">
                    <a href="<?= $basePath ?>/events" class="holo-nav-btn holo-nav-btn-secondary">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        <span>List View</span>
                    </a>

                    <div class="holo-month-nav">
                        <a href="<?= $prevLink ?>" class="holo-nav-btn holo-nav-btn-secondary holo-nav-btn-arrow" aria-label="Previous Month">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </a>
                        <h1 class="holo-month-title"><?= $monthName ?> <?= $year ?></h1>
                        <a href="<?= $nextLink ?>" class="holo-nav-btn holo-nav-btn-secondary holo-nav-btn-arrow" aria-label="Next Month">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    <a href="<?= $basePath ?>/events/create" class="holo-nav-btn holo-nav-btn-primary">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>Add Event</span>
                    </a>
                </div>

                <!-- Calendar Grid -->
                <div class="holo-glass-card">
                    <div class="holo-calendar-grid">
                        <!-- Header Row -->
                        <div class="holo-cal-header">Sun</div>
                        <div class="holo-cal-header">Mon</div>
                        <div class="holo-cal-header">Tue</div>
                        <div class="holo-cal-header">Wed</div>
                        <div class="holo-cal-header">Thu</div>
                        <div class="holo-cal-header">Fri</div>
                        <div class="holo-cal-header">Sat</div>

                        <?php
                        // Empty cells for days before start of month
                        for ($i = 0; $i < $dayOfWeek; $i++) {
                            echo '<div class="holo-cal-day empty"></div>';
                        }

                        // Days of Month
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $isToday = ($day == date('j') && $month == date('m') && $year == date('Y'));
                            $dayEvents = $eventsByDay[$day] ?? [];

                            echo '<div class="holo-cal-day ' . ($isToday ? 'today' : '') . '">';
                            echo '<div class="holo-day-number">' . $day . '</div>';

                            if (!empty($dayEvents)) {
                                echo '<div class="holo-day-events">';
                                foreach ($dayEvents as $ev) {
                                    $color = $ev['category_color'] ?? '#f97316';
                                    $time = date('g:ia', strtotime($ev['start_time']));
                                    echo '<a href="' . $basePath . '/events/' . $ev['id'] . '" class="holo-cal-event" style="--event-color: ' . htmlspecialchars($color) . ';">';
                                    echo '<span class="holo-ev-time">' . $time . '</span>';
                                    echo '<span class="holo-ev-title">' . htmlspecialchars($ev['title']) . '</span>';
                                    echo '</a>';
                                }
                                echo '</div>';
                            }

                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div><!-- /civicone-width-container -->

<script src="/assets/js/civicone-events-calendar.js?v=<?= time() ?>"></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
