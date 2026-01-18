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

<style>
/* ============================================
   HOLOGRAPHIC GLASSMORPHISM CALENDAR
   Full Modern Design System - Orange/Amber Theme
   ============================================ */

/* Page Background with Ambient Effects */
.holo-calendar-page {
    min-height: 100vh;
    padding: 180px 20px 60px;
    position: relative;
    overflow: hidden;
}

@media (max-width: 900px) {
    .holo-calendar-page {
        padding: 20px 16px 120px;
    }
}

/* Animated Background Gradient */
.holo-calendar-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse 80% 50% at 20% 40%, rgba(249, 115, 22, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 60%, rgba(234, 88, 12, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 80%, rgba(251, 191, 36, 0.1) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
    animation: holoShift 20s ease-in-out infinite alternate;
}

[data-theme="dark"] .holo-calendar-page::before {
    background:
        radial-gradient(ellipse 80% 50% at 20% 40%, rgba(249, 115, 22, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 60%, rgba(234, 88, 12, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 80%, rgba(251, 191, 36, 0.12) 0%, transparent 50%);
}

@keyframes holoShift {
    0% { opacity: 1; transform: scale(1); }
    100% { opacity: 0.8; transform: scale(1.1); }
}

/* Floating Orbs */
.holo-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: -1;
    opacity: 0.4;
}

.holo-orb-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #f97316, #ea580c);
    top: 10%;
    left: -10%;
    animation: orbFloat1 15s ease-in-out infinite;
}

.holo-orb-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    bottom: 20%;
    right: -5%;
    animation: orbFloat2 18s ease-in-out infinite;
}

.holo-orb-3 {
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, #fb923c, #fdba74);
    top: 60%;
    left: 30%;
    animation: orbFloat3 12s ease-in-out infinite;
}

@keyframes orbFloat1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(50px, 30px) scale(1.1); }
}

@keyframes orbFloat2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-40px, -20px) scale(0.9); }
}

@keyframes orbFloat3 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(30px, -40px) scale(1.05); }
}

/* Main Container */
.holo-calendar-container {
    max-width: 1200px;
    margin: 0 auto;
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.holo-calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 16px;
}

@media (max-width: 768px) {
    .holo-calendar-header {
        flex-direction: column;
        align-items: stretch;
    }
}

.holo-nav-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    cursor: pointer;
}

.holo-nav-btn-secondary {
    background: rgba(255, 255, 255, 0.6);
    color: var(--htb-text-main, #1e293b);
    border: 1px solid rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .holo-nav-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, 0.1);
}

.holo-nav-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .holo-nav-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

.holo-nav-btn-primary {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: white;
    box-shadow: 0 8px 20px rgba(249, 115, 22, 0.3);
}

.holo-nav-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(249, 115, 22, 0.4);
}

.holo-nav-btn-arrow {
    padding: 12px 16px;
    min-width: 48px;
}

/* Month Title */
.holo-month-nav {
    display: flex;
    align-items: center;
    gap: 16px;
}

.holo-month-title {
    font-size: 1.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
    letter-spacing: -0.5px;
}

@media (max-width: 768px) {
    .holo-month-title {
        font-size: 1.5rem;
    }

    .holo-month-nav {
        justify-content: center;
        order: -1;
        width: 100%;
    }

    .holo-header-actions {
        display: flex;
        justify-content: space-between;
        width: 100%;
        gap: 12px;
    }

    .holo-header-actions .holo-nav-btn {
        flex: 1;
        justify-content: center;
    }
}

/* Glass Card */
.holo-glass-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(40px) saturate(180%);
    -webkit-backdrop-filter: blur(40px) saturate(180%);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-shadow:
        0 25px 50px rgba(0, 0, 0, 0.08),
        0 0 100px rgba(249, 115, 22, 0.08),
        inset 0 0 0 1px rgba(255, 255, 255, 0.3);
    overflow: hidden;
    position: relative;
}

[data-theme="dark"] .holo-glass-card {
    background: rgba(15, 23, 42, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow:
        0 25px 50px rgba(0, 0, 0, 0.4),
        0 0 100px rgba(249, 115, 22, 0.15),
        inset 0 0 0 1px rgba(255, 255, 255, 0.05);
}

/* Card Shimmer Effect */
.holo-glass-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.1),
        transparent
    );
    animation: shimmer 8s ease-in-out infinite;
    pointer-events: none;
    z-index: 1;
}

@keyframes shimmer {
    0% { left: -100%; }
    50%, 100% { left: 100%; }
}

/* Calendar Grid */
.holo-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    position: relative;
    z-index: 2;
}

/* Calendar Header Row */
.holo-cal-header {
    padding: 16px 8px;
    text-align: center;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--htb-text-muted, #64748b);
    background: rgba(0, 0, 0, 0.02);
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .holo-cal-header {
    background: rgba(255, 255, 255, 0.02);
    border-bottom-color: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
}

/* Calendar Days */
.holo-cal-day {
    min-height: 120px;
    padding: 8px;
    border-right: 1px solid rgba(0, 0, 0, 0.04);
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
    transition: background 0.2s ease;
}

[data-theme="dark"] .holo-cal-day {
    border-color: rgba(255, 255, 255, 0.04);
}

.holo-cal-day:nth-child(7n) {
    border-right: none;
}

.holo-cal-day:hover {
    background: rgba(249, 115, 22, 0.03);
}

[data-theme="dark"] .holo-cal-day:hover {
    background: rgba(249, 115, 22, 0.08);
}

.holo-cal-day.empty {
    background: rgba(0, 0, 0, 0.01);
}

[data-theme="dark"] .holo-cal-day.empty {
    background: rgba(0, 0, 0, 0.1);
}

.holo-cal-day.today {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.08) 0%, rgba(251, 191, 36, 0.05) 100%);
}

[data-theme="dark"] .holo-cal-day.today {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.15) 0%, rgba(251, 191, 36, 0.1) 100%);
}

/* Day Number */
.holo-day-number {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--htb-text-muted, #94a3b8);
    text-align: right;
    margin-bottom: 6px;
}

[data-theme="dark"] .holo-day-number {
    color: #64748b;
}

.holo-cal-day.today .holo-day-number {
    color: #f97316;
    font-size: 1rem;
}

/* Events Container */
.holo-day-events {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

/* Event Item */
.holo-cal-event {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.8);
    border-left: 3px solid #f97316;
    text-decoration: none;
    transition: all 0.2s ease;
    overflow: hidden;
}

[data-theme="dark"] .holo-cal-event {
    background: rgba(0, 0, 0, 0.2);
}

.holo-cal-event:hover {
    transform: translateX(2px);
    background: rgba(255, 255, 255, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .holo-cal-event:hover {
    background: rgba(255, 255, 255, 0.1);
}

.holo-ev-time {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--htb-text-muted, #64748b);
    white-space: nowrap;
}

.holo-ev-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--htb-text-main, #334155);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .holo-ev-title {
    color: #e2e8f0;
}

/* Mobile List View */
@media (max-width: 768px) {
    .holo-calendar-grid {
        display: flex;
        flex-direction: column;
    }

    .holo-cal-header {
        display: none;
    }

    .holo-cal-day {
        min-height: auto;
        padding: 16px;
        border-right: none;
        display: flex;
        gap: 16px;
        align-items: flex-start;
    }

    .holo-cal-day.empty {
        display: none;
    }

    .holo-day-number {
        font-size: 1.2rem;
        min-width: 40px;
        text-align: center;
        margin-bottom: 0;
        padding: 8px;
        background: rgba(249, 115, 22, 0.1);
        border-radius: 10px;
        color: #f97316;
    }

    .holo-cal-day.today .holo-day-number {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
    }

    .holo-day-events {
        flex: 1;
    }

    .holo-cal-event {
        padding: 10px 12px;
    }

    .holo-ev-time {
        font-size: 0.8rem;
    }

    .holo-ev-title {
        font-size: 0.85rem;
    }
}

/* Empty State */
.holo-empty-day {
    color: var(--htb-text-muted, #94a3b8);
    font-size: 0.8rem;
    font-style: italic;
}

/* Offline Banner */
.holo-offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transform: translateY(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.holo-offline-banner.visible {
    transform: translateY(0);
}

/* Focus Visible */
.holo-nav-btn:focus-visible,
.holo-cal-event:focus-visible {
    outline: 3px solid #f97316;
    outline-offset: 2px;
}
</style>

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
