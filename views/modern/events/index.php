<?php
// Events Index - Glassmorphism 2025
$pageTitle = "Community Events";
$pageSubtitle = "Discover local happenings and gatherings";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Events - Local Gatherings & Activities');
Nexus\Core\SEO::setDescription('Discover local community events, workshops, meetups, and gatherings. Connect with neighbors and join activities in your area.');

require __DIR__ . '/../../layouts/modern/header.php';
$base = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Main content wrapper (main tag opened in header.php) -->
<div class="htb-container-full">
<div id="events-glass-wrapper">

<!-- CSS moved to /assets/css/events-index.css -->

    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Community Events</h1>
        <p class="nexus-welcome-subtitle">Discover exciting local events, workshops, and community gatherings. Connect with neighbors and find activities that match your interests.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $base ?>/events" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-calendar"></i>
                <span>All Events</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $base ?>/events/my-events" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-ticket"></i>
                <span>My Events</span>
            </a>
            <?php endif; ?>
            <a href="<?= $base ?>/events?date=weekend" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-star"></i>
                <span>This Weekend</span>
            </a>
            <a href="<?= $base ?>/compose?type=event" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-plus"></i>
                <span>Host Event</span>
            </a>
        </div>
    </div>

    <!-- Glass Search Card -->
    <div class="glass-search-card">
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; flex-wrap: wrap; gap: 10px;">
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0;">Find Events</h2>
                <span style="font-size: 0.9rem; font-weight: 600; color: var(--htb-text-muted);">
                    <?= count($events ?? []) ?> events available
                </span>
            </div>

            <form action="<?= $base ?>/events" method="GET" style="display: flex; flex-direction: column; gap: 15px;">
                <div style="position: relative; width: 100%;">
                    <input type="search" aria-label="Search" name="search" placeholder="Search events, locations, hosts..."
                           value="<?= htmlspecialchars($searchQuery ?? '') ?>" class="glass-search-input">
                    <i class="fa-solid fa-search" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 1rem;"></i>
                </div>

                <div class="filter-row" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <label for="event-category-filter" class="visually-hidden">Filter by category</label>
                    <select id="event-category-filter" name="category" onchange="this.form.submit()" class="glass-select" aria-label="Filter by category">
                        <option value="">All Categories</option>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($selectedCategory) && $selectedCategory == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <label for="event-date-filter" class="visually-hidden">Filter by date</label>
                    <select id="event-date-filter" name="date" onchange="this.form.submit()" class="glass-select" aria-label="Filter by date">
                        <option value="">Any Time</option>
                        <option value="today" <?= (isset($selectedDate) && $selectedDate == 'today') ? 'selected' : '' ?>>Today</option>
                        <option value="tomorrow" <?= (isset($selectedDate) && $selectedDate == 'tomorrow') ? 'selected' : '' ?>>Tomorrow</option>
                        <option value="weekend" <?= (isset($selectedDate) && $selectedDate == 'weekend') ? 'selected' : '' ?>>This Weekend</option>
                        <option value="week" <?= (isset($selectedDate) && $selectedDate == 'week') ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= (isset($selectedDate) && $selectedDate == 'month') ? 'selected' : '' ?>>This Month</option>
                    </select>

                    <button type="submit" class="glass-btn-primary">
                        <i class="fa-solid fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-calendar-days" style="color: #f97316; font-size: 1.1rem;"></i>
        <h2>Upcoming Events</h2>
    </div>

    <!-- Skeleton Loader (shown during initial load / AJAX) -->
    <div class="events-skeleton-grid skeleton-container" id="eventsSkeleton" aria-label="Loading events">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="event-card-skeleton">
            <div class="skeleton-date-header">
                <div class="skeleton skeleton-date-box"></div>
                <div class="skeleton-time-location">
                    <div class="skeleton skeleton-text" style="width: 60px; height: 14px;"></div>
                    <div class="skeleton skeleton-text" style="width: 120px; height: 12px;"></div>
                </div>
            </div>
            <div class="skeleton-body">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-desc"></div>
                <div class="skeleton skeleton-desc"></div>
                <div class="skeleton-host">
                    <div class="skeleton skeleton-avatar small"></div>
                    <div class="skeleton skeleton-text" style="width: 100px; height: 14px; margin: 0;"></div>
                </div>
            </div>
            <div class="skeleton-footer">
                <div class="skeleton skeleton-text" style="width: 80px; height: 14px; margin: 0;"></div>
                <div class="skeleton skeleton-text" style="width: 50px; height: 14px; margin: 0;"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Events Grid -->
    <div class="events-grid" id="eventsGrid">
        <?php if (!empty($events)): ?>
            <?php foreach ($events as $ev): ?>
                <?php
                $date = strtotime($ev['start_time']);
                $month = date('M', $date);
                $day = date('d', $date);
                $time = date('g:i A', $date);
                ?>
                <a href="<?= $base ?>/events/<?= $ev['id'] ?>" class="glass-event-card">
                    <!-- Date Header -->
                    <div class="card-date-header">
                        <div class="date-box">
                            <div class="month"><?= $month ?></div>
                            <div class="day"><?= $day ?></div>
                        </div>
                        <div class="time-location">
                            <div class="time"><?= $time ?></div>
                            <div class="location">
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars(substr($ev['location'] ?? 'TBA', 0, 30)) ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="event-title">
                            <?= htmlspecialchars($ev['title']) ?>
                        </h3>
                        <p class="event-desc">
                            <?= htmlspecialchars(substr($ev['description'] ?? '', 0, 120)) ?>...
                        </p>
                        <div class="host-info">
                            <i class="fa-solid fa-user-circle"></i>
                            Hosted by <strong style="margin-left: 4px;"><?= htmlspecialchars($ev['organizer_name'] ?? 'Community Member') ?></strong>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="attendee-count">
                            <i class="fa-solid fa-users"></i>
                            <span><?= $ev['attendee_count'] ?? 0 ?> attending</span>
                        </div>
                        <span class="view-link">
                            RSVP <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">📅</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No upcoming events</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Be the first to create an event for your community!</p>
                <a href="<?= $base ?>/compose?type=event" class="glass-btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Event
                </a>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #events-glass-wrapper -->
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Skeleton Loader Transition
(function initSkeletonLoader() {
    const skeleton = document.getElementById('eventsSkeleton');
    const grid = document.getElementById('eventsGrid');
    if (!skeleton || !grid) return;

    // Hide skeleton and show content after short delay for smooth transition
    setTimeout(function() {
        skeleton.classList.add('hidden');
        grid.classList.add('content-loaded');
    }, 300);
})();

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

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.htb-btn, button, .nexus-smart-btn, .glass-btn-primary, .view-link').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#f97316';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#f97316');
        }
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
