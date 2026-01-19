<?php
// Phoenix Calendar View - Full Holographic Glassmorphism Edition
$monthName = date('F', mktime(0, 0, 0, $month, 10));
$hero_title = "$monthName $year";
$hero_subtitle = "Community Schedule";
$hero_gradient = 'htb-hero-gradient-events';
$hero_type = 'Event';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

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

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-calendar-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-calendar-container">
        <!-- Header -->
        <div class="holo-calendar-header">
            <a href="<?= $basePath ?>/events" class="holo-nav-btn holo-nav-btn-secondary">
                <i class="fa-solid fa-list"></i>
                <span>List View</span>
            </a>

            <div class="holo-month-nav">
                <a href="<?= $prevLink ?>" class="holo-nav-btn holo-nav-btn-secondary holo-nav-btn-arrow" aria-label="Previous Month">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <h1 class="holo-month-title"><?= $monthName ?> <?= $year ?></h1>
                <a href="<?= $nextLink ?>" class="holo-nav-btn holo-nav-btn-secondary holo-nav-btn-arrow" aria-label="Next Month">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>

            <a href="<?= $basePath ?>/events/create" class="holo-nav-btn holo-nav-btn-primary">
                <i class="fa-solid fa-plus"></i>
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
                            echo '<a href="' . $basePath . '/events/' . $ev['id'] . '" class="holo-cal-event" style="border-left-color: ' . htmlspecialchars($color) . ';">';
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

<script>
// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Touch feedback
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.holo-nav-btn, .holo-cal-event').forEach(el => {
        el.addEventListener('pointerdown', () => el.style.transform = 'scale(0.97)');
        el.addEventListener('pointerup', () => el.style.transform = '');
        el.addEventListener('pointerleave', () => el.style.transform = '');
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    let metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        metaTheme = document.createElement('meta');
        metaTheme.name = 'theme-color';
        document.head.appendChild(metaTheme);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        metaTheme.setAttribute('content', isDark ? '#0f172a' : '#f97316');
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
